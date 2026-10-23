<?php

declare(strict_types=1);

namespace App\Security;

final readonly class SecureCookieManager
{
    public function __construct(
        private string $sessionName,
        private string $loginCsrfName,
        private int $loginCsrfTtlSeconds,
    ) {
    }

    public function sessionName(): string
    {
        return $this->sessionName;
    }

    public function loginCsrfName(): string
    {
        return $this->loginCsrfName;
    }

    public function session(string $token, int $absoluteExpiry, ?int $now = null): string
    {
        $now ??= time();
        return $this->build($this->sessionName, $token, max(0, $absoluteExpiry - $now), $absoluteExpiry);
    }

    public function loginCsrf(string $bindingSecret, ?int $now = null): string
    {
        $now ??= time();
        return $this->build($this->loginCsrfName, $bindingSecret, $this->loginCsrfTtlSeconds, $now + $this->loginCsrfTtlSeconds);
    }

    public function clearSession(): string
    {
        return $this->clear($this->sessionName);
    }

    public function clearLoginCsrf(): string
    {
        return $this->clear($this->loginCsrfName);
    }

    private function build(string $name, string $value, int $maxAge, int $expiry): string
    {
        return sprintf(
            '%s=%s; Expires=%s; Max-Age=%d; Path=/; Secure; HttpOnly; SameSite=Strict',
            $name,
            rawurlencode($value),
            gmdate('D, d M Y H:i:s', $expiry) . ' GMT',
            $maxAge,
        );
    }

    private function clear(string $name): string
    {
        return sprintf(
            '%s=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Strict',
            $name,
        );
    }
}
