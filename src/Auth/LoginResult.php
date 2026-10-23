<?php

declare(strict_types=1);

namespace App\Auth;

final readonly class LoginResult
{
    private function __construct(
        public bool $succeeded,
        public ?IssuedAdminSession $session,
        public int $retryAfterSeconds,
    ) {
    }

    public static function success(IssuedAdminSession $session): self
    {
        return new self(true, $session, 0);
    }

    public static function failure(int $retryAfterSeconds = 0): self
    {
        return new self(false, null, max(0, $retryAfterSeconds));
    }
}
