<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'job_logs' => 'job_logs',
        'job_log_steps' => 'job_log_steps',
        'job_log_records' => 'job_log_records',
    ],

    /*
    |--------------------------------------------------------------------------
    | Old Records Cleanup
    |--------------------------------------------------------------------------
    | schedule — automatic registration in Laravel Schedule
    |   false — disabled (default), user configures cron manually
    |   'daily' — daily at specified time
    |   'weekly' — weekly at specified time
    |   'hourly' — every hour
    */
    'cleanup' => [
        'days' => 30,
        'schedule' => false,
        'time' => '03:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Console Output
    |--------------------------------------------------------------------------
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
        // Default — app/Jobs
    ],
];
