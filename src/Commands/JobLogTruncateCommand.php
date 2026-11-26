<?php

namespace ArtemYurov\JobLog\Commands;

use ArtemYurov\JobLog\Database\JobLogModelResolver;
use Illuminate\Console\Command;

class JobLogTruncateCommand extends Command
{
    protected $signature = 'joblog:truncate {--force : Skip confirmation prompt}';

    protected $description = 'Truncate all JobLog records';

    public function handle(): int
    {
        $totalCount = JobLogModelResolver::log()::count();

        if ($totalCount === 0) {
            $this->info(__('joblog::joblog.command.truncate_no_records'));
            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm(__('joblog::joblog.command.truncate_confirm', ['count' => $totalCount]))) {
            $this->info(__('joblog::joblog.command.truncate_cancelled'));
            return self::SUCCESS;
        }

        JobLogModelResolver::log()::truncateAll();

        $this->info(__('joblog::joblog.command.truncate_success'));

        return self::SUCCESS;
    }
}
