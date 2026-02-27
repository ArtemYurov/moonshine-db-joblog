<?php

namespace ArtemYurov\JobLog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobLogRecord extends Model
{
    protected $table = 'job_log_records';

    public $timestamps = false;

    protected $casts = [
        'context' => 'collection',
        'created_at' => 'datetime',
    ];

    public function jobLog(): BelongsTo
    {
        return $this->belongsTo(JobLog::class);
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(JobLogStep::class, 'job_log_step_id');
    }
}
