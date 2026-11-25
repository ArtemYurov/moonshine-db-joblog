<?php

namespace ArtemYurov\JobLog\Database\Stores;

use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogRecord;
use ArtemYurov\JobLog\Models\JobLogStep;

/**
 * Log storage on the SQL models (PostgreSQL by contract, through the
 * connection resolved from joblog.database).
 */
class EloquentJobLogStore extends ModelBackedJobLogStore
{
    protected function logModel(): string
    {
        return JobLog::class;
    }

    protected function stepModel(): string
    {
        return JobLogStep::class;
    }

    protected function recordModel(): string
    {
        return JobLogRecord::class;
    }

    protected function applyTagsFilter(mixed $query, array $tags): void
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
    }
}
