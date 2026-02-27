<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JobLog extends Model
{
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
        return $this->hasOne(JobLogStep::class)->latestOfMany();
    }

    public function latestErrorRecord(): HasOne
    {
        return $this->hasOne(JobLogRecord::class)
            ->whereNull('job_log_step_id')
            ->where('level', 'error')
            ->latestOfMany();
    }

    public static function findByJobUuid(string $jobUuid): self
    {
        try {
            return self::where('job_uuid', $jobUuid)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \RuntimeException('JobLog not found for UUID: ' . $jobUuid, 0, $e);
        }
    }

    public static function getLatestCompletedFinishedTime(string $jobClass, ?int $relatedId = null): ?Carbon
    {
        $query = self::select('finished_at')
            ->where('job_class', $jobClass)
            ->where('status', JobLogStatus::PROCESSED)
            ->orderByDesc('finished_at');

        if ($relatedId !== null) {
            $query->where('related_id', $relatedId);
        }

        return $query->first()?->finished_at;
    }

    public static function getLatestCompletedStartedTime(string $jobClass, ?int $relatedId = null): ?Carbon
    {
        $query = self::select('started_at')
            ->where('job_class', $jobClass)
            ->where('status', JobLogStatus::PROCESSED)
            ->orderByDesc('started_at');

        if ($relatedId !== null) {
            $query->where('related_id', $relatedId);
        }

        return $query->first()?->started_at;
    }

    public function getLastErrorRecord(): ?JobLogRecord
    {
        return $this->records()
            ->where('level', 'error')
            ->latest()
            ->orderBy('id', 'desc')
            ->first();
    }
}
