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
- Step-by-step progress with named steps
- PSR-3 compatible logging (emergency, alert, critical, error, warning, notice, info, debug)
- Polymorphic `related` relation — link any Eloquent model to a job
- Auto-detection of the first Eloquent model from job constructor arguments
- Color-coded console output during `artisan` execution
- MoonShine admin resources with filters, query tags, and detail views
- Laravel Horizon integration (tag resolution, purge interception)
- Configurable table names, cleanup, and scan paths
- i18n support (EN, RU out of the box)

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x
- MoonShine 4.x

## Installation

```bash
composer require artemyurov/moonshine-db-joblog
```

The package auto-discovers the service provider. Run migrations:

```bash
php artisan migrate
```

Optionally publish the config:

```bash
php artisan vendor:publish --tag=joblog-config
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

## Extending the resource

Create a custom resource that extends `JobLogResource` to add domain-specific formatting:

```php
use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;

class MyJobLogResource extends JobLogResource
{
    protected function formatRelated(JobLog $item): string
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
    // Custom table names
    'tables' => [
        'job_logs' => 'job_logs',
        'job_log_steps' => 'job_log_steps',
        'job_log_records' => 'job_log_records',
    ],

    // Cleanup old records
    'cleanup' => [
        'default_days' => 30,
    ],

    // Console output during artisan commands
    'console_output' => true,

    // Laravel Horizon integration
    'horizon' => [
        'enabled' => 'auto',       // 'auto', true, or false
        'intercept_purge' => true,  // Prevent Horizon from purging tracked jobs
    ],

    // Paths to scan for Loggable jobs (for filter dropdown)
    'job_class_scan_paths' => [
        // Defaults to app/Jobs
    ],
];
```

## Artisan commands

```bash
# Cleanup records older than N days (default: 30)
php artisan joblog:cleanup
php artisan joblog:cleanup --days=7

# Truncate all records
php artisan joblog:truncate
```

## Localization

The package ships with EN and RU translations. Publish to customize:

```bash
php artisan vendor:publish --tag=joblog-lang
```

Files will be placed in `lang/vendor/joblog/`. Translation namespace: `joblog::joblog`.

## Database schema

### job_logs

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| connection | string | Queue connection name |
| queue | string | Queue name |
| job_uuid | uuid | Laravel job UUID |
| job_class | string | Fully qualified job class name |
| related_type | string (nullable) | Polymorphic model type |
| related_id | bigint (nullable) | Polymorphic model ID |
| queued_at | timestamp(3) | When queued |
| started_at | timestamp(3) | When processing started |
| finished_at | timestamp(3) | When finished |
| runtime_seconds | integer | Total runtime in seconds |
| progress | tinyint | Progress 0–100 |
| status | string | queued / processing / processed / failed / interrupted |
| pid | integer (nullable) | Process ID |
| args | json | Serialized constructor arguments |
| tags | json | Horizon tags |
| data | json | Custom key-value data |

### job_log_steps

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| job_log_id | bigint (FK) | Parent job log |
| step_key | string | Step identifier |
| step_name | string | Human-readable step name |
| status | string | processing / processed / failed |
| custom_status | string (nullable) | User-defined status text |
| progress | tinyint | Step progress 0–100 |
| started_at | timestamp(3) | Step start time |
| finished_at | timestamp(3) | Step finish time |
| runtime_seconds | integer | Step runtime |
| data | json | Step-specific data |

### job_log_records

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| job_log_id | bigint (FK) | Parent job log |
| step_key | string (nullable) | Step key (null for job-level records) |
| level | string(20) | PSR-3 log level |
| message | text | Log message |
| context | json (nullable) | Additional context |
| trace | longtext (nullable) | Exception stack trace |
| created_at | timestamp(3) | Record timestamp |

## License

MIT
