<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Database\JobLogStores;
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

        JobLogStores::resolve()->createRecord(
            $this->jobLog,
            $this->stepId,
            strtolower($record->level->name),
            $record->message,
            !empty($context) ? $context : null,
            $trace,
            $record->datetime,
        );
    }
}
