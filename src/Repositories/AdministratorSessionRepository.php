<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final readonly class AdministratorSessionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, mixed> $session */
    public function create(array $session): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO administrator_sessions
                (administrator_id, token_hash, csrf_secret, security_version, ip_hash,
                 user_agent_hash, created_at, last_seen_at, idle_expires_at, absolute_expires_at)
             VALUES
                (:administrator_id, :token_hash, :csrf_secret, :security_version, :ip_hash,
                 :user_agent_hash, :created_at, :last_seen_at, :idle_expires_at, :absolute_expires_at)'
        );
        $statement->execute($session);
        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.*, a.email, a.security_version AS administrator_security_version, a.disabled_at
               FROM administrator_sessions s
               JOIN administrators a ON a.id = s.administrator_id
              WHERE s.token_hash = :token_hash
              LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function touch(int $sessionId, string $lastSeenAt, string $idleExpiresAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrator_sessions
                SET last_seen_at = :last_seen_at, idle_expires_at = :idle_expires_at
              WHERE id = :id AND revoked_at IS NULL'
        );
        $statement->execute([
            'last_seen_at' => $lastSeenAt,
            'idle_expires_at' => $idleExpiresAt,
            'id' => $sessionId,
        ]);
    }

    public function revokeById(int $sessionId, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrator_sessions SET revoked_at = COALESCE(revoked_at, :now) WHERE id = :id'
        );
        $statement->execute(['now' => $now, 'id' => $sessionId]);
    }

    public function revokeByTokenHash(string $tokenHash, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrator_sessions SET revoked_at = COALESCE(revoked_at, :now) WHERE token_hash = :token_hash'
        );
        $statement->execute(['now' => $now, 'token_hash' => $tokenHash]);
    }

    public function revokeAllForAdministrator(int $administratorId, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrator_sessions
                SET revoked_at = COALESCE(revoked_at, :now)
              WHERE administrator_id = :administrator_id'
        );
        $statement->execute(['now' => $now, 'administrator_id' => $administratorId]);
    }
}
