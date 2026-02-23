<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

class FakeJobWithScalarArgs
{
    public function __construct(
        public string $name,
        public int $count,
    ) {}
}
