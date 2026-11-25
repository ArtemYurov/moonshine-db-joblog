<?php

namespace ArtemYurov\JobLog\Database\Stores;

use ArtemYurov\JobLog\Models\Mongo\JobLog;
use ArtemYurov\JobLog\Models\Mongo\JobLogRecord;
use ArtemYurov\JobLog\Models\Mongo\JobLogStep;

/**
 * Log storage on MongoDB collections via mongodb/laravel-mongodb models.
 *
 * Semantic deltas kept here and nowhere else:
 *  - tags are matched with the native $all operator instead of JSON contains;
 *  - truncation uses mass deletes (no TRUNCATE in MongoDB).
 */
class MongoDbJobLogStore extends ModelBackedJobLogStore
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
        // A document carrying every tag: the MongoDB equivalent of a
        // conjunction of JSON-contains conditions.
        $query->where('tags', 'all', $tags);
    }

    public function truncateLogs(): void
    {
        // MongoDB has no TRUNCATE; delete all documents instead. The order
        // is kept for symmetry with the SQL store.
        $this->recordModel()::query()->delete();
        $this->stepModel()::query()->delete();
        $this->logModel()::query()->delete();
    }
}
