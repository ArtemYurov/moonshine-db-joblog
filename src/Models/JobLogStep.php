<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLogStep extends Model
{
    public function getTable(): string
    {
        return config('joblog.tables.job_log_steps', 'job_log_steps');
    }

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

    public function jobLog(): BelongsTo
    {
        return $this->belongsTo(JobLog::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(JobLogRecord::class, 'job_log_id', 'job_log_id')
            ->where('step_key', $this->step_key)
            ->orderBy('created_at', 'desc');
    }

    public function getLastErrorRecord(): ?JobLogRecord
    {
        return JobLogRecord::where('job_log_id', $this->job_log_id)
            ->where('step_key', $this->step_key)
            ->where('level', 'error')
            ->latest('created_at')
            ->first();
    }

    public function getErrorRecords()
    {
        return JobLogRecord::where('job_log_id', $this->job_log_id)
            ->where('step_key', $this->step_key)
            ->where('level', 'error')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
