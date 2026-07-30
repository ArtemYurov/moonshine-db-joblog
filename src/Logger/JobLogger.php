<?php

namespace ArtemYurov\JobLog\Logger;

use ArtemYurov\JobLog\Tags\TagResolver;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Traits\JobLoggerMethods;
use ArtemYurov\JobLog\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class JobLogger
{
    use JobLoggerMethods;

    protected JobLog $jobLog;

    /** @var array<JobLoggerStep> */
    protected array $stepLoggers = [];

    public function __construct(
        protected readonly string $jobUuid,
        protected readonly array  $steps = [],
        protected readonly bool   $autoStepProgress = true,
    )
    {
        $this->jobLog = JobLog::findByJobUuid($jobUuid);

        $databaseHandler = new DatabaseHandler(Level::Debug, true);
        $databaseHandler->setJobLog($this->jobLog);

        $monolog = new Logger("joblog-{$this->jobLog->getKey()}", [$databaseHandler]);
        $this->psrLogger = new PsrLogger($monolog, consoleOutput: $this->isConsoleOutputEnabled());
    }

    /**
     * Initialize log entry when job is dispatched to queue
     */
    public static function init(string $connection, ?string $queue, array $payload, object $jobObject): void
    {
        $uuid = $payload['uuid'];
        $jobClass = get_class($jobObject);

        [$jobArgs, $relatedModel] = self::extractJobArgsAndRelated($jobObject);

        $queueName = Str::replaceFirst('queues:', '', $queue);

        /** @var TagResolver $tagResolver */
        $tagResolver = app(TagResolver::class);
        $tags = $tagResolver->resolve($jobObject);

        JobLog::create([
            'connection' => $connection,
            'queue' => $queueName,
            'job_uuid' => $uuid,
            'job_class' => $jobClass,
            'status' => JobLogStatus::QUEUED,
            'queued_at' => Carbon::now(),
            'related_type' => $relatedModel ? get_class($relatedModel) : null,
            'related_id' => $relatedModel ? $relatedModel->getKey() : null,
            'args' => !empty($jobArgs) ? collect($jobArgs) : null,
            'tags' => !empty($tags) ? collect($tags) : null,
        ]);
    }

    public static function changeStatusFromEvent(string $uuid, JobLogStatus $status, ?\Throwable $e = null): void
    {
        $logger = new self($uuid);

        if (in_array($status, [JobLogStatus::FAILED, JobLogStatus::INTERRUPTED])) {
            $logger->finalizeCurrentProcessingStep($status);
        }

        $logger->updateStatus($status, $e);
    }

    public function step(string $stepKey): JobLoggerStep
    {
        if (isset($this->stepLoggers[$stepKey])) {
            return $this->stepLoggers[$stepKey];
        }

        if (!empty($this->steps) && !isset($this->steps[$stepKey])) {
            throw new \InvalidArgumentException("Step key '{$stepKey}' is not defined in the steps array. Available steps: " . implode(', ', array_keys($this->steps)));
        }

        $stepName = $this->steps[$stepKey] ?? null;

        $this->recalculateAutoStepProgress();

        $stepLogger = new JobLoggerStep($this->jobLog, $stepKey, $stepName);
        $this->stepLoggers[$stepKey] = $stepLogger;

        return $stepLogger;
    }

    public function clearStepLoggers(): void
    {
        $this->stepLoggers = [];
    }

    public function getStepLoggers(): array
    {
        return $this->stepLoggers;
    }

    public function recalculateAutoStepProgress(): void
    {
        if ($this->autoStepProgress && !empty($this->steps)) {
            $totalSteps = count($this->steps);
            $completedSteps = count($this->stepLoggers);
            $this->progress(
                $this->calculateProgressPercent($completedSteps, $totalSteps)
            );
        }
    }

    private function finalizeCurrentProcessingStep(JobLogStatus $status): void
    {
        $processingStep = $this->jobLog->steps()
            ->where('status', JobLogStatus::PROCESSING)
            ->latest()
            ->first();

        if ($processingStep) {
            $stepLogger = new JobLoggerStep($this->jobLog, $processingStep->step_key, $processingStep->step_name);
            $stepLogger->updateStatus($status);
        }
    }

    public function getStarted(): Carbon
    {
        return $this->jobLog->started_at;
    }

    protected function getModel()
    {
        return $this->jobLog;
    }

    /**
     * The JobLog entry this logger is bound to (the job's own record).
     */
    public function getJobLog(): JobLog
    {
        return $this->jobLog;
    }

    /**
     * Extract constructor arguments and first Eloquent model (related)
     *
     * If job defines related() method, it is used.
     * Otherwise — auto-detect first Eloquent argument via Reflection.
     */
    private static function extractJobArgsAndRelated(object $jobObject): array
    {
        $properties = [];
        $relatedModel = null;

        // If job explicitly defines related(), use it
        if (method_exists($jobObject, 'related')) {
            $relatedModel = $jobObject->related();
        }

        $reflection = new ReflectionClass($jobObject);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return [$properties, $relatedModel];
        }

        $constructorParameters = $constructor->getParameters();
        $useAutoDetect = !method_exists($jobObject, 'related');

        foreach ($constructorParameters as $parameter) {
            $propertyName = $parameter->getName();

            if (!empty($parameter->getAttributes(\SensitiveParameter::class))) {
                $properties[$propertyName] = '********';
                continue;
            }

            try {
                $property = $reflection->getProperty($propertyName);

                if (!$property->isInitialized($jobObject)) {
                    continue;
                }

                $value = $property->getValue($jobObject);

                if ($value instanceof \Illuminate\Database\Eloquent\Model) {
                    // Auto-detect: first Eloquent model
                    if ($useAutoDetect && !$relatedModel) {
                        $relatedModel = $value;
                    }

                    $properties[$propertyName] = [
                        'class' => get_class($value),
                        'id' => $value->getKey()
                    ];
                } else {
                    $properties[$propertyName] = $value;
                }
            } catch (\ReflectionException $e) {
                continue;
            }
        }

        return [$properties, $relatedModel];
    }
}
