<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

use ArtemYurov\JobLog\Traits\Loggable;

class FakeLoggableJob
{
    use Loggable;

    public array $middleware = [];

    public function __construct()
    {
        $this->initializeLoggable();
    }
}
