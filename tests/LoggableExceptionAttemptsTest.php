<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Middleware\LoggableExceptionAttempts;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobForMiddleware;
use PHPUnit\Framework\TestCase;

class LoggableExceptionAttemptsTest extends TestCase
{
    public function test_passes_through_on_success(): void
    {
        $middleware = new LoggableExceptionAttempts();
        $job = new FakeJobForMiddleware();
        $called = false;

        $middleware->handle($job, function ($job) use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function test_rethrows_exception(): void
    {
        $middleware = new LoggableExceptionAttempts();
        $job = new FakeJobForMiddleware();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');

        $middleware->handle($job, function ($job) {
            throw new \RuntimeException('test error');
        });
    }

    public function test_works_with_job_without_log_method(): void
    {
        $middleware = new LoggableExceptionAttempts();
        $job = new \stdClass();
        $called = false;

        $middleware->handle($job, function ($job) use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }
}
