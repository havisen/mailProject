<?php

declare(strict_types=1);

use App\App\AppContext;
use App\Http\RequestIdMiddleware;
use Slim\Factory\AppFactory;

/** @var AppContext $context */
$context = require __DIR__ . '/app.php';

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    $context->config->bool('app.debug'),
    true,
    false,
    $context->logger,
);
$app->add(new RequestIdMiddleware());

// Feature routes are registered only when their owning milestone is integrated.
return $app;
