<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class FakeEloquentModel extends Model
{
    public $id;
    protected $table = 'fake_models';

    public function getKey()
    {
        return $this->id;
    }
}
