<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Middleware\LoggableExceptionAttempts;
use ArtemYurov\JobLog\Tests\Fixtures\FakeLoggableJob;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Loggable trait initializes middleware correctly via $middleware property
 */
class LoggableMiddlewareReturnTest extends TestCase
{
    public function test_loggable_middleware_returns_array(): void
    {
        $job = new FakeLoggableJob();

        $this->assertIsArray($job->middleware);
    }

    public function test_loggable_middleware_contains_exception_attempts(): void
    {
        $job = new FakeLoggableJob();

        $this->assertCount(1, $job->middleware);
        $this->assertInstanceOf(LoggableExceptionAttempts::class, $job->middleware[0]);
    }

    public function test_loggable_has_default_steps_method(): void
    {
        $job = new FakeLoggableJob();

        $reflection = new \ReflectionMethod($job, 'steps');
        $reflection->setAccessible(true);

        $this->assertSame([], $reflection->invoke($job));
    }

    public function test_loggable_has_auto_step_progress_enabled_by_default(): void
    {
        $job = new FakeLoggableJob();

        $reflection = new \ReflectionProperty($job, 'autoStepProgress');

        $this->assertTrue($reflection->getValue($job));
    }

    public function test_disable_auto_step_progress(): void
    {
        $job = new FakeLoggableJob();

        $reflection = new \ReflectionMethod($job, 'disableAutoStepProgress');
        $reflection->invoke($job);

        $property = new \ReflectionProperty($job, 'autoStepProgress');

        $this->assertFalse($property->getValue($job));
    }

    public function test_loggable_job_logger_is_initially_null(): void
    {
        $job = new FakeLoggableJob();

        $reflection = new \ReflectionProperty($job, 'jobLogger');
        $reflection->setAccessible(true);

        $this->assertNull($reflection->getValue($job));
    }
}
