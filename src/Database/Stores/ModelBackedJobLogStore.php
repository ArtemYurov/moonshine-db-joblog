<?php

namespace ArtemYurov\JobLog\Database\Stores;

use ArtemYurov\JobLog\Database\Contracts\JobLogStore;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use ArtemYurov\JobLog\Models\Contracts\JobLogStepContract;
use Carbon\Carbon;

/**
 * Shared implementation for stores backed by an Eloquent-compatible model
 * pair (Illuminate SQL models or mongodb/laravel-mongodb models — their
 * query APIs coincide for everything used here).
 *
 * Driver-specific corners are delegated: tag matching (JSON contains vs the
 * MongoDB $all operator) and truncation.
 */
abstract class ModelBackedJobLogStore implements JobLogStore
{
    /**
     * @return class-string<JobLogContract> with the shared Eloquent-style API
     */
    abstract protected function logModel(): string;

    /**
     * @return class-string<JobLogStepContract> with the shared Eloquent-style API
     */
    abstract protected function stepModel(): string;

    /**
     * @return class-string<object> with the shared Eloquent-style API
     */
    abstract protected function recordModel(): string;

    /**
     * Narrow an overlap query to rows carrying all the given tags.
     *
     * @param list<string> $tags
     */
    abstract protected function applyTagsFilter(mixed $query, array $tags): void;

    public function createLog(array $attributes): JobLogContract
    {
        /** @var JobLogContract $log */
        $log = $this->logModel()::create($attributes);

        return $log;
    }

    public function findByJobUuid(string $jobUuid): ?JobLogContract
    {
        /** @var JobLogContract|null $log */
        $log = $this->logModel()::query()->where('job_uuid', $jobUuid)->first();

        return $log;
    }

    public function createRecord(
        JobLogContract $log,
        mixed $stepId,
        string $level,
        string $message,
        ?array $context,
        ?string $trace,
        \DateTimeInterface $createdAt,
    ): void {
        $this->recordModel()::create([
            'job_log_id' => $log->getKey(),
            'job_log_step_id' => $stepId,
            'level' => $level,
            'message' => $message,
            'context' => !empty($context) ? $context : null,
            'trace' => $trace,
            'created_at' => $createdAt,
        ]);
    }

    public function findOrCreateStep(JobLogContract $log, string $stepKey, ?string $stepName): JobLogStepContract
    {
        /** @var JobLogStepContract $step */
        $step = $this->stepModel()::firstOrCreate(
            [
                'job_log_id' => $log->getKey(),
                'step_key' => $stepKey,
            ],
            [
                'step_name' => $stepName,
                'status' => JobLogStatus::PROCESSING,
                'progress' => 0,
                'started_at' => Carbon::now(),
            ],
        );

        return $step;
    }

    public function findProcessingStep(JobLogContract $log): ?JobLogStepContract
    {
        /** @var JobLogStepContract|null $step */
        $step = $this->stepModel()::query()
            ->where('job_log_id', $log->getKey())
            ->where('status', JobLogStatus::PROCESSING)
            ->orderByDesc('id')
            ->first();

        return $step;
    }

    public function findActiveOverlap(JobLogContract $self, int $expiresAfterSeconds): ?JobLogContract
    {
        $tags = $self->tags?->all() ?? [];

        $query = $this->logModel()::query()
            ->where('status', JobLogStatus::PROCESSING)
            ->where('job_uuid', '!=', $self->job_uuid)
            ->whereNotNull('pid')
            ->where('started_at', '>=', Carbon::now()->subSeconds($expiresAfterSeconds))
            ->where(function ($q) use ($self) {
                $q->where('queued_at', '<', $self->queued_at)
                    ->orWhere(function ($q2) use ($self) {
                        $q2->where('queued_at', '=', $self->queued_at)
                            ->where('job_uuid', '<', $self->job_uuid);
                    });
            });

        if (empty($tags)) {
            $query->where('job_class', $self->job_class);
        } else {
            $this->applyTagsFilter($query, $tags);
        }

        // Liveness is not expressible in the query languages, so only the candidates are asked.
        return $query->get()->first(fn (JobLogContract $row) => $row->pidIsAlive());
    }

    public function getLatestCompletedFinishedTime(string $jobClass, int|string|null $relatedId = null): ?Carbon
    {
        return $this->logModel()::getLatestCompletedFinishedTime($jobClass, $relatedId);
    }

    public function getLatestCompletedStartedTime(string $jobClass, int|string|null $relatedId = null): ?Carbon
    {
        return $this->logModel()::getLatestCompletedStartedTime($jobClass, $relatedId);
    }

    public function purgeQueue(string $queue, array $fromStatuses, JobLogStatus $toStatus, Carbon $finishedAt): int
    {
        return $this->logModel()::query()
            ->where('queue', $queue)
            ->whereIn('status', $fromStatuses)
            ->update([
                'status' => $toStatus,
                'finished_at' => $finishedAt,
            ]);
    }

    public function countLogs(): int
    {
        return $this->logModel()::query()->count();
    }

    public function countLogsOlderThan(Carbon $cutoff): int
    {
        return $this->logModel()::query()
            ->where('queued_at', '<', $cutoff)
            ->count();
    }

    public function deleteLogsOlderThan(Carbon $cutoff): int
    {
        return $this->logModel()::query()
            ->where('queued_at', '<', $cutoff)
            ->delete();
    }

    public function truncateLogs(): void
    {
        // Order matters for SQL foreign keys.
        $this->recordModel()::query()->truncate();
        $this->stepModel()::query()->truncate();
        $this->logModel()::query()->truncate();
    }
}
