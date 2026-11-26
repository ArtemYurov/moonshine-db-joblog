<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    | Named connection from the host's config/database.php that stores the log.
    | When null, the application default connection is used (DB_CONNECTION).
    | The storage driver is the driver of that connection: any SQL driver
    | Laravel supports, or mongodb (needs mongodb/laravel-mongodb).
    */
    'database' => [
        'connection' => env('JOBLOG_DB_CONNECTION'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Old Records Cleanup
    |--------------------------------------------------------------------------
    | schedule — automatic registration in Laravel Schedule
    */
    'cleanup' => [
        'days' => (int) env('JOBLOG_CLEANUP_DAYS', 30),
        'schedule' => env('JOBLOG_CLEANUP_SCHEDULE', false), // false, 'daily', 'weekly', 'hourly'
        'time' => env('JOBLOG_CLEANUP_TIME', '03:00'),
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
    'console_output' => (bool) env('JOBLOG_CONSOLE_OUTPUT', true),

    /*
    |--------------------------------------------------------------------------
    | Horizon Integration
    |--------------------------------------------------------------------------
    | Horizon is detected automatically via class_exists.
    | intercept_purge — log interrupted jobs when Horizon purges a queue.
    */
    'horizon' => [
        'intercept_purge' => (bool) env('JOBLOG_HORIZON_INTERCEPT_PURGE', true),
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
