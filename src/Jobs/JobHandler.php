<?php

declare(strict_types=1);

namespace App\Jobs;

interface JobHandler
{
    /** @param array<string, mixed> $job */
    public function handle(array $job, JobExecutionContext $context): JobResult;

    /**
     * Resolve an interrupted attempt. SMTP handlers must normally mark their
     * submission uncertain; safely resumable sync handlers may return retry.
     *
     * @param array<string, mixed> $job
     */
    public function recoverExpired(array $job, JobExecutionContext $context): JobResult;
}
