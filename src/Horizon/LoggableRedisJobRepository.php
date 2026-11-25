<?php

namespace ArtemYurov\JobLog\Horizon;

use ArtemYurov\JobLog\Database\JobLogModelResolver;
use ArtemYurov\JobLog\Enums\JobLogStatus;
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

        $count = JobLogModelResolver::log()::where('queue', $queue)
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
