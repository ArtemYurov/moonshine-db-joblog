<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobLogStep extends Model
{
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

    public function jobLog(): BelongsTo
    {
        return $this->belongsTo(JobLog::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(JobLogRecord::class)
            ->orderBy('created_at', 'desc');
    }

    public function getLastErrorRecord(): ?JobLogRecord
    {
        return $this->records()
            ->where('level', 'error')
            ->latest('created_at')
            ->first();
    }

    public function getErrorRecords()
    {
        return $this->records()
            ->where('level', 'error')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
