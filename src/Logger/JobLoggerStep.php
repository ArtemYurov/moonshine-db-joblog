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
        protected JobLog  $jobLog,
        protected string  $stepKey,
        protected ?string $stepName,
    ) {
        $this->initStep();

        $databaseHandler = new DatabaseHandler(Level::Debug, true);
        $databaseHandler->setJobLog($this->jobLog);
        $databaseHandler->setStepId($this->stepModel->id);

        $monolog = new Logger("joblog-step-{$this->jobLog->getKey()}-{$this->stepKey}", [$databaseHandler]);
        $this->psrLogger = new PsrLogger($monolog, "[STEP: {$this->stepKey}]", $this->isConsoleOutputEnabled());
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
}
