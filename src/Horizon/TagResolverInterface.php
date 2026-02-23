<?php

namespace ArtemYurov\JobLog\Horizon;

interface TagResolverInterface
{
    /**
     * Resolve tags for a job object
     *
     * @return array<string>
     */
    public function resolve(object $job): array;
}
