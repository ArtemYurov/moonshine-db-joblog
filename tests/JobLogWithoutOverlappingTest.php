<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Middleware\JobLogWithoutOverlapping;
use ArtemYurov\JobLog\Models\JobLog;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour tests for the JobLogWithoutOverlapping middleware.
 *
 * The DB-touching part (hasActiveOverlap) is overridden in a test subclass so
 * the branching logic can be verified without an Eloquent/database bootstrap —
 * consistent with the package's cache/db-free unit style.
 *
 * Both overlap outcomes resolve to Laravel's canonical lifecycle statuses, so
 * these tests never assert a custom status: serialize releases for retry (→
 * PROCESSED/FAILED later), drop returns without $next (→ PROCESSED via the
 * JobProcessed lifecycle, covered end-to-end in the integration test).
 */
class JobLogWithoutOverlappingTest extends TestCase
{
    public function test_proceeds_for_job_without_log_method(): void
    {
        $middleware = new JobLogWithoutOverlapping(10);
        $called = false;

        $middleware->handle(new \stdClass(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function test_serializes_by_class_when_no_tags(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $middleware->overlap = true; // a same-class peer is running
        $job = new FakeOverlappingJob(new JobLog(['job_uuid' => 'me', 'tags' => []]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called, 'no tags → still serialized (by job class)');
        $this->assertTrue($middleware->checked, 'overlap is queried even without tags');
        $this->assertSame(10, $job->releasedWith);
        $this->assertFalse($job->deleted, 'serialize mode must not delete the job');
    }

    public function test_releases_for_retry_on_overlap(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $middleware->overlap = true;
        $job = new FakeOverlappingJob(new JobLog(['job_uuid' => 'me', 'tags' => ['payments:1']]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called, 'job must not proceed while tags are busy');
        $this->assertSame(10, $job->releasedWith, 'released with configured releaseAfter');
        $this->assertFalse($job->deleted, 'serialize mode must not delete the job');
        $this->assertSame('me', $middleware->checkedSelf?->job_uuid, 'self record passed for exclusion');
    }

    public function test_proceeds_when_no_overlap(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $middleware->overlap = false;
        $job = new FakeOverlappingJob(new JobLog(['job_uuid' => 'me', 'tags' => ['payments:1']]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertNull($job->releasedWith);
        $this->assertFalse($job->deleted);
    }

    public function test_dont_release_bare_returns_on_overlap(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $middleware->overlap = true;
        $middleware->dontRelease(); // drop mode: releaseAfter = null
        $job = new FakeOverlappingJob(new JobLog(['job_uuid' => 'me', 'tags' => ['payments:1']]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        // Bare return: no $next, no release, no delete. Laravel's success
        // lifecycle then marks the redundant run PROCESSED.
        $this->assertFalse($called, 'job must not proceed while tags are busy');
        $this->assertNull($job->releasedWith, 'drop mode must not release the job');
        $this->assertFalse($job->deleted, 'drop mode does not delete — it bare-returns');
    }
}

/**
 * JobLogWithoutOverlapping with the DB lookup stubbed to a controllable flag.
 */
class SpyJobLogWithoutOverlapping extends JobLogWithoutOverlapping
{
    public bool $overlap = false;

    public bool $checked = false;

    public ?JobLog $checkedSelf = null;

    protected function hasActiveOverlap(JobLog $self, array $keyTags): bool
    {
        $this->checked = true;
        $this->checkedSelf = $self;

        return $this->overlap;
    }
}

/**
 * Fake logger exposing only what the middleware uses.
 */
class FakeOverlapLogger
{
    public function __construct(private JobLog $jobLog) {}

    public function getJobLog(): JobLog
    {
        return $this->jobLog;
    }

    public function debug(string|\Stringable $message, array $context = []): self
    {
        return $this;
    }
}

/**
 * Plain job exposing only the surface the middleware touches: log() and
 * release(). delete() is kept only to assert it is never called any more.
 */
class FakeOverlappingJob
{
    public ?int $releasedWith = null;

    public bool $deleted = false;

    private FakeOverlapLogger $logger;

    public function __construct(JobLog $self)
    {
        $this->logger = new FakeOverlapLogger($self);
    }

    public function log(): FakeOverlapLogger
    {
        return $this->logger;
    }

    public function release(int $seconds): void
    {
        $this->releasedWith = $seconds;
    }

    public function delete(): void
    {
        $this->deleted = true;
    }
}
