<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final readonly class AuditRepository
{
    public const EVENT_TYPES = [
        'administrator_created',
        'administrator_password_reset',
        'login_succeeded',
        'login_failed',
        'login_throttled',
        'logout',
        'password_changed',
        'session_expired',
        'csrf_rejected',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function record(
        string $eventType,
        ?int $administratorId,
        ?string $requestId,
        ?string $ipHash,
        string $now,
    ): void {
        if (!in_array($eventType, self::EVENT_TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported audit event type.');
        }
        if ($requestId !== null && preg_match('/^[a-f0-9]{32}$/', $requestId) !== 1) {
            $requestId = null;
        }
        if ($ipHash !== null && preg_match('/^[a-f0-9]{64}$/', $ipHash) !== 1) {
            $ipHash = null;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO audit_events
                (event_type, administrator_id, request_id, ip_hash, created_at)
             VALUES (:event_type, :administrator_id, :request_id, :ip_hash, :created_at)'
        );
        $statement->execute([
            'event_type' => $eventType,
            'administrator_id' => $administratorId,
            'request_id' => $requestId,
            'ip_hash' => $ipHash,
            'created_at' => $now,
        ]);
    }
}
