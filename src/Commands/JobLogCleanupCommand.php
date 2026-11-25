<?php

namespace ArtemYurov\JobLog\Commands;

use ArtemYurov\JobLog\Database\Contracts\JobLogStore;
use ArtemYurov\JobLog\Database\JobLogStores;
use Carbon\Carbon;
use Illuminate\Console\Command;

class JobLogCleanupCommand extends Command
{
    protected $signature = 'joblog:cleanup {--days= : Days to keep records}';

    protected $description = 'Cleanup JobLog records older than specified days';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('joblog.cleanup.days', 30));

        $cutoffDate = Carbon::now()->subDays($days);

        $this->info(__('joblog::joblog.command.cleanup_start', [
            'days' => $days,
            'date' => $cutoffDate->format('Y-m-d H:i:s'),
        ]));

        /** @var JobLogStore $store */
        $store = JobLogStores::resolve();
        $totalCount = $store->countLogsOlderThan($cutoffDate);

        if ($totalCount === 0) {
            $this->info(__('joblog::joblog.command.cleanup_no_records'));
            return self::SUCCESS;
        }

        $this->info(__('joblog::joblog.command.cleanup_found', ['count' => $totalCount]));

        $deletedCount = $store->deleteLogsOlderThan($cutoffDate);

        $this->info(__('joblog::joblog.command.cleanup_success', ['count' => $deletedCount]));

        return self::SUCCESS;
    }
}
