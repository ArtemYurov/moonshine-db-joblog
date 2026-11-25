<?php

namespace ArtemYurov\JobLog\Models\Concerns;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Contracts\JobLogRecordContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Behaviour shared by the SQL and MongoDB JobLog models. Static queries use
 * `static::` so they run against whichever model class uses the trait.
 *
 * Requires the using class to define the `records()` relation.
 */
trait HasJobLogBehavior
{
    /**
     * Does this record still credibly claim the job is active?
     *
     * QUEUED means waiting, which needs no process. PROCESSING is a claim that only
     * holds while the worker that made it exists — nothing writes a final status when
     * one is killed outright, so a dead pid is how such a row is recognised.
     */
    public function isActive(): bool
    {
        /** @var \ArtemYurov\JobLog\Enums\JobLogStatus $status */
        $status = $this->status;

        return match ($status) {
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

    public function getLastErrorRecord(): ?JobLogRecordContract
    {
        return $this->records()
            ->where('level', 'error')
            ->orderByDesc('id')
            ->first();
    }

    public static function findByJobUuid(string $jobUuid): self
    {
        try {
            return static::where('job_uuid', $jobUuid)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new \RuntimeException('JobLog not found for UUID: ' . $jobUuid, 0, $e);
        }
    }

    public static function getLatestCompletedFinishedTime(string $jobClass, int|string|null $relatedId = null): ?Carbon
    {
        $query = static::select('finished_at')
            ->where('job_class', $jobClass)
            ->where('status', JobLogStatus::PROCESSED)
            ->orderByDesc('finished_at');

        if ($relatedId !== null) {
            $query->where('related_id', $relatedId);
        }

        return $query->first()?->finished_at;
    }

    public static function getLatestCompletedStartedTime(string $jobClass, int|string|null $relatedId = null): ?Carbon
    {
        $query = static::select('started_at')
            ->where('job_class', $jobClass)
            ->where('status', JobLogStatus::PROCESSED)
            ->orderByDesc('started_at');

        if ($relatedId !== null) {
            $query->where('related_id', $relatedId);
        }

        return $query->first()?->started_at;
    }
}
