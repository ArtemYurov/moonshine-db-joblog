<?php

namespace ArtemYurov\JobLog\Horizon;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Logger\JobLogger;
use ArtemYurov\JobLog\Models\JobLog;
use Laravel\Horizon\Repositories\RedisJobRepository;

/**
 * Intercept queue purge to correctly update JobLog statuses
 */
class LoggableRedisJobRepository extends RedisJobRepository
{
    public function purge($queue)
    {
        $result = parent::purge($queue);

        $activeJobs = JobLog::where('queue', $queue)
            ->whereIn('status', [JobLogStatus::QUEUED, JobLogStatus::PROCESSING])
            ->get();

        $activeJobs->each(fn($jobLog) => JobLogger::changeStatusFromEvent($jobLog->job_uuid, JobLogStatus::INTERRUPTED));

        if (app()->runningInConsole()) {
            echo "\nJobLog: Interrupted {$activeJobs->count()} jobs.\n";
        }

        return $result;
    }
}
