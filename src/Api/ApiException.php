<?php

declare(strict_types=1);

namespace App\Api;

final class ApiException extends \RuntimeException
{
    public function __construct(
        public readonly int $httpStatus,
        public readonly string $errorCode,
        string $safeMessage,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($safeMessage, 0, $previous);
    }
}
