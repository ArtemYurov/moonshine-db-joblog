<?php

namespace ArtemYurov\JobLog\Commands;

use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogRecord;
use ArtemYurov\JobLog\Models\JobLogStep;
use Illuminate\Console\Command;

class JobLogTruncateCommand extends Command
{
    protected $signature = 'joblog:truncate {--force : Skip confirmation prompt}';

    protected $description = 'Truncate all JobLog records';

    public function handle(): int
    {
        $totalCount = JobLog::count();

        if ($totalCount === 0) {
            $this->info(__('joblog::joblog.command.truncate_no_records'));
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm(__('joblog::joblog.command.truncate_confirm', ['count' => $totalCount]))) {
            $this->info(__('joblog::joblog.command.truncate_cancelled'));
            return self::SUCCESS;
        }

        // Truncate in correct order (foreign keys)
        JobLogRecord::truncate();
        JobLogStep::truncate();
        JobLog::truncate();

        $this->info(__('joblog::joblog.command.truncate_success'));

        return self::SUCCESS;
    }
}
