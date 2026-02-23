<?php

namespace ArtemYurov\JobLog\Traits;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLogStep;
use ArtemYurov\JobLog\Support\SimpleCliDumper;
use Carbon\Carbon;
use Monolog\Logger;

trait JobLoggerMethods
{
    protected Logger $monolog;

    public function start(): self
    {
        return $this->processing();
    }

    public function finish(): self
    {
        return $this->processed();
    }

    public function processing(): self
    {
        return $this->updateStatus(JobLogStatus::PROCESSING);
    }

    public function processed(): self
    {
        return $this->updateStatus(JobLogStatus::PROCESSED);
    }

    public function failed(?\Throwable $exception = null): self
    {
        return $this->updateStatus(JobLogStatus::FAILED, $exception);
    }

    public function progress(int $progress): self
    {
        $this->getModel()->update([
            'progress' => max(0, min(100, $progress)),
            'runtime_seconds' => $this->calculateRuntimeSeconds()
        ]);

        return $this;
    }

    public function getProgress(): int
    {
        return $this->getModel()->progress ?? 0;
    }

    public function getStatus(): JobLogStatus
    {
        return $this->getModel()->status;
    }

    public function setProgressFromCounts(int $processed, int $total): self
    {
        $progressPercent = $this->calculateProgressPercent($processed, $total);
        return $this->progress($progressPercent);
    }

    public function incrementProgress(int $amount = 1): self
    {
        $currentProgress = $this->getProgress();
        $newProgress = min(100, $currentProgress + $amount);
        return $this->progress($newProgress);
    }

    public function exception(\Throwable $exception, ?string $message = null): self
    {
        if (empty($message)) {
            $exceptionClass = get_class($exception);
            $exceptionCode = $exception->getCode();
            $exceptionMessage = $exception->getMessage();
            $message = $exceptionClass . PHP_EOL . ($exceptionCode ? $exceptionCode . ' ' . PHP_EOL : '') . $exceptionMessage;
        }

        $this->error($message, [
            'exception' => $exception,
        ]);

        return $this;
    }

    public function addData(array $data): self
    {
        $model = $this->getModel();
        $currentData = $model->data?->toArray() ?? [];
        $mergedData = array_merge($currentData, $data);

        $model->update([
            'data' => collect($mergedData)
        ]);

        return $this;
    }

    public function getData(?string $key = null): mixed
    {
        $model = $this->getModel();
        $data = $model->data;

        if ($key === null) {
            return $data?->toArray() ?? [];
        }

        return $data?->get($key);
    }

    public function calculateRuntimeSeconds(?Carbon $finishedAt = null): ?int
    {
        $model = $this->getModel();
        if (!$model->started_at) {
            return null;
        }

        $endTime = $finishedAt ?? Carbon::now();
        return $endTime->diffInSeconds($model->started_at);
    }

    public function updateStatus(JobLogStatus $status, ?\Throwable $exception = null): self
    {
        if ($exception) {
            $this->exception($exception);
        }

        if ($status === JobLogStatus::QUEUED && $this->isStepModel()) {
            throw new \InvalidArgumentException('QUEUED status cannot be set for step models. Steps can only be PROCESSING, PROCESSED, or FAILED.');
        }

        $updateData = ['status' => $status];

        match ($status) {
            JobLogStatus::QUEUED => $updateData['queued_at'] = Carbon::now(),
            JobLogStatus::PROCESSING => [
                $updateData['started_at'] = Carbon::now(),
                $updateData['pid'] = getmypid(),
            ],
            JobLogStatus::PROCESSED, JobLogStatus::FAILED, JobLogStatus::INTERRUPTED => [
                $updateData['finished_at'] = Carbon::now(),
                $updateData['runtime_seconds'] = $this->calculateRuntimeSeconds(),
            ],
        };

        if ($status === JobLogStatus::PROCESSED) {
            $updateData['progress'] = 100;
        }

        $this->getModel()->update($updateData);
        return $this;
    }

    protected function isStepModel(): bool
    {
        return $this->getModel() instanceof JobLogStep;
    }

    protected function calculateProgressPercent(int $current, int $total): int
    {
        if ($total === 0) {
            return 100;
        }

        return (int) min(100, round(($current / $total) * 100));
    }

    // PSR-3 LoggerInterface methods with console output
    public function emergency(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('EMERGENCY', (string)$message, $context, "\033[1;37;45m", "\033[0m");
        $this->monolog->emergency($message, $context);
        return $this;
    }

    public function alert(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('ALERT', (string)$message, $context, "\033[1;37;41m", "\033[0m");
        $this->monolog->alert($message, $context);
        return $this;
    }

    public function critical(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('CRITICAL', (string)$message, $context, "\033[1;37;41m", "\033[0m");
        $this->monolog->critical($message, $context);
        return $this;
    }

    public function error(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('ERROR', (string)$message, $context, "\033[0;37;41m", "\033[0m");
        $this->monolog->error($message, $context);
        return $this;
    }

    public function warning(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('WARNING', (string)$message, $context, "\033[0;30;43m", "\033[0m");
        $this->monolog->warning($message, $context);
        return $this;
    }

    public function notice(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('NOTICE', (string)$message, $context, "\033[0;36m", "\033[0m");
        $this->monolog->notice($message, $context);
        return $this;
    }

    public function info(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('INFO', (string)$message, $context, "\033[0;32m", "\033[0m");
        $this->monolog->info($message, $context);
        return $this;
    }

    public function debug(string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog('DEBUG', (string)$message, $context, "\033[0;33m", "\033[0m");
        $this->monolog->debug($message, $context);
        return $this;
    }

    public function log($level, string|\Stringable $message, array $context = []): self
    {
        $this->consoleLog(strtoupper((string)$level), (string)$message, $context, "\033[0;37m", "\033[0m");
        $this->monolog->log($level, $message, $context);
        return $this;
    }

    protected function consoleLog(string $level, string $message, array $context, string $colorStart, string $colorEnd): void
    {
        if (!config('joblog.console_output', true)) {
            return;
        }

        if (app()->runningInConsole()) {
            echo "{$colorStart}[{$level}]{$colorEnd} {$message}" . PHP_EOL;

            if (!empty($context)) {
                $this->dumpWithoutTrace($context);
            }
        }
    }

    protected function dumpWithoutTrace($data): void
    {
        $cloner = new \Symfony\Component\VarDumper\Cloner\VarCloner();
        $dumper = new SimpleCliDumper();

        $dumper->dump($cloner->cloneVar($data));
    }

    abstract protected function getModel();
}
