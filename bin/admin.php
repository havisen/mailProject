<?php

declare(strict_types=1);

use App\Config\ConfigException;
use App\Services\AuthService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is CLI-only.\n");
    exit(64);
}

/** @return array<string, string|bool> */
function adminOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '--password-stdin') {
            $options['password-stdin'] = true;
        } elseif (preg_match('/^--email=(.+)$/', $argument, $match) === 1) {
            $options['email'] = $match[1];
        }
    }
    return $options;
}

function adminReadPassword(bool $fromStdin): string
{
    if ($fromStdin) {
        $first = fgets(STDIN);
        $second = fgets(STDIN);
        if (!is_string($first) || !is_string($second)) {
            throw new RuntimeException('Password input requires two lines.');
        }
        $first = rtrim($first, "\r\n");
        $second = rtrim($second, "\r\n");
        if (!hash_equals($first, $second)) {
            throw new RuntimeException('Password confirmation does not match.');
        }
        return $first;
    }

    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        throw new RuntimeException('Use --password-stdin for noninteractive input.');
    }
    $terminalState = function_exists('shell_exec') ? shell_exec('stty -g 2>/dev/null') : null;
    if (!is_string($terminalState) || trim($terminalState) === '') {
        throw new RuntimeException('Cannot disable terminal echo; use --password-stdin.');
    }
    $terminalState = trim($terminalState);

    try {
        shell_exec('stty -echo 2>/dev/null');
        fwrite(STDERR, 'New password: ');
        $first = fgets(STDIN);
        fwrite(STDERR, "\nConfirm password: ");
        $second = fgets(STDIN);
        fwrite(STDERR, "\n");
    } finally {
        shell_exec('stty ' . escapeshellarg($terminalState) . ' 2>/dev/null');
    }
    if (!is_string($first) || !is_string($second)) {
        throw new RuntimeException('Password input failed.');
    }
    $first = rtrim($first, "\r\n");
    $second = rtrim($second, "\r\n");
    if (!hash_equals($first, $second)) {
        throw new RuntimeException('Password confirmation does not match.');
    }
    return $first;
}

$action = $argv[1] ?? '';
$options = adminOptions(array_slice($argv, 2));
$email = $options['email'] ?? null;
if (!in_array($action, ['create', 'reset-password'], true) || !is_string($email) || $email === '') {
    fwrite(STDERR, "Usage: php bin/admin.php [create|reset-password] --email=ADDRESS [--password-stdin]\n");
    exit(64);
}

try {
    $dependencies = require dirname(__DIR__) . '/bootstrap/dependencies.php';
    /** @var AuthService $auth */
    $auth = $dependencies[AuthService::class];
    $password = adminReadPassword(($options['password-stdin'] ?? false) === true);
    if ($action === 'create') {
        $auth->createAdministrator($email, $password);
        fwrite(STDOUT, "Administrator created.\n");
    } else {
        $auth->resetAdministratorPassword($email, $password);
        fwrite(STDOUT, "Administrator password reset; all existing sessions were revoked.\n");
    }
    $password = '';
    exit(0);
} catch (ConfigException $exception) {
    fwrite(STDERR, 'Configuration error: ' . $exception->getMessage() . "\n");
    exit(78);
} catch (PDOException) {
    fwrite(STDERR, "Administrator database operation failed.\n");
    exit(1);
} catch (DomainException|InvalidArgumentException|RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Administrator command failed.\n");
    exit(1);
}
