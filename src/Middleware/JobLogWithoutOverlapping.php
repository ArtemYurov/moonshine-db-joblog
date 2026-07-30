<?php

namespace ArtemYurov\JobLog\Middleware;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use Closure;

/**
 * Execution-level "without overlapping" for Loggable jobs, backed by the JobLog
 * table (no cache lock). On pickup it checks for another job with the same tags
 * currently PROCESSING; if one exists the run waits or is dropped, and both
 * outcomes settle to Laravel's canonical statuses — no custom status:
 *
 *  - serialize (default): released back to the queue and retried → PROCESSED
 *    (peer freed, it ran) or FAILED (tries/retryUntil ran out).
 *  - drop (dontRelease(), $releaseAfter === null): returns without $next — the
 *    peer is already doing the work, so Laravel's success lifecycle marks it
 *    PROCESSED. On the sync queue release() is a no-op, so serialize also settles
 *    to PROCESSED there.
 *
 * $releaseAfter encodes the mode (null = drop), mirroring the native Illuminate
 * WithoutOverlapping. Not attached by the package itself — an external
 * orchestrator (moonshine-command-schedule-job) opts a job in at dispatch time.
 * The check is a SELECT, so it shares the dispatch-time guard's non-atomic
 * (TOCTOU) trade-off: a fully-concurrent start is a known (rare) race.
 */
class JobLogWithoutOverlapping
{
    /**
     * @param  int|null  $releaseAfter  Seconds to wait before retrying while tags
     *                                  are busy. null = do not release (drop the
     *                                  redundant overlapping run instead).
     */
    public function __construct(protected ?int $releaseAfter = 10) {}

    /** Set the release delay (seconds) before a busy job retries. */
    public function releaseAfter(?int $seconds): static
    {
        $this->releaseAfter = $seconds;

        return $this;
    }

    /** Drop the redundant run on overlap instead of releasing (settles to PROCESSED). */
    public function dontRelease(): static
    {
        $this->releaseAfter = null;

        return $this;
    }

    public function handle(object $job, Closure $next): void
    {
        // Without the JobLog mechanism there is nothing to serialize on.
        if (!method_exists($job, 'log')) {
            $next($job);

            return;
        }

        $logger = $job->log();
        $self = $logger->getJobLog();

        // Tags from the job's own JobLog entry (same source as the dispatch guard);
        // empty → job class as the key, see hasActiveOverlap.
        $tags = $self->tags?->all() ?? [];

        if ($this->hasActiveOverlap($self, $tags)) {
            if ($this->releaseAfter === null) {
                // drop: peer already does the work; return without $next → JobProcessed marks it PROCESSED.
                $logger->debug('JobLogWithoutOverlapping: tags busy, skipping (dontRelease)', ['tags' => $tags]);

                return; // do NOT call $next
            }

            // serialize: release for retry (async waits; sync no-op) → canonical PROCESSED/FAILED.
            $logger->debug('JobLogWithoutOverlapping: tags busy, releasing for retry', ['tags' => $tags]);
            $job->release($this->releaseAfter);

            return;
        }

        $logger->debug('JobLogWithoutOverlapping: no overlap, proceeding');
        $next($job);
    }

    /**
     * Is another job with all key tags running with priority over this one?
     *
     * Only a PROCESSING peer blocks (not QUEUED — else released jobs livelock). On
     * a simultaneous start a deterministic tie-break yields to the earlier
     * queued_at, or the smaller uuid when equal, so exactly one job proceeds.
     * Empty $keyTags falls back to job_class, matching the dispatch-time guard.
     *
     * @param  array<string>  $keyTags
     */
    protected function hasActiveOverlap(JobLog $self, array $keyTags): bool
    {
        $query = JobLog::query()
            ->where('status', JobLogStatus::PROCESSING)
            ->where('job_uuid', '!=', $self->job_uuid)
            ->where(function ($q) use ($self) {
                $q->where('queued_at', '<', $self->queued_at)
                    ->orWhere(function ($q2) use ($self) {
                        $q2->where('queued_at', '=', $self->queued_at)
                            ->where('job_uuid', '<', $self->job_uuid);
                    });
            });

        if (empty($keyTags)) {
            $query->where('job_class', $self->job_class);
        } else {
            foreach ($keyTags as $tag) {
                $query->whereJsonContains('tags', $tag);
            }
        }

        return $query->exists();
    }
}
