<?php

declare(strict_types=1);

namespace App\App;

use App\Config\Config;
use PDO;
use Psr\Log\LoggerInterface;
use Twig\Environment as TwigEnvironment;

final readonly class AppContext
{
    public function __construct(
        public Config $config,
        public PDO $pdo,
        public LoggerInterface $logger,
        public TwigEnvironment $view,
    ) {
    }
}
