<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Traits\Loggable;
use ArtemYurov\JobLog\Tests\Fixtures\FakeChildLoggableJob;
use ArtemYurov\JobLog\Tests\Fixtures\FakeLoggableJob;
use ArtemYurov\JobLog\Tests\Fixtures\FakeRegularJob;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Loggable trait detection via class_uses_recursive
 */
class LoggableTraitDetectionTest extends TestCase
{
    public function test_class_with_loggable_trait_is_detected(): void
    {
        $this->assertTrue(
            in_array(Loggable::class, class_uses_recursive(FakeLoggableJob::class))
        );
    }

    public function test_class_without_loggable_trait_is_not_detected(): void
    {
        $this->assertFalse(
            in_array(Loggable::class, class_uses_recursive(FakeRegularJob::class))
        );
    }

    public function test_object_with_loggable_trait_is_detected(): void
    {
        $job = new FakeLoggableJob();
        $this->assertTrue(
            in_array(Loggable::class, class_uses_recursive($job))
        );
    }

    public function test_child_class_inherits_trait_detection(): void
    {
        $this->assertTrue(
            in_array(Loggable::class, class_uses_recursive(FakeChildLoggableJob::class))
        );
    }
}
