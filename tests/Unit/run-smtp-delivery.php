<?php

declare(strict_types=1);

use App\Jobs\JobExecutionContext;
use App\Jobs\JobResult;
use App\Mail\DeliveryResult;
use App\Mail\SmtpTransport;
use App\Mail\TransportMessage;
use App\Repositories\JobRepository;
use App\Services\SendingAccountProvider;
use App\Submissions\SubmissionDeliveryStore;
use App\Workers\SendWorker;

foreach ([
    'src/Repositories/JobRepository.php',
    'src/Jobs/JobHandler.php',
    'src/Jobs/JobResult.php',
    'src/Jobs/JobExecutionContext.php',
    'src/Mail/SmtpTransport.php',
    'src/Mail/TransportMessage.php',
    'src/Mail/DeliveryResult.php',
    'src/Services/SendingAccountProvider.php',
    'src/Submissions/SubmissionDeliveryStore.php',
    'src/Workers/SendWorker.php',
] as $file) {
    require dirname(__DIR__, 2) . '/' . $file;
}

final class DeliveryJobRepository extends JobRepository
{
    public function __construct() {}
    public function heartbeat(string $publicId, string $workerId, int $leaseSeconds, ?array $checkpoint = null): bool { return true; }
    public function heartbeatWorker(string $workerId): void {}
}

final class MemoryDeliveryStore implements SubmissionDeliveryStore
{
    public ?array $snapshot;
    public array $finishes = [];
    public array $requeues = [];
    public bool $failPersistence = false;
    public bool $failedQueued = false;

    public function __construct(int $attempt = 1)
    {
        $this->snapshot = [
            'account_id' => 'acct_' . str_repeat('a', 32),
            'from' => ['email' => 'sender@example.com', 'name' => 'Sender'],
            'reply_to' => null,
            'to' => ['to@example.com'], 'cc' => [], 'bcc' => ['blind@example.com'],
            'subject' => 'Fixture subject', 'text' => 'Fixture body', 'html' => null,
            '_submission' => [
                'id' => 'sub_' . str_repeat('b', 32),
                'message_id' => '<fixture@example.com>',
                'account_id' => 'acct_' . str_repeat('a', 32),
                'attempt' => $attempt,
                'deadline_at' => gmdate('Y-m-d H:i:s.u', time() + 3600),
            ],
        ];
    }

    public function claimForSending(string $submissionId, ?DateTimeImmutable $startedAt = null): ?array { return $this->snapshot; }
    public function finishAttempt(string $submissionId, string $state, ?string $failureCategory, array $outcomes): void
    {
        if ($this->failPersistence) { throw new RuntimeException('controlled persistence failure'); }
        $this->finishes[] = compact('state', 'failureCategory', 'outcomes');
    }
    public function recordAndRequeueDefinitelyNotAccepted(string $submissionId, string $failureCategory, array $outcomes): bool
    {
        $this->requeues[] = compact('failureCategory', 'outcomes'); return true;
    }
    public function failQueuedWithoutAttempt(string $submissionId, string $failureCategory): bool { $this->failedQueued = true; return true; }
    public function recoverExpiredSend(string $submissionId): JobResult { return JobResult::fail('uncertain_outcome'); }
}

final class MemorySendingAccounts implements SendingAccountProvider
{
    public bool $enabled = true;
    public int $authFailures = 0;
    public function configurationForWorker(string $accountId): ?array
    {
        return $this->enabled ? [
            'smtp_host' => 'mail.example.com', 'smtp_port' => 587, 'smtp_tls' => 'starttls',
            'smtp_username' => 'sender@example.com', 'password' => 'fixture-only',
        ] : null;
    }
    public function recordAuthenticationFailure(string $accountId): void { $this->authFailures++; }
}

final class ScriptedSmtpTransport implements SmtpTransport
{
    public ?TransportMessage $lastMessage = null;
    public function __construct(public DeliveryResult $result) {}
    public function deliver(TransportMessage $message): DeliveryResult { $this->lastMessage = $message; return $this->result; }
}

function deliveryResult(string $disposition, ?string $category = null, bool $auth = false): DeliveryResult
{
    $outcome = match ($disposition) {
        'accepted' => 'accepted',
        'partially_accepted' => 'accepted',
        'uncertain' => 'unknown',
        'transient' => 'transient_failure',
        default => 'rejected',
    };
    return new DeliveryResult($disposition, $category, [
        ['ordinal' => 0, 'outcome' => $outcome, 'failure_category' => $category],
        ['ordinal' => 1, 'outcome' => $disposition === 'partially_accepted' ? 'rejected' : $outcome, 'failure_category' => $category],
    ], $auth);
}

