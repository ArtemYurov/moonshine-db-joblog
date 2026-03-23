<?php

namespace ArtemYurov\JobLog\Tags;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionProperty;

/**
 * Standalone tag resolver (no Horizon dependency).
 *
 * Logic mirrors Horizon\Tags::for() — checks explicit tags() method first,
 * then inspects job properties via reflection for Eloquent models.
 */
class TagResolver
{
    public function resolve(object $job): array
    {
        // 1. Explicit tags() method on the job
        if (method_exists($job, 'tags')) {
            return $job->tags();
        }

        // 2. Auto-detect Eloquent models from job properties
        return $this->modelsFor($job);
    }

    /**
     * Extract Eloquent model tags from job properties via reflection.
     *
     * @return array<string>
     */
    private function modelsFor(object $job): array
    {
        $tags = [];

        $properties = (new ReflectionClass($job))->getProperties();

        foreach ($properties as $property) {
            $value = $this->getValue($property, $job);

            if ($value instanceof Model) {
                $tags[] = get_class($value) . ':' . $value->getKey();
            } elseif ($value instanceof EloquentCollection) {
                foreach ($value as $model) {
                    $tags[] = get_class($model) . ':' . $model->getKey();
                }
            }
        }

        return array_values(array_unique($tags));
    }

    private function getValue(ReflectionProperty $property, object $target): mixed
    {
        if (!$property->isInitialized($target)) {
            return null;
        }

        return $property->getValue($target);
    }
}
