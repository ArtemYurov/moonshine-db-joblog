<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class FakeJobWithNullTrackable
{
    public function __construct(
        public FakeEloquentModel $model,
    ) {}

    public function related(): ?Model
    {
        return null;
    }
}
