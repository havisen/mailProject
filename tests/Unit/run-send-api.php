<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Api/ApiException.php';
require_once __DIR__ . '/../../src/Api/ValidatedSendRequest.php';
require_once __DIR__ . '/../../src/Api/SendRequestValidator.php';
require_once __DIR__ . '/../../src/Submissions/SubmissionRequest.php';

use App\Api\ApiException;
use App\Api\SendRequestValidator;

$failed = 0;
$check = static function (string $name, callable $callback) use (&$failed): void {
    try {
        $callback();
        fwrite(STDOUT, "ok - {$name}\n");
    } catch (Throwable $throwable) {
        $failed++;
        fwrite(STDERR, "FAIL - {$name}: {$throwable->getMessage()}\n");
    }
};
$expectApi = static function (int $status, callable $callback): void {
    try {
        $callback();
    } catch (ApiException $exception) {
        if ($exception->httpStatus === $status) {
            return;
        }
        throw new RuntimeException('Unexpected API status ' . $exception->httpStatus);
    }
    throw new RuntimeException('Expected API validation failure.');
};
$now = new DateTimeImmutable('2026-10-23T10:00:00+00:00');
$validator = new SendRequestValidator();
$base = [
    'account_id' => 'acct_' . str_repeat('a', 32),
    'to' => [['email' => 'person@exämple.test', 'name' => 'Person']],
    'subject' => 'Authentication code',
    'text' => 'Synthetic fixture content.',
];

$check('valid request normalizes IDNA and keeps Bcc inside the submission only', function () use ($validator, $base, $now): void {
    $value = $validator->validate($base + ['bcc' => [['email' => 'audit@example.test']]], $now);
    if ($value->submission->to[0]['email'] !== 'person@xn--exmple-cua.test' || $value->recipientCount !== 2) {
        throw new RuntimeException('Recipient normalization failed.');
    }
});

$check('omitted deadline is absent from the canonical caller fingerprint', function () use ($validator, $base, $now): void {
    $first = $validator->validate($base, $now);
    $later = $validator->validate($base, $now->modify('+2 minutes'));
    if ($first->canonicalJson !== $later->canonicalJson || $first->submission->deadline !== null) {
        throw new RuntimeException('Omitted default leaked into the fingerprint.');
    }
});

$check('unknown fields and duplicate normalized recipients are rejected', function () use ($validator, $base, $now, $expectApi): void {
    $expectApi(422, fn () => $validator->validate($base + ['unexpected' => true], $now));
    $duplicate = $base;
    $duplicate['cc'] = [['email' => 'PERSON@EXÄMPLE.TEST']];
    $expectApi(422, fn () => $validator->validate($duplicate, $now));
});

$check('SMTPUTF8 local parts and control-character injection are rejected', function () use ($validator, $base, $now, $expectApi): void {
    $unicode = $base;
    $unicode['to'] = [['email' => 'pérson@example.test']];
    $expectApi(422, fn () => $validator->validate($unicode, $now));
    $injected = $base;
    $injected['subject'] = "Subject\r\nBcc: hidden@example.test";
    $expectApi(422, fn () => $validator->validate($injected, $now));
});

$check('recipient and deadline bounds are enforced', function () use ($validator, $base, $now, $expectApi): void {
    $tooMany = $base;
    $tooMany['to'] = array_map(static fn (int $i): array => ['email' => "user{$i}@example.test"], range(1, 11));
    $expectApi(422, fn () => $validator->validate($tooMany, $now));
    $late = $base + ['send_before' => '2026-10-24T10:00:01+00:00'];
    $expectApi(422, fn () => $validator->validate($late, $now));
});

fwrite(STDOUT, sprintf("API validation checks failed: %d\n", $failed));
exit($failed === 0 ? 0 : 1);
