<?php

namespace ArtemYurov\JobLog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLogRecord extends Model
{
    public function getTable(): string
    {
        return config('joblog.tables.job_log_records', 'job_log_records');
    }

    public $timestamps = false;

    protected $casts = [
        'context' => 'collection',
        'created_at' => 'datetime',
    ];

    public function jobLog(): BelongsTo
    {
        return $this->belongsTo(JobLog::class);
    }
}
