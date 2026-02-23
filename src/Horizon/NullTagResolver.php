<?php

namespace ArtemYurov\JobLog\Horizon;

/**
 * Null tag resolver (when Horizon is not available)
 */
class NullTagResolver implements TagResolverInterface
{
    public function resolve(object $job): array
    {
        return [];
    }
}
