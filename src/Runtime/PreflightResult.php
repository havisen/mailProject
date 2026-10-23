<?php

declare(strict_types=1);

namespace App\Runtime;

final readonly class PreflightResult
{
    public function __construct(
        public string $name,
        public bool $passed,
        public string $message,
        public string $category = 'runtime',
    ) {
    }
}
