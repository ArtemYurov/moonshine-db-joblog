<?php

namespace ArtemYurov\JobLog\Horizon;
use Laravel\Horizon\Tags;

/**
 * Tag resolver using Laravel Horizon
 */
class HorizonTagResolver implements TagResolverInterface
{
    public function resolve(object $job): array
    {
        return Tags::for($job);
    }
}
