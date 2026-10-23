<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AdminSession;
use App\Http\RequestMetadata;
use App\Repositories\AuditRepository;
use App\Security\CsrfTokenManager;
use App\Services\AuthService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CsrfTokenManager $tokens,
        private AuditRepository $audit,
        private AuthService $auth,
        private ResponseFactoryInterface $responses,
        private string $action,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $request->getAttribute(AuthMiddleware::ATTRIBUTE);
        $body = $request->getParsedBody();
        $token = is_array($body) && is_string($body['_csrf'] ?? null) ? $body['_csrf'] : '';
        if (!$session instanceof AdminSession || !$this->tokens->validate($token, $this->action, $session->csrfSecret)) {
            $this->audit->record(
                'csrf_rejected',
                $session instanceof AdminSession ? $session->administratorId : null,
                RequestMetadata::requestId($request),
                $this->auth->ipHash(RequestMetadata::clientIp($request)),
                gmdate('Y-m-d H:i:s.u'),
            );
            $response = $this->responses->createResponse(403)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withHeader('Cache-Control', 'no-store');
            $response->getBody()->write('Request could not be verified.');
            return $response;
        }

        return $handler->handle($request);
    }
}
