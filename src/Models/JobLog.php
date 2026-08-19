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

    /**
     * Does this record still credibly claim the job is active?
     *
     * QUEUED means waiting, which needs no process. PROCESSING is a claim that only
     * holds while the worker that made it exists — nothing writes a final status when
     * one is killed outright, so a dead pid is how such a row is recognised.
     */
    public function isActive(): bool
    {
        return match ($this->status) {
            JobLogStatus::QUEUED => true,
            JobLogStatus::PROCESSING => $this->pidIsAlive(),
            default => false,
        };
    }

    /**
     * Is the process recorded in `pid` still running? This is what overlap protection
     * treats as busy. Without ext-posix a recorded pid counts as live. Pid reuse can
     * only make a dead process look alive, never the reverse.
     */
    public function pidIsAlive(): bool
    {
        if ($this->pid === null) {
            return false;
        }

        if (!extension_loaded('posix')) {
            return true;
        }

        return posix_getpgid($this->pid) !== false;
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
