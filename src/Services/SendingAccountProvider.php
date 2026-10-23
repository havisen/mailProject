<?php

declare(strict_types=1);

namespace App\Services;

interface SendingAccountProvider
{
    /** @return array<string, mixed>|null Null means missing or paused/disabled. */
    public function configurationForWorker(string $accountId): ?array;

    public function recordAuthenticationFailure(string $accountId): void;
}
