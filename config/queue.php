<?php

declare(strict_types=1);

return [
    // Designed for a one-minute cron. Keep enough headroom for process shutdown.
    'run_budget_seconds' => 40,
    'lease_seconds' => 30,
    'minimum_start_seconds' => 5,
    'idle_sleep_milliseconds' => 150,
    'recovery_limit' => 25,
    'queues' => [
        'outbound' => ['priority' => 10],
        'sync' => ['priority' => 50],
        'maintenance' => ['priority' => 100],
    ],
];
