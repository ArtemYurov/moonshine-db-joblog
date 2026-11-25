<?php

namespace ArtemYurov\JobLog\Database\Contracts;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use ArtemYurov\JobLog\Models\Contracts\JobLogStepContract;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * The write side of the log storage plus every driver-specific query.
 *
 * This is the single point where SQL and MongoDB behaviour may legally
 * differ: consumers (JobLogger, DatabaseHandler, middleware, Horizon hook,
 * artisan commands) talk to this contract only. Plain Eloquent-compatible
 * reads (MoonShine listings, filters, relations) go through ModelResolver
 * and the model contracts instead.
 */
interface JobLogStore
{
    /**
     * Insert a job_logs row (dispatch time).
     *
     * @param array<string, mixed> $attributes
     */
    public function createLog(array $attributes): JobLogContract;

    /** Find the log row for a queue job UUID. */
    public function findByJobUuid(string $jobUuid): ?JobLogContract;

    /**
     * Insert a log record (job_log_records row or document equivalent).
     *
     * @param mixed $stepId Step primary key (int for SQL, ObjectId for MongoDB) or null
     * @param array<string, mixed>|null $context
     */
    public function createRecord(
        JobLogContract $log,
        mixed $stepId,
        string $level,
        string $message,
        ?array $context,
        ?string $trace,
        DateTimeInterface $createdAt,
    ): void;

    /**
     * Get (or create) the step row for a step key within the log.
     */
    public function findOrCreateStep(JobLogContract $log, string $stepKey, ?string $stepName): JobLogStepContract;

    /** The step of this log that is still PROCESSING, if any. */
    public function findProcessingStep(JobLogContract $log): ?JobLogStepContract;

    /**
     * The JobLog row of another live execution holding the same tags (or the
     * same message), or null when this run may proceed.
     */
    public function findActiveOverlap(JobLogContract $self, int $expiresAfterSeconds): ?JobLogContract;

    public function getLatestCompletedFinishedTime(string $jobClass, int|string|null $relatedId = null): ?Carbon;

    public function getLatestCompletedStartedTime(string $jobClass, int|string|null $relatedId = null): ?Carbon;

    /**
     * Mark every non-final log row of a purged queue with the given status.
     *
     * @param list<JobLogStatus> $fromStatuses
     * @return int Number of affected rows
     */
    public function purgeQueue(string $queue, array $fromStatuses, JobLogStatus $toStatus, Carbon $finishedAt): int;

    /** Total number of log rows. */
    public function countLogs(): int;

    public function countLogsOlderThan(Carbon $cutoff): int;

    /**
     * Delete log rows older than the cutoff (cascade covers steps/records).
     *
     * @return int Number of deleted rows
     */
    public function deleteLogsOlderThan(Carbon $cutoff): int;

    /** Remove every log row, step and record. */
    public function truncateLogs(): void;
}
