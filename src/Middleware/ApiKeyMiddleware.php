<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiKeyService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ApiKeyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ApiKeyService $keys,
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach (['access_token', 'api_key', 'token'] as $queryName) {
            if (array_key_exists($queryName, $request->getQueryParams())) {
                return $this->unauthorized($request);
            }
        }
        $authorization = $request->getHeaderLine('Authorization');
        if (preg_match('/\ABearer ([^\s]+)\z/iD', $authorization, $matches) !== 1) {
            return $this->unauthorized($request);
        }
        $principal = $this->keys->authenticate($matches[1]);
        if ($principal === null) {
            return $this->unauthorized($request);
        }
        return $handler->handle($request->withAttribute('api_principal', $principal));
    }

    private function unauthorized(ServerRequestInterface $request): ResponseInterface
    {
        $requestId = (string) ($request->getAttribute('request_id') ?? 'unavailable');
        $payload = json_encode([
            'error' => ['code' => 'invalid_api_key', 'message' => 'A valid Bearer API key is required.'],
            'request_id' => $requestId,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return $this->responses->createResponse(401)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('WWW-Authenticate', 'Bearer')
            ->withBody($this->streams->createStream($payload));
    }
}
