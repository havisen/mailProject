<?php

declare(strict_types=1);

/**
 * SMTP-only account settings. IMAP settings belong to a future additive
 * migration and must not be added to this table as part of the sender slice.
 */
return static function (\PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS mail_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(37) NOT NULL,
    label VARCHAR(120) NOT NULL,
    smtp_host VARCHAR(253) NOT NULL,
    smtp_port SMALLINT UNSIGNED NOT NULL,
    smtp_tls ENUM('implicit', 'starttls') NOT NULL,
    smtp_username VARCHAR(255) NOT NULL,
    password_enc TEXT NOT NULL,
    password_aad VARCHAR(190) NOT NULL DEFAULT '',
    password_changed_at DATETIME NOT NULL,
    from_email VARCHAR(254) NOT NULL,
    from_name VARCHAR(120) NOT NULL DEFAULT '',
    reply_to_email VARCHAR(254) DEFAULT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    pause_reason VARCHAR(255) DEFAULT NULL,
    max_recipients SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    max_hourly_recipients SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    max_outbound_bytes INT UNSIGNED NOT NULL DEFAULT 20971520,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mail_accounts_public_id (public_id),
    KEY idx_mail_accounts_enabled (enabled),
    KEY idx_mail_accounts_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
