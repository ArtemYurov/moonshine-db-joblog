<?php

namespace ArtemYurov\JobLog\Commands;

use ArtemYurov\JobLog\Database\JobLogStorage;
use Illuminate\Console\Command;

class JobLogInstallCommand extends Command
{
    protected $signature = 'joblog:install
        {--mongo : Store the log in MongoDB}
        {--connection=mongodb : MongoDB connection from config/database.php}
        {--force : Run migrations without confirmation}';

    protected $description = 'Create the JobLog storage, optionally switching it to MongoDB';

    public function handle(): int
    {
        $connection = $this->option('mongo') ? (string) $this->option('connection') : null;

        if ($connection !== null) {
            // Only this process sees the switch until .env is written below.
            config(['joblog.database.connection' => $connection]);
            JobLogStorage::assertSupported();

            if (!JobLogStorage::isMongo()) {
                $this->components->error(__('joblog::joblog.command.install_not_mongo', ['connection' => $connection]));

                return self::FAILURE;
            }
        }

        $this->components->info(__('joblog::joblog.command.install_storage', [
            'driver' => JobLogStorage::driver(),
            'connection' => JobLogStorage::connectionName(),
        ]));

        // Migrations run before .env changes: a connection that fails here never becomes the log storage.
        if ($this->call('migrate', ['--force' => $this->option('force')]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        if ($connection !== null) {
            $this->writeEnv('JOBLOG_DB_CONNECTION', $connection);
        }

        return self::SUCCESS;
    }

    private function writeEnv(string $key, string $value): void
    {
        $path = $this->laravel->environmentFilePath();

        if (!is_file($path)) {
            $this->components->warn(__('joblog::joblog.command.install_env_missing', ['line' => "{$key}={$value}"]));

            return;
        }

        file_put_contents($path, self::withEnvLine((string) file_get_contents($path), $key, $value));
        $this->components->info("{$key}={$value} → .env");

        if ($this->laravel->configurationIsCached()) {
            $this->components->warn(__('joblog::joblog.command.install_config_cached'));
        }
    }

    /** KEY=value: replaces an existing line, otherwise appends one. */
    public static function withEnvLine(string $content, string $key, string $value): string
    {
        $line = "{$key}={$value}";
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

        if (preg_match($pattern, $content)) {
            return preg_replace_callback($pattern, fn () => $line, $content);
        }

        return ($content === '' ? '' : rtrim($content) . PHP_EOL) . $line . PHP_EOL;
    }
}
