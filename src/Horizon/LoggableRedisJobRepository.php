<?php

namespace ArtemYurov\JobLog\Horizon;

use ArtemYurov\JobLog\Database\JobLogStores;
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

        $count = JobLogStores::resolve()->purgeQueue(
            $queue,
            [JobLogStatus::QUEUED, JobLogStatus::PROCESSING],
            JobLogStatus::INTERRUPTED,
            Carbon::now(),
        );

        if (app()->runningInConsole()) {
            echo "\nJobLog: Interrupted {$count} jobs.\n";
        }

        return $result;
    }
}
