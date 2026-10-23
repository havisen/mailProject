<?php

declare(strict_types=1);

namespace App\Config;

final class ConfigLoader
{
    /**
     * @param array<string, mixed>|null $environment
     */
    public static function fromEnvironment(?array $environment = null, ?string $basePath = null): Config
    {
        $environment ??= array_merge($_SERVER, $_ENV);
        $basePath ??= dirname(__DIR__, 2);
        $basePath = self::absolutePath($basePath, 'application base path');

        $appEnvironment = self::required($environment, 'APP_ENV');
        if (!in_array($appEnvironment, ['production', 'staging', 'development', 'test'], true)) {
            throw new ConfigException('APP_ENV must be production, staging, development, or test.');
        }

        $debug = self::boolean($environment, 'APP_DEBUG', false);
        if ($appEnvironment === 'production' && $debug) {
            throw new ConfigException('APP_DEBUG must be disabled in production.');
        }

        $storagePath = self::absolutePath(
            self::optional($environment, 'PRIVATE_STORAGE_PATH', $basePath . DIRECTORY_SEPARATOR . 'storage'),
            'PRIVATE_STORAGE_PATH'
        );
        $logPath = self::absolutePath(
            self::optional($environment, 'LOG_PATH', $storagePath . DIRECTORY_SEPARATOR . 'logs'),
            'LOG_PATH'
        );
        self::assertPrivate($storagePath, $basePath . DIRECTORY_SEPARATOR . 'public', 'PRIVATE_STORAGE_PATH');
        self::assertPrivate($logPath, $basePath . DIRECTORY_SEPARATOR . 'public', 'LOG_PATH');
        self::assertDirectory($storagePath, 'PRIVATE_STORAGE_PATH');
        self::assertDirectory($logPath, 'LOG_PATH');

        $credentialKeyset = self::keyset($environment, 'MAILPANEL_CREDENTIAL_KEYSET');
        $contentKeyset = self::keyset($environment, 'MAILPANEL_CONTENT_KEYSET');
        $credentialActiveKey = self::activeKey($environment, 'MAILPANEL_CREDENTIAL_ACTIVE_KEY_ID', $credentialKeyset);
        $contentActiveKey = self::activeKey($environment, 'MAILPANEL_CONTENT_ACTIVE_KEY_ID', $contentKeyset);
        $fingerprintKey = self::decodedSecret($environment, 'MAILPANEL_API_FINGERPRINT_KEY', 32);
        $messageDomain = self::required($environment, 'MAILPANEL_MESSAGE_ID_DOMAIN');
        if (filter_var($messageDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new ConfigException('MAILPANEL_MESSAGE_ID_DOMAIN must be a valid hostname.');
        }

        $queue = require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'queue.php';
        $mail = require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'mail.php';
        $api = require $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'api.php';

        return new Config([
            'app' => [
                'environment' => $appEnvironment,
                'debug' => $debug,
                'base_path' => $basePath,
            ],
            'database' => [
                'dsn' => self::required($environment, 'MAILPANEL_DB_DSN'),
                'username' => self::required($environment, 'MAILPANEL_DB_USER'),
                'password' => self::required($environment, 'MAILPANEL_DB_PASSWORD', true),
            ],
            'paths' => [
                'storage' => $storagePath,
                'logs' => $logPath,
                'public' => self::normalize($basePath . DIRECTORY_SEPARATOR . 'public'),
            ],
            'security' => [
                // Existing cipher services decode these values themselves.
                'credential_keyset' => $credentialKeyset,
                'credential_active_key_id' => $credentialActiveKey,
                'content_keyset' => $contentKeyset,
                'content_active_key_id' => $contentActiveKey,
                'api_fingerprint_key' => $fingerprintKey,
            ],
            'mail' => array_merge($mail, [
                'message_id_domain' => strtolower($messageDomain),
                'tls_ca_bundle' => self::nullable($environment, 'MAILPANEL_TLS_CA_BUNDLE'),
            ]),
            'queue' => $queue,
            'api' => array_merge($api, [
                'maximum_body_bytes' => self::boundedInt($environment, 'MAILPANEL_API_MAXIMUM_BODY_BYTES', (int) $api['maximum_body_bytes'], 1024, 1048576),
                'requests_per_minute_per_key' => self::boundedInt($environment, 'MAILPANEL_API_REQUESTS_PER_MINUTE', (int) $api['requests_per_minute_per_key'], 1, 10000),
                'accepted_recipients_per_hour_per_account' => self::boundedInt($environment, 'MAILPANEL_API_RECIPIENTS_PER_HOUR', (int) $api['accepted_recipients_per_hour_per_account'], 1, 100000),
                'idempotency_retention_seconds' => self::boundedInt($environment, 'MAILPANEL_IDEMPOTENCY_RETENTION_SECONDS', (int) $api['idempotency_retention_seconds'], 86400, 31536000),
            ]),
        ]);
    }

    /** @param array<string, mixed> $environment */
    private static function required(array $environment, string $name, bool $allowEmpty = false): string
    {
        $value = $environment[$name] ?? null;
        if (!is_string($value) || (!$allowEmpty && trim($value) === '')) {
            throw new ConfigException(sprintf('%s is required.', $name));
        }

        return $allowEmpty ? $value : trim($value);
    }

    /** @param array<string, mixed> $environment */
    private static function optional(array $environment, string $name, string $default): string
    {
        $value = $environment[$name] ?? null;
        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }

    /** @param array<string, mixed> $environment */
    private static function nullable(array $environment, string $name): ?string
    {
        $value = $environment[$name] ?? null;
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @param array<string, mixed> $environment */
    private static function boolean(array $environment, string $name, bool $default): bool
    {
        $value = $environment[$name] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new ConfigException(sprintf('%s must be a boolean.', $name));
        }

        return $parsed;
    }

    /** @param array<string, mixed> $environment */
    private static function boundedInt(array $environment, string $name, int $default, int $minimum, int $maximum): int
    {
        $value = $environment[$name] ?? null;
        if ($value === null || $value === '') {
            return $default;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new ConfigException(sprintf('%s must be an integer.', $name));
        }
        $parsed = (int) $value;
        if ($parsed < $minimum || $parsed > $maximum) {
            throw new ConfigException(sprintf('%s is outside its allowed range.', $name));
        }

        return $parsed;
    }

    /** @param array<string, mixed> $environment
     *  @return array<string, string>
     */
    private static function keyset(array $environment, string $name): array
    {
        $raw = self::required($environment, $name);
        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ConfigException(sprintf('%s must contain a JSON object.', $name));
        }
        if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            throw new ConfigException(sprintf('%s must contain a non-empty JSON object.', $name));
        }

