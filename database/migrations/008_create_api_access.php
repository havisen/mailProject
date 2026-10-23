<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_keys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    lookup_prefix VARCHAR(32) NOT NULL,
    secret_hash VARCHAR(255) NOT NULL,
    label VARCHAR(120) NOT NULL,
    scope VARCHAR(32) NOT NULL DEFAULT 'mail:send',
    created_at DATETIME(6) NOT NULL,
    last_used_at DATETIME(6) DEFAULT NULL,
    revoked_at DATETIME(6) DEFAULT NULL,
    replaced_by_key_id BIGINT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_keys_public_id (public_id),
    UNIQUE KEY uq_api_keys_lookup_prefix (lookup_prefix),
    KEY idx_api_keys_active (revoked_at, id),
    CONSTRAINT fk_api_keys_replacement
        FOREIGN KEY (replaced_by_key_id) REFERENCES api_keys (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_key_accounts (
    api_key_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (api_key_id, account_id),
    KEY idx_api_key_accounts_account (account_id, api_key_id),
    CONSTRAINT fk_api_key_accounts_key
        FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_key_accounts_account
        FOREIGN KEY (account_id) REFERENCES mail_accounts (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_idempotency (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_key_id BIGINT UNSIGNED NOT NULL,
    endpoint VARCHAR(80) NOT NULL,
    identifier_hash BINARY(32) NOT NULL,
    payload_fingerprint BINARY(32) NOT NULL,
    submission_id BIGINT UNSIGNED NOT NULL,
    response_json JSON NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_idempotency_key_endpoint_identifier (api_key_id, endpoint, identifier_hash),
    KEY idx_api_idempotency_expiry (expires_at),
    CONSTRAINT fk_api_idempotency_key
        FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE RESTRICT,
    CONSTRAINT fk_api_idempotency_submission
        FOREIGN KEY (submission_id) REFERENCES outgoing_submissions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_rate_limit_buckets (
    subject_type ENUM('key', 'account') NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    window_name ENUM('minute', 'hour') NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    counter INT UNSIGNED NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (subject_type, subject_id, window_name, window_started_at),
    KEY idx_api_rate_limit_cleanup (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_key_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    api_key_id BIGINT UNSIGNED DEFAULT NULL,
    event_type ENUM('created', 'revoked', 'replacement_linked') NOT NULL,
    recorded_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_api_key_audit_key_time (api_key_id, recorded_at),
    CONSTRAINT fk_api_key_audit_key
        FOREIGN KEY (api_key_id) REFERENCES api_keys (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
