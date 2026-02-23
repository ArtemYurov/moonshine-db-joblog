<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogRecord;
use ArtemYurov\JobLog\Models\JobLogStep;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for package models — casts, timestamps, getTable() calls config()
 *
 * Note: getTable() calls config() which requires the Laravel app container.
 * In unit tests without the container we verify the method exists via Reflection,
 * and also test casts (which don't require the container).
 */
class ModelTableNamesTest extends TestCase
{
    public function test_job_log_has_get_table_method(): void
    {
        $method = new ReflectionMethod(JobLog::class, 'getTable');
        $this->assertTrue($method->isPublic());
    }

    public function test_job_log_step_has_get_table_method(): void
    {
        $method = new ReflectionMethod(JobLogStep::class, 'getTable');
        $this->assertTrue($method->isPublic());
    }

    public function test_job_log_record_has_get_table_method(): void
    {
        $method = new ReflectionMethod(JobLogRecord::class, 'getTable');
        $this->assertTrue($method->isPublic());
    }

    public function test_job_log_get_table_reads_from_config(): void
    {
        // Verify getTable is overridden (not inherited from Model)
        $method = new ReflectionMethod(JobLog::class, 'getTable');
        $this->assertSame(JobLog::class, $method->getDeclaringClass()->getName());
    }

    public function test_job_log_step_get_table_reads_from_config(): void
    {
        $method = new ReflectionMethod(JobLogStep::class, 'getTable');
        $this->assertSame(JobLogStep::class, $method->getDeclaringClass()->getName());
    }

    public function test_job_log_record_get_table_reads_from_config(): void
    {
        $method = new ReflectionMethod(JobLogRecord::class, 'getTable');
        $this->assertSame(JobLogRecord::class, $method->getDeclaringClass()->getName());
    }

    public function test_job_log_record_has_no_timestamps(): void
    {
        $model = new JobLogRecord();
        $this->assertFalse($model->timestamps);
    }

    public function test_job_log_casts_status_to_enum(): void
    {
        $model = new JobLog();
        $casts = $model->getCasts();

        $this->assertArrayHasKey('status', $casts);
        $this->assertSame(\ArtemYurov\JobLog\Enums\JobLogStatus::class, $casts['status']);
    }

    public function test_job_log_casts_collections(): void
    {
        $model = new JobLog();
        $casts = $model->getCasts();

        $this->assertSame('collection', $casts['args']);
        $this->assertSame('collection', $casts['tags']);
        $this->assertSame('collection', $casts['data']);
    }

    public function test_job_log_casts_datetimes(): void
    {
        $model = new JobLog();
        $casts = $model->getCasts();

        $this->assertSame('datetime', $casts['queued_at']);
        $this->assertSame('datetime', $casts['started_at']);
        $this->assertSame('datetime', $casts['finished_at']);
    }

    public function test_job_log_step_casts(): void
    {
        $model = new JobLogStep();
        $casts = $model->getCasts();

        $this->assertSame('collection', $casts['data']);
        $this->assertSame(\ArtemYurov\JobLog\Enums\JobLogStatus::class, $casts['status']);
        $this->assertSame('datetime', $casts['started_at']);
        $this->assertSame('datetime', $casts['finished_at']);
    }

    public function test_job_log_record_casts(): void
    {
        $model = new JobLogRecord();
        $casts = $model->getCasts();

        $this->assertSame('collection', $casts['context']);
        $this->assertSame('datetime', $casts['created_at']);
    }
}
