<?php

declare(strict_types=1);

namespace App\Submissions;

/** A provisional result that must not be exposed as accepted before commit. */
final class PendingSubmission
{
    public function __construct(
        public readonly string $submissionId,
        public readonly string $deadlineAt,
        public readonly string $messageId,
        public readonly bool $duplicate
    ) {
    }
}
