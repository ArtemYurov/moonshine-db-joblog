<?php

namespace ArtemYurov\JobLog;

use ArtemYurov\JobLog\Commands\JobLogCleanupCommand;
use ArtemYurov\JobLog\Commands\JobLogTruncateCommand;
use ArtemYurov\JobLog\Horizon\TagResolverInterface;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Logger\JobLogger;
use ArtemYurov\JobLog\Middleware\LoggableExceptionAttempts;
use ArtemYurov\JobLog\Horizon\HorizonTagResolver;
use ArtemYurov\JobLog\Horizon\NullTagResolver;
use ArtemYurov\JobLog\Traits\Loggable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class JobLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/joblog.php', 'joblog');

        // Register TagResolver
        $this->app->singleton(TagResolverInterface::class, function () {
            return $this->isHorizonAvailable() ? new HorizonTagResolver() : new NullTagResolver();
        });

        // Optional Horizon purge interceptor
        $this->registerHorizonPurgeInterceptor();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'joblog');

        $this->publishes([
            __DIR__ . '/../config/joblog.php' => config_path('joblog.php'),
        ], 'joblog-config');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/joblog'),
        ], 'joblog-lang');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'joblog-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                JobLogCleanupCommand::class,
                JobLogTruncateCommand::class,
            ]);
        }

        $this->registerQueueEventListeners();
        $this->registerCleanupSchedule();
    }

    /**
     * Register automatic log cleanup schedule (opt-in)
     */
    private function registerCleanupSchedule(): void
    {
        $schedule = config('joblog.cleanup.schedule', false);

        if (!$schedule) {
            return;
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $instance) use ($schedule) {
            $days = config('joblog.cleanup.days', 30);
            $time = config('joblog.cleanup.time', '03:00');

            $event = $instance->command("joblog:cleanup --days={$days}");

            match ($schedule) {
                'daily' => $event->daily()->at($time),
                'weekly' => $event->weekly()->at($time),
                'hourly' => $event->hourly(),
                default => $event->daily()->at($time),
            };
        });
    }

    /**
     * Register Horizon purge interceptor
     */
    private function registerHorizonPurgeInterceptor(): void
    {
        if (!$this->isHorizonAvailable() || !config('joblog.horizon.intercept_purge', true)) {
            return;
        }

        $this->app->singleton(\Laravel\Horizon\Contracts\JobRepository::class, function ($app) {
            return new \ArtemYurov\JobLog\Horizon\LoggableRedisJobRepository($app['redis']);
        });
    }

    /**
     * Register queue event listeners
     */
    private function registerQueueEventListeners(): void
    {
        // Add payload when dispatching job to queue
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            $jobObject = $payload['data']['command'];

            $mergePayload = [
                'isLoggableJob' => $this->isLoggableJob($jobObject),
                'hasLoggableExceptionAttempts' => $this->hasMiddleware($jobObject, LoggableExceptionAttempts::class)
            ];

            if ($mergePayload['isLoggableJob']) {
                JobLogger::init($connection, $queue, $payload, $jobObject);

                if ($connection == 'sync') {
                    $this->registerSyncJobSignalHandlers($payload['uuid']);
                }
            }

            return $mergePayload;
        });

        Event::listen(JobQueued::class, function (JobQueued $event) {
            $isLoggableJobQueued = method_exists($event, 'payload') ? $event->payload()['isLoggableJob'] ?? false : false;
            if ($isLoggableJobQueued) {
                JobLogger::changeStatusFromEvent($event->payload()['uuid'], JobLogStatus::QUEUED);
            }
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event) {
            if ($this->isEventPayloadLoggableJob($event)) {
                JobLogger::changeStatusFromEvent($event->job->uuid(), JobLogStatus::PROCESSING);
            }
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event) {
            if ($this->isEventPayloadLoggableJob($event)) {
                JobLogger::changeStatusFromEvent($event->job->uuid(), JobLogStatus::PROCESSED);
            }
        });

        Event::listen(JobFailed::class, function (JobFailed $event) {
            if ($this->isEventPayloadLoggableJob($event)) {
                $exception = !$this->isEventPayloadHasLoggableExceptionAttempts($event)
                    || ($event->exception instanceof MaxAttemptsExceededException)
                    ? $event->exception
                    : null;
                JobLogger::changeStatusFromEvent($event->job->uuid(), JobLogStatus::FAILED, $exception);
            }
        });
    }

    private function isHorizonAvailable(): bool
    {
        return class_exists(\Laravel\Horizon\Contracts\JobRepository::class);
    }

    private function isLoggableJob(object $jobObject): bool
    {
        return in_array(Loggable::class, class_uses_recursive($jobObject));
    }

    private function hasMiddleware(object $jobObject, string $needleMiddlewareClass): bool
    {
        $middleware = array_merge(
            $jobObject->middleware ?? [],
            method_exists($jobObject, 'middleware') ? $jobObject->middleware() : []
        );

        foreach ($middleware as $middlewareItem) {
            $middlewareClass = is_string($middlewareItem) ? $middlewareItem : get_class($middlewareItem);
            if ($middlewareClass === $needleMiddlewareClass) {
                return true;
            }
        }

        return false;
    }

    private function isEventPayloadLoggableJob(JobProcessing|JobProcessed|JobFailed $event): bool
    {
        return method_exists($event->job, 'payload') ? $event->job->payload()['isLoggableJob'] ?? false : false;
    }

    private function isEventPayloadHasLoggableExceptionAttempts(JobProcessing|JobProcessed|JobFailed $event): bool
    {
        return method_exists($event->job, 'payload') ? $event->job->payload()['hasLoggableExceptionAttempts'] ?? false : false;
    }

    private function getSignals(): array
    {
        if (!extension_loaded('pcntl')) {
            return [];
        }

        return [
            SIGTERM => 'SIGTERM',
            SIGINT => 'SIGINT (Ctrl+C)',
            SIGHUP => 'SIGHUP',
            SIGUSR1 => 'SIGUSR1',
            SIGUSR2 => 'SIGUSR2',
            SIGQUIT => 'SIGQUIT',
            SIGALRM => 'SIGALRM',
        ];
    }

    private function registerSyncJobSignalHandlers(string $uuid): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        $signalHandler = function ($signal) use ($uuid) {
            JobLogger::changeStatusFromEvent($uuid, JobLogStatus::INTERRUPTED);

            echo "\n" . __('joblog::joblog.signal_interrupted', ['signal' => $this->getSignalName($signal)]) . "\n";

            exit(130);
        };

        foreach (array_keys($this->getSignals()) as $signal) {
            pcntl_signal($signal, $signalHandler);
        }
    }

    private function getSignalName(int $signal): string
    {
        $signals = $this->getSignals();
        return $signals[$signal] ?? "Signal $signal";
    }
}
