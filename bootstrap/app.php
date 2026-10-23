<?php

declare(strict_types=1);

use App\App\AppContext;
use App\Config\ConfigLoader;
use App\Database\DatabaseConfig;
use App\Database\PdoFactory;
use App\Logging\LoggerFactory;
use App\Runtime\Environment;
use App\View\TwigFactory;

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Composer dependencies are not installed.');
}
require_once $autoload;

$config = ConfigLoader::fromEnvironment(Environment::variables(), dirname(__DIR__));
$logger = LoggerFactory::create($config);
$pdo = PdoFactory::create(DatabaseConfig::fromConfig($config));

return new AppContext($config, $pdo, $logger, TwigFactory::create($config));
