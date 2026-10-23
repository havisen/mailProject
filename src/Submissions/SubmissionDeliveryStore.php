<?php

declare(strict_types=1);

namespace App\Submissions;

use App\Jobs\JobResult;

interface SubmissionDeliveryStore
{
    /** @return array<string, mixed>|null */
    public function claimForSending(string $submissionId, ?\DateTimeImmutable $startedAt = null): ?array;

    /** @param list<array{ordinal:int,outcome:string,failure_category:?string}> $outcomes */
    public function finishAttempt(string $submissionId, string $state, ?string $failureCategory, array $outcomes): void;

    /** @param list<array{ordinal:int,outcome:string,failure_category:?string}> $outcomes */
    public function recordAndRequeueDefinitelyNotAccepted(
        string $submissionId,
        string $failureCategory,
        array $outcomes
    ): bool;

    public function failQueuedWithoutAttempt(string $submissionId, string $failureCategory): bool;

    public function recoverExpiredSend(string $submissionId): JobResult;
}
