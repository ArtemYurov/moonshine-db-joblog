# MoonShine DB JobLog

[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.x|12.x-red.svg)](https://laravel.com)
[![MoonShine](https://img.shields.io/badge/MoonShine-4.x-purple.svg)](https://moonshine-laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**[Русская версия (README.ru.md)](README.ru.md)**

Job queue logging package with [MoonShine](https://moonshine-laravel.com) admin panel integration for Laravel.

Track your queue jobs in real-time: statuses, steps, progress, errors — all visible in MoonShine admin.

## Features

- Automatic tracking of queue job lifecycle (queued → processing → processed/failed)
- Log storage on PostgreSQL (zero configuration), MySQL/MariaDB, SQLite (tests, local) or MongoDB (opt-in switch, no data migration)
- Step-by-step progress with named steps
- PSR-3 compatible logging (emergency, alert, critical, error, warning, notice, info, debug)
- Polymorphic `related` relation — link any Eloquent model to a job
- Auto-detection of the first Eloquent model from job constructor arguments
- Color-coded console output during `artisan` execution
- MoonShine admin resources with filters, query tags, and detail views
- Laravel Horizon integration (tag resolution, purge interception)
- "Without overlapping" middleware — serialize job execution by tags via the JobLog table (no cache lock), opted in by an external orchestrator
- Configurable cleanup schedule and job scan paths
- i18n support (EN, RU out of the box)

## Requirements

- PHP 8.2+
- Laravel 11.x, 12.x or 13.x
- MoonShine 4.x
- PostgreSQL, MySQL/MariaDB, SQLite or MongoDB for the log

## Installation

```bash
composer require artemyurov/moonshine-db-joblog
```

The package auto-discovers the service provider. Run migrations:

```bash
php artisan migrate
```

Or use the installer: it prints the driver and connection the log will use, then runs `migrate`
(MongoDB: see [Database storage](#database-storage)):

```bash
php artisan joblog:install
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=joblog-config
```

### Localization

The package ships with EN and RU translations. Publish to customize:

```bash
php artisan vendor:publish --tag=joblog-lang
```

Files will be placed in `lang/vendor/joblog/`. Translation namespace: `joblog::joblog`.

## Database storage

The log lives on the connection named in `joblog.database.connection` (`JOBLOG_DB_CONNECTION`), or
on the application default connection (`DB_CONNECTION`) when that is not set. Its driver is always
the connection's own:

- `pgsql` — the main storage, nothing to configure; `data` and `args` get GIN indexes;
- `mysql` / `mariadb` — supported, not covered by the package tests;
- `sqlite` — tests and local development;
- `mongodb` — opt-in, needs the `mongodb/laravel-mongodb` package.

### Switching the log to MongoDB

The application keeps its own database; only the log storage moves. Existing records are not
copied.

1. Install the integration (needs the `ext-mongodb` PHP extension):

   ```bash
   composer require mongodb/laravel-mongodb
   ```

2. Declare the connection in `config/database.php`:

   ```php
   'connections' => [
       'mongodb' => [
           'driver'   => 'mongodb',
           'dsn'      => env('MONGODB_URI', 'mongodb://localhost:27017'),
           'database' => env('MONGODB_DATABASE', 'joblog'),
       ],
   ],
   ```

3. Switch the log and create the collections:

   ```bash
   php artisan joblog:install --mongo                    # connection "mongodb"
   php artisan joblog:install --mongo --connection=logs  # any other MongoDB connection
   ```

   The command checks that the connection exists, uses the `mongodb` driver and that the package is
   installed, runs `migrate`, and only once that succeeds writes `JOBLOG_DB_CONNECTION` to `.env`
   (an existing line is replaced). `--force` skips the production confirmation of `migrate`. With a
   cached configuration, run `php artisan config:cache` again afterwards.

   By hand it is the same: `JOBLOG_DB_CONNECTION=mongodb` in `.env`, then `php artisan migrate`.

   Switch while the queues of `Loggable` jobs are empty, delayed jobs included, and restart the
   workers right after (`php artisan queue:restart` or `horizon:terminate`): a queued job looks up
   its log record in the new storage, and a worker that still runs the old configuration writes to
   the old one. `migrate --pretend` is not supported for MongoDB: laravel-mongodb creates the
   collections and indexes even then.

On MongoDB the MoonShine list loads `latestStep` and `latestErrorRecord` lazily, one query per row:
eager loading a `hasOne` there reads every step and error record of the page. With
`Model::preventLazyLoading()` enabled, declare `$with` in your `JobLogResource` subclass — a `$with`
declared by a subclass is kept as is:

```php
protected array $with = ['latestStep', 'latestErrorRecord'];
```

## Quick Start

### 1. Add the Loggable trait to your job

```php
use ArtemYurov\JobLog\Traits\Loggable;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Loggable;

    public function __construct(
        public readonly Order $order
    ) {}

    public function handle(): void
    {
        $this->log()->info('Starting order processing');

        // Your job logic...

        $this->log()->info('Order processed successfully');
    }
}
```

The `Order` model will be automatically detected as the `related` model.

### 2. Define steps for complex jobs

```php
class ImportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Loggable;

    protected function steps(): array
    {
        return [
            'download'  => 'Download data',
            'validate'  => 'Validate records',
            'import'    => 'Import to database',
            'cleanup'   => 'Cleanup temp files',
        ];
    }

    public function handle(): void
    {
        $this->log()->step('download')->info('Downloading...');
        // ... download logic
        $this->log()->step('download')->processed();

        $this->log()->step('validate')->info('Validating...');
        // ... validation logic
        $this->log()->step('validate')->processed();

        $this->log()->step('import')->info('Importing...');
        foreach ($records as $i => $record) {
            // ... import logic
            $this->log()->step('import')->setProgressFromCounts($i + 1, count($records));
        }
        $this->log()->step('import')->processed();

        $this->log()->step('cleanup')->info('Cleaning up...');
        // ... cleanup logic
        $this->log()->step('cleanup')->processed();
    }
}
```

Progress is automatically calculated based on completed steps (enabled by default). To disable, call `$this->disableAutoStepProgress()` in your job.

### 3. Register MoonShine resources

```php
// In your MoonShineLayout or MoonShineServiceProvider
use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;

MenuItem::make('Job Logs', JobLogResource::class),
```

Job log resources are available to super users only: the MoonShine user must return `true` from `isSuperUser()`. The default `MoonshineUser` model has this method; a custom user model should implement it.

## Usage

### Logging methods (PSR-3)

```php
$this->log()->emergency('System is unusable');
$this->log()->alert('Action must be taken');
$this->log()->critical('Critical condition');
$this->log()->error('Error occurred', ['code' => 500]);
$this->log()->warning('Warning message');
$this->log()->notice('Normal but significant');
$this->log()->info('Informational message');
$this->log()->debug('Debug details', ['query' => $sql]);
```

### Exception logging

```php
try {
    // risky operation
} catch (\Throwable $e) {
    $this->log()->exception($e, 'Optional custom message');
    $this->log()->step('import')->failed($e);
}
```

### Progress tracking

```php
// Set exact progress (0-100)
$this->log()->progress(50);
$this->log()->step('import')->progress(75);

// Calculate from counts
$this->log()->step('import')->setProgressFromCounts($processed, $total);

// Increment
$this->log()->step('import')->incrementProgress(5);
```

### Step status management

```php
$step = $this->log()->step('validate');

$step->start();       // alias for processing()
$step->processing();  // set status to PROCESSING
$step->processed();   // set status to PROCESSED, progress to 100%
$step->failed();      // set status to FAILED

// Custom status (displayed alongside the standard status)
$step->customStatus('Waiting for API response');
$step->customStatus('Rate limited', 'API returned 429');
```

### Data storage

```php
// Store key-value data on job or step
$this->log()->addData(['total_records' => 1500]);
$this->log()->step('import')->addData(['skipped' => 3, 'errors' => 1]);

// Retrieve data
$total = $this->log()->getData('total_records');
$allData = $this->log()->step('import')->getData();
```

### Explicit related model

By default, the first Eloquent model in constructor arguments is auto-detected. Override this:

```php
class SyncJob implements ShouldQueue
{
    use Loggable;

    public function __construct(
        public readonly Branch $branch,
        public readonly array $options
    ) {}

    // Explicitly define the related model
    public function related(): Branch
    {
        return $this->branch;
    }
}
```

### Hiding sensitive arguments

Constructor arguments are automatically serialized and stored in the database. Use PHP 8.2 `#[\SensitiveParameter]` attribute to mask sensitive values:

```php
class SendPaymentJob implements ShouldQueue
{
    use Loggable;

    public function __construct(
        public readonly Order $order,
        #[\SensitiveParameter] public readonly string $apiKey,
        #[\SensitiveParameter] public readonly string $secretToken,
    ) {}
}
```

In the database and MoonShine UI, sensitive arguments will be stored as `********`.

### Preventing overlapping runs (serialize by tags)

`ArtemYurov\JobLog\Middleware\JobLogWithoutOverlapping` serializes jobs by their JobLog tags.
Busy means a **live process**, not a held timer: a peer blocks a run only while its recorded `pid`
exists. Tags come from `TagResolver` (an explicit `tags()` method, otherwise the Eloquent models on
the job's properties). The package doesn't attach it — an orchestrator
([`moonshine-command-schedule-job`](https://github.com/ArtemYurov/moonshine-command-schedule-job))
opts a job in at dispatch time, or the job declares it in its own `middleware()`.

Two overlaps are covered:

- **One message, two executions** — a driver re-issues a still-running job once its `retry_after`
  expires. Both write to the same `job_logs` row (one row per uuid), which is why the `PROCESSING`
  transition keeps the first live `pid`; the second execution sees it and yields.
- **Two messages, one resource** — different uuids, same tags. The run yields to a live peer that
  wins the `(queued_at, uuid)` tie-break.

No lock and no atomicity are needed: every execution writes its own `PROCESSING` row *before* it
queries for peers, so the two cannot miss each other.

Requires **ext-posix**, and pids are meaningful on **one host** only. Without the extension every
recorded pid counts as live.

#### Serialize vs drop

The mode is encoded through the release delay, mirroring the native
`Illuminate\Queue\Middleware\WithoutOverlapping`:

```php
new JobLogWithoutOverlapping(30);                   // serialize: wait 30s and retry
(new JobLogWithoutOverlapping())->releaseAfter(30); // same, fluent
(new JobLogWithoutOverlapping())->dontRelease();    // drop the redundant run
```

Both settle to Laravel's canonical statuses (no custom status): a released run is retried until the
peer is gone → `PROCESSED` or `FAILED`; a dropped run returns without executing → `PROCESSED`.

> **Important:** `release()` increments `attempts()`, so a serialized job **must** tolerate retries
> (`$tries > 1` or `retryUntil()`) — otherwise the first release exhausts its single attempt and it
> fails with `MaxAttemptsExceeded`.

`expireAfter()` (default 3 hours) is **not** a lock TTL: it caps how long a `PROCESSING` row may
keep blocking, so a lost bookkeeping write cannot wedge a tag forever. It is raised to the job's own
`timeout + 60s` when that would outlast it — with a warning on the job — so it never writes off a
run that is still legitimately going.

#### Upgrading to 1.3

`new JobLogWithoutOverlapping(30)`, `releaseAfter()` and `dontRelease()` are unchanged. What differs:

- **Two executions of the same message are now caught** — previously they shared one row and
  excluded each other as "self", so a re-issued job ran twice.
- **A crashed job no longer blocks its tags** until someone edits the row: its pid is gone, so the
  next attempt proceeds. Without ext-posix it blocks until `expireAfter()` passes.
- **A released job now goes back to `QUEUED` with no `pid`** instead of `PROCESSED` with
  `finished_at`. `JobReleasedAfterException` is handled too — it previously left the row stuck in
  `PROCESSING`.
- **`expireAfter()` changed meaning** (lock TTL → staleness cap) and its default rose to 3 hours.
- `hasActiveOverlap()` is gone; test doubles should stub `findActiveOverlapByTags()` instead.

## Extending the resource

Create a custom resource that extends `JobLogResource` to add domain-specific formatting:

```php
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;

class MyJobLogResource extends JobLogResource
{
    protected function formatRelated(JobLogContract $item): string
    {
        if ($item->related instanceof Branch) {
            return $item->related->city ?? "Branch #{$item->related->getKey()}";
        }

        return parent::formatRelated($item);
    }
}
```

## Configuration

```php
// config/joblog.php
return [
    // Log storage connection: null = application default (DB_CONNECTION);
    // the driver comes from that connection (any SQL driver or mongodb)
    'database' => [
        'connection' => env('JOBLOG_DB_CONNECTION'),
    ],

    // Cleanup old records
    'cleanup' => [
        'days' => (int) env('JOBLOG_CLEANUP_DAYS', 30),
        'schedule' => env('JOBLOG_CLEANUP_SCHEDULE', false), // false, 'daily', 'weekly', 'hourly'
        'time' => env('JOBLOG_CLEANUP_TIME', '03:00'),
    ],

    // Console output during artisan commands
    'console_output' => (bool) env('JOBLOG_CONSOLE_OUTPUT', true),

    // Laravel Horizon integration (detected automatically)
    'horizon' => [
        'intercept_purge' => (bool) env('JOBLOG_HORIZON_INTERCEPT_PURGE', true),
    ],

    // Paths to scan for Loggable jobs (for filter dropdown)
    'job_class_scan_paths' => [
        // Defaults to app/Jobs
    ],
];
```

## Artisan commands

```bash
# Show the log storage (driver, connection) and run migrations
php artisan joblog:install

# Switch the log to MongoDB: checks, migrate, then JOBLOG_DB_CONNECTION in .env
php artisan joblog:install --mongo [--connection=mongodb] [--force]

# Cleanup records older than N days (default: 30)
php artisan joblog:cleanup
php artisan joblog:cleanup --days=7

# Truncate all records
php artisan joblog:truncate
```

## Upgrading to 2.0

- **Signatures take the model contracts** (`ArtemYurov\JobLog\Models\Contracts\JobLogContract`)
  instead of the SQL models, so both storages fit. Update overrides and test doubles of:
  - `JobLogger::getJobLog(): JobLogContract`;
  - `DatabaseHandler::setJobLog(JobLogContract)`, `getJobLog(): JobLogContract`; the step id is `mixed`;
  - `JobLogResource::formatRelated(JobLogContract $item)`;
  - `JobLogWithoutOverlapping::findActiveOverlap()` and `findActiveOverlapByTags()` — `JobLogContract` in and out.
- **Query the log through `JobLogModelResolver`**: `JobLogModelResolver::log()` is the model class of the
  current storage. A hard-coded `ArtemYurov\JobLog\Models\JobLog` still works on SQL, but with the
  log in MongoDB it sends SQL queries to the MongoDB connection and fails. Match tags with the
  `withAllTags()` scope instead of `whereJsonContains()`:

  ```php
  use ArtemYurov\JobLog\Database\JobLogModelResolver;

  JobLogModelResolver::log()::query()->withAllTags(['payments:1'])->exists();
  JobLogModelResolver::log()::getLatestCompletedFinishedTime(SyncOrdersJob::class);
  ```
- **Pass `related_id` in the type of the model key** to `getLatestCompletedFinishedTime()` and
  `getLatestCompletedStartedTime()`: MongoDB compares types, so `'5'` does not match a stored `5`.

## License

MIT
