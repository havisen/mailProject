<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final readonly class AdministratorRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM administrators')->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM administrators WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM administrators WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function lockById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM administrators WHERE id = :id LIMIT 1 FOR UPDATE');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function anyPasswordHash(): ?string
    {
        $value = $this->pdo->query('SELECT password_hash FROM administrators ORDER BY id LIMIT 1')->fetchColumn();
        return is_string($value) ? $value : null;
    }

    public function create(string $email, string $passwordHash, string $now): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO administrators
                (email, password_hash, security_version, password_changed_at, created_at, updated_at)
             VALUES (:email, :password_hash, 1, :now, :now, :now)'
        );
        $statement->execute(['email' => $email, 'password_hash' => $passwordHash, 'now' => $now]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateHashIfCurrent(int $id, string $currentHash, string $passwordHash, string $now): bool
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrators
                SET password_hash = :password_hash, updated_at = :now
              WHERE id = :id AND password_hash = :current_hash'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'now' => $now,
            'id' => $id,
            'current_hash' => $currentHash,
        ]);
        return $statement->rowCount() === 1;
    }

    public function changePassword(int $id, string $passwordHash, string $now): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrators
                SET password_hash = :password_hash, security_version = security_version + 1,
                    password_changed_at = :now, updated_at = :now
              WHERE id = :id'
        );
        $statement->execute(['password_hash' => $passwordHash, 'now' => $now, 'id' => $id]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Administrator password update failed.');
        }

        $version = $this->pdo->prepare('SELECT security_version FROM administrators WHERE id = :id');
        $version->execute(['id' => $id]);
        return (int) $version->fetchColumn();
    }

    public function recordLogin(int $id, string $now): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE administrators SET last_login_at = :now, updated_at = :now WHERE id = :id'
        );
        $statement->execute(['now' => $now, 'id' => $id]);
    }
}
