<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Prepared-statement persistence for SMTP sending accounts.
 *
 * Panel reads deliberately omit the encrypted password. Only the explicit
 * worker/service lookup returns encrypted credential fields.
 */
class AccountRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listForPanel(int $limit = 100): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, public_id, label, smtp_host, smtp_port, smtp_tls, smtp_username,
                    from_email, from_name, reply_to_email, enabled, pause_reason,
                    max_recipients, max_hourly_recipients, max_outbound_bytes,
                    password_changed_at, created_at, updated_at
               FROM mail_accounts
              ORDER BY updated_at DESC, public_id ASC
              LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findByPublicId(string $publicId): ?array
    {
        return $this->find($publicId, false);
    }

    /** @return array<string, mixed>|null */
    public function findWithSecretByPublicId(string $publicId): ?array
    {
        return $this->find($publicId, true);
    }

    /** @param array<string, mixed> $account */
    public function insert(array $account): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO mail_accounts
                (public_id, label, smtp_host, smtp_port, smtp_tls, smtp_username,
                 password_enc, password_aad, password_changed_at,
                 from_email, from_name, reply_to_email, enabled, pause_reason,
                 max_recipients, max_hourly_recipients, max_outbound_bytes,
                 created_at, updated_at)
             VALUES
                (:public_id, :label, :smtp_host, :smtp_port, :smtp_tls, :smtp_username,
                 :password_enc, :password_aad, :password_changed_at,
                 :from_email, :from_name, :reply_to_email, :enabled, :pause_reason,
                 :max_recipients, :max_hourly_recipients, :max_outbound_bytes,
                 :created_at, :updated_at)'
        );
        $statement->execute([
            ':public_id' => $account['public_id'],
            ':label' => $account['label'],
            ':smtp_host' => $account['smtp_host'],
            ':smtp_port' => (int) $account['smtp_port'],
            ':smtp_tls' => $account['smtp_tls'],
            ':smtp_username' => $account['smtp_username'],
            ':password_enc' => $account['password_enc'],
            ':password_aad' => $account['password_aad'] ?? '',
            ':password_changed_at' => $account['password_changed_at'] ?? $now,
            ':from_email' => $account['from_email'],
            ':from_name' => $account['from_name'] ?? '',
            ':reply_to_email' => $account['reply_to_email'] ?? null,
            ':enabled' => !empty($account['enabled']) ? 1 : 0,
            ':pause_reason' => $account['pause_reason'] ?? null,
            ':max_recipients' => (int) ($account['max_recipients'] ?? 10),
            ':max_hourly_recipients' => (int) ($account['max_hourly_recipients'] ?? 100),
            ':max_outbound_bytes' => (int) ($account['max_outbound_bytes'] ?? 20971520),
            ':created_at' => $account['created_at'] ?? $now,
            ':updated_at' => $account['updated_at'] ?? $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $account */
    public function update(string $publicId, array $account): bool
    {
        $assignments = [
            'label = :label',
            'smtp_host = :smtp_host',
            'smtp_port = :smtp_port',
            'smtp_tls = :smtp_tls',
            'smtp_username = :smtp_username',
            'from_email = :from_email',
            'from_name = :from_name',
            'reply_to_email = :reply_to_email',
            'enabled = :enabled',
            'pause_reason = :pause_reason',
            'max_recipients = :max_recipients',
            'max_hourly_recipients = :max_hourly_recipients',
            'max_outbound_bytes = :max_outbound_bytes',
            'updated_at = :updated_at',
        ];
        $parameters = [
            ':public_id' => $publicId,
            ':label' => $account['label'],
            ':smtp_host' => $account['smtp_host'],
            ':smtp_port' => (int) $account['smtp_port'],
            ':smtp_tls' => $account['smtp_tls'],
            ':smtp_username' => $account['smtp_username'],
            ':from_email' => $account['from_email'],
            ':from_name' => $account['from_name'] ?? '',
            ':reply_to_email' => $account['reply_to_email'] ?? null,
            ':enabled' => !empty($account['enabled']) ? 1 : 0,
            ':pause_reason' => $account['pause_reason'] ?? null,
            ':max_recipients' => (int) ($account['max_recipients'] ?? 10),
            ':max_hourly_recipients' => (int) ($account['max_hourly_recipients'] ?? 100),
            ':max_outbound_bytes' => (int) ($account['max_outbound_bytes'] ?? 20971520),
            ':updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        if (array_key_exists('password_enc', $account)) {
            $assignments[] = 'password_enc = :password_enc';
            $assignments[] = 'password_aad = :password_aad';
            $assignments[] = 'password_changed_at = :password_changed_at';
            $parameters[':password_enc'] = $account['password_enc'];
            $parameters[':password_aad'] = $account['password_aad'] ?? '';
            $parameters[':password_changed_at'] = $account['password_changed_at'] ?? gmdate('Y-m-d H:i:s');
        }

        $statement = $this->pdo->prepare(
            'UPDATE mail_accounts SET ' . implode(', ', $assignments) . ' WHERE public_id = :public_id'
        );
        $statement->execute($parameters);

        return $statement->rowCount() > 0;
    }

    public function disable(string $publicId, string $reason): bool
    {
        $reason = function_exists('mb_substr') ? mb_substr($reason, 0, 255) : substr($reason, 0, 255);
        $statement = $this->pdo->prepare(
            'UPDATE mail_accounts
                SET enabled = 0, pause_reason = :reason, updated_at = :updated_at
              WHERE public_id = :public_id'
        );
        $statement->execute([
            ':reason' => $reason,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
            ':public_id' => $publicId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function delete(string $publicId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM mail_accounts WHERE public_id = :public_id');
        $statement->execute([':public_id' => $publicId]);

        return $statement->rowCount() > 0;
    }

    public function existsPublicId(string $publicId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM mail_accounts WHERE public_id = :public_id LIMIT 1');
        $statement->execute([':public_id' => $publicId]);

        return (bool) $statement->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function find(string $publicId, bool $withSecret): ?array
    {
        $secretColumns = $withSecret ? ', password_enc, password_aad' : '';
        $statement = $this->pdo->prepare(
            'SELECT id, public_id, label, smtp_host, smtp_port, smtp_tls, smtp_username'
            . $secretColumns
            . ', password_changed_at, from_email, from_name, reply_to_email,
                 enabled, pause_reason, max_recipients, max_hourly_recipients,
                 max_outbound_bytes, created_at, updated_at
               FROM mail_accounts
              WHERE public_id = :public_id
              LIMIT 1'
        );
        $statement->execute([':public_id' => $publicId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }
}
