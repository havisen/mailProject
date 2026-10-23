<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Api\ApiKeyStore;
use PDO;

final class ApiKeyRepository implements ApiKeyStore
{
    public function __construct(private PDO $pdo)
    {
    }

    public function create(array $key, array $accountIds): int
    {
        if ($accountIds === []) {
            throw new \InvalidArgumentException('At least one sending account is required.');
        }
        $owned = !$this->pdo->inTransaction();
        if ($owned) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO api_keys
                    (public_id, lookup_prefix, secret_hash, label, scope, created_at)
                 VALUES (:public_id, :lookup_prefix, :secret_hash, :label, :scope, :created_at)'
            );
            $statement->execute([
                ':public_id' => $key['public_id'],
                ':lookup_prefix' => $key['lookup_prefix'],
                ':secret_hash' => $key['secret_hash'],
                ':label' => $key['label'],
                ':scope' => 'mail:send',
                ':created_at' => $key['created_at'],
            ]);
            $id = (int) $this->pdo->lastInsertId();
            $mapping = $this->pdo->prepare(
                'INSERT INTO api_key_accounts (api_key_id, account_id, created_at)
                 SELECT :key_id, id, :created_at FROM mail_accounts WHERE public_id = :account_id'
            );
            foreach (array_values(array_unique($accountIds)) as $accountId) {
                $mapping->execute([':key_id' => $id, ':created_at' => $key['created_at'], ':account_id' => $accountId]);
                if ($mapping->rowCount() !== 1) {
                    throw new \InvalidArgumentException('A selected sending account does not exist.');
                }
            }
            $this->audit($id, 'created', (string) $key['created_at']);
            if ($owned) {
                $this->pdo->commit();
            }
            return $id;
        } catch (\Throwable $throwable) {
            if ($owned && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    public function findByPrefix(string $prefix): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, public_id, lookup_prefix, secret_hash, scope, revoked_at
               FROM api_keys WHERE lookup_prefix = :prefix LIMIT 1'
        );
        $statement->execute([':prefix' => $prefix]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $accounts = $this->pdo->prepare(
            'SELECT a.public_id FROM api_key_accounts ka
             JOIN mail_accounts a ON a.id = ka.account_id
             WHERE ka.api_key_id = :id ORDER BY a.public_id'
        );
        $accounts->execute([':id' => $row['id']]);
        $row['account_ids'] = array_map('strval', $accounts->fetchAll(PDO::FETCH_COLUMN) ?: []);
        return $row;
    }

    /** @return list<array<string, mixed>> Hashes are deliberately excluded. */
    public function listForPanel(): array
    {
        $statement = $this->pdo->query(
            'SELECT public_id, lookup_prefix, label, scope, created_at, last_used_at, revoked_at,
                    replaced_by_key_id
               FROM api_keys ORDER BY created_at DESC, id DESC'
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recordUse(int $id, string $usedAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE api_keys SET last_used_at = :used_at WHERE id = :id AND revoked_at IS NULL'
        );
        $statement->execute([':used_at' => $usedAt, ':id' => $id]);
    }

    public function revoke(string $publicId, string $revokedAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE api_keys SET revoked_at = :revoked_at WHERE public_id = :public_id AND revoked_at IS NULL'
        );
        $statement->execute([':revoked_at' => $revokedAt, ':public_id' => $publicId]);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $id = $this->idForPublicId($publicId);
        $this->audit($id, 'revoked', $revokedAt);
        return true;
    }

    public function linkReplacement(string $oldPublicId, int $replacementId, string $recordedAt): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE api_keys SET replaced_by_key_id = :replacement WHERE public_id = :public_id'
        );
        $statement->execute([':replacement' => $replacementId, ':public_id' => $oldPublicId]);
        if ($statement->rowCount() !== 1) {
            return false;
        }
        $this->audit($this->idForPublicId($oldPublicId), 'replacement_linked', $recordedAt);
        return true;
    }

    public function publicIdExists(string $publicId): bool
    {
        return $this->exists('public_id', $publicId);
    }

    public function prefixExists(string $prefix): bool
    {
        return $this->exists('lookup_prefix', $prefix);
    }

    private function exists(string $column, string $value): bool
    {
        $statement = $this->pdo->prepare("SELECT 1 FROM api_keys WHERE {$column} = :value LIMIT 1");
        $statement->execute([':value' => $value]);
        return (bool) $statement->fetchColumn();
    }

    private function idForPublicId(string $publicId): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM api_keys WHERE public_id = :public_id');
        $statement->execute([':public_id' => $publicId]);
        return (int) $statement->fetchColumn();
    }

    private function audit(int $id, string $event, string $at): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO api_key_audit_events (api_key_id, event_type, recorded_at)
             VALUES (:id, :event, :recorded_at)'
        );
        $statement->execute([':id' => $id, ':event' => $event, ':recorded_at' => $at]);
    }
}
