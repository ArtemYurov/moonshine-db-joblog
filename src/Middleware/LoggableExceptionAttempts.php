<?php

namespace ArtemYurov\JobLog\Middleware;

use Closure;

class LoggableExceptionAttempts
{
    public function handle(object $job, Closure $next): void
    {
        $haveJobLogger = method_exists($job, 'log');

        try {
            if ($haveJobLogger) {
                $job->log()->info("Attemt: {$job->attempts()}");
            }
            $next($job);
        } catch (\Throwable $e) {
            if ($haveJobLogger) {
                $job->log()->exception($e);
            }
            throw $e;
        }
    }
}
