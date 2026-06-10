<?php

return [
    'woocommerce' => [
        'timeout_seconds' => env('LAMMAH_WOO_TIMEOUT_SECONDS', 20),
        'retry_attempts' => env('LAMMAH_WOO_RETRY_ATTEMPTS', 3),
        'retry_sleep_ms' => env('LAMMAH_WOO_RETRY_SLEEP_MS', 300),
        'page_size' => env('LAMMAH_WOO_PAGE_SIZE', 100),
        'max_pages_per_job' => env('LAMMAH_WOO_MAX_PAGES_PER_JOB', 25),
        'webhook_skew_seconds' => env('LAMMAH_WOO_WEBHOOK_SKEW_SECONDS', 300),
    ],
];
