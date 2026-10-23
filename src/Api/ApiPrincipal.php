<?php

declare(strict_types=1);

namespace App\Api;

final readonly class ApiPrincipal
{
    /** @param list<string> $accountIds */
    public function __construct(
        public int $databaseId,
        public string $publicId,
        public string $scope,
        public array $accountIds
    ) {
    }

    public function permits(string $scope, string $accountId): bool
    {
        return hash_equals($this->scope, $scope) && in_array($accountId, $this->accountIds, true);
    }
}
