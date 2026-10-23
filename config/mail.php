<?php

declare(strict_types=1);

return [
    'connect_timeout_seconds' => 10,
    'operation_timeout_seconds' => 20,
    'retry_delays_seconds' => [60, 120, 240],
    'maximum_attempts' => 4,
    'authentication_pause_threshold' => 3,
    'authentication_failure_window_seconds' => 900,
];
