<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AdminSession;
use App\Http\RequestMetadata;
use App\Security\SecureCookieManager;
use App\Services\AuthService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AuthMiddleware implements MiddlewareInterface
{
    public const ATTRIBUTE = 'administrator_session';

    public function __construct(
        private AuthService $auth,
        private SecureCookieManager $cookies,
        private ResponseFactoryInterface $responses,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getCookieParams()[$this->cookies->sessionName()] ?? '';
        $session = is_string($token)
            ? $this->auth->authenticateSession(
                $token,
                RequestMetadata::clientIp($request),
                RequestMetadata::requestId($request),
            )
            : null;
        if (!$session instanceof AdminSession) {
            $response = $this->responses->createResponse(in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 303 : 401)
                ->withHeader('Cache-Control', 'no-store')
                ->withAddedHeader('Set-Cookie', $this->cookies->clearSession());
            if (in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
                return $response->withHeader('Location', '/login');
            }
            $response->getBody()->write('Authentication required.');
            return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $session));
    }
}
