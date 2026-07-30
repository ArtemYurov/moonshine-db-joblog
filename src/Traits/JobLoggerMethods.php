<?php

namespace ArtemYurov\JobLog\Traits;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Logger\PsrLogger;
use ArtemYurov\JobLog\Models\JobLogStep;
use Carbon\Carbon;
use Psr\Log\LoggerInterface;

trait JobLoggerMethods
{
    protected PsrLogger $psrLogger;

    public function customStatus(string $customStatus, ?string $errorMessage = null): self
    {
        $this->getModel()->update(['custom_status' => $customStatus]);

        if ($errorMessage) {
            $this->error($errorMessage);
        }

        return $this;
    }

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

        // Cast explicitly: diffInSeconds returns a float, and a sub-second
        // runtime would otherwise trigger an implicit float→int deprecation.
        return (int) $endTime->diffInSeconds($model->started_at);
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

        $now = Carbon::now();

        match ($status) {
            JobLogStatus::QUEUED => $updateData['queued_at'] = $now,
            JobLogStatus::PROCESSING => [
                $updateData['started_at'] = $now,
                $updateData['pid'] = getmypid(),
            ],
            JobLogStatus::PROCESSED, JobLogStatus::FAILED, JobLogStatus::INTERRUPTED => [
                $updateData['finished_at'] = $now,
                $updateData['runtime_seconds'] = $this->calculateRuntimeSeconds($now),
            ],
            default => null,
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

    protected function isConsoleOutputEnabled(): bool
    {
        return function_exists('config')
            && config('joblog.console_output', true)
            && function_exists('app')
            && app()->runningInConsole();
    }

    protected function calculateProgressPercent(int $current, int $total): int
    {
        if ($total === 0) {
            return 100;
        }

        return (int) min(100, round(($current / $total) * 100));
    }

    /**
     * Returns PSR-3 LoggerInterface for dependency injection into services.
     */
    public function getLoggerInterface(): LoggerInterface
    {
        return $this->psrLogger;
    }

    // PSR-3 methods — delegate to PsrLogger, fluent return self
    public function emergency(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->emergency($message, $context);
        return $this;
    }

    public function alert(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->alert($message, $context);
        return $this;
    }

    public function critical(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->critical($message, $context);
        return $this;
    }

    public function error(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->error($message, $context);
        return $this;
    }

    public function warning(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->warning($message, $context);
        return $this;
    }

    public function notice(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->notice($message, $context);
        return $this;
    }

    public function info(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->info($message, $context);
        return $this;
    }

    public function debug(string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->debug($message, $context);
        return $this;
    }

    public function log($level, string|\Stringable $message, array $context = []): self
    {
        $this->psrLogger->log($level, $message, $context);
        return $this;
    }

    abstract protected function getModel();
}
