<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS outgoing_submissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    account_reference CHAR(37) NOT NULL,
    source ENUM('panel', 'api', 'test') NOT NULL,
    browser_operation_id VARCHAR(128) DEFAULT NULL,
    message_id VARCHAR(255) NOT NULL,
    snapshot_ciphertext LONGTEXT NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    state ENUM('queued', 'sending', 'accepted', 'partially_accepted', 'failed', 'uncertain', 'expired', 'canceled') NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    deadline_at DATETIME(6) NOT NULL,
    last_error_category VARCHAR(64) DEFAULT NULL,
    sent_copy_state ENUM('not_requested', 'pending', 'accepted', 'failed') NOT NULL DEFAULT 'not_requested',
    sending_started_at DATETIME(6) DEFAULT NULL,
    terminal_at DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_outgoing_submissions_public_id (public_id),
    UNIQUE KEY uq_outgoing_submissions_message_id (message_id),
    UNIQUE KEY uq_outgoing_submissions_browser_operation (account_id, source, browser_operation_id),
    KEY idx_outgoing_submissions_outbox (state, created_at),
    KEY idx_outgoing_submissions_account (account_id, state, created_at),
    KEY idx_outgoing_submissions_deadline (state, deadline_at),
    CONSTRAINT fk_outgoing_submissions_account
        FOREIGN KEY (account_id) REFERENCES mail_accounts (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS submission_recipients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT UNSIGNED NOT NULL,
    recipient_type ENUM('to', 'cc', 'bcc') NOT NULL,
    ordinal SMALLINT UNSIGNED NOT NULL,
    address_ciphertext TEXT NOT NULL,
    address_hash CHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submission_recipient_position (submission_id, ordinal),
    KEY idx_submission_recipient_hash (submission_id, address_hash),
    CONSTRAINT fk_submission_recipients_submission
        FOREIGN KEY (submission_id) REFERENCES outgoing_submissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS submission_recipient_outcomes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_recipient_id BIGINT UNSIGNED NOT NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    outcome ENUM('accepted', 'rejected', 'transient_failure', 'unknown') NOT NULL,
    failure_category VARCHAR(64) DEFAULT NULL,
    recorded_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_submission_recipient_attempt (submission_recipient_id, attempt_number),
    KEY idx_submission_outcomes_recorded (recorded_at),
    CONSTRAINT fk_submission_outcomes_recipient
        FOREIGN KEY (submission_recipient_id) REFERENCES submission_recipients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
