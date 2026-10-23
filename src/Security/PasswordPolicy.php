<?php

declare(strict_types=1);

namespace App\Security;

use InvalidArgumentException;

final readonly class PasswordPolicy
{
    public function __construct(
        private int $minimumCharacters = 14,
        private int $maximumBytes = 1024,
    ) {
    }

    public function validate(string $password): void
    {
        if (strlen($password) > $this->maximumBytes) {
            throw new InvalidArgumentException('Password is too long.');
        }
        if (!mb_check_encoding($password, 'UTF-8')) {
            throw new InvalidArgumentException('Password must be valid UTF-8.');
        }
        if (mb_strlen($password, 'UTF-8') < $this->minimumCharacters) {
            throw new InvalidArgumentException(sprintf('Password must contain at least %d characters.', $this->minimumCharacters));
        }
        if (str_contains($password, "\0") || str_contains($password, "\r") || str_contains($password, "\n")) {
            throw new InvalidArgumentException('Password contains unsupported control characters.');
        }
    }
}
