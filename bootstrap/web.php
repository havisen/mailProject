<?php

declare(strict_types=1);

use App\App\AppContext;
use App\Http\RequestIdMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use Slim\Factory\AppFactory;

$dependencies = require __DIR__ . '/dependencies.php';
/** @var AppContext $context */
$context = $dependencies[AppContext::class];

$app = AppFactory::create();
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(
    $context->config->bool('app.debug'),
    true,
    false,
    $context->logger,
);
$registerWebRoutes = require dirname(__DIR__) . '/routes/web.php';
$registerWebRoutes($app, $dependencies);
$app->add(new SecurityHeadersMiddleware());
$app->add(new RequestIdMiddleware());
return $app;
