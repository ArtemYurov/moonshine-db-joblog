<?php

namespace ArtemYurov\JobLog\Database;

use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogRecord;
use ArtemYurov\JobLog\Models\JobLogStep;
use ArtemYurov\JobLog\Models\Mongo\JobLog as MongoJobLog;
use ArtemYurov\JobLog\Models\Mongo\JobLogRecord as MongoJobLogRecord;
use ArtemYurov\JobLog\Models\Mongo\JobLogStep as MongoJobLogStep;

/**
 * Returns the model class in effect for the resolved driver. Consumers use
 * these instead of hard-coded model FQCNs so the whole package (stores,
 * commands, MoonShine resources) transparently switches between SQL and
 * MongoDB models.
 */
final class ModelResolver
{
    /**
     * @return class-string<JobLog|MongoJobLog>
     */
    public static function jobLog(): string
    {
        return JobLogDatabase::isMongo() ? MongoJobLog::class : JobLog::class;
    }

    /**
     * @return class-string<JobLogStep|MongoJobLogStep>
     */
    public static function jobLogStep(): string
    {
        return JobLogDatabase::isMongo() ? MongoJobLogStep::class : JobLogStep::class;
    }

    /**
     * @return class-string<JobLogRecord|MongoJobLogRecord>
     */
    public static function jobLogRecord(): string
    {
        return JobLogDatabase::isMongo() ? MongoJobLogRecord::class : JobLogRecord::class;
    }
}
