<?php

namespace ArtemYurov\JobLog\Tests\Fixtures;

use ArtemYurov\JobLog\Middleware\LoggableExceptionAttempts;

class FakeJobWithMiddlewareProperty
{
    public array $middleware;

    public function __construct()
    {
        $this->middleware = [new LoggableExceptionAttempts()];
    }
}
