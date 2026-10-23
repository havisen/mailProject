<?php

declare(strict_types=1);

namespace App\Api;

use App\Submissions\SubmissionRequest;

final readonly class ValidatedSendRequest
{
    public function __construct(
        public SubmissionRequest $submission,
        public string $canonicalJson,
        public int $recipientCount
    ) {
    }
}
