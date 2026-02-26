<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Old Records Cleanup
    |--------------------------------------------------------------------------
    | schedule — automatic registration in Laravel Schedule
    */
    'cleanup' => [
        'days' => 30,
        'schedule' => false, // false, 'daily', 'weekly', 'hourly'
        'time' => '03:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Output
    |--------------------------------------------------------------------------
    | When enabled, job logger messages (info, warning, error, step start, etc.)
    | are duplicated to stdout with ANSI coloring. Only works when running in
    | CLI context (artisan commands, queue workers, Horizon).
    |
    | Set to false to write only to the database (job_log_records table).
    */
    'console_output' => true,

    /*
    |--------------------------------------------------------------------------
    | Horizon Integration
    |--------------------------------------------------------------------------
    | 'auto' — automatically detects Horizon availability
    | true — always enabled (requires Horizon installed)
    | false — disabled
    */
    'horizon' => [
        'enabled' => 'auto',
        'intercept_purge' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Class Scan Paths
    |--------------------------------------------------------------------------
    | Used in MoonShine resource for filtering by job class
    */
    'job_class_scan_paths' => [
        // app_path('Jobs'),
    ],
];
