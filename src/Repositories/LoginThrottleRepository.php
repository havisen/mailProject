<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final readonly class LoginThrottleRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function retryAfter(string $scopeHash, DateTimeImmutable $now): int
    {
        $statement = $this->pdo->prepare(
            'SELECT blocked_until FROM administrator_login_throttles WHERE scope_hash = :scope_hash'
        );
        $statement->execute(['scope_hash' => $scopeHash]);
        $blockedUntil = $statement->fetchColumn();
        if (!is_string($blockedUntil) || $blockedUntil === '') {
            return 0;
        }
        $blocked = new DateTimeImmutable($blockedUntil, new DateTimeZone('UTC'));
        return max(0, $blocked->getTimestamp() - $now->getTimestamp());
    }

    public function recordFailure(
        string $scopeHash,
        DateTimeImmutable $now,
        int $windowSeconds,
        int $maximumFailures,
        int $blockSeconds,
    ): int {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('Throttle updates own their transaction.');
        }

        $formattedNow = self::format($now);
        $this->pdo->beginTransaction();
        try {
            $seed = $this->pdo->prepare(
                'INSERT IGNORE INTO administrator_login_throttles
                    (scope_hash, window_started_at, attempts, blocked_until, updated_at)
                 VALUES (:scope_hash, :now, 0, NULL, :now)'
            );
            $seed->execute(['scope_hash' => $scopeHash, 'now' => $formattedNow]);

            $lock = $this->pdo->prepare(
                'SELECT window_started_at, attempts, blocked_until
                   FROM administrator_login_throttles
                  WHERE scope_hash = :scope_hash FOR UPDATE'
            );
            $lock->execute(['scope_hash' => $scopeHash]);
            $row = $lock->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new \RuntimeException('Login throttle row could not be locked.');
            }

            $windowStart = new DateTimeImmutable((string) $row['window_started_at'], new DateTimeZone('UTC'));
            $windowExpired = $windowStart->getTimestamp() <= $now->getTimestamp() - $windowSeconds;
            $attempts = $windowExpired ? 1 : ((int) $row['attempts'] + 1);
            $newWindowStart = $windowExpired ? $formattedNow : (string) $row['window_started_at'];
            $blockedUntil = $windowExpired ? null : ($row['blocked_until'] ?: null);
            if ($attempts >= $maximumFailures) {
                $candidate = $now->modify('+' . $blockSeconds . ' seconds');
                if ($blockedUntil === null || (new DateTimeImmutable((string) $blockedUntil, new DateTimeZone('UTC'))) < $candidate) {
                    $blockedUntil = self::format($candidate);
                }
            }

            $update = $this->pdo->prepare(
                'UPDATE administrator_login_throttles
                    SET window_started_at = :window_started_at, attempts = :attempts,
                        blocked_until = :blocked_until, updated_at = :updated_at
                  WHERE scope_hash = :scope_hash'
            );
            $update->execute([
                'window_started_at' => $newWindowStart,
                'attempts' => $attempts,
                'blocked_until' => $blockedUntil,
                'updated_at' => $formattedNow,
                'scope_hash' => $scopeHash,
            ]);
            $this->pdo->commit();

            return $blockedUntil === null
                ? 0
                : max(0, (new DateTimeImmutable((string) $blockedUntil, new DateTimeZone('UTC')))->getTimestamp() - $now->getTimestamp());
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $throwable;
        }
    }

    /** @param list<string> $scopeHashes */
    public function clear(array $scopeHashes): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM administrator_login_throttles WHERE scope_hash = :scope_hash'
        );
        foreach (array_unique($scopeHashes) as $scopeHash) {
            $statement->execute(['scope_hash' => $scopeHash]);
        }
    }

    private static function format(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
}