function runWorker(MemoryDeliveryStore $store, MemorySendingAccounts $accounts, DeliveryResult $result): array
{
    $transport = new ScriptedSmtpTransport($result);
    $worker = new SendWorker($store, $accounts, $transport);
    $context = new JobExecutionContext(
        new DeliveryJobRepository(), 'job_' . str_repeat('c', 32), 'worker:test', 30,
        microtime(true) + 30, static fn (): bool => false
    );
    return [$worker->handle([
        'target_reference' => 'sub_' . str_repeat('b', 32),
        'account_reference' => 'acct_' . str_repeat('a', 32),
    ], $context), $transport];
}

$failed = 0;
$test = static function (string $name, callable $callback) use (&$failed): void {
    try { $callback(); fwrite(STDOUT, "ok - {$name}\n"); }
    catch (Throwable $e) { $failed++; fwrite(STDERR, "FAIL - {$name}: {$e->getMessage()}\n"); }
};

$test('accepted DATA is terminal and Bcc remains an envelope recipient', function (): void {
    $store = new MemoryDeliveryStore(); $accounts = new MemorySendingAccounts();
    [$result, $transport] = runWorker($store, $accounts, deliveryResult('accepted'));
    if ($result->action !== 'complete' || $store->finishes[0]['state'] !== 'accepted') { throw new RuntimeException('acceptance not terminal'); }
    if ($transport->lastMessage->recipients[1]['type'] !== 'bcc') { throw new RuntimeException('Bcc envelope missing'); }
});
$test('partial acceptance is terminal and never retries the whole message', function (): void {
    $store = new MemoryDeliveryStore(); $accounts = new MemorySendingAccounts();
    [$result] = runWorker($store, $accounts, deliveryResult('partially_accepted', 'permanent'));
    if ($result->action !== 'complete' || $store->requeues !== [] || $store->finishes[0]['state'] !== 'partially_accepted') { throw new RuntimeException('partial result unsafe'); }
});
$test('definite temporary non-acceptance retries at one minute', function (): void {
    $store = new MemoryDeliveryStore(1); $accounts = new MemorySendingAccounts();
    [$result] = runWorker($store, $accounts, deliveryResult('transient', 'network'));
    if ($result->action !== 'retry' || count($store->requeues) !== 1) { throw new RuntimeException('transient did not retry'); }
});
$test('fourth temporary failure becomes terminal', function (): void {
    $store = new MemoryDeliveryStore(4); $accounts = new MemorySendingAccounts();
    [$result] = runWorker($store, $accounts, deliveryResult('transient', 'network'));
    if ($result->action !== 'fail' || $store->finishes[0]['state'] !== 'failed') { throw new RuntimeException('attempt ceiling ignored'); }
});
$test('uncertain DATA is terminal and never retries', function (): void {
    $store = new MemoryDeliveryStore(); $accounts = new MemorySendingAccounts();
    [$result] = runWorker($store, $accounts, deliveryResult('uncertain', 'uncertain_outcome'));
    if ($result->action !== 'fail' || $store->finishes[0]['state'] !== 'uncertain' || $store->requeues !== []) { throw new RuntimeException('uncertain result unsafe'); }
});
$test('persistence failure after SMTP leaves lease for uncertain recovery', function (): void {
    $store = new MemoryDeliveryStore(); $store->failPersistence = true; $accounts = new MemorySendingAccounts();
    [$result] = runWorker($store, $accounts, deliveryResult('accepted'));
    if ($result->action !== 'abandon') { throw new RuntimeException('accepted persistence failure was finalized'); }
});
$test('disabled account is not sent', function (): void {
    $store = new MemoryDeliveryStore(); $accounts = new MemorySendingAccounts(); $accounts->enabled = false;
    [$result] = runWorker($store, $accounts, deliveryResult('accepted'));
    if ($result->failureCategory !== 'account_disabled' || !$store->failedQueued) { throw new RuntimeException('disabled account not respected'); }
});
$test('expired lease delegates to conservative submission recovery', function (): void {
    $store = new MemoryDeliveryStore(); $accounts = new MemorySendingAccounts();
    $worker = new SendWorker($store, $accounts, new ScriptedSmtpTransport(deliveryResult('accepted')));
    $context = new JobExecutionContext(new DeliveryJobRepository(), 'job_' . str_repeat('c', 32), 'worker:test', 30, microtime(true) + 30, static fn (): bool => false);
    $result = $worker->recoverExpired(['target_reference' => 'sub_' . str_repeat('b', 32)], $context);
    if ($result->failureCategory !== 'uncertain_outcome') { throw new RuntimeException('recovery not conservative'); }
});

fwrite(STDOUT, "SMTP worker checks failed: {$failed}\n");
exit($failed === 0 ? 0 : 1);
