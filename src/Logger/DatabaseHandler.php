<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Database\JobLogModelResolver;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class DatabaseHandler extends AbstractProcessingHandler
{
    private JobLogContract $jobLog;
    private mixed $stepId = null;

    public function setJobLog(JobLogContract $jobLog): void
    {
        $this->jobLog = $jobLog;
    }

    public function getJobLog(): JobLogContract
    {
        return $this->jobLog;
    }

    public function setStepId(mixed $stepId): void
    {
        $this->stepId = $stepId;
    }

    public function getStepId(): mixed
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

        JobLogModelResolver::record()::create([
            'job_log_id' => $this->jobLog->getKey(),
            'job_log_step_id' => $this->stepId,
            'level' => strtolower($record->level->name),
            'message' => $record->message,
            'context' => !empty($context) ? $context : null,
            'trace' => $trace,
            'created_at' => $record->datetime,
        ]);
    }
}
