<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Middleware\JobLogWithoutOverlapping;
use ArtemYurov\JobLog\Models\JobLog;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour tests for the JobLogWithoutOverlapping middleware: which pid makes a run
 * yield and which does not. The tag query is stubbed in a test subclass to keep these
 * DB-free — the real one is exercised in the integration test.
 */
class JobLogWithoutOverlappingTest extends TestCase
{
    /** A pid that exists but is not ours. */
    private function foreignLivePid(): int
    {
        return function_exists('posix_getppid') ? posix_getppid() : 999999;
    }

    /** A pid nothing is using. */
    private function deadPid(): int
    {
        $pid = 999999;

        if (extension_loaded('posix') && posix_getpgid($pid) !== false) {
            $this->markTestSkipped("pid {$pid} is in use on this machine");
        }

        return $pid;
    }

    public function test_proceeds_for_job_without_log_method(): void
    {
        $middleware = new JobLogWithoutOverlapping(10);
        $called = false;

        $middleware->handle(new \stdClass(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function test_proceeds_when_no_active_overlap(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $job = new FakeOverlappingJob($this->row(['pid' => getmypid()]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertNull($job->releasedWith);
    }

    public function test_yields_when_own_row_carries_a_foreign_live_pid(): void
    {
        // The PROCESSING transition kept the first execution's live pid.
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $job = new FakeOverlappingJob($this->row(['pid' => $this->foreignLivePid()]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called, 'a live execution of the same message must win');
        $this->assertSame(10, $job->releasedWith);
    }

    public function test_proceeds_when_own_row_carries_a_dead_pid(): void
    {
        // Killed without bookkeeping: a dead pid holds nothing, recovery is immediate.
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $job = new FakeOverlappingJob($this->row(['pid' => $this->deadPid()]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
        $this->assertNull($job->releasedWith);
    }

    public function test_proceeds_when_own_row_has_no_pid(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $job = new FakeOverlappingJob($this->row(['pid' => null]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function test_releases_for_retry_on_active_overlap(): void
    {
        $middleware = new SpyJobLogWithoutOverlapping(10);
        $middleware->activeOverlap = $this->row(['job_uuid' => 'other', 'pid' => $this->foreignLivePid()]);
        $job = new FakeOverlappingJob($this->row(['pid' => getmypid()]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called, 'job must not proceed while an overlap is active');
        $this->assertSame(10, $job->releasedWith, 'released with configured releaseAfter');
        $this->assertFalse($job->deleted, 'serialize mode must not delete the job');
    }

    public function test_dont_release_bare_returns_on_active_overlap(): void
    {
        $middleware = (new SpyJobLogWithoutOverlapping)->dontRelease();
        $middleware->activeOverlap = $this->row(['job_uuid' => 'other', 'pid' => $this->foreignLivePid()]);
        $job = new FakeOverlappingJob($this->row(['pid' => getmypid()]));
        $called = false;

        $middleware->handle($job, function () use (&$called) {
            $called = true;
        });

        // Bare return: no $next, no release, no delete → PROCESSED via the success lifecycle.
        $this->assertFalse($called, 'job must not proceed while an overlap is active');
        $this->assertNull($job->releasedWith, 'drop mode must not release the job');
        $this->assertFalse($job->deleted, 'drop mode does not delete — it bare-returns');
    }

    public function test_staleness_cap_is_never_shorter_than_the_job_timeout(): void
    {
        // Below its own timeout the cap would write off a run that is still legitimately
        // going, and that run would stop protecting its tags.
        $middleware = new SpyJobLogWithoutOverlapping(10, 600);

        $short = new FakeOverlappingJob($this->row(['pid' => getmypid()]));
        $short->timeout = 120;
        $middleware->handle($short, fn () => null);
        $this->assertSame(600, $middleware->capUsed, 'a job well inside the cap keeps the configured value');
        $this->assertSame([], $short->log()->warnings);

        $long = new FakeOverlappingJob($this->row(['pid' => getmypid()]));
        $long->timeout = 900;
        $middleware->handle($long, fn () => null);
        $this->assertSame(960, $middleware->capUsed, 'timeout + margin wins when the cap is too short');
        $this->assertCount(1, $long->log()->warnings, 'the shortfall is reported on the job itself');
    }

    private function row(array $attributes): JobLog
    {
        return new JobLog(array_merge([
            'job_uuid' => 'me',
            'job_class' => 'FakeJob',
            'tags' => ['payments:1'],
        ], $attributes));
    }
}

/**
 * JobLogWithoutOverlapping with the tag lookup stubbed to a controllable row.
 */
class SpyJobLogWithoutOverlapping extends JobLogWithoutOverlapping
{
    public ?JobLog $activeOverlap = null;

    public ?int $capUsed = null;

    protected function findActiveOverlapByTags(JobLog $self, int $expiresAfter): ?JobLog
    {
        $this->capUsed = $expiresAfter;

        return $this->activeOverlap;
    }
}

/**
 * Fake logger exposing only what the middleware uses.
 */
class FakeOverlapLogger
{
    /** @var string[] */
    public array $warnings = [];

    public function __construct(private JobLog $jobLog) {}

    public function getJobLog(): JobLog
    {
        return $this->jobLog;
    }

    public function debug(string|\Stringable $message, array $context = []): self
    {
        return $this;
    }

    public function warning(string|\Stringable $message, array $context = []): self
    {
        $this->warnings[] = (string) $message;

        return $this;
    }
}

/**
 * Plain job exposing only the surface the middleware touches: log() and release().
 * delete() is kept only to assert it is never called any more.
 */
class FakeOverlappingJob
{
    public ?int $timeout = null;

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

    public function logger(): FakeOverlapLogger
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
