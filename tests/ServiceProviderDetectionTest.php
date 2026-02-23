<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Middleware\LoggableExceptionAttempts;
use ArtemYurov\JobLog\Traits\Loggable;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobWithMiddlewareProperty;
use ArtemYurov\JobLog\Tests\Fixtures\FakeLoggableJob;
use ArtemYurov\JobLog\Tests\Fixtures\FakeRegularJob;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ServiceProvider detection logic (without running Laravel)
 */
class ServiceProviderDetectionTest extends TestCase
{
    /**
     * Reproduce isLoggableJob logic from ServiceProvider
     */
    private function isLoggableJob(object $jobObject): bool
    {
        return in_array(Loggable::class, class_uses_recursive($jobObject));
    }

    /**
     * Reproduce hasMiddleware logic from ServiceProvider
     */
    private function hasMiddleware(object $jobObject, string $needleMiddlewareClass): bool
    {
        $middleware = array_merge(
            $jobObject->middleware ?? [],
            method_exists($jobObject, 'middleware') ? $jobObject->middleware() : []
        );

        foreach ($middleware as $middlewareItem) {
            $middlewareClass = is_string($middlewareItem) ? $middlewareItem : get_class($middlewareItem);
            if ($middlewareClass === $needleMiddlewareClass) {
                return true;
            }
        }

        return false;
    }

    public function test_loggable_job_detected(): void
    {
        $job = new FakeLoggableJob();
        $this->assertTrue($this->isLoggableJob($job));
    }

    public function test_regular_job_not_detected(): void
    {
        $job = new FakeRegularJob();
        $this->assertFalse($this->isLoggableJob($job));
    }

    public function test_has_middleware_from_method(): void
    {
        $job = new FakeLoggableJob();
        $this->assertTrue($this->hasMiddleware($job, LoggableExceptionAttempts::class));
    }

    public function test_has_no_middleware(): void
    {
        $job = new FakeRegularJob();
        $this->assertFalse($this->hasMiddleware($job, LoggableExceptionAttempts::class));
    }

    public function test_has_middleware_from_property(): void
    {
        $job = new FakeJobWithMiddlewareProperty();
        $this->assertTrue($this->hasMiddleware($job, LoggableExceptionAttempts::class));
    }
}
