<?php

declare(strict_types=1);

namespace App\Logging;

use App\Config\Config;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use RuntimeException;

final class LoggerFactory
{
    public static function create(Config $config): Logger
    {
        $directory = $config->string('paths.logs');
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new RuntimeException('Private log directory is not writable.');
        }

        $handler = new StreamHandler(
            rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'application.log',
            Level::Info,
            true,
            0660,
        );
        $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_JSON, true));

        $logger = new Logger('mailpanel');
        $logger->pushProcessor(new SecretRedactor());
        $logger->pushHandler($handler);

        return $logger;
    }
}
