<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogStep;
use ArtemYurov\JobLog\Traits\JobLoggerMethods;
use Carbon\Carbon;
use Monolog\Level;
use Monolog\Logger;

class JobLoggerStep
{
    use JobLoggerMethods;

    protected ?JobLogStep $stepModel = null;

    public function __construct(
        protected JobLog   $jobLog,
        protected string   $stepKey,
        protected string   $stepName)
    {
        $databaseHandler = new DatabaseHandler(Level::Debug, true);
        $databaseHandler->setJobLog($this->jobLog);
        $databaseHandler->setStep($this->stepKey);

        $this->monolog = new Logger("joblog-step-{$this->jobLog->getKey()}-{$this->stepKey}", [$databaseHandler]);

        $this->initStep();
    }

    public function customStatus(string $customStatus, ?string $errorMessage = null): self
    {
        $this->getModel()->update(['custom_status' => $customStatus]);

        if ($errorMessage) {
            $this->error($errorMessage);
        }

        return $this;
    }

    public function getStatus(): JobLogStatus
    {
        return $this->stepModel->status;
    }

    protected function initStep(): void
    {
        $this->stepModel = JobLogStep::firstOrCreate([
            'job_log_id' => $this->jobLog->id,
            'step_key' => $this->stepKey,
        ], [
            'step_name' => $this->stepName,
            'status' => JobLogStatus::PROCESSING,
            'progress' => 0,
            'started_at' => Carbon::now(),
        ]);
    }

    protected function getModel()
    {
        return $this->stepModel;
    }

    protected function consoleLog(string $level, string $message, array $context, string $colorStart, string $colorEnd): void
    {
        if (!config('joblog.console_output', true)) {
            return;
        }

        if (app()->runningInConsole()) {
            echo "\033[1m[STEP: {$this->stepKey}]\033[0m {$colorStart}[{$level}]{$colorEnd} {$message}" . PHP_EOL;

            if (!empty($context)) {
                $this->dumpWithoutTrace($context);
            }
        }
    }
}
