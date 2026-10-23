<?php

declare(strict_types=1);

namespace App\Api;

interface ApiKeyStore
{
    /** @param array<string, mixed> $key @param list<string> $accountIds */
    public function create(array $key, array $accountIds): int;

    /** @return array<string, mixed>|null */
    public function findByPrefix(string $prefix): ?array;

    public function recordUse(int $id, string $usedAt): void;

    public function revoke(string $publicId, string $revokedAt): bool;

    public function linkReplacement(string $oldPublicId, int $replacementId, string $recordedAt): bool;

    public function publicIdExists(string $publicId): bool;

    public function prefixExists(string $prefix): bool;
}
