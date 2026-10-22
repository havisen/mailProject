<?php

declare(strict_types=1);

use App\Repositories\AccountRepository;
use App\Repositories\JobRepository;
use App\Repositories\SubmissionRepository;
use App\Security\CredentialCipher;
use App\Security\QueuedContentCipher;
use App\Services\SubmissionService;
use App\Submissions\SubmissionRequest;
use App\Submissions\SubmissionValidator;

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $root . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$dsn = getenv('MAILPANEL_TEST_DB_DSN');
if (!is_string($dsn) || $dsn === '' || getenv('MAILPANEL_TEST_ALLOW_RESET') !== '1') {
    fwrite(STDERR, "Set MAILPANEL_TEST_DB_DSN for a disposable database and MAILPANEL_TEST_ALLOW_RESET=1.\n");
    exit(77);
}

$pdo = new PDO(
    $dsn,
    (string) (getenv('MAILPANEL_TEST_DB_USER') ?: ''),
    (string) (getenv('MAILPANEL_TEST_DB_PASSWORD') ?: ''),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['worker_runs', 'job_account_leases', 'jobs', 'submission_recipient_outcomes', 'submission_recipients', 'outgoing_submissions', 'mail_accounts'] as $table) {
    $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
foreach (['002_create_accounts.php', '003_create_submissions.php', '007_create_jobs.php'] as $migration) {
    (require $root . '/database/migrations/' . $migration)($pdo);
}

$accounts = new AccountRepository($pdo);
$accountId = 'acct_' . bin2hex(random_bytes(16));
$accounts->insert([
    'public_id' => $accountId,
    'label' => 'Disposable test sender',
    'smtp_host' => 'mail.example.com',
    'smtp_port' => 587,
    'smtp_tls' => 'starttls',
    'smtp_username' => 'sender@example.com',
    'password_enc' => 'not-a-real-secret',
    'password_aad' => '',
    'from_email' => 'sender@example.com',
    'from_name' => 'Test Sender',
    'enabled' => true,
]);
$submissions = new SubmissionRepository($pdo);
$jobs = new JobRepository($pdo);
$contentKey = base64_encode(random_bytes(CredentialCipher::KEY_BYTES));
$service = new SubmissionService(
    $pdo,
    $accounts,
    $submissions,
    $jobs,
    new QueuedContentCipher(['test-key' => $contentKey], 'test-key'),
    new SubmissionValidator(300, 86400, 'mail.example.com')
);

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
$request = static fn (string $operation, string $subject = 'Database test'): SubmissionRequest => new SubmissionRequest(
    $accountId,
    'panel',
    ['recipient@example.com'],
    [],
    ['blind@example.com'],
    $subject,
    'Fixture content only',
    browserOperationId: $operation
);

$first = null;
$test('submission and job commit atomically', function () use ($service, $request, $pdo, &$first): void {
    $first = $service->create($request('operation_database_0001'));
    $submissionCount = (int) $pdo->query('SELECT COUNT(*) FROM outgoing_submissions')->fetchColumn();
    $jobCount = (int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
    if ($submissionCount !== 1 || $jobCount !== 1 || $first->state !== 'queued') {
        throw new RuntimeException('Submission/job atomic commit failed.');
    }
});

$test('same browser operation queues once', function () use ($service, $request, $pdo, &$first): void {
    $again = $service->create($request('operation_database_0001'));
    if (!$again->duplicate || $again->submissionId !== $first->submissionId) {
        throw new RuntimeException('Idempotent replay did not return the original.');
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn() !== 1) {
        throw new RuntimeException('Idempotent replay created an extra job.');
    }
});

$test('changed content conflicts with browser operation', function () use ($service, $request): void {
    try {
        $service->create($request('operation_database_0001', 'Changed content'));
    } catch (DomainException) {
        return;
    }
    throw new RuntimeException('Changed content was accepted under the same operation.');
});

$test('caller rollback leaves no sendable orphan', function () use ($pdo, $service, $request): void {
    $pdo->beginTransaction();
    $pending = $service->createInCurrentTransaction($request('operation_database_rollback'));
    $pdo->rollBack();
    if ($sub = $pdo->query("SELECT COUNT(*) FROM outgoing_submissions WHERE browser_operation_id = 'operation_database_rollback'")->fetchColumn()) {
        throw new RuntimeException('Rolled-back submission remained.');
    }
    if ($pdo->query("SELECT COUNT(*) FROM jobs WHERE target_reference = " . $pdo->quote($pending->submissionId))->fetchColumn()) {
        throw new RuntimeException('Rolled-back job remained.');
    }
});

$test('queued cancellation cancels its queued job', function () use ($service, $request, $pdo): void {
    $created = $service->create($request('operation_database_cancel'));
    if (!$service->cancelQueued($created->submissionId)) {
        throw new RuntimeException('Queued cancellation failed.');
    }
    $state = $pdo->query('SELECT state FROM outgoing_submissions WHERE public_id = ' . $pdo->quote($created->submissionId))->fetchColumn();
    $jobState = $pdo->query('SELECT status FROM jobs WHERE target_reference = ' . $pdo->quote($created->submissionId))->fetchColumn();
    if ($state !== 'canceled' || $jobState !== 'canceled') {
        throw new RuntimeException('Cancellation was not atomic.');
    }
});

$test('sending claim wins safely over later cancellation and interruption becomes uncertain', function () use ($service, $request, $pdo): void {
    $created = $service->create($request('operation_database_race'));
    $snapshot = $service->claimForSending($created->submissionId);
    if (($snapshot['subject'] ?? null) !== 'Database test' || $service->cancelQueued($created->submissionId)) {
        throw new RuntimeException('Cancellation won after sending claim.');
    }
    $decision = $service->recoverExpiredSend($created->submissionId);
    $state = $pdo->query('SELECT state FROM outgoing_submissions WHERE public_id = ' . $pdo->quote($created->submissionId))->fetchColumn();
    if ($decision->failureCategory !== 'uncertain_outcome' || $state !== 'uncertain') {
        throw new RuntimeException('Interrupted send was not made uncertain.');
    }
});

$test('account lease prevents overlapping account work', function () use ($jobs, $accountId): void {
    $one = $jobs->enqueue([
        'type' => 'sync.account', 'queue_name' => 'sync', 'target_type' => 'account',
        'target_reference' => 'one', 'account_reference' => $accountId,
    ]);
    $jobs->enqueue([
        'type' => 'sync.account', 'queue_name' => 'sync', 'target_type' => 'account',
        'target_reference' => 'two', 'account_reference' => $accountId,
    ]);
    $claimed = $jobs->claim(['sync'], 'worker:test:one', 30);
    $overlap = $jobs->claim(['sync'], 'worker:test:two', 30);
    if (($claimed['public_id'] ?? null) !== $one || $overlap !== null) {
        throw new RuntimeException('Account overlap was not prevented.');
    }
    $jobs->complete($one, 'worker:test:one');
    if ($jobs->claim(['sync'], 'worker:test:two', 30) === null) {
        throw new RuntimeException('Second account job did not become available.');
    }
});

fwrite(STDOUT, sprintf("Database checks failed: %d\n", $failed));
exit($failed === 0 ? 0 : 1);
