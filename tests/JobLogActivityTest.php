<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use PHPUnit\Framework\TestCase;

/**
 * JobLog::isActive() — whether a record still credibly claims the job is active.
 * Consumed by the overlap middleware and by the dispatch guard in
 * moonshine-command-schedule-job, so the rule lives here rather than in either caller.
 */
class JobLogActivityTest extends TestCase
{
    public function test_queued_is_active_without_a_pid(): void
    {
        // Waiting in the queue needs no process.
        $this->assertTrue($this->row(JobLogStatus::QUEUED, null)->isActive());
    }

    public function test_processing_with_a_live_pid_is_active(): void
    {
        $this->assertTrue($this->row(JobLogStatus::PROCESSING, getmypid())->isActive());
    }

    public function test_processing_with_a_dead_pid_is_not_active(): void
    {
        // Killed outright: nothing wrote a final status, so only the missing process
        // reveals that the claim is stale.
        $this->assertFalse($this->row(JobLogStatus::PROCESSING, $this->deadPid())->isActive());
    }

    public function test_processing_without_a_pid_is_not_active(): void
    {
        $this->assertFalse($this->row(JobLogStatus::PROCESSING, null)->isActive());
    }

    public function test_finished_statuses_are_not_active(): void
    {
        foreach ([JobLogStatus::PROCESSED, JobLogStatus::FAILED, JobLogStatus::INTERRUPTED] as $status) {
            $this->assertFalse(
                $this->row($status, getmypid())->isActive(),
                "{$status->value} must not count as active even with a live pid"
            );
        }
    }

    private function row(JobLogStatus $status, ?int $pid): JobLog
    {
        return new JobLog(['job_uuid' => 'me', 'status' => $status, 'pid' => $pid]);
    }

    private function deadPid(): int
    {
        $pid = 999999;

        if (extension_loaded('posix') && posix_getpgid($pid) !== false) {
            $this->markTestSkipped("pid {$pid} is in use on this machine");
        }

        return $pid;
    }
}
