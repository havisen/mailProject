<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    type VARCHAR(80) NOT NULL,
    queue_name ENUM('outbound', 'sync', 'maintenance') NOT NULL,
    target_type VARCHAR(80) NOT NULL,
    target_reference VARCHAR(191) NOT NULL,
    account_reference CHAR(37) DEFAULT NULL,
    dedupe_key VARCHAR(191) DEFAULT NULL,
    payload_json JSON DEFAULT NULL,
    checkpoint_json JSON DEFAULT NULL,
    status ENUM('queued', 'leased', 'completed', 'failed', 'canceled') NOT NULL DEFAULT 'queued',
    priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    scheduled_at DATETIME(6) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    lease_owner VARCHAR(128) DEFAULT NULL,
    lease_expires_at DATETIME(6) DEFAULT NULL,
    heartbeat_at DATETIME(6) DEFAULT NULL,
    failure_category VARCHAR(64) DEFAULT NULL,
    completed_at DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_jobs_public_id (public_id),
    UNIQUE KEY uq_jobs_type_dedupe (type, dedupe_key),
    KEY idx_jobs_claim (queue_name, status, scheduled_at, priority, id),
    KEY idx_jobs_expired_lease (status, lease_expires_at),
    KEY idx_jobs_target (target_type, target_reference),
    KEY idx_jobs_account (account_reference, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS job_account_leases (
    account_reference CHAR(37) NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    worker_id VARCHAR(128) NOT NULL,
    lease_expires_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (account_reference),
    UNIQUE KEY uq_job_account_leases_job (job_id),
    KEY idx_job_account_leases_expiry (lease_expires_at),
    CONSTRAINT fk_job_account_leases_job
        FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS worker_runs (
    worker_id VARCHAR(128) NOT NULL,
    queue_name VARCHAR(32) NOT NULL,
    started_at DATETIME(6) NOT NULL,
    heartbeat_at DATETIME(6) NOT NULL,
    finished_at DATETIME(6) DEFAULT NULL,
    exit_reason VARCHAR(64) DEFAULT NULL,
    jobs_completed INT UNSIGNED NOT NULL DEFAULT 0,
    jobs_failed INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (worker_id),
    KEY idx_worker_runs_heartbeat (heartbeat_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
