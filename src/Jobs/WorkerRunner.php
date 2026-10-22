<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\JobRepository;

final class WorkerRunner
{
    /** @var array<string, JobHandler> */
    private array $handlers = [];
    private bool $stop = false;

    public function __construct(
        private JobRepository $jobs,
        private int $leaseSeconds = 30,
        private int $minimumStartSeconds = 5,
        private int $recoveryLimit = 25
    ) {
    }

    public function register(string $type, JobHandler $handler): void
    {
        if (isset($this->handlers[$type])) {
            throw new \LogicException('A handler is already registered for job type ' . $type . '.');
        }
        $this->handlers[$type] = $handler;
    }

    public function requestStop(): void
    {
        $this->stop = true;
    }

    /** @param list<string> $queues @return array{completed:int,failed:int,reason:string} */
    public function run(array $queues, int $budgetSeconds, ?string $workerId = null): array
    {
        $budgetSeconds = max(1, min(55, $budgetSeconds));
        $workerId ??= sprintf('worker:%s:%d:%s', gethostname() ?: 'host', getmypid(), bin2hex(random_bytes(4)));
        $queueLabel = implode(',', $queues);
        $deadline = microtime(true) + $budgetSeconds;
        $completed = 0;
        $failed = 0;
        $reason = 'budget_exhausted';
        $this->jobs->startWorker($workerId, $queueLabel);

        try {
            for ($recovered = 0; $recovered < $this->recoveryLimit && !$this->stop; $recovered++) {
                if (($deadline - microtime(true)) < $this->minimumStartSeconds) {
                    break;
                }
                $job = $this->jobs->claimExpiredForRecovery($queues, $workerId, $this->leaseSeconds);
                if ($job === null) {
                    break;
                }
                $result = $this->recover($job, $workerId, $deadline);
                $this->applyResult($job, $workerId, $result);
                $result->action === 'complete' ? $completed++ : $failed++;
            }

            while (!$this->stop && ($deadline - microtime(true)) >= $this->minimumStartSeconds) {
                $job = $this->jobs->claim($queues, $workerId, $this->leaseSeconds);
                if ($job === null) {
                    $reason = 'queue_empty';
                    break;
                }
                $result = $this->execute($job, $workerId, $deadline);
                $this->applyResult($job, $workerId, $result);
                $result->action === 'complete' ? $completed++ : $failed++;
                $this->jobs->heartbeatWorker($workerId);
            }
            if ($this->stop) {
                $reason = 'stop_requested';
            }
        } finally {
            $this->jobs->finishWorker($workerId, $reason, $completed, $failed);
        }

        return ['completed' => $completed, 'failed' => $failed, 'reason' => $reason];
    }

    /** @param array<string, mixed> $job */
    private function execute(array $job, string $workerId, float $deadline): JobResult
    {
        $handler = $this->handlers[(string) $job['type']] ?? null;
        if ($handler === null) {
            return JobResult::fail('configuration');
        }
        try {
            return $handler->handle($job, $this->context($job, $workerId, $deadline));
        } catch (\Throwable) {
            return JobResult::fail('internal');
        }
    }

    /** @param array<string, mixed> $job */
    private function recover(array $job, string $workerId, float $deadline): JobResult
    {
        $handler = $this->handlers[(string) $job['type']] ?? null;
        if ($handler === null) {
            return JobResult::fail('configuration');
        }
        try {
            return $handler->recoverExpired($job, $this->context($job, $workerId, $deadline));
        } catch (\Throwable) {
            return JobResult::fail('internal');
        }
    }

    /** @param array<string, mixed> $job */
    private function context(array $job, string $workerId, float $deadline): JobExecutionContext
    {
        return new JobExecutionContext(
            $this->jobs,
            (string) $job['public_id'],
            $workerId,
            $this->leaseSeconds,
            $deadline,
            fn (): bool => $this->stop
        );
    }

    /** @param array<string, mixed> $job */
    private function applyResult(array $job, string $workerId, JobResult $result): void
    {
        $ok = match ($result->action) {
            'complete' => $this->jobs->complete((string) $job['public_id'], $workerId),
            'fail' => $this->jobs->fail(
                (string) $job['public_id'],
                $workerId,
                $result->failureCategory ?? 'internal'
            ),
            'retry' => $this->jobs->retry(
                (string) $job['public_id'],
                $workerId,
                $result->failureCategory ?? 'internal',
                $result->scheduledAt ?? gmdate('Y-m-d H:i:s.u', time() + 60)
            ),
            default => false,
        };
        if (!$ok) {
            throw new \RuntimeException('Job lease was lost before the handler result could be stored.');
        }
    }
}
