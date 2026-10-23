<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Http\Message\ServerRequestInterface;

final class RequestMetadata
{
    public static function clientIp(ServerRequestInterface $request): string
    {
        $value = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        return is_string($value) && filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : '';
    }

    public static function userAgent(ServerRequestInterface $request): string
    {
        return substr($request->getHeaderLine('User-Agent'), 0, 512);
    }

    public static function requestId(ServerRequestInterface $request): ?string
    {
        $value = $request->getAttribute(RequestIdMiddleware::ATTRIBUTE);
        return is_string($value) && preg_match('/^[a-f0-9]{32}$/', $value) === 1 ? $value : null;
    }
}
