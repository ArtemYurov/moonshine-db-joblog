<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Database\JobLogStorage;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Concerns\HasJobLogStepBehavior;
use ArtemYurov\JobLog\Models\Contracts\JobLogStepContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLogStep extends Model implements JobLogStepContract
{
    use HasJobLogStepBehavior;

    protected $table = 'job_log_steps';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'collection',
            'status' => JobLogStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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

    public function records(): HasMany
    {
        return $this->hasMany(JobLogRecord::class)
            ->orderByDesc('created_at');
    }
}
