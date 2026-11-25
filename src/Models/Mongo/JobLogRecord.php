<?php

namespace ArtemYurov\JobLog\Models\Mongo;

use ArtemYurov\JobLog\Database\JobLogDatabase;
use ArtemYurov\JobLog\Models\Contracts\JobLogRecordContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * MongoDB variant of the JobLogRecord model.
 */
class JobLogRecord extends Model implements JobLogRecordContract
{
    public $timestamps = false;

    protected $table = 'job_log_records';

    protected $collection = 'job_log_records';

    protected $guarded = [];

    protected $casts = [
        'context' => 'collection',
        'created_at' => 'datetime',
    ];

    /**
     * The log runs on the connection resolved from joblog.database — the
     * application default when nothing is configured.
     */
    public function getConnectionName()
    {
        return JobLogDatabase::connectionName() ?? $this->connection;
    }

    public function jobLog(): BelongsTo
    {
        return $this->belongsTo(JobLog::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(JobLogStep::class, 'job_log_step_id');
    }
}
