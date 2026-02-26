<?php

namespace ArtemYurov\JobLog\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for default config values (without Laravel app — reads file directly)
 */
class ConfigDefaultsTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require __DIR__ . '/../config/joblog.php';
    }

    public function test_config_returns_array(): void
    {
        $this->assertIsArray($this->config);
    }

    public function test_no_tables_section(): void
    {
        $this->assertArrayNotHasKey('tables', $this->config);
    }

    public function test_cleanup_default_days(): void
    {
        $this->assertArrayHasKey('cleanup', $this->config);
        $this->assertSame(30, $this->config['cleanup']['days']);
    }

    public function test_cleanup_schedule_disabled_by_default(): void
    {
        $this->assertFalse($this->config['cleanup']['schedule']);
    }

    public function test_cleanup_default_time(): void
    {
        $this->assertSame('03:00', $this->config['cleanup']['time']);
    }

    public function test_console_output_enabled_by_default(): void
    {
        $this->assertArrayHasKey('console_output', $this->config);
        $this->assertTrue($this->config['console_output']);
    }

    public function test_horizon_auto_by_default(): void
    {
        $this->assertArrayHasKey('horizon', $this->config);
        $this->assertSame('auto', $this->config['horizon']['enabled']);
        $this->assertTrue($this->config['horizon']['intercept_purge']);
    }

    public function test_job_class_scan_paths_empty_by_default(): void
    {
        $this->assertArrayHasKey('job_class_scan_paths', $this->config);
        $this->assertIsArray($this->config['job_class_scan_paths']);
        $this->assertEmpty($this->config['job_class_scan_paths']);
    }
}
