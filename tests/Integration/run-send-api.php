<?php

declare(strict_types=1);

use App\Api\ApiException;
use App\Api\SendRequestValidator;
use App\Repositories\AccountRepository;
use App\Repositories\ApiAccessRepository;
use App\Repositories\ApiKeyRepository;
use App\Repositories\JobRepository;
use App\Repositories\SubmissionRepository;
use App\Security\CredentialCipher;
use App\Security\QueuedContentCipher;
use App\Services\ApiKeyService;
use App\Services\ApiSendService;
use App\Services\SubmissionService;
use App\Submissions\SubmissionValidator;

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $path = $root . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

$dsn = getenv('MAILPANEL_TEST_DB_DSN');
if (!is_string($dsn) || $dsn === '' || getenv('MAILPANEL_TEST_ALLOW_RESET') !== '1') {
    fwrite(STDERR, "Set MAILPANEL_TEST_DB_DSN for a disposable database and MAILPANEL_TEST_ALLOW_RESET=1.\n");
    exit(77);
}
$pdo = new PDO($dsn, (string) (getenv('MAILPANEL_TEST_DB_USER') ?: ''), (string) (getenv('MAILPANEL_TEST_DB_PASSWORD') ?: ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$tables = ['api_key_audit_events', 'api_rate_limit_buckets', 'api_idempotency', 'api_key_accounts', 'api_keys',
    'worker_runs', 'job_account_leases', 'jobs', 'submission_recipient_outcomes', 'submission_recipients',
    'outgoing_submissions', 'mail_accounts'];
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tables as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
foreach (['002_create_accounts.php', '003_create_submissions.php', '007_create_jobs.php', '008_create_api_access.php'] as $migration) {
    (require $root . '/database/migrations/' . $migration)($pdo);
}

$accounts = new AccountRepository($pdo);
$accountId = 'acct_' . bin2hex(random_bytes(16));
$accounts->insert([
    'public_id' => $accountId, 'label' => 'API fixture', 'smtp_host' => 'mail.example.test',
    'smtp_port' => 587, 'smtp_tls' => 'starttls', 'smtp_username' => 'sender@example.test',
    'password_enc' => 'synthetic-not-a-secret', 'password_aad' => '', 'from_email' => 'sender@example.test',
    'from_name' => 'Fixture', 'enabled' => true, 'max_hourly_recipients' => 100,
]);
$submissions = new SubmissionService(
    $pdo,
    $accounts,
    new SubmissionRepository($pdo),
    new JobRepository($pdo),
    new QueuedContentCipher(['test' => base64_encode(random_bytes(CredentialCipher::KEY_BYTES))], 'test'),
    new SubmissionValidator(300, 86400, 'mail.example.test')
);
$keyService = new ApiKeyService(new ApiKeyRepository($pdo));
$issued = $keyService->create('integration fixture', [$accountId]);
$principal = $keyService->authenticate($issued->secret) ?? throw new RuntimeException('Fixture key failed.');
$api = new ApiSendService($pdo, new ApiAccessRepository($pdo), $submissions, random_bytes(32), 30, 100, 604800);
$validator = new SendRequestValidator();
$payload = [
    'account_id' => $accountId,
    'to' => [['email' => 'recipient@example.test']],
    'subject' => 'Fixture subject',
    'text' => 'Synthetic content only.',
];
$validated = $validator->validate($payload);
$failed = 0;
$test = static function (string $name, callable $callback) use (&$failed): void {
    try {
        $callback();
        fwrite(STDOUT, "ok - {$name}\n");
    } catch (Throwable $throwable) {
        $failed++;
        fwrite(STDERR, "FAIL - {$name}: {$throwable->getMessage()}\n");
    }
};
$first = null;
$test('submission, job, quota and replay mapping commit together', function () use ($api, $principal, $validated, $pdo, &$first): void {
    $api->consumeRequestAllowance($principal);
    $first = $api->submit($principal, $validated, 'fixture-operation-1', 'req_original');
    foreach (['outgoing_submissions', 'jobs', 'api_idempotency'] as $table) {
        if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 1) {
            throw new RuntimeException($table . ' did not commit exactly once.');
        }
    }
});
$test('same payload replays original 202 data without recipient quota', function () use ($api, $principal, $validated, $pdo, &$first): void {
    $api->consumeRequestAllowance($principal);
    $again = $api->submit($principal, $validated, 'fixture-operation-1', 'req_changed');
    if (!$again['replay'] || $again['response'] !== $first['response']) {
        throw new RuntimeException('Original response was not replayed.');
    }
    $quota = $pdo->query("SELECT counter FROM api_rate_limit_buckets WHERE subject_type = 'account'")->fetchColumn();
    if ((int) $quota !== 1 || (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn() !== 1) {
        throw new RuntimeException('Replay consumed quota or created another job.');
    }
});
$test('changed content conflicts under the same identifier', function () use ($api, $principal, $validator, $payload): void {
    $changed = $payload;
    $changed['subject'] = 'Changed';
    try {
        $api->submit($principal, $validator->validate($changed), 'fixture-operation-1', 'req_conflict');
    } catch (ApiException $exception) {
        if ($exception->httpStatus === 409) {
            return;
        }
    }
    throw new RuntimeException('Changed content was not rejected.');
});
$test('disabled account cannot create a submission', function () use ($pdo, $api, $principal, $validated, $accountId): void {
    $before = (int) $pdo->query('SELECT COUNT(*) FROM outgoing_submissions')->fetchColumn();
    $pdo->prepare('UPDATE mail_accounts SET enabled = 0 WHERE public_id = :id')->execute([':id' => $accountId]);
    try {
        $api->submit($principal, $validated, 'fixture-disabled-account', 'req_disabled');
    } catch (ApiException $exception) {
        $pdo->prepare('UPDATE mail_accounts SET enabled = 1 WHERE public_id = :id')->execute([':id' => $accountId]);
        if ($exception->httpStatus === 503
            && (int) $pdo->query('SELECT COUNT(*) FROM outgoing_submissions')->fetchColumn() === $before) {
            return;
        }
        throw $exception;
    }
    $pdo->prepare('UPDATE mail_accounts SET enabled = 1 WHERE public_id = :id')->execute([':id' => $accountId]);
    throw new RuntimeException('Disabled account was durably accepted.');
});
$failurePrincipal = null;
$test('revocation denies authentication before replay lookup', function () use ($keyService, $issued, $api, $principal, $validated, $accountId, &$failurePrincipal): void {
    if (!$keyService->revoke($issued->publicId) || $keyService->authenticate($issued->secret) !== null) {
        throw new RuntimeException('Revoked key remained valid.');
    }
    try {
        $api->submit($principal, $validated, 'fixture-operation-1', 'req_revoked');
    } catch (ApiException $exception) {
        if ($exception->httpStatus !== 401) {
            throw $exception;
        }
        $replacement = $keyService->create('database failure fixture', [$accountId]);
        $failurePrincipal = $keyService->authenticate($replacement->secret);
        return;
    }
    throw new RuntimeException('Stale principal replayed after revocation.');
});
$test('database outage cannot leave a sendable orphan or success response', function () use ($pdo, $api, &$failurePrincipal, $validated): void {
    if ($failurePrincipal === null) {
        throw new RuntimeException('Replacement fixture principal was not created.');
    }
    $before = (int) $pdo->query('SELECT COUNT(*) FROM outgoing_submissions')->fetchColumn();
    $pdo->exec('DROP TABLE api_idempotency');
    try {
        $api->submit($failurePrincipal, $validated, 'fixture-db-failure', 'req_failure');
    } catch (ApiException $exception) {
        if ($exception->httpStatus === 503
            && (int) $pdo->query('SELECT COUNT(*) FROM outgoing_submissions')->fetchColumn() === $before) {
            return;
        }
    }
    throw new RuntimeException('Database failure was not safely rejected.');
});

fwrite(STDOUT, sprintf("API database checks failed: %d\n", $failed));
exit($failed === 0 ? 0 : 1);
