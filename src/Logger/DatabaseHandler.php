<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Models\JobLogRecord;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseHandler extends AbstractProcessingHandler
{
    private JobLog $jobLog;
    private ?int $stepId = null;

    public function setJobLog(JobLog $jobLog): void
    {
        $this->jobLog = $jobLog;
    }

    public function getJobLog(): JobLog
    {
        return $this->jobLog;
    }

    public function setStepId(?int $stepId): void
    {
        $this->stepId = $stepId;
    }

    public function getStepId(): ?int
    {
        return $this->stepId;
    }

    protected function write(LogRecord $record): void
    {
        $context = $record->context;
        $trace = null;

        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            unset($context['exception']);

            $exceptionClass = get_class($exception);
            $exceptionCode = $exception->getCode();
            $file = $exception->getFile();
            $line = $exception->getLine();
            $trace = $exception->getFile() . ':' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString();

            $context['exception_class'] = $exceptionClass;
            $context['exception_code'] = $exceptionCode;
            $context['file'] = $file;
            $context['line'] = $line;
        }

        JobLogRecord::create([
            'job_log_id' => $this->jobLog->id,
            'job_log_step_id' => $this->stepId,
            'level' => strtolower($record->level->name),
            'message' => $record->message,
            'context' => !empty($context) ? $context : null,
            'trace' => $trace,
            'created_at' => $record->datetime,
        ]);
    }
}
