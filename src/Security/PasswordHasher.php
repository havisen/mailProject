<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class PasswordHasher
{
    public function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);
        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
