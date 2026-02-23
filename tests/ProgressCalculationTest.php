<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Logger\JobLogger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for calculateProgressPercent via Reflection
 * (private method from JobLoggerMethods trait, used in JobLogger)
 */
class ProgressCalculationTest extends TestCase
{
    private function callCalculate(int $current, int $total): int
    {
        $method = new ReflectionMethod(JobLogger::class, 'calculateProgressPercent');
        $method->setAccessible(true);

        // Create mock without calling constructor (constructor requires UUID and DB)
        $reflection = new \ReflectionClass(JobLogger::class);
        $instance = $reflection->newInstanceWithoutConstructor();

        return $method->invoke($instance, $current, $total);
    }

    public function test_zero_of_zero_returns_100(): void
    {
        $this->assertSame(100, $this->callCalculate(0, 0));
    }

    public function test_zero_of_ten_returns_0(): void
    {
        $this->assertSame(0, $this->callCalculate(0, 10));
    }

    public function test_five_of_ten_returns_50(): void
    {
        $this->assertSame(50, $this->callCalculate(5, 10));
    }

    public function test_ten_of_ten_returns_100(): void
    {
        $this->assertSame(100, $this->callCalculate(10, 10));
    }

    public function test_one_of_three_rounds_to_33(): void
    {
        $this->assertSame(33, $this->callCalculate(1, 3));
    }

    public function test_two_of_three_rounds_to_67(): void
    {
        $this->assertSame(67, $this->callCalculate(2, 3));
    }

    public function test_never_exceeds_100(): void
    {
        // If current > total, should not exceed 100
        $this->assertSame(100, $this->callCalculate(15, 10));
    }

    public function test_one_of_hundred_returns_1(): void
    {
        $this->assertSame(1, $this->callCalculate(1, 100));
    }

    public function test_99_of_100_returns_99(): void
    {
        $this->assertSame(99, $this->callCalculate(99, 100));
    }
}
