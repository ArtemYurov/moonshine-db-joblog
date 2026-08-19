<?php

namespace ArtemYurov\JobLog\Middleware;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use Carbon\Carbon;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Execution-level "without overlapping" for Loggable jobs, keyed by their JobLog tags.
 *
 * Busy means a live process, not a held timer. Two overlaps are covered: a second
 * execution of the same message (a driver re-issues a running job once retry_after
 * expires; both share one job_logs row, which is why the first live pid is kept) and a
 * different message on the same tags.
 *
 * No lock, no atomicity needed: each execution writes its PROCESSING row before it
 * queries for overlaps, so they cannot miss each other, and the (queued_at, uuid) tie-break
 * lets exactly one through.
 *
 * `$expiresAfter` caps how long a PROCESSING row may keep blocking, so a lost
 * bookkeeping write cannot wedge a tag forever. It is raised to the job's own
 * `timeout` plus a margin when that would outlast it, so it never writes off a run
 * that is still legitimately going.
 *
 * Needs ext-posix and one host; without the extension every recorded pid counts as live.
 * Extends the native middleware for its fluent contract only — `key`, `prefix` and
 * shared() are unused.
 *
 *     public function middleware(): array
 *     {
 *         return [(new JobLogWithoutOverlapping())->dontRelease()];
 *     }
 */
class JobLogWithoutOverlapping extends WithoutOverlapping
{
    /** Seconds added on top of a job's timeout when the configured cap is shorter. */
    protected const EXPIRY_MARGIN = 60;

    /**
     * @param  int|null  $releaseAfter  Retry delay while another execution runs; null = drop instead.
     * @param  \DateTimeInterface|int  $expiresAfter  When a PROCESSING row is written off as broken.
     */
    public function __construct(?int $releaseAfter = 10, $expiresAfter = 3 * 60 * 60)
    {
        parent::__construct('', $releaseAfter, $expiresAfter);
    }

    /**
     * @param  mixed  $job
     * @param  callable  $next
     */
    public function handle($job, $next)
    {
        if (!method_exists($job, 'log')) {
            return $next($job);
        }

        $logger = $job->log();
        $overlap = $this->findActiveOverlap($logger->getJobLog(), $this->resolveExpiresAfter($job, $logger));

        if ($overlap === null) {
            return $next($job);
        }

        $logger->debug('JobLogWithoutOverlapping: another live execution holds these tags', [
            'overlap_uuid' => $overlap->job_uuid,
            'overlap_pid' => $overlap->pid,
            'mode' => $this->releaseAfter === null ? 'drop' : 'release',
        ]);

        // Both outcomes settle to Laravel's canonical statuses: a bare return is
        // PROCESSED via the success lifecycle, a release is retried later.
        if ($this->releaseAfter === null) {
            return null;
        }

        $job->release($this->releaseAfter);

        return null;
    }

    /**
     * Cap on how long a PROCESSING row may keep blocking, never shorter than the run
     * it protects.
     *
     * A job cannot outlive its own `timeout` by more than signal delivery — the worker
     * arms SIGALRM and kills it — so the cap only has to clear that plus a margin.
     * Below it a still-running job would stop protecting its tags, which is exactly
     * what this middleware exists to prevent.
     */
    protected function resolveExpiresAfter(object $job, object $logger): int
    {
        $timeout = $job->timeout ?? 0;

        if ($timeout <= 0 || $timeout + static::EXPIRY_MARGIN <= $this->expiresAfter) {
            return $this->expiresAfter;
        }

        $raised = $timeout + static::EXPIRY_MARGIN;

        $logger->warning('JobLogWithoutOverlapping: staleness cap of ' . $this->expiresAfter
            . 's is shorter than this job\'s timeout (' . $timeout . 's); using ' . $raised
            . 's instead. Raise expireAfter() to silence this.');

        return $raised;
    }

    /** The JobLog row of the execution to yield to, or null when this run may proceed. */
    protected function findActiveOverlap(JobLog $self, int $expiresAfter): ?JobLog
    {
        // Same message: our own row still carries someone else's live pid.
        if ($self->pid !== null && $self->pid !== getmypid() && $self->pidIsAlive()) {
            return $self;
        }

        return $this->findActiveOverlapByTags($self, $expiresAfter);
    }

    /**
     * Another message on the same tags, narrowed to a live process.
     *
     * Only PROCESSING rows block — QUEUED ones would livelock released jobs. Empty
     * tags fall back to the job class, matching the dispatch-time guard.
     */
    protected function findActiveOverlapByTags(JobLog $self, int $expiresAfter): ?JobLog
    {
        $tags = $self->tags?->all() ?? [];

        $query = JobLog::query()
            ->where('status', JobLogStatus::PROCESSING)
            ->where('job_uuid', '!=', $self->job_uuid)
            ->whereNotNull('pid')
            ->where('started_at', '>=', Carbon::now()->subSeconds($expiresAfter))
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
            foreach ($tags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        // Liveness is not expressible in SQL, so only the candidates are asked.
        return $query->get()->first(fn (JobLog $row) => $row->pidIsAlive());
    }
}
