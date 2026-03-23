<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Tags\TagResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class TagResolverTest extends TestCase
{
    private TagResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TagResolver();
    }

    public function test_resolve_returns_empty_array_for_plain_object(): void
    {
        $result = $this->resolver->resolve(new \stdClass());
        $this->assertSame([], $result);
    }

    public function test_resolve_uses_explicit_tags_method(): void
    {
        $job = new class {
            public function tags(): array
            {
                return ['custom:tag', 'another:tag'];
            }
        };

        $result = $this->resolver->resolve($job);
        $this->assertSame(['custom:tag', 'another:tag'], $result);
    }

    public function test_resolve_extracts_model_tag(): void
    {
        $model = $this->createMock(Model::class);
        $model->method('getKey')->willReturn(42);

        $job = new class($model) {
            public function __construct(public Model $model) {}
        };

        $result = $this->resolver->resolve($job);
        $this->assertCount(1, $result);
        $this->assertStringContainsString(':42', $result[0]);
    }

    public function test_resolve_extracts_models_from_eloquent_collection(): void
    {
        $model1 = $this->createMock(Model::class);
        $model1->method('getKey')->willReturn(1);

        $model2 = $this->createMock(Model::class);
        $model2->method('getKey')->willReturn(2);

        $collection = new EloquentCollection([$model1, $model2]);

        $job = new class($collection) {
            public function __construct(public EloquentCollection $models) {}
        };

        $result = $this->resolver->resolve($job);
        $this->assertCount(2, $result);
    }

    public function test_resolve_deduplicates_tags(): void
    {
        $model = $this->createMock(Model::class);
        $model->method('getKey')->willReturn(1);

        $job = new class($model, $model) {
            public function __construct(public Model $a, public Model $b) {}
        };

        $result = $this->resolver->resolve($job);
        $this->assertCount(1, $result);
    }

    public function test_resolve_skips_non_model_properties(): void
    {
        $job = new class('hello', 42, true) {
            public function __construct(
                public string $name,
                public int $count,
                public bool $flag,
            ) {}
        };

        $result = $this->resolver->resolve($job);
        $this->assertSame([], $result);
    }
}
