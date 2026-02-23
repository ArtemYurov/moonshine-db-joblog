<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class FakeJobWithExplicitTrackable
{
    public function __construct(
        public FakeEloquentModel $first,
        public FakeEloquentModel $second,
    ) {}

    public function related(): ?Model
    {
        return $this->second;
    }
}
