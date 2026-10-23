<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command is CLI-only.\n");
    exit(64);
}

$command = $argv[1] ?? 'help';
if ($command === 'help' || in_array($command, ['--help', '-h'], true)) {
    fwrite(STDOUT, "Commands:\n  migrate [status|up]\n  preflight\n  admin:create --email=ADDRESS [--password-stdin]\n  admin:reset-password --email=ADDRESS [--password-stdin]\n");
    exit(0);
}

if (in_array($command, ['admin:create', 'admin:reset-password'], true)) {
    $argv[1] = $command === 'admin:create' ? 'create' : 'reset-password';
    $_SERVER['argv'] = $argv;
    require __DIR__ . '/admin.php';
}

if ($command === 'migrate') {
    array_splice($argv, 1, 1);
    $_SERVER['argv'] = $argv;
    require __DIR__ . '/migrate.php';
}

if ($command === 'preflight') {
    array_splice($argv, 1, 1);
    $_SERVER['argv'] = $argv;
    require __DIR__ . '/preflight.php';
}

fwrite(STDERR, "Unknown command. Run: php bin/console.php help\n");
exit(64);
