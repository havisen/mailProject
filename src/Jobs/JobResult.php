<?php

declare(strict_types=1);

namespace App\Jobs;

final class JobResult
{
    private function __construct(
        public readonly string $action,
        public readonly ?string $failureCategory = null,
        public readonly ?string $scheduledAt = null
    ) {
    }

    public static function complete(): self
    {
        return new self('complete');
    }

    public static function fail(string $failureCategory): self
    {
        return new self('fail', $failureCategory);
    }

    public static function retry(string $failureCategory, string $scheduledAt): self
    {
        return new self('retry', $failureCategory, $scheduledAt);
    }
}
