<?php

namespace ArtemYurov\JobLog\Enums;

enum JobLogStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case PROCESSED = 'processed';
    case FAILED = 'failed';
    case INTERRUPTED = 'interrupted';

    public function toString(): string
    {
        return __('joblog::joblog.status.' . $this->value);
    }
}
