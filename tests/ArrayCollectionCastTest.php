<?php

namespace ArtemYurov\JobLog\Tests;

use ArtemYurov\JobLog\Models\Mongo\Casts\ArrayCollectionCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class ArrayCollectionCastTest extends TestCase
{
    private function model(): Model
    {
        return new class extends Model {
            protected $casts = [
                'tags' => ArrayCollectionCast::class,
                'data' => ArrayCollectionCast::class,
            ];
        };
    }

    public function test_collection_and_array_are_stored_as_arrays_under_their_key(): void
    {
        $model = $this->model();
        $model->tags = collect(['payments:1', 'shop:2']);
        $model->data = ['import' => ['rows' => 3]];

        $this->assertSame([
            'tags' => ['payments:1', 'shop:2'],
            'data' => ['import' => ['rows' => 3]],
        ], $model->getAttributes());
    }

    public function test_null_is_stored_as_null(): void
    {
        $model = $this->model();
        $model->tags = null;

        $this->assertSame(['tags' => null], $model->getAttributes());
        $this->assertNull($model->tags);
    }

    public function test_reads_back_a_collection(): void
    {
        $model = $this->model();
        $model->data = ['import' => ['rows' => 3]];

        $this->assertInstanceOf(Collection::class, $model->data);
        $this->assertSame(['import' => ['rows' => 3]], $model->data->all());
    }
}
