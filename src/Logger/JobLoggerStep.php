<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Database\JobLogModelResolver;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use ArtemYurov\JobLog\Models\Contracts\JobLogStepContract;
use ArtemYurov\JobLog\Traits\JobLoggerMethods;
use Carbon\Carbon;
use Monolog\Level;
use Monolog\Logger;

class JobLoggerStep
{
    use JobLoggerMethods;

    protected ?JobLogStepContract $stepModel = null;

    public function __construct(
        protected JobLogContract $jobLog,
        protected string         $stepKey,
        protected ?string        $stepName,
    ) {
        $this->initStep();

        $databaseHandler = new DatabaseHandler(Level::Debug, true);
        $databaseHandler->setJobLog($this->jobLog);
        $databaseHandler->setStepId($this->stepModel->getKey());

        $monolog = new Logger("joblog-step-{$this->jobLog->getKey()}-{$this->stepKey}", [$databaseHandler]);
        $this->psrLogger = new PsrLogger($monolog, "[STEP: {$this->stepKey}]", $this->isTerminalOutputEnabled());
    }

    public function getStatus(): JobLogStatus
    {
        return $this->stepModel->status;
    }

    protected function initStep(): void
    {
        $this->stepModel = JobLogModelResolver::step()::firstOrCreate([
            'job_log_id' => $this->jobLog->getKey(),
            'step_key' => $this->stepKey,
        ], [
            'step_name' => $this->stepName,
            'status' => JobLogStatus::PROCESSING,
            'progress' => 0,
            'started_at' => Carbon::now(),
        ]);
    }

    /**
     * @return JobLogStepContract
     */
    protected function getModel()
    {
        return $this->stepModel;
    }
}
