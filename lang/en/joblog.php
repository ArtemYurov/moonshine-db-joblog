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
