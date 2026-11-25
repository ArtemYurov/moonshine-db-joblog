<?php

namespace ArtemYurov\JobLog\Models\Concerns;

use ArtemYurov\JobLog\Models\Contracts\JobLogRecordContract;

/**
 * Behaviour shared by the SQL and MongoDB JobLogStep models.
 *
 * Requires the using class to define the `records()` relation.
 */
trait HasJobLogStepBehavior
{
    public function getLastErrorRecord(): ?JobLogRecordContract
    {
        return $this->records()
            ->where('level', 'error')
            ->orderByDesc('created_at')
            ->first();
    }

    public function getErrorRecords(): iterable
    {
        return $this->records()
            ->where('level', 'error')
            ->orderByDesc('created_at')
            ->get();
    }
}
