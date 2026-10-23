<?php

declare(strict_types=1);

use App\App\AppContext;
use App\Controllers\AuthController;
use App\Database\MigrationRunner;
use App\Repositories\AdministratorRepository;
use App\Repositories\AdministratorSessionRepository;
use App\Repositories\AuditRepository;
use App\Repositories\LoginThrottleRepository;
use App\Security\CsrfTokenManager;
use App\Security\PasswordHasher;
use App\Security\PasswordPolicy;
use App\Security\SecureCookieManager;
use App\Services\AuthService;
use Slim\Psr7\Factory\ResponseFactory;
use Twig\Environment as TwigEnvironment;

/** @var AppContext $context */
$context = require __DIR__ . '/app.php';
$config = $context->config;
$administrators = new AdministratorRepository($context->pdo);
$sessions = new AdministratorSessionRepository($context->pdo);
$throttles = new LoginThrottleRepository($context->pdo);
$audit = new AuditRepository($context->pdo);
$passwordHasher = new PasswordHasher();
$passwordPolicy = new PasswordPolicy(
    $config->int('security.minimum_password_characters'),
    $config->int('security.maximum_password_bytes'),
);
$auth = new AuthService(
    $context->pdo,
    $administrators,
    $sessions,
    $throttles,
    $audit,
    $passwordHasher,
    $passwordPolicy,
    $config->string('security.auth_throttle_key'),
    $config->int('app.session_idle_seconds'),
    $config->int('app.session_absolute_seconds'),
    $config->int('security.login_window_seconds'),
    $config->int('security.login_max_failures'),
    $config->int('security.login_block_seconds'),
);
$csrf = new CsrfTokenManager(
    $config->string('security.auth_csrf_key'),
    $config->int('security.csrf_ttl_seconds'),
);
$cookies = new SecureCookieManager(
    $config->string('app.session_cookie_name'),
    $config->string('app.login_csrf_cookie_name'),
    $config->int('security.csrf_ttl_seconds'),
);
$responses = new ResponseFactory();
$authController = new AuthController($auth, $csrf, $cookies, $context->view);

return [
    AppContext::class => $context,
    TwigEnvironment::class => $context->view,
    MigrationRunner::class => new MigrationRunner(
        $context->pdo,
        dirname(__DIR__) . '/database/migrations',
    ),
    AdministratorRepository::class => $administrators,
    AdministratorSessionRepository::class => $sessions,
    LoginThrottleRepository::class => $throttles,
    AuditRepository::class => $audit,
    PasswordHasher::class => $passwordHasher,
    PasswordPolicy::class => $passwordPolicy,
    AuthService::class => $auth,
    CsrfTokenManager::class => $csrf,
    SecureCookieManager::class => $cookies,
    ResponseFactory::class => $responses,
    AuthController::class => $authController,
];
