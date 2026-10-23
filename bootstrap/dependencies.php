<?php

declare(strict_types=1);

use App\App\AppContext;
use App\Database\MigrationRunner;
use Twig\Environment as TwigEnvironment;

/** @var AppContext $context */
$context = require __DIR__ . '/app.php';

return [
    AppContext::class => static fn (): AppContext => $context,
    TwigEnvironment::class => static fn (): TwigEnvironment => $context->view,
    MigrationRunner::class => static fn (): MigrationRunner => new MigrationRunner(
        $context->pdo,
        dirname(__DIR__) . '/database/migrations',
    ),
];
