<?php

namespace ArtemYurov\JobLog\Models\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Contract shared by the SQL and MongoDB JobLogStep models.
 *
 * @property mixed $id
 * @property mixed $job_log_id
 * @property string|null $step_key
 * @property string|null $step_name
 * @property \ArtemYurov\JobLog\Enums\JobLogStatus|null $status
 * @property string|null $custom_status
 * @property int|null $progress
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $runtime_seconds
 * @property Collection|null $data
 */
interface JobLogStepContract
{
    /** Primary key of the step row (int for SQL, ObjectId string for MongoDB). */
    public function getKey();

    /** Latest error-level record attached to this step. */
    public function getLastErrorRecord(): ?JobLogRecordContract;

    /** All error-level records of this step, newest first. */
    public function getErrorRecords(): iterable;
}
