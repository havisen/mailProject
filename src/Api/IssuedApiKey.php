<?php

declare(strict_types=1);

namespace App\Api;

/** The secret exists only in this creation result and must be displayed once. */
final readonly class IssuedApiKey
{
    public function __construct(
        public string $publicId,
        public string $secret,
        public string $label,
        public string $createdAt
    ) {
    }
}
