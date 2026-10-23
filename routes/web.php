<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Repositories\AuditRepository;
use App\Security\CsrfTokenManager;
use App\Security\SecureCookieManager;
use App\Services\AuthService;
use Slim\App;
use Slim\Psr7\Factory\ResponseFactory;

/** @param array<class-string, object> $dependencies */
return static function (App $app, array $dependencies): void {
    /** @var AuthController $controller */
    $controller = $dependencies[AuthController::class];
    /** @var AuthService $auth */
    $auth = $dependencies[AuthService::class];
    /** @var SecureCookieManager $cookies */
    $cookies = $dependencies[SecureCookieManager::class];
    /** @var ResponseFactory $responses */
    $responses = $dependencies[ResponseFactory::class];
    /** @var CsrfTokenManager $csrf */
    $csrf = $dependencies[CsrfTokenManager::class];
    /** @var AuditRepository $audit */
    $audit = $dependencies[AuditRepository::class];

    $authMiddleware = new AuthMiddleware($auth, $cookies, $responses);

    $app->get('/login', [$controller, 'showLogin']);
    $app->post('/login', [$controller, 'login']);

    $app->get('/', [$controller, 'dashboard'])->add($authMiddleware);
    $app->get('/settings/password', [$controller, 'showPassword'])->add($authMiddleware);
    $app->post('/settings/password', [$controller, 'changePassword'])
        ->add(new CsrfMiddleware($csrf, $audit, $auth, $responses, 'password-change'))
        ->add($authMiddleware);
    $app->post('/logout', [$controller, 'logout'])
        ->add(new CsrfMiddleware($csrf, $audit, $auth, $responses, 'logout'))
        ->add($authMiddleware);
};
