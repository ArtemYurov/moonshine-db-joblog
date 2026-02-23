<?php

namespace ArtemYurov\JobLog\Traits;

use ArtemYurov\JobLog\Logger\JobLogger;
use ArtemYurov\JobLog\Middleware\LoggableExceptionAttempts;

/**
 * Trait for adding logging to queue jobs
 *
 * Usage:
 *   class MyJob implements ShouldQueue
 *   {
 *       use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
 *       use Loggable;
 *   }
 */
trait Loggable
{
    protected ?JobLogger $jobLogger = null;

    private bool $autoStepProgress = true;

    /** @return array<string, string> Step definitions: key => name */
    protected function steps(): array
    {
        return [];
    }

    /** Disable automatic progress recalculation on step transitions */
    protected function disableAutoStepProgress(): void
    {
        $this->autoStepProgress = false;
    }

    public function middleware(): array
    {
        return [(new LoggableExceptionAttempts())];
    }

    public function log(): JobLogger
    {
        if (!$this->jobLogger) {
            $this->jobLogger = new JobLogger($this->job->uuid(), $this->steps(), $this->autoStepProgress);
        }

        return $this->jobLogger;
    }
}
