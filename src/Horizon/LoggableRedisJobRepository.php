<?php

namespace ArtemYurov\JobLog\Horizon;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use Carbon\Carbon;
use Laravel\Horizon\Repositories\RedisJobRepository;

/**
 * Intercept queue purge to correctly update JobLog statuses
 */
class LoggableRedisJobRepository extends RedisJobRepository
{
    public function purge($queue)
    {
        $result = parent::purge($queue);

        $count = JobLog::where('queue', $queue)
            ->whereIn('status', [JobLogStatus::QUEUED, JobLogStatus::PROCESSING])
            ->update([
                'status' => JobLogStatus::INTERRUPTED,
                'finished_at' => Carbon::now(),
            ]);

        if (app()->runningInConsole()) {
            echo "\nJobLog: Interrupted {$count} jobs.\n";
        }

        return $result;
    }
}
