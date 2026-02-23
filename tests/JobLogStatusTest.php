<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use PHPUnit\Framework\TestCase;

class JobLogStatusTest extends TestCase
{
    public function test_enum_has_all_expected_cases(): void
    {
        $cases = JobLogStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertSame('queued', JobLogStatus::QUEUED->value);
        $this->assertSame('processing', JobLogStatus::PROCESSING->value);
        $this->assertSame('processed', JobLogStatus::PROCESSED->value);
        $this->assertSame('failed', JobLogStatus::FAILED->value);
        $this->assertSame('interrupted', JobLogStatus::INTERRUPTED->value);
    }

    public function test_to_string_method_exists(): void
    {
        // toString() calls __() which requires Laravel translator
        // Verify the method exists via Reflection
        $method = new \ReflectionMethod(JobLogStatus::class, 'toString');
        $this->assertTrue($method->isPublic());
        $this->assertSame('string', (string) $method->getReturnType());
    }

    public function test_enum_can_be_created_from_value(): void
    {
        $this->assertSame(JobLogStatus::QUEUED, JobLogStatus::from('queued'));
        $this->assertSame(JobLogStatus::FAILED, JobLogStatus::from('failed'));
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(JobLogStatus::tryFrom('invalid'));
    }
}
