<?php

declare(strict_types=1);

namespace App\Database;

use App\Config\Config;

final readonly class DatabaseConfig
{
    public function __construct(
        public string $dsn,
        public string $username,
        public string $password,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            $config->string('database.dsn'),
            $config->string('database.username'),
            (string) $config->get('database.password', ''),
        );
    }
}
