<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Logger\JobLogger;
use ArtemYurov\JobLog\Tests\Fixtures\FakeEloquentModel;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobWithExplicitTrackable;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobWithModel;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobWithNullTrackable;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobWithScalarArgs;
use ArtemYurov\JobLog\Tests\Fixtures\FakeJobWithoutConstructor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for extractJobArgsAndRelated method via Reflection
 */
class ExtractJobArgsTest extends TestCase
{
    /**
     * Access private method via Reflection
     */
    private function callExtract(object $jobObject): array
    {
        $method = new ReflectionMethod(JobLogger::class, 'extractJobArgsAndRelated');
        $method->setAccessible(true);
        return $method->invoke(null, $jobObject);
    }

    public function test_extracts_scalar_arguments(): void
    {
        $job = new FakeJobWithScalarArgs('hello', 42);

        [$args, $related] = $this->callExtract($job);

        $this->assertSame('hello', $args['name']);
        $this->assertSame(42, $args['count']);
        $this->assertNull($related);
    }

    public function test_auto_detects_first_eloquent_model(): void
    {
        $model = new FakeEloquentModel();
        $model->id = 5;
        $job = new FakeJobWithModel($model, 'extra');

        [$args, $related] = $this->callExtract($job);

        $this->assertSame($model, $related);
        $this->assertSame(FakeEloquentModel::class, $args['branch']['class']);
        $this->assertSame(5, $args['branch']['id']);
        $this->assertSame('extra', $args['param']);
    }

    public function test_explicit_related_overrides_auto_detect(): void
    {
        $model1 = new FakeEloquentModel();
        $model1->id = 1;
        $model2 = new FakeEloquentModel();
        $model2->id = 2;

        $job = new FakeJobWithExplicitTrackable($model1, $model2);

        [$args, $related] = $this->callExtract($job);

        // Explicit related() returns $model2
        $this->assertSame($model2, $related);
    }

    public function test_related_returns_null_disables_auto_detect(): void
    {
        $model = new FakeEloquentModel();
        $model->id = 1;

        $job = new FakeJobWithNullTrackable($model);

        [$args, $related] = $this->callExtract($job);

        $this->assertNull($related);
    }

    public function test_job_without_constructor_returns_empty(): void
    {
        $job = new FakeJobWithoutConstructor();

        [$args, $related] = $this->callExtract($job);

        $this->assertEmpty($args);
        $this->assertNull($related);
    }
}
