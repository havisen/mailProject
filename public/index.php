<?php

declare(strict_types=1);

try {
    $app = require dirname(__DIR__) . '/bootstrap/web.php';
    $app->run();
} catch (Throwable) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    try {
        header('X-Request-ID: ' . bin2hex(random_bytes(16)));
    } catch (Throwable) {
        // Keep the startup failure response generic even if secure randomness is unavailable.
    }
    echo 'Service unavailable.';
}
