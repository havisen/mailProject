<?php

declare(strict_types=1);

namespace App\Services;

use App\Api\ApiKeyStore;
use App\Api\ApiPrincipal;
use App\Api\IssuedApiKey;

final class ApiKeyService
{
    private string $dummyHash;

    public function __construct(private ApiKeyStore $keys)
    {
        $this->dummyHash = password_hash(random_bytes(32), PASSWORD_ARGON2ID);
    }

    /** @param list<string> $accountIds */
    public function create(string $label, array $accountIds): IssuedApiKey
    {
        $label = trim($label);
        if ($label === '' || $this->length($label) > 120) {
            throw new \InvalidArgumentException('Label is required and must be at most 120 characters.');
        }
        $accountIds = array_values(array_unique(array_map('strval', $accountIds)));
        if ($accountIds === []) {
            throw new \InvalidArgumentException('At least one sending account is required.');
        }
        [$secret, $prefix] = $this->allocateSecret();
        $publicId = $this->allocatePublicId();
        $createdAt = gmdate('Y-m-d H:i:s.u');
        $this->keys->create([
            'public_id' => $publicId,
            'lookup_prefix' => $prefix,
            'secret_hash' => password_hash($secret, PASSWORD_ARGON2ID),
            'label' => $label,
            'created_at' => $createdAt,
        ], $accountIds);
        return new IssuedApiKey($publicId, $secret, $label, $createdAt);
    }

    public function authenticate(string $secret): ?ApiPrincipal
    {
        $prefix = $this->prefixFromSecret($secret);
        $record = $prefix === null ? null : $this->keys->findByPrefix($prefix);
        $hash = $record['secret_hash'] ?? $this->dummyHash;
        if (!password_verify($secret, (string) $hash) || $record === null || $record['revoked_at'] !== null) {
            return null;
        }
        $this->keys->recordUse((int) $record['id'], gmdate('Y-m-d H:i:s.u'));
        return new ApiPrincipal(
            (int) $record['id'],
            (string) $record['public_id'],
            (string) $record['scope'],
            array_values($record['account_ids'])
        );
    }

    public function revoke(string $publicId): bool
    {
        return $this->keys->revoke($publicId, gmdate('Y-m-d H:i:s.u'));
    }

    /** Create a replacement while leaving the old key valid for an explicit overlap period. */
    public function createReplacement(string $oldPublicId, string $label, array $accountIds): IssuedApiKey
    {
        $issued = $this->create($label, $accountIds);
        $replacement = $this->keys->findByPrefix($this->prefixFromSecret($issued->secret) ?? '');
        if ($replacement === null || !$this->keys->linkReplacement(
            $oldPublicId,
            (int) $replacement['id'],
            gmdate('Y-m-d H:i:s.u')
        )) {
            $this->keys->revoke($issued->publicId, gmdate('Y-m-d H:i:s.u'));
            throw new \InvalidArgumentException('Key being replaced was not found.');
        }
        return $issued;
    }

    /** @return array{string,string} */
    private function allocateSecret(): array
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $prefix = 'mail_sk_' . bin2hex(random_bytes(8));
            if (!$this->keys->prefixExists($prefix)) {
                $random = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                return [$prefix . '_' . $random, $prefix];
            }
        }
        throw new \RuntimeException('Could not allocate an API key prefix.');
    }

    private function allocatePublicId(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = 'key_' . bin2hex(random_bytes(16));
            if (!$this->keys->publicIdExists($id)) {
                return $id;
            }
        }
        throw new \RuntimeException('Could not allocate an API key ID.');
    }

    private function prefixFromSecret(string $secret): ?string
    {
        return preg_match('/\A(mail_sk_[a-f0-9]{16})_[A-Za-z0-9_-]{43}\z/D', $secret, $matches) === 1
            ? $matches[1]
            : null;
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}
