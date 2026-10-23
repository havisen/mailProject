<?php

declare(strict_types=1);

use App\App\AppContext;
use App\Config\ConfigException;
use App\Database\MigrationRunner;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is CLI-only.\n");
    exit(64);
}

try {
    /** @var AppContext $context */
    $context = require dirname(__DIR__) . '/bootstrap/app.php';
    $runner = new MigrationRunner($context->pdo, dirname(__DIR__) . '/database/migrations');
    $action = $argv[1] ?? 'status';

    if ($action === 'up') {
        $applied = $runner->up();
        if ($applied === []) {
            fwrite(STDOUT, "No pending migrations.\n");
        } else {
            foreach ($applied as $filename) {
                fwrite(STDOUT, "Applied {$filename}\n");
            }
        }
        exit(0);
    }

    if ($action === 'status') {
        $unsafe = false;
        foreach ($runner->status() as $migration) {
            fwrite(STDOUT, sprintf("%-9s %s\n", strtoupper($migration['status']), $migration['filename']));
            $unsafe = $unsafe || in_array($migration['status'], ['changed', 'missing'], true);
        }
        exit($unsafe ? 2 : 0);
    }

    fwrite(STDERR, "Usage: php bin/migrate.php [status|up]\n");
    exit(64);
} catch (ConfigException $exception) {
    fwrite(STDERR, 'Configuration error: ' . $exception->getMessage() . "\n");
    exit(78);
} catch (PDOException) {
    fwrite(STDERR, "Migration database operation failed.\n");
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration command failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
