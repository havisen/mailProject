<?php

declare(strict_types=1);

return static function (\PDO $pdo): void {
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(80) NOT NULL,
    administrator_id BIGINT UNSIGNED DEFAULT NULL,
    request_id CHAR(32) DEFAULT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    subject_type VARCHAR(40) DEFAULT NULL,
    subject_reference VARCHAR(128) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_events_created (created_at),
    KEY idx_audit_events_admin_created (administrator_id, created_at),
    KEY idx_audit_events_type_created (event_type, created_at),
    CONSTRAINT fk_audit_events_admin
        FOREIGN KEY (administrator_id) REFERENCES administrators (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
};
