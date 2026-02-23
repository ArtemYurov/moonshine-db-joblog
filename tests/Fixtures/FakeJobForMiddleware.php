<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

class FakeJobForMiddleware
{
    public function attempts(): int
    {
        return 1;
    }
}