        $validated = [];
        foreach ($decoded as $id => $encoded) {
            if (!is_string($id) || preg_match('/^[A-Za-z0-9-]{1,32}$/', $id) !== 1 || !is_string($encoded)) {
                throw new ConfigException(sprintf('%s contains an invalid key entry.', $name));
            }
            $key = base64_decode($encoded, true);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new ConfigException(sprintf('%s keys must decode to exactly 32 bytes.', $name));
            }
            $validated[$id] = $encoded;
        }

        return $validated;
    }

    /** @param array<string, mixed> $environment
     *  @param array<string, string> $keyset
     */
    private static function activeKey(array $environment, string $name, array $keyset): string
    {
        $active = self::required($environment, $name);
        if (!array_key_exists($active, $keyset)) {
            throw new ConfigException(sprintf('%s must name a key in its keyset.', $name));
        }

        return $active;
    }

    /** @param array<string, mixed> $environment */
    private static function decodedSecret(array $environment, string $name, int $minimumBytes): string
    {
        $decoded = base64_decode(self::required($environment, $name), true);
        if ($decoded === false || strlen($decoded) < $minimumBytes) {
            throw new ConfigException(sprintf('%s must be base64 for at least %d random bytes.', $name, $minimumBytes));
        }

        return $decoded;
    }

    private static function absolutePath(string $path, string $name): string
    {
        if (!str_starts_with($path, '/') && preg_match('/^[A-Za-z]:[\\\\\/]/', $path) !== 1) {
            throw new ConfigException(sprintf('%s must be an absolute path.', $name));
        }

        return self::normalize($path);
    }

    private static function assertPrivate(string $path, string $publicPath, string $name): void
    {
        $path = rtrim(strtolower(self::normalize($path)), '/');
        $publicPath = rtrim(strtolower(self::normalize($publicPath)), '/');
        if ($path === $publicPath || str_starts_with($path . '/', $publicPath . '/')) {
            throw new ConfigException(sprintf('%s must remain outside public/.', $name));
        }
    }

    private static function assertDirectory(string $path, string $name): void
    {
        if (!is_dir($path)) {
            throw new ConfigException(sprintf('%s must name an existing directory.', $name));
        }
    }

    private static function normalize(string $path): string
    {
        $realPath = realpath($path);
        $path = str_replace('\\', '/', $realPath !== false ? $realPath : $path);
        $drive = '';
        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            $drive = strtoupper(substr($path, 0, 2));
            $path = substr($path, 2);
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    throw new ConfigException('A configured path escapes the filesystem root.');
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return $drive . '/' . implode('/', $segments);
    }
}
