<?php

declare(strict_types=1);

use App\Database\MigrationRunner;

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "SKIP: Composer dependencies are not installed.\n");
    exit(77);
}
require $autoload;

if (getenv('MAILPANEL_TEST_ALLOW_RESET') !== '1') {
    fwrite(STDERR, "SKIP: set MAILPANEL_TEST_ALLOW_RESET=1 for a disposable database.\n");
    exit(77);
}

$dsn = getenv('MAILPANEL_TEST_DB_DSN');
$user = getenv('MAILPANEL_TEST_DB_USER');
$password = getenv('MAILPANEL_TEST_DB_PASSWORD');
if (!is_string($dsn) || $dsn === '' || !is_string($user)) {
    fwrite(STDERR, "SKIP: disposable database variables are not configured.\n");
    exit(77);
}

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mailpanel-migrations-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) {
    throw new RuntimeException('Could not create the migration test directory.');
}

$pdo = new PDO($dsn, $user, is_string($password) ? $password : '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$secondPdo = new PDO($dsn, $user, is_string($password) ? $password : '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    $pdo->exec('DROP TABLE IF EXISTS foundation_migration_failed');
    $pdo->exec('DROP TABLE IF EXISTS foundation_migration_probe');
    $pdo->exec('DROP TABLE IF EXISTS schema_migrations');

    file_put_contents($directory . '/001_probe.php', <<<'PHP'
<?php
return static function (PDO $pdo): void {
    $pdo->exec('CREATE TABLE foundation_migration_probe (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
};
PHP);

    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    $lockName = 'mailpanel:migrations:' . substr(hash('sha256', $database), 0, 24);
    $lockStatement = $secondPdo->prepare('SELECT GET_LOCK(:name, 0)');
    $lockStatement->execute(['name' => $lockName]);
    try {
        (new MigrationRunner($pdo, $directory, 0))->up();
        throw new RuntimeException('Overlapping migration runner was not rejected.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'migration lock'), 'Overlap failure was not safely categorized.');
    } finally {
        $unlockStatement = $secondPdo->prepare('SELECT RELEASE_LOCK(:name)');
        $unlockStatement->execute(['name' => $lockName]);
    }

    $runner = new MigrationRunner($pdo, $directory, 0);
    $assert($runner->up() === ['001_probe.php'], 'Pending migration was not applied once.');
    $assert($runner->up() === [], 'Applied migration ran more than once.');
    $assert((int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 1, 'Migration ledger count is incorrect.');

    file_put_contents($directory . '/001_probe.php', file_get_contents($directory . '/001_probe.php') . "\n// changed\n");
    $status = $runner->status();
    $assert($status[0]['status'] === 'changed', 'Checksum changes were not detected.');
    try {
        $runner->up();
        throw new RuntimeException('Changed migration was incorrectly accepted.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), 'has changed'), 'Checksum failure was not safely reported.');
    }

    unlink($directory . '/001_probe.php');
    $pdo->exec('DROP TABLE foundation_migration_probe');
    $pdo->exec('DROP TABLE schema_migrations');
    file_put_contents($directory . '/001_failure.php', <<<'PHP'
<?php
return static function (PDO $pdo): void {
    $pdo->exec('CREATE TABLE foundation_migration_failed (id INT NOT NULL PRIMARY KEY) ENGINE=InnoDB');
    throw new RuntimeException('controlled failure');
};
PHP);
    try {
        (new MigrationRunner($pdo, $directory, 0))->up();
        throw new RuntimeException('Failing migration was incorrectly accepted.');
    } catch (RuntimeException $exception) {
        $assert(str_contains($exception->getMessage(), '001_failure.php failed'), 'Failure did not identify the migration safely.');
    }
    $assert((int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === 0, 'Failed migration was recorded as applied.');

    fwrite(STDOUT, sprintf("Migration acceptance checks passed (%d assertions).\n", $assertions));
} finally {
    $pdo->exec('DROP TABLE IF EXISTS foundation_migration_failed');
    $pdo->exec('DROP TABLE IF EXISTS foundation_migration_probe');
    $pdo->exec('DROP TABLE IF EXISTS schema_migrations');
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($directory);
}
