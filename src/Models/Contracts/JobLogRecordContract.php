<?php

namespace ArtemYurov\JobLog\Models\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Contract shared by the SQL and MongoDB JobLogRecord models.
 *
 * @property mixed $id
 * @property mixed $job_log_id
 * @property mixed $job_log_step_id
 * @property string|null $level
 * @property string|null $message
 * @property Collection|null $context
 * @property string|null $trace
 * @property Carbon|null $created_at
 */
interface JobLogRecordContract
{
    /** Primary key of the record row (int for SQL, ObjectId string for MongoDB). */
    public function getKey();
}
