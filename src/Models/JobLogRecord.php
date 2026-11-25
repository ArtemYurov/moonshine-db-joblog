<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Database\JobLogStorage;
use ArtemYurov\JobLog\Models\Contracts\JobLogRecordContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLogRecord extends Model implements JobLogRecordContract
{
    protected $table = 'job_log_records';

    protected $guarded = [];

    public $timestamps = false;

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
        return JobLogStorage::connectionName() ?? $this->connection;
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
