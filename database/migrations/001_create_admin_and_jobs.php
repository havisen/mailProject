<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS administrators (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    singleton_key TINYINT UNSIGNED NOT NULL DEFAULT 1,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    security_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    last_login_at DATETIME(6) DEFAULT NULL,
    password_changed_at DATETIME(6) NOT NULL,
    disabled_at DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_administrators_singleton (singleton_key),
    UNIQUE KEY uq_administrators_email (email),
    KEY idx_administrators_enabled (disabled_at),
    CONSTRAINT chk_administrators_singleton CHECK (singleton_key = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS administrator_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    administrator_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    csrf_secret CHAR(64) NOT NULL,
    security_version BIGINT UNSIGNED NOT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    user_agent_hash CHAR(64) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    last_seen_at DATETIME(6) NOT NULL,
    idle_expires_at DATETIME(6) NOT NULL,
    absolute_expires_at DATETIME(6) NOT NULL,
    revoked_at DATETIME(6) DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_administrator_sessions_token_hash (token_hash),
    KEY idx_administrator_sessions_admin_active (administrator_id, revoked_at, absolute_expires_at),
    KEY idx_administrator_sessions_expiry (revoked_at, idle_expires_at, absolute_expires_at),
    CONSTRAINT fk_administrator_sessions_admin
        FOREIGN KEY (administrator_id) REFERENCES administrators (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS administrator_login_throttles (
    scope_hash CHAR(64) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    blocked_until DATETIME(6) DEFAULT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (scope_hash),
    KEY idx_administrator_login_throttles_cleanup (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
