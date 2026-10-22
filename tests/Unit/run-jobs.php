<?php

declare(strict_types=1);

use App\Jobs\JobExecutionContext;
use App\Jobs\JobHandler;
use App\Jobs\JobResult;
use App\Jobs\WorkerRunner;
use App\Repositories\JobRepository;

require __DIR__ . '/../../src/Repositories/JobRepository.php';
require __DIR__ . '/../../src/Jobs/JobHandler.php';
require __DIR__ . '/../../src/Jobs/JobResult.php';
require __DIR__ . '/../../src/Jobs/JobExecutionContext.php';
require __DIR__ . '/../../src/Jobs/WorkerRunner.php';

final class MemoryJobRepository extends JobRepository
{
    /** @var list<array<string, mixed>> */
    public array $queued = [];
    /** @var list<array<string, mixed>> */
    public array $expired = [];
    /** @var list<array{action:string,id:string,category:?string}> */
    public array $results = [];
    /** @var list<array<string, mixed>> */
    public array $checkpoints = [];

    public function __construct()
    {
    }

    public function claim(array $queues, string $workerId, int $leaseSeconds): ?array
    {
        return array_shift($this->queued);
    }

    public function claimExpiredForRecovery(array $queues, string $workerId, int $leaseSeconds): ?array
    {
        return array_shift($this->expired);
    }

    public function heartbeat(string $publicId, string $workerId, int $leaseSeconds, ?array $checkpoint = null): bool
    {
        if ($checkpoint !== null) {
            $this->checkpoints[] = $checkpoint;
        }
        return true;
    }

    public function complete(string $publicId, string $workerId): bool
    {
        $this->results[] = ['action' => 'complete', 'id' => $publicId, 'category' => null];
        return true;
    }

    public function fail(string $publicId, string $workerId, string $category): bool
    {
        $this->results[] = ['action' => 'fail', 'id' => $publicId, 'category' => $category];
        return true;
    }

    public function retry(string $publicId, string $workerId, string $category, string $scheduledAt): bool
    {
        $this->results[] = ['action' => 'retry', 'id' => $publicId, 'category' => $category];
        return true;
    }

    public function startWorker(string $workerId, string $queueName): void
    {
    }

    public function heartbeatWorker(string $workerId): void
    {
    }

    public function finishWorker(string $workerId, string $reason, int $completed, int $failed): void
    {
    }
}

final class RecordingHandler implements JobHandler
{
    public int $handled = 0;
    public int $recovered = 0;

    public function __construct(private JobResult $normal, private JobResult $recovery)
    {
    }

    public function handle(array $job, JobExecutionContext $context): JobResult
    {
        $this->handled++;
        if (($job['checkpoint'] ?? null) !== null) {
            $context->checkpoint(['cursor' => 42]);
        }
        if ($context->networkTimeoutSeconds(20) > 20) {
            throw new RuntimeException('Network timeout exceeded configured maximum.');
        }
        return $this->normal;
    }

    public function recoverExpired(array $job, JobExecutionContext $context): JobResult
    {
        $this->recovered++;
        return $this->recovery;
    }
}

/** @return array<string, mixed> */
function fakeJob(string $id, string $type, ?array $checkpoint = null): array
{
    return [
        'public_id' => $id,
        'type' => $type,
        'checkpoint' => $checkpoint,
    ];
}

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
        fwrite(STDERR, "FAIL - {$message}\n");
    } else {
        fwrite(STDOUT, "ok - {$message}\n");
    }
};

$repository = new MemoryJobRepository();
$repository->queued[] = fakeJob('job_' . str_repeat('a', 32), 'sync.account', ['cursor' => 1]);
$handler = new RecordingHandler(JobResult::complete(), JobResult::retry('network', gmdate('Y-m-d H:i:s', time() + 60)));
$runner = new WorkerRunner($repository, 30, 1, 5);
$runner->register('sync.account', $handler);
$result = $runner->run(['sync'], 3, 'worker:test:1');
$check($handler->handled === 1, 'a claimed job is handled exactly once');
$check(count($repository->checkpoints) === 1, 'a sync handler persists a resumable checkpoint');
$check($repository->results[0]['action'] === 'complete', 'a successful handler completes its lease');
$check($result['completed'] === 1 && $result['reason'] === 'queue_empty', 'runner reports bounded completion');

$repository = new MemoryJobRepository();
$repository->expired[] = fakeJob('job_' . str_repeat('b', 32), 'smtp.send');
$sendHandler = new RecordingHandler(JobResult::complete(), JobResult::fail('uncertain_outcome'));
$runner = new WorkerRunner($repository, 30, 1, 5);
$runner->register('smtp.send', $sendHandler);
$runner->run(['outbound'], 3, 'worker:test:2');
$check($sendHandler->handled === 0 && $sendHandler->recovered === 1, 'an expired send delegates to feature recovery');
$check($repository->results[0]['category'] === 'uncertain_outcome', 'expired SMTP is not blindly retried');

$repository = new MemoryJobRepository();
$repository->expired[] = fakeJob('job_' . str_repeat('c', 32), 'sync.account');
$syncHandler = new RecordingHandler(JobResult::complete(), JobResult::retry('network', gmdate('Y-m-d H:i:s', time() + 60)));
$runner = new WorkerRunner($repository, 30, 1, 5);
$runner->register('sync.account', $syncHandler);
$runner->run(['sync'], 3, 'worker:test:3');
$check($repository->results[0]['action'] === 'retry', 'expired sync can explicitly resume through its handler');

$repository = new MemoryJobRepository();
$repository->queued[] = fakeJob('job_' . str_repeat('d', 32), 'missing.handler');
$runner = new WorkerRunner($repository, 30, 1, 5);
$runner->run(['maintenance'], 3, 'worker:test:4');
$check($repository->results[0]['category'] === 'configuration', 'unregistered work fails visibly and safely');

fwrite(STDOUT, sprintf("Passed: %d, Failed: %d\n", 8 - count($failures), count($failures)));
exit($failures === [] ? 0 : 1);
