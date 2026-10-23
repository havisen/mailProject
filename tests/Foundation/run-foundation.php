<?php

declare(strict_types=1);

use App\Config\ConfigException;
use App\Config\ConfigLoader;

require dirname(__DIR__, 2) . '/src/Config/ConfigException.php';
require dirname(__DIR__, 2) . '/src/Config/Config.php';
require dirname(__DIR__, 2) . '/src/Config/ConfigLoader.php';

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertThrows = static function (callable $callback, string $message) use (&$assertions): void {
    ++$assertions;
    try {
        $callback();
    } catch (ConfigException) {
        return;
    }
    throw new RuntimeException($message);
};

$basePath = dirname(__DIR__, 2);
$encoded = base64_encode(str_repeat('k', 32));
$fingerprint = base64_encode(str_repeat('f', 32));
$environment = [
    'APP_ENV' => 'test',
    'APP_DEBUG' => 'false',
    'MAILPANEL_DB_DSN' => 'mysql:host=127.0.0.1;dbname=mailpanel_test;charset=utf8mb4',
    'MAILPANEL_DB_USER' => 'mailpanel_test',
    'MAILPANEL_DB_PASSWORD' => '',
    'MAILPANEL_CREDENTIAL_KEYSET' => json_encode(['primary' => $encoded], JSON_THROW_ON_ERROR),
    'MAILPANEL_CREDENTIAL_ACTIVE_KEY_ID' => 'primary',
    'MAILPANEL_CONTENT_KEYSET' => json_encode(['primary' => $encoded], JSON_THROW_ON_ERROR),
    'MAILPANEL_CONTENT_ACTIVE_KEY_ID' => 'primary',
    'MAILPANEL_API_FINGERPRINT_KEY' => $fingerprint,
    'MAILPANEL_AUTH_CSRF_KEY' => base64_encode(str_repeat('c', 32)),
    'MAILPANEL_AUTH_THROTTLE_KEY' => base64_encode(str_repeat('t', 32)),
    'MAILPANEL_MESSAGE_ID_DOMAIN' => 'mail.example.test',
];

$config = ConfigLoader::fromEnvironment($environment, $basePath);
$assert($config->get('security.credential_keyset.primary') === $encoded, 'Cipher keysets must remain base64 encoded for existing services.');
$assert($config->get('security.content_keyset.primary') === $encoded, 'Content keysets must remain base64 encoded for existing services.');
$assert($config->get('security.api_fingerprint_key') === str_repeat('f', 32), 'Fingerprint secret must be decoded once.');
$assert($config->get('api.requests_per_minute_per_key') === 30, 'Default API request limit is incorrect.');
$assert($config->get('queue.run_budget_seconds') === 40, 'Existing queue configuration must be preserved.');
$assert($config->get('app.session_idle_seconds') === 1800, 'Default session idle timeout is incorrect.');
$assert($config->get('app.session_absolute_seconds') === 43200, 'Default session absolute timeout is incorrect.');
$redacted = $config->redacted();
$assert($redacted['database']['password'] === '[redacted]', 'Database password was not redacted.');
$assert($redacted['database']['dsn'] === '[redacted]', 'Database DSN was not redacted.');
$assert($redacted['security']['credential_keyset'] === '[redacted]', 'Credential keyset was not redacted.');
$assert($redacted['security']['auth_csrf_key'] === '[redacted]', 'Authentication CSRF key was not redacted.');

$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_diff_key($environment, ['MAILPANEL_DB_USER' => true]), $basePath),
    'Missing database user should fail configuration loading.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, ['APP_ENV' => 'production', 'APP_DEBUG' => 'true']), $basePath),
    'Production debug mode should be rejected.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, ['MAILPANEL_CONTENT_KEYSET' => '{"primary":"short"}']), $basePath),
    'Short encryption keys should be rejected.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_diff_key($environment, ['MAILPANEL_AUTH_CSRF_KEY' => true]), $basePath),
    'Missing authentication CSRF key should fail configuration loading.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, ['MAILPANEL_AUTH_THROTTLE_KEY' => base64_encode('short')]), $basePath),
    'Short authentication throttle keys should be rejected.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, [
        'MAILPANEL_SESSION_IDLE_SECONDS' => '1800',
        'MAILPANEL_SESSION_ABSOLUTE_SECONDS' => '1800',
    ]), $basePath),
    'Absolute session timeout must exceed the idle timeout.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, [
        'MAILPANEL_SESSION_COOKIE_NAME' => 'same_cookie',
        'MAILPANEL_LOGIN_CSRF_COOKIE_NAME' => 'same_cookie',
    ]), $basePath),
    'Session and login CSRF cookies must use different names.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, ['PRIVATE_STORAGE_PATH' => $basePath . '/public/private']), $basePath),
    'Storage inside public should be rejected.'
);
$assertThrows(
    static fn () => ConfigLoader::fromEnvironment(array_merge($environment, ['PRIVATE_STORAGE_PATH' => $basePath . '/storage/../public/private']), $basePath),
    'A traversed storage path inside public should be rejected.'
);

fwrite(STDOUT, sprintf("Foundation configuration checks passed (%d assertions).\n", $assertions));
