<?php

declare(strict_types=1);

namespace App\Mail;

final class DeliveryResult
{
    /**
     * @param list<array{ordinal:int,outcome:string,failure_category:?string}> $recipientOutcomes
     */
    public function __construct(
        public readonly string $disposition,
        public readonly ?string $failureCategory,
        public readonly array $recipientOutcomes,
        public readonly bool $authenticationFailure = false
    ) {
        if (!in_array($disposition, ['accepted', 'partially_accepted', 'transient', 'permanent', 'uncertain'], true)) {
            throw new \InvalidArgumentException('Invalid SMTP delivery disposition.');
        }
    }

    public function smtpAcceptancePossible(): bool
    {
        return in_array($this->disposition, ['accepted', 'partially_accepted', 'uncertain'], true);
    }
}
