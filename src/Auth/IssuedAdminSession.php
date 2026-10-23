<?php

declare(strict_types=1);

namespace App\Auth;

final readonly class IssuedAdminSession
{
    public function __construct(public string $cookieToken, public AdminSession $identity)
    {
    }
}
