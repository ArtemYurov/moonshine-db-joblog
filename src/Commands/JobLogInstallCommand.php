<?php

namespace ArtemYurov\JobLog\Commands;

use ArtemYurov\JobLog\Database\Exceptions\DatabaseConfigurationException;
use ArtemYurov\JobLog\Database\Exceptions\MissingMongoDbPackageException;
use ArtemYurov\JobLog\Database\Exceptions\UnsupportedDatabaseDriverException;
use ArtemYurov\JobLog\Database\JobLogDatabase;
use Illuminate\Console\Command;

/**
 * Guided installation of the log storage.
 *
 * Nothing has to be configured beforehand: on a standard PostgreSQL
 * application the command just publishes the package assets and migrates.
 * When the default connection uses a driver the package does not support,
 * the user is offered to switch the log to MongoDB (the application stays
 * on its own database, no data is migrated) — or is pointed to the
 * repository issues to request support for that driver.
 */
class JobLogInstallCommand extends Command
{
    protected $signature = 'joblog:install {--migrate : Run the migrations right away} {--force : Skip confirmation prompts}';

    protected $description = 'Install the JobLog package (publish config and run migrations)';

    public function handle(): int
    {
        $driver = JobLogDatabase::effectiveDriver();

        if ($driver !== null && !in_array($driver, JobLogDatabase::SUPPORTED_DRIVERS, true)) {
            // Genuinely unsupported driver: offer the MongoDB switch, or
            // point the user to the repository issues.
            if ($this->offerMongoSwitch()) {
                $this->installOnSupportedDriver();

                return self::SUCCESS;
            }

            $this->error(UnsupportedDatabaseDriverException::unsupported($driver)->getMessage());

            return self::FAILURE;
        }

        // Driver looks supported — run the full validation to surface
        // configuration errors (mismatch, missing package, missing connection).
        try {
            JobLogDatabase::assertSupported();
        } catch (DatabaseConfigurationException $e) {
            if ($e instanceof MissingMongoDbPackageException) {
                $this->components->twoColumnDetail('JobLog', 'composer require mongodb/laravel-mongodb');
            }

            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->installOnSupportedDriver();

        return self::SUCCESS;
    }

    /**
     * Interactive switch of the log storage to MongoDB: env keys are
     * appended (with confirmation), runtime config updated so the rest of
     * this command sees the new driver immediately.
     */
    private function offerMongoSwitch(): bool
    {
        $driver = JobLogDatabase::effectiveDriver() ?? 'unknown';

        $this->warn(__('joblog::joblog.command.install_unsupported', ['driver' => $driver]));

        if (!$this->option('force') && !$this->confirm(__('joblog::joblog.command.install_switch_confirm'), true)) {
            return false;
        }

        if (!JobLogDatabase::mongodbPackageInstalled()) {
            $this->error(__('joblog::joblog.command.install_mongo_package_missing'));

            return false;
        }

        if (config('database.connections.mongodb') === null) {
            $this->error(__('joblog::joblog.command.install_mongo_connection_missing'));

            return false;
        }

        $this->appendToEnv('JOBLOG_DB_DRIVER', JobLogDatabase::DRIVER_MONGODB);
        $this->appendToEnv('JOBLOG_DB_CONNECTION', 'mongodb');

        // Make the switch visible to this very process (the env file only
        // takes effect in a fresh artisan invocation).
        config([
            'joblog.database.driver' => JobLogDatabase::DRIVER_MONGODB,
            'joblog.database.connection' => 'mongodb',
        ]);

        $this->info(__('joblog::joblog.command.install_switched'));

        return true;
    }

    private function installOnSupportedDriver(): void
    {
        $driver = JobLogDatabase::effectiveDriver();
        $connection = JobLogDatabase::connectionName();

        $this->components->twoColumnDetail(
            'JobLog storage',
            sprintf('driver "%s" on connection "%s"', $driver, $connection),
        );

        $this->call('vendor:publish', [
            '--tag' => ['joblog-config', 'joblog-migrations'],
        ]);

        if ($this->option('migrate') || (!$this->option('force') && $this->confirm(__('joblog::joblog.command.install_migrate_confirm'), true))) {
            $this->call('migrate');
        } else {
            $this->line(__('joblog::joblog.command.install_migrate_skipped'));
        }
    }

    private function appendToEnv(string $key, string $value): void
    {
        $path = $this->laravel->environmentFilePath();

        if (!is_file($path)) {
            $this->warn(__('joblog::joblog.command.install_env_missing', ['key' => $key, 'value' => $value]));

            return;
        }

        $content = (string) file_get_contents($path);

        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $content)) {
            $this->warn(__('joblog::joblog.command.install_env_exists', ['key' => $key, 'value' => $value]));

            return;
        }

        file_put_contents($path, rtrim($content) . PHP_EOL . "{$key}={$value}" . PHP_EOL, FILE_APPEND | LOCK_EX);

        $this->info("{$key}={$value} → .env");
    }
}
