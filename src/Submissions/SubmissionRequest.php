<?php

declare(strict_types=1);

namespace App\Submissions;

final class SubmissionRequest
{
    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     * @param list<string> $ownedBlobReferences
     */
    public function __construct(
        public readonly string $accountId,
        public readonly string $source,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly string $subject,
        public readonly string $text,
        public readonly ?string $html = null,
        public readonly ?string $replyTo = null,
        public readonly ?\DateTimeImmutable $deadline = null,
        public readonly ?string $browserOperationId = null,
        public readonly array $ownedBlobReferences = [],
        public readonly ?string $requestedFrom = null
    ) {
    }
}
