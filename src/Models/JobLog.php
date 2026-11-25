<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Database\JobLogDatabase;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Concerns\HasJobLogBehavior;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JobLog extends Model implements JobLogContract
{
    use HasJobLogBehavior;

    protected $table = 'job_logs';

    protected $guarded = [];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'status' => JobLogStatus::class,
        'args' => 'collection',
        'tags' => 'collection',
        'data' => 'collection',
        'pid' => 'integer',
    ];

    /**
     * The log runs on the connection resolved from joblog.database — the
     * application default when nothing is configured.
     */
    public function getConnectionName()
    {
        return JobLogDatabase::connectionName() ?? $this->connection;
    }

    public function records(): HasMany
    {
        return $this->hasMany(JobLogRecord::class)
            ->whereNull('job_log_step_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(JobLogStep::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function latestStep(): HasOne
    {
        return $this->hasOne(JobLogStep::class)->orderByDesc('id');
    }

    public function latestErrorRecord(): HasOne
    {
        return $this->hasOne(JobLogRecord::class)
            ->whereNull('job_log_step_id')
            ->where('level', 'error')
            ->orderByDesc('id');
    }
}
