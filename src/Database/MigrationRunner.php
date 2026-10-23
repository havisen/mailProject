<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;
use Throwable;

final class MigrationRunner
{
    private ?string $lockName = null;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationPath,
        private readonly int $lockTimeoutSeconds = 10,
    ) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Migrations require MySQL or MariaDB.');
        }
    }

    /** @return list<string> */
    public function up(): array
    {
        $locked = false;
        try {
            $locked = $this->acquireLock();
            if (!$locked) {
                throw new RuntimeException('Another migration process holds the migration lock.');
            }

            $this->ensureLedger();
            $files = $this->migrationFiles();
            $applied = $this->appliedMigrations();
            $knownFiles = array_fill_keys(array_keys($files), true);
            foreach (array_keys($applied) as $filename) {
                if (!isset($knownFiles[$filename])) {
                    throw new RuntimeException(sprintf('Applied migration %s is missing from disk.', $filename));
                }
            }

            $newlyApplied = [];
            foreach ($files as $filename => $path) {
                $checksum = $this->checksum($path);
                if (isset($applied[$filename])) {
                    if (!hash_equals($applied[$filename], $checksum)) {
                        throw new RuntimeException(sprintf('Applied migration %s has changed.', $filename));
                    }
                    continue;
                }

                try {
                    $migration = require $path;
                } catch (Throwable $exception) {
                    throw new RuntimeException(sprintf('Migration %s could not be loaded.', $filename), 0, $exception);
                }
                if (!is_callable($migration)) {
                    throw new RuntimeException(sprintf('Migration %s must return a callable.', $filename));
                }

                try {
                    $migration($this->pdo);
                    $statement = $this->pdo->prepare(
                        'INSERT INTO schema_migrations (filename, checksum, applied_at) VALUES (:filename, :checksum, UTC_TIMESTAMP(6))'
                    );
                    $statement->execute(['filename' => $filename, 'checksum' => $checksum]);
                } catch (Throwable $exception) {
                    throw new RuntimeException(sprintf('Migration %s failed.', $filename), 0, $exception);
                }
                $newlyApplied[] = $filename;
            }

            return $newlyApplied;
        } finally {
            if ($locked) {
                $this->releaseLock();
            }
        }
    }

    /** @return list<array{filename: string, status: string, checksum: string}> */
    public function status(): array
    {
        $this->ensureLedger();
        $files = $this->migrationFiles();
        $applied = $this->appliedMigrations();
        $status = [];

        foreach ($files as $filename => $path) {
            $checksum = $this->checksum($path);
            $state = !isset($applied[$filename])
                ? 'pending'
                : (hash_equals($applied[$filename], $checksum) ? 'applied' : 'changed');
            $status[] = ['filename' => $filename, 'status' => $state, 'checksum' => $checksum];
            unset($applied[$filename]);
        }
        foreach ($applied as $filename => $checksum) {
            $status[] = ['filename' => $filename, 'status' => 'missing', 'checksum' => $checksum];
        }

        return $status;
    }

    private function ensureLedger(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'filename VARCHAR(191) NOT NULL PRIMARY KEY, '
            . 'checksum CHAR(64) NOT NULL, '
            . 'applied_at DATETIME(6) NOT NULL'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** @return array<string, string> */
    private function migrationFiles(): array
    {
        if (!is_dir($this->migrationPath) || !is_readable($this->migrationPath)) {
            throw new RuntimeException('Migration directory is not readable.');
        }
        $paths = glob(rtrim($this->migrationPath, '/\\') . DIRECTORY_SEPARATOR . '*.php');
        if ($paths === false) {
            throw new RuntimeException('Migration directory could not be scanned.');
        }
        sort($paths, SORT_STRING);

        $files = [];
        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new RuntimeException(sprintf('Migration %s is not readable.', basename($path)));
            }
            if (trim($contents) === '') {
                continue;
            }
            $files[basename($path)] = $path;
        }

        return $files;
    }

    /** @return array<string, string> */
    private function appliedMigrations(): array
    {
        $rows = $this->pdo->query('SELECT filename, checksum FROM schema_migrations ORDER BY filename')->fetchAll();
        $applied = [];
        foreach ($rows as $row) {
            $applied[(string) $row['filename']] = (string) $row['checksum'];
        }

        return $applied;
    }

    private function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException(sprintf('Migration %s could not be checksummed.', basename($path)));
        }

        return $checksum;
    }

    private function acquireLock(): bool
    {
        $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout)');
        $statement->bindValue('name', $this->lockName());
        $statement->bindValue('timeout', $this->lockTimeoutSeconds, PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn() === 1;
    }

    private function releaseLock(): void
    {
        try {
            $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $statement->execute(['name' => $this->lockName()]);
        } catch (Throwable) {
            // The connection closing also releases a MySQL advisory lock. Do not
            // mask the migration result with a cleanup error.
        }
    }

    private function lockName(): string
    {
        if ($this->lockName !== null) {
            return $this->lockName;
        }
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database === '') {
            throw new RuntimeException('The migration connection has no selected database.');
        }

        return $this->lockName = 'mailpanel:migrations:' . substr(hash('sha256', $database), 0, 24);
    }
}
