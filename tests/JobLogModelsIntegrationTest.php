<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogRecord;
use ArtemYurov\JobLog\Models\JobLogStep;
use ArtemYurov\JobLog\Tests\Support\ArrayConfigRepository;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DbCapsule;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * SQL model queries on a real in-memory SQLite built by the package migrations.
 */
class JobLogModelsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Provide app()/config() only inside this isolated process.
        require_once __DIR__ . '/Support/integration_helpers.php';

        $container = new Container;
        Container::setInstance($container);
        Facade::setFacadeApplication($container);
        Facade::clearResolvedInstances();
        $container->instance('config', new ArrayConfigRepository(['joblog' => []]));

        $capsule = new DbCapsule($container);
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            // SQLite enforces the cascades only with foreign keys switched on.
            'foreign_key_constraints' => true,
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $container->instance('db', $capsule->getDatabaseManager());
        $container->bind('db.schema', fn ($app) => $app['db']->connection()->getSchemaBuilder());

        foreach ([
            '2025_01_01_000001_create_job_logs_table.php',
            '2025_01_01_000002_create_job_log_steps_table.php',
            '2025_01_01_000003_create_job_log_records_table.php',
            '2025_01_01_000004_add_custom_status_to_job_logs_table.php',
        ] as $file) {
            (require __DIR__ . '/../database/migrations/sql/' . $file)->up();
        }
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_with_all_tags_needs_every_tag(): void
    {
        $this->log('both', ['payments:1', 'shop:2']);
        $this->log('partial', ['payments:1']);

        $this->assertSame(['both'], JobLog::withAllTags(['payments:1', 'shop:2'])->pluck('job_uuid')->all());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_delete_queued_before_takes_steps_and_records_along(): void
    {
        $old = $this->log('old', queuedAt: Carbon::now()->subDays(40));
        $fresh = $this->log('fresh');

        foreach ([$old, $fresh] as $log) {
            $step = JobLogStep::create(['job_log_id' => $log->id, 'step_key' => 'import']);
            $this->record($log, 'info', 'step record', $step->id);
            $this->record($log, 'info', 'log record');
        }

        $this->assertSame(1, JobLog::deleteQueuedBefore(Carbon::now()->subDays(30)));
        $this->assertSame(['fresh'], JobLog::pluck('job_uuid')->all());
        $this->assertEquals([$fresh->id], JobLogStep::pluck('job_log_id')->all());
        $this->assertEquals([$fresh->id, $fresh->id], JobLogRecord::pluck('job_log_id')->all());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_latest_error_record_skips_later_records_and_step_errors(): void
    {
        $log = $this->log('me');
        $step = JobLogStep::create(['job_log_id' => $log->id, 'step_key' => 'import']);

        $this->record($log, 'error', 'log error');
        $this->record($log, 'info', 'later info');
        $this->record($log, 'error', 'step error', $step->id);

        $this->assertSame('log error', $log->latestErrorRecord?->message, 'lazy');
        $this->assertSame('log error', JobLog::with('latestErrorRecord')->first()->latestErrorRecord?->message, 'eager');
    }

    private function log(string $uuid, ?array $tags = null, ?Carbon $queuedAt = null): JobLog
    {
        return JobLog::create([
            'job_uuid' => $uuid,
            'job_class' => 'FakeJob',
            'queued_at' => $queuedAt ?? Carbon::now(),
            'tags' => $tags,
        ]);
    }

    private function record(JobLog $log, string $level, string $message, ?int $stepId = null): void
    {
        JobLogRecord::create([
            'job_log_id' => $log->id,
            'job_log_step_id' => $stepId,
            'level' => $level,
            'message' => $message,
        ]);
    }
}
