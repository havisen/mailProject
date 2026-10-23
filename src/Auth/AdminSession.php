<?php

declare(strict_types=1);

namespace App\Auth;

final readonly class AdminSession
{
    public function __construct(
        public int $sessionId,
        public int $administratorId,
        public string $email,
        public int $securityVersion,
        public string $csrfSecret,
        public int $absoluteExpiry,
    ) {
    }
}
