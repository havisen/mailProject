#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Jobs\JobHandler;
use App\Jobs\WorkerRunner;
use App\Repositories\JobRepository;

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    spl_autoload_register(static function (string $class) use ($root): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $path = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    });
}

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "worker.php may only run from the CLI.\n");
    exit(64);
}

$options = getopt('', ['queue::', 'budget::', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Usage: php bin/worker.php [--queue=outbound|sync|maintenance|all] [--budget=1..55]\n");
    exit(0);
}

$configuration = require $root . '/config/queue.php';
$queueOption = (string) ($options['queue'] ?? 'all');
$queues = $queueOption === 'all' ? array_keys($configuration['queues']) : [$queueOption];
if (array_diff($queues, JobRepository::QUEUES) !== []) {
    fwrite(STDERR, "Unknown queue. Use outbound, sync, maintenance, or all.\n");
    exit(64);
}
$budget = (int) ($options['budget'] ?? $configuration['run_budget_seconds']);
if ($budget < 1 || $budget > 55) {
    fwrite(STDERR, "Worker budget must be between 1 and 55 seconds.\n");
    exit(64);
}

$dsn = getenv('MAILPANEL_DB_DSN');
if (!is_string($dsn) || $dsn === '') {
    fwrite(STDERR, "MAILPANEL_DB_DSN is required.\n");
    exit(78);
}

try {
    $pdo = new PDO(
        $dsn,
        (string) (getenv('MAILPANEL_DB_USER') ?: ''),
        (string) (getenv('MAILPANEL_DB_PASSWORD') ?: ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $repository = new JobRepository($pdo);
    $runner = new WorkerRunner(
        $repository,
        (int) $configuration['lease_seconds'],
        (int) $configuration['minimum_start_seconds'],
        (int) $configuration['recovery_limit']
    );

    $registrationFile = $root . '/bootstrap/worker.php';
    if (is_file($registrationFile)) {
        $handlers = require $registrationFile;
        if (!is_array($handlers)) {
            throw new RuntimeException('bootstrap/worker.php must return a job-type-to-handler map.');
        }
        foreach ($handlers as $type => $handler) {
            if (!is_string($type) || !$handler instanceof JobHandler) {
                throw new RuntimeException('Invalid worker handler registration.');
            }
            $runner->register($type, $handler);
        }
    }

    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static fn () => $runner->requestStop());
        pcntl_signal(SIGINT, static fn () => $runner->requestStop());
    }

    $result = $runner->run($queues, $budget);
    fwrite(STDOUT, sprintf(
        "worker finished: completed=%d failed=%d reason=%s\n",
        $result['completed'],
        $result['failed'],
        $result['reason']
    ));
    exit($result['failed'] > 0 ? 1 : 0);
} catch (PDOException $exception) {
    fwrite(STDERR, "Database unavailable; worker did not run.\n");
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, "Worker failed safely: " . $throwable->getMessage() . "\n");
    exit(1);
}
