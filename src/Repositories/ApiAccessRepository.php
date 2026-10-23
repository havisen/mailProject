<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Api\ApiException;
use PDO;

final class ApiAccessRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** Counts every authenticated request, including replays and rejected payloads. */
    public function consumeRequestLimit(int $keyId, int $limit, \DateTimeImmutable $now): void
    {
        $window = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:00.000000');
        $owned = !$this->pdo->inTransaction();
        if ($owned) {
            $this->pdo->beginTransaction();
        }
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO api_rate_limit_buckets
                    (subject_type, subject_id, window_name, window_started_at, counter, updated_at)
                 VALUES (\'key\', :id, \'minute\', :window, 1, :now)
                 ON DUPLICATE KEY UPDATE counter = counter + 1, updated_at = VALUES(updated_at)'
            );
            $statement->execute([':id' => $keyId, ':window' => $window, ':now' => self::sqlTime($now)]);
            $count = $this->bucketCount('key', $keyId, 'minute', $window);
            if ($owned) {
                $this->pdo->commit();
            }
            if ($count > $limit) {
                throw new ApiException(429, 'request_rate_limited', 'Too many API requests.', 60 - (int) $now->format('s'));
            }
        } catch (\Throwable $throwable) {
            if ($owned && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    /** Must be called inside the submission transaction. */
    public function consumeRecipientQuota(string $accountId, int $recipients, int $configuredLimit, \DateTimeImmutable $now): void
    {
        $account = $this->pdo->prepare(
            'SELECT id, enabled, max_hourly_recipients FROM mail_accounts
             WHERE public_id = :public_id LIMIT 1 FOR UPDATE'
        );
        $account->execute([':public_id' => $accountId]);
        $row = $account->fetch(PDO::FETCH_ASSOC);
        if ($row === false || empty($row['enabled'])) {
            throw new ApiException(503, 'account_unavailable', 'The sending account is unavailable.');
        }
        $limit = min($configuredLimit, (int) $row['max_hourly_recipients']);
        $window = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00.000000');
        $statement = $this->pdo->prepare(
            'INSERT INTO api_rate_limit_buckets
                (subject_type, subject_id, window_name, window_started_at, counter, updated_at)
             VALUES (\'account\', :id, \'hour\', :window, :increment, :now)
             ON DUPLICATE KEY UPDATE counter = counter + VALUES(counter), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            ':id' => (int) $row['id'],
            ':window' => $window,
            ':increment' => $recipients,
            ':now' => self::sqlTime($now),
        ]);
        if ($this->bucketCount('account', (int) $row['id'], 'hour', $window) > $limit) {
            $retry = 3600 - ((int) $now->format('i') * 60 + (int) $now->format('s'));
            throw new ApiException(429, 'recipient_quota_exceeded', 'The account recipient quota is exhausted.', $retry);
        }
    }

    /** Locks authentication state through replay/new-work decision. */
    public function isKeyActive(int $keyId, string $publicId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT revoked_at FROM api_keys WHERE id = :id AND public_id = :public_id LIMIT 1 FOR UPDATE'
        );
        $statement->execute([':id' => $keyId, ':public_id' => $publicId]);
        $revokedAt = $statement->fetchColumn();
        return $revokedAt !== false && $revokedAt === null;
    }

    public function deleteExpiredIdentifier(int $keyId, string $endpoint, string $identifierHash, string $now): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM api_idempotency
             WHERE api_key_id = :key_id AND endpoint = :endpoint
               AND identifier_hash = :identifier AND expires_at <= :now'
        );
        $statement->bindValue(':key_id', $keyId, PDO::PARAM_INT);
        $statement->bindValue(':endpoint', $endpoint);
        $statement->bindValue(':identifier', $identifierHash, PDO::PARAM_LOB);
        $statement->bindValue(':now', $now);
        $statement->execute();
    }

    /** @return array<string, mixed>|null */
    public function findReplay(int $keyId, string $endpoint, string $identifierHash, string $now): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT payload_fingerprint, response_json FROM api_idempotency
             WHERE api_key_id = :key_id AND endpoint = :endpoint
               AND identifier_hash = :identifier AND expires_at > :now LIMIT 1'
        );
        $statement->bindValue(':key_id', $keyId, PDO::PARAM_INT);
        $statement->bindValue(':endpoint', $endpoint);
        $statement->bindValue(':identifier', $identifierHash, PDO::PARAM_LOB);
        $statement->bindValue(':now', $now);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function storeReplay(
        int $keyId,
        string $endpoint,
        string $identifierHash,
        string $fingerprint,
        string $submissionPublicId,
        string $responseJson,
        string $expiresAt,
        string $createdAt
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO api_idempotency
                (api_key_id, endpoint, identifier_hash, payload_fingerprint,
                 submission_id, response_json, expires_at, created_at)
             SELECT :key_id, :endpoint, :identifier, :fingerprint,
                    s.id, :response, :expires_at, :created_at
               FROM outgoing_submissions s WHERE s.public_id = :submission_id'
        );
        $statement->bindValue(':key_id', $keyId, PDO::PARAM_INT);
        $statement->bindValue(':endpoint', $endpoint);
        $statement->bindValue(':identifier', $identifierHash, PDO::PARAM_LOB);
        $statement->bindValue(':fingerprint', $fingerprint, PDO::PARAM_LOB);
        $statement->bindValue(':submission_id', $submissionPublicId);
        $statement->bindValue(':response', $responseJson);
        $statement->bindValue(':expires_at', $expiresAt);
        $statement->bindValue(':created_at', $createdAt);
        $statement->execute();
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Idempotency mapping could not be stored.');
        }
    }

    private function bucketCount(string $type, int $id, string $windowName, string $window): int
    {
        $statement = $this->pdo->prepare(
            'SELECT counter FROM api_rate_limit_buckets
             WHERE subject_type = :type AND subject_id = :id
               AND window_name = :name AND window_started_at = :window'
        );
        $statement->execute([':type' => $type, ':id' => $id, ':name' => $windowName, ':window' => $window]);
        return (int) $statement->fetchColumn();
    }

    private static function sqlTime(\DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
