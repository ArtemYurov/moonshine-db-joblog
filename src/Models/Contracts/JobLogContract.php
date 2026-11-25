<?php

namespace ArtemYurov\JobLog\Models\Contracts;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Contract shared by the SQL and MongoDB JobLog models. Attribute access,
 * update() and Eloquent mechanics come from the respective base classes and
 * stay compatible; the relations the package relies on are declared here.
 *
 * @property mixed $id
 * @property string|null $connection
 * @property string|null $queue
 * @property string|null $job_uuid
 * @property string|null $job_class
 * @property JobLogStatus|null $status
 * @property string|null $custom_status
 * @property int|string|null $pid
 * @property int|null $progress
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $runtime_seconds
 * @property string|null $related_type
 * @property int|string|null $related_id
 * @property Collection|null $args
 * @property Collection|null $tags
 * @property Collection|null $data
 *
 * @method static \Illuminate\Database\Eloquent\Builder withAllTags(array $tags) Rows carrying every given tag
 */
interface JobLogContract
{
    /** Primary key of the log row (int for SQL, ObjectId string for MongoDB). */
    public function getKey();

    /** Steps attached to the log. */
    public function steps();

    /** Records attached to the log directly (not through a step). */
    public function records();

    /** Does this record still credibly claim the job is active? */
    public function isActive(): bool;

    /** Is the process recorded in `pid` still running? */
    public function pidIsAlive(): bool;

    /** Latest error-level record attached directly to the log. */
    public function getLastErrorRecord(): ?JobLogRecordContract;
}
