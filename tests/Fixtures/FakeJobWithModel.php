<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

class FakeJobWithModel
{
    public function __construct(
        public FakeEloquentModel $branch,
        public string $param,
    ) {}
}
