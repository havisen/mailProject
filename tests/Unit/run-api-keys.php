<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/Api/ApiKeyStore.php';
require_once __DIR__ . '/../../src/Api/ApiPrincipal.php';
require_once __DIR__ . '/../../src/Api/IssuedApiKey.php';
require_once __DIR__ . '/../../src/Services/ApiKeyService.php';

use App\Api\ApiKeyStore;
use App\Services\ApiKeyService;

final class MemoryApiKeyStore implements ApiKeyStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    private int $nextId = 1;

    public function create(array $key, array $accountIds): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = $key + [
            'id' => $id,
            'scope' => 'mail:send',
            'account_ids' => $accountIds,
            'revoked_at' => null,
            'last_used_at' => null,
            'replaced_by_key_id' => null,
        ];
        return $id;
    }

    public function findByPrefix(string $prefix): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['lookup_prefix'] === $prefix) {
                return $row;
            }
        }
        return null;
    }

    public function recordUse(int $id, string $usedAt): void
    {
        $this->rows[$id]['last_used_at'] = $usedAt;
    }

    public function revoke(string $publicId, string $revokedAt): bool
    {
        foreach ($this->rows as $id => $row) {
            if ($row['public_id'] === $publicId && $row['revoked_at'] === null) {
                $this->rows[$id]['revoked_at'] = $revokedAt;
                return true;
            }
        }
        return false;
    }

    public function linkReplacement(string $oldPublicId, int $replacementId, string $recordedAt): bool
    {
        foreach ($this->rows as $id => $row) {
            if ($row['public_id'] === $oldPublicId) {
                $this->rows[$id]['replaced_by_key_id'] = $replacementId;
                return true;
            }
        }
        return false;
    }

    public function publicIdExists(string $publicId): bool
    {
        return array_any($this->rows, static fn (array $row): bool => $row['public_id'] === $publicId);
    }

    public function prefixExists(string $prefix): bool
    {
        return array_any($this->rows, static fn (array $row): bool => $row['lookup_prefix'] === $prefix);
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$store = new MemoryApiKeyStore();
$service = new ApiKeyService($store);
$first = $service->create('production sender', ['acct_a']);
check(str_starts_with($first->secret, 'mail_sk_'), 'Creation must reveal a typed secret once.');
check(!array_any($store->rows, static fn (array $row): bool => in_array($first->secret, $row, true)), 'Plaintext secret must not be stored.');
check(password_verify($first->secret, $store->rows[1]['secret_hash']), 'Stored hash must verify the secret.');

$principal = $service->authenticate($first->secret);
check($principal !== null && $principal->permits('mail:send', 'acct_a'), 'Valid key must carry sender scope.');
check(!$principal->permits('mail:send', 'acct_b'), 'Key must reject an unassigned account.');
check(!$principal->permits('mail:read', 'acct_a'), 'Key must have no read scope.');
check($service->authenticate($first->secret . 'x') === null, 'Invalid key must fail.');

$replacement = $service->createReplacement($first->publicId, 'replacement', ['acct_a']);
check($service->authenticate($first->secret) !== null, 'Rotation must allow explicit overlap.');
check($service->authenticate($replacement->secret) !== null, 'Replacement must authenticate.');
check($service->revoke($first->publicId), 'Original key must revoke.');
check($service->authenticate($first->secret) === null, 'Revoked key must fail before any replay authorization.');
check($service->authenticate($replacement->secret) !== null, 'Replacement must remain active.');

echo "PASS api key creation, hash-only storage, scope, overlap, and revocation\n";
