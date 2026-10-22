<?php

declare(strict_types=1);

use App\Security\CredentialCipher;
use App\Security\QueuedContentCipher;
use App\Submissions\SubmissionRequest;
use App\Submissions\SubmissionResult;
use App\Submissions\SubmissionValidator;

require __DIR__ . '/../../src/Security/CredentialCipher.php';
require __DIR__ . '/../../src/Security/QueuedContentCipher.php';
require __DIR__ . '/../../src/Submissions/SubmissionRequest.php';
require __DIR__ . '/../../src/Submissions/SubmissionResult.php';
require __DIR__ . '/../../src/Submissions/SubmissionValidator.php';

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        fwrite(STDOUT, "ok - {$name}\n");
    } catch (Throwable $throwable) {
        $failures[] = $name;
        fwrite(STDERR, "FAIL - {$name}: {$throwable->getMessage()}\n");
    }
};
$expect = static function (bool $condition, string $message = 'Expectation failed.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (string $class, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        if ($throwable instanceof $class) {
            return;
        }
        throw new RuntimeException('Expected ' . $class . ', received ' . $throwable::class . '.');
    }
    throw new RuntimeException('Expected ' . $class . ', but nothing was thrown.');
};

$accountId = 'acct_' . str_repeat('a', 32);
$account = [
    'id' => 1,
    'public_id' => $accountId,
    'enabled' => 1,
    'from_email' => 'sender@example.com',
    'from_name' => 'Example Sender',
    'reply_to_email' => null,
    'max_recipients' => 10,
    'max_outbound_bytes' => 1048576,
];
$submissionId = 'sub_' . str_repeat('b', 32);
$validator = new SubmissionValidator(300, 86400, 'mail.example.com');
$now = new DateTimeImmutable('2026-10-23 10:00:00', new DateTimeZone('UTC'));
$valid = new SubmissionRequest(
    $accountId,
    'panel',
    ['to@example.com'],
    ['copy@example.com'],
    ['blind@example.com'],
    'Subject',
    'Private body',
    null,
    null,
    $now->modify('+5 minutes'),
    'operation_1234567890'
);

$test('valid submission produces a stable sender snapshot and Message-ID', function () use ($validator, $valid, $account, $submissionId, $now, $expect): void {
    $result = $validator->validate($valid, $account, $submissionId, $now);
    $expect($result['snapshot']['from']['email'] === 'sender@example.com');
    $expect($result['message_id'] === '<' . str_repeat('b', 32) . '@mail.example.com>');
    $expect(count($result['recipients']) === 3);
});

$test('caller recipient edits do not mutate the request snapshot', function () use ($validator, $account, $accountId, $submissionId, $now, $expect): void {
    $to = ['original@example.com'];
    $request = new SubmissionRequest($accountId, 'panel', $to, [], [], 'Stable', 'Body');
    $to[0] = 'changed@example.com';
    $result = $validator->validate($request, $account, $submissionId, $now);
    $expect($result['snapshot']['to'] === ['original@example.com']);
});

$test('duplicate recipients are removed across To Cc and Bcc', function () use ($validator, $account, $accountId, $submissionId, $now, $expect): void {
    $request = new SubmissionRequest(
        $accountId,
        'panel',
        ['same@example.com'],
        ['SAME@example.com'],
        ['other@example.com'],
        'Deduplicate',
        'Body'
    );
    $result = $validator->validate($request, $account, $submissionId, $now);
    $expect(count($result['recipients']) === 2);
    $expect($result['snapshot']['cc'] === []);
});

$test('header injection is rejected', function () use ($validator, $valid, $account, $submissionId, $now, $throws): void {
    $request = new SubmissionRequest(
        $valid->accountId,
        'panel',
        $valid->to,
        [],
        [],
        "Subject\r\nBcc: stolen@example.com",
        'Body'
    );
    $throws(InvalidArgumentException::class, fn () => $validator->validate($request, $account, $submissionId, $now));
});

$test('unapproved sender identity is rejected', function () use ($validator, $account, $accountId, $submissionId, $now, $throws): void {
    $request = new SubmissionRequest(
        $accountId,
        'panel',
        ['to@example.com'],
        [],
        [],
        'Subject',
        'Body',
        requestedFrom: 'other@example.com'
    );
    $throws(DomainException::class, fn () => $validator->validate($request, $account, $submissionId, $now));
});

$test('API attachments are rejected even when they are owned references', function () use ($validator, $account, $accountId, $submissionId, $now, $throws): void {
    $request = new SubmissionRequest(
        $accountId,
        'api',
        ['to@example.com'],
        [],
        [],
        'Subject',
        'Body',
        ownedBlobReferences: ['blob_' . str_repeat('c', 32)]
    );
    $throws(InvalidArgumentException::class, fn () => $validator->validate($request, $account, $submissionId, $now));
});

$test('paths and URLs cannot be attachment references', function () use ($validator, $account, $accountId, $submissionId, $now, $throws): void {
    foreach (['C:\\private.txt', '/tmp/private.txt', 'https://example.com/private.txt'] as $value) {
        $request = new SubmissionRequest(
            $accountId,
            'panel',
            ['to@example.com'],
            [],
            [],
            'Subject',
            'Body',
            ownedBlobReferences: [$value]
        );
        $throws(InvalidArgumentException::class, fn () => $validator->validate($request, $account, $submissionId, $now));
    }
});

$test('deadline is an attempt-start limit within 24 hours', function () use ($validator, $account, $accountId, $submissionId, $now, $throws): void {
    $request = new SubmissionRequest(
        $accountId,
        'panel',
        ['to@example.com'],
        [],
        [],
        'Subject',
        'Body',
        deadline: $now->modify('+25 hours')
    );
    $throws(InvalidArgumentException::class, fn () => $validator->validate($request, $account, $submissionId, $now));
});

$test('durable response cannot reveal Bcc or message content', function () use ($expect): void {
    $response = (new SubmissionResult('sub_' . str_repeat('d', 32), 'queued', '2026-10-23 10:05:00', '<id@example.com>', false))->toArray();
    $expect(!isset($response['bcc']) && !isset($response['text']) && !isset($response['html']));
});

$test('queued content encryption is contextual and authenticated', function () use ($submissionId, $expect, $throws): void {
    $key = base64_encode(random_bytes(CredentialCipher::KEY_BYTES));
    $cipher = new QueuedContentCipher(['content-1' => $key], 'content-1');
    $encrypted = $cipher->encryptSnapshot($submissionId, '{"private":true}');
    $expect(!str_contains($encrypted, 'private'));
    $expect($cipher->decryptSnapshot($submissionId, $encrypted) === '{"private":true}');
    $otherId = 'sub_' . str_repeat('e', 32);
    $throws(RuntimeException::class, fn () => $cipher->decryptSnapshot($otherId, $encrypted));
});

fwrite(STDOUT, sprintf("Passed: %d, Failed: %d\n", 10 - count($failures), count($failures)));
exit($failures === [] ? 0 : 1);
