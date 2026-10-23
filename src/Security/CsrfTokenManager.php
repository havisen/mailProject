<?php

declare(strict_types=1);

namespace App\Security;

final readonly class CsrfTokenManager
{
    public function __construct(private string $serverKey, private int $ttlSeconds = 900)
    {
        if (strlen($this->serverKey) < 32) {
            throw new \InvalidArgumentException('CSRF server key must contain at least 32 bytes.');
        }
    }

    public function newBindingSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function issue(string $action, string $bindingSecret, ?int $now = null): string
    {
        $this->validateAction($action);
        $now ??= time();
        $expiry = $now + $this->ttlSeconds;
        $nonce = self::base64UrlEncode(random_bytes(16));
        $mac = $this->mac($action, $bindingSecret, $expiry, $nonce);

        return implode('.', ['v1', (string) $expiry, $nonce, self::base64UrlEncode($mac)]);
    }

    public function validate(string $token, string $action, string $bindingSecret, ?int $now = null): bool
    {
        $this->validateAction($action);
        $now ??= time();
        $parts = explode('.', $token);
        if (count($parts) !== 4 || $parts[0] !== 'v1' || !ctype_digit($parts[1])) {
            return false;
        }
        $expiry = (int) $parts[1];
        if ($expiry < $now || $expiry > $now + $this->ttlSeconds + 60) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9_-]{20,32}$/', $parts[2]) !== 1) {
            return false;
        }
        $provided = self::base64UrlDecode($parts[3]);
        if ($provided === null) {
            return false;
        }

        return hash_equals($this->mac($action, $bindingSecret, $expiry, $parts[2]), $provided);
    }

    private function mac(string $action, string $bindingSecret, int $expiry, string $nonce): string
    {
        $bindingKey = hash_hmac('sha256', $bindingSecret, $this->serverKey, true);
        return hash_hmac('sha256', implode("\0", ['mailpanel-csrf-v1', $action, (string) $expiry, $nonce]), $bindingKey, true);
    }

    private function validateAction(string $action): void
    {
        if (preg_match('/^[a-z][a-z0-9._-]{1,79}$/', $action) !== 1) {
            throw new \InvalidArgumentException('Invalid CSRF action.');
        }
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
