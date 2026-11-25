<?php

return [
    // Ресурсы — заголовки
    'resource' => [
        'job_logs' => 'Job Logs',
        'job_log_steps' => 'Job Steps',
        'job_log_records' => 'Log Records',
    ],

    // Статусы
    'status' => [
        'queued' => 'Queued',
        'processing' => 'Processing',
        'processed' => 'Completed',
        'failed' => 'Failed',
        'interrupted' => 'Interrupted',
    ],

    // Поля — общие
    'field' => [
        'connection' => 'Connection',
        'queue' => 'Queue',
        'job_class' => 'Job',
        'job_uuid' => 'Job UUID',
        'related' => 'Related Model',
        'queued_at' => 'Queued',
        'started_at' => 'Started',
        'finished_at' => 'Finished',
        'error' => 'Error',
        'last_error' => 'Last Error',
        'current_step' => 'Step',
        'status' => 'Status',
        'progress' => 'Progress (%)',
        'runtime' => 'Runtime (sec)',
        'args' => 'Arguments',
        'data' => 'Data',
        'steps' => 'Steps',
        'records' => 'Log Records',
        'pid' => 'PID',
        'tags' => 'Tags',
        'custom_status' => 'Custom Status',
    ],

    // Поля — шаги
    'step' => [
        'step_key' => 'Step',
        'step_name' => 'Name',
        'custom_status' => 'Custom Status',
        'created_at' => 'Created',
        'updated_at' => 'Updated',
        'last_error' => 'Last Error',
        'records' => 'Step Log Records',
        'data' => 'Data',
    ],

    // Поля — записи логов
    'record' => [
        'step_key' => 'Step',
        'level' => 'Level',
        'message' => 'Message',
        'context' => 'Context',
        'trace' => 'Stack Trace',
        'created_at' => 'Created',
    ],

    // Query tags
    'query_tag' => [
        'all' => 'All',
        'queued' => 'Queued',
        'processing' => 'In Progress',
        'processed' => 'Completed',
        'failed' => 'Failed',
        'interrupted' => 'Interrupted',
        'last_24h' => 'Last 24 hours',
    ],

    // Фильтры
    'filter' => [
        'status' => 'Status',
        'job_class' => 'Job',
        'queue' => 'Queue',
        'related_type' => 'Related Model',
    ],

    // Команды
    'command' => [
        'install_description' => 'Install the JobLog package (publish config and run migrations)',
        'install_unsupported' => 'JobLog: driver ":driver" is not supported (PostgreSQL and MongoDB are).',
        'install_switch_confirm' => 'Configure the JobLog storage to use MongoDB? The application database stays as is; existing log data is not migrated.',
        'install_switched' => 'JobLog storage switched to MongoDB (JOBLOG_DB_DRIVER/JOBLOG_DB_CONNECTION in .env).',
        'install_mongo_package_missing' => 'JobLog: the mongodb/laravel-mongodb package is not installed. Run: composer require mongodb/laravel-mongodb',
        'install_mongo_connection_missing' => 'JobLog: no "mongodb" connection in config/database.php. Add one (driver "mongodb", dsn, database) and re-run joblog:install.',
        'install_migrate_confirm' => 'Run the JobLog migrations now?',
        'install_migrate_skipped' => 'Skipped. Run "php artisan migrate" when ready.',
        'install_env_missing' => '.env not found — set :key=:value manually.',
        'install_env_exists' => ':key is already set in .env — update it manually to :key=:value.',

        'cleanup_description' => 'Cleanup JobLog records older than specified days',
        'cleanup_days_option' => 'Number of days to keep records',
        'cleanup_start' => 'Starting cleanup of JobLog records older than :days days (before :date)',
        'cleanup_no_records' => 'No records to delete',
        'cleanup_found' => 'Found :count records to delete',
        'cleanup_success' => 'Successfully deleted :count JobLog records',

        'truncate_description' => 'Truncate all JobLog records',
        'truncate_no_records' => 'No records to delete',
        'truncate_confirm' => ':count JobLog records will be deleted. Continue?',
        'truncate_cancelled' => 'Operation cancelled',
        'truncate_success' => 'Successfully truncated all JobLog records',
    ],

    // Прочее
    'signal_interrupted' => 'Job interrupted by signal :signal',
];
