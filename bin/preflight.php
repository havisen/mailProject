<?php

declare(strict_types=1);

use App\Config\ConfigException;
use App\Config\ConfigLoader;
use App\Database\DatabaseConfig;
use App\Database\PdoFactory;
use App\Runtime\Environment;
use App\Runtime\Preflight;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is CLI-only.\n");
    exit(64);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Composer dependencies are not installed.\n");
    exit(1);
}
require_once $autoload;

try {
    $config = ConfigLoader::fromEnvironment(Environment::variables(), dirname(__DIR__));
} catch (ConfigException $exception) {
    fwrite(STDERR, 'Configuration error: ' . $exception->getMessage() . "\n");
    exit(78);
}

try {
    $pdo = PdoFactory::create(DatabaseConfig::fromConfig($config));
} catch (Throwable) {
    $pdo = null;
}

$results = (new Preflight($config, $pdo))->run();
$exitCode = 0;
foreach ($results as $result) {
    fwrite(STDOUT, sprintf("[%s] %s: %s\n", $result->passed ? 'OK' : 'FAIL', $result->name, $result->message));
    if (!$result->passed) {
        $exitCode = $result->category === 'database' ? max($exitCode, 3) : max($exitCode, 1);
    }
}
exit($exitCode);
