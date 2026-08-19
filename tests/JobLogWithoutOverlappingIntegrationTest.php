<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\JobLogServiceProvider;
use ArtemYurov\JobLog\Middleware\JobLogWithoutOverlapping;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DbCapsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Connectors\SyncConnector;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Queue as BaseQueue;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end proof that overlap outcomes resolve to canonical statuses. Wires a real
 * container, DB, events and the sync queue, then runs a Loggable job while another live
 * execution holds the same tags: drop settles as PROCESSED, release goes back to QUEUED.
 */
class JobLogWithoutOverlappingIntegrationTest extends TestCase
{
    private Container $container;

    private DbCapsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        // Provide app()/config() only inside this isolated process.
        require_once __DIR__ . '/Support/integration_helpers.php';

        $this->container = new Container;
        Container::setInstance($this->container);
        $this->container->instance(\Illuminate\Contracts\Container\Container::class, $this->container);
        Facade::setFacadeApplication($this->container);
        Facade::clearResolvedInstances();

        // Minimal dot-aware config repository.
        $config = new \ArtemYurov\JobLog\Tests\Support\ArrayConfigRepository([
            'queue' => [
                'default' => 'sync',
                'connections' => ['sync' => ['driver' => 'sync']],
            ],
            'joblog' => ['console_output' => false], // keep test output clean
        ]);
        $this->container->instance('config', $config);

        // Real event dispatcher (alias + contract, both resolved downstream).
        $events = new Dispatcher($this->container);
        $this->container->instance('events', $events);
        $this->container->instance(\Illuminate\Contracts\Events\Dispatcher::class, $events);

        // Bus dispatcher — CallQueuedHandler depends on it.
        $this->container->singleton(
            \Illuminate\Contracts\Bus\Dispatcher::class,
            fn ($app) => new \Illuminate\Bus\Dispatcher(
                $app,
                fn ($connection = null) => $app->make('queue')->connection($connection)
            )
        );

        // Real in-memory SQLite via Eloquent Capsule (shares the container).
        $this->capsule = new DbCapsule($this->container);
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $this->capsule->setEventDispatcher($events);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        // Bind the manager as 'db' so the Schema facade + Eloquent use this connection.
        $this->container->instance('db', $this->capsule->getDatabaseManager());

        // Schema builder for the package migrations (they use the Schema facade).
        $this->container->bind('db.schema', fn ($app) => $app['db']->connection()->getSchemaBuilder());

        $this->runMigrations();

        // Real queue manager with the sync connector.
        $queue = new QueueManager($this->container);
        $queue->addConnector('sync', fn () => new SyncConnector);
        $this->container->instance('queue', $queue);

        // Wire the package's real queue event listeners + payload hook.
        $provider = new JobLogServiceProvider($this->container);
        $register = new \ReflectionMethod($provider, 'registerQueueEventListeners');
        $register->setAccessible(true);
        $register->invoke($provider);

        DropOnOverlapJob::$handled = false;
        ReleaseOnOverlapJob::$handled = false;
    }

    protected function tearDown(): void
    {
        // Flush base-Queue static payload callbacks (they close over this container).
        $callbacks = new \ReflectionProperty(BaseQueue::class, 'createPayloadCallbacks');
        $callbacks->setAccessible(true);
        $callbacks->setValue(null, []);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);

        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_dropped_overlap_is_finalized_as_processed(): void
    {
        // Live overlap, queued earlier → wins the tie-break. Its pid is this process.
        JobLog::create([
            'connection' => 'sync',
            'queue' => 'default',
            'job_uuid' => 'peer-uuid',
            'job_class' => 'PeerJob',
            'status' => JobLogStatus::PROCESSING,
            'queued_at' => Carbon::now()->subMinute(),
            'started_at' => Carbon::now()->subMinute(),
            'pid' => getmypid(),
            'tags' => collect(['payments:1']),
        ]);

        // Real dispatch: createPayload → JobProcessing → middleware → JobProcessed.
        $this->container->make('queue')->connection('sync')->push(new DropOnOverlapJob(), '', 'default');

        $self = JobLog::where('job_class', DropOnOverlapJob::class)->firstOrFail();

        $this->assertFalse(
            DropOnOverlapJob::$handled,
            'handle() must not run while a live overlap holds the tag'
        );
        $this->assertSame(
            JobLogStatus::PROCESSED,
            $self->status,
            'dropped overlap must be finalized as canonical PROCESSED via JobProcessed'
        );
        $this->assertSame(100, $self->progress, 'PROCESSED sets progress to 100');
        $this->assertNotNull($self->finished_at, 'PROCESSED sets finished_at');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_released_overlap_goes_back_to_queued_without_a_pid(): void
    {
        // Yielding is not completion: PROCESSED would claim work that has not happened
        // and leave a pid behind that blocks every further attempt.
        JobLog::create([
            'connection' => 'sync',
            'queue' => 'default',
            'job_uuid' => 'peer-uuid',
            'job_class' => 'PeerJob',
            'status' => JobLogStatus::PROCESSING,
            'queued_at' => Carbon::now()->subMinute(),
            'started_at' => Carbon::now()->subMinute(),
            'pid' => getmypid(),
            'tags' => collect(['payments:1']),
        ]);

        $this->container->make('queue')->connection('sync')->push(new ReleaseOnOverlapJob(), '', 'default');

        $self = JobLog::where('job_class', ReleaseOnOverlapJob::class)->firstOrFail();

        $this->assertFalse(ReleaseOnOverlapJob::$handled, 'handle() must not run while a live overlap holds the tag');
        $this->assertSame(JobLogStatus::QUEUED, $self->status, 'a released job belongs back in the queue');
        $this->assertNull($self->pid, 'no attempt is running, so no pid may remain');
        $this->assertNull($self->finished_at, 'nothing finished');
    }

    private function runMigrations(): void
    {
        $dir = __DIR__ . '/../database/migrations';

        // Core tables only; the Postgres-only GIN index migration (…000005…) is skipped.
        foreach ([
            '2025_01_01_000001_create_job_logs_table.php',
            '2025_01_01_000002_create_job_log_steps_table.php',
            '2025_01_01_000003_create_job_log_records_table.php',
            '2025_01_01_000004_add_custom_status_to_job_logs_table.php',
        ] as $file) {
            (require $dir . '/' . $file)->up();
        }
    }
}

/** Loggable job whose overlap middleware serializes (releases) instead of dropping. */
class ReleaseOnOverlapJob
{
    use InteractsWithQueue;
    use Loggable;

    public static bool $handled = false;

    public array $middleware = [];

    public function __construct()
    {
        $this->initializeLoggable();
        array_unshift($this->middleware, new JobLogWithoutOverlapping(10));
    }

    /** @return array<string> */
    public function tags(): array
    {
        return ['payments:1'];
    }

    public function handle(): void
    {
        self::$handled = true;
    }
}

/** Loggable job whose first middleware is JobLogWithoutOverlapping in dontRelease mode. */
class DropOnOverlapJob
{
    use InteractsWithQueue;
    use Loggable;

    public static bool $handled = false;

    public array $middleware = [];

    public function __construct()
    {
        $this->initializeLoggable();
        // Drop middleware runs first so an overlap short-circuits the pipeline.
        array_unshift($this->middleware, (new JobLogWithoutOverlapping())->dontRelease());
    }

    /** @return array<string> */
    public function tags(): array
    {
        return ['payments:1'];
    }

    public function handle(): void
    {
        self::$handled = true;
    }
}
