<?php

namespace ArtemYurov\JobLog\Models\Mongo;

use ArtemYurov\JobLog\Database\JobLogStorage;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Concerns\HasJobLogBehavior;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MongoDB\Laravel\Eloquent\Model;

/**
 * MongoDB variant of the JobLog model. Mirrors the SQL model's contract on
 * top of mongodb/laravel-mongodb: ObjectId primary key, collections instead
 * of tables, JSON-native fields stored as plain document fields.
 */
class JobLog extends Model implements JobLogContract
{
    use HasJobLogBehavior;

    protected $table = 'job_logs';

    protected $collection = 'job_logs';

    protected $guarded = [];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'status' => JobLogStatus::class,
        'args' => 'collection',
        'tags' => 'collection',
        'data' => 'collection',
        'pid' => 'integer',
    ];

    /**
     * The log runs on the connection resolved from joblog.database — the
     * application default when nothing is configured.
     */
    public function getConnectionName()
    {
        return JobLogStorage::connectionName() ?? $this->connection;
    }

    public function records(): HasMany
    {
        return $this->hasMany(JobLogRecord::class)
            ->whereNull('job_log_step_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(JobLogStep::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function latestStep(): HasOne
    {
        return $this->hasOne(JobLogStep::class)->orderByDesc('id');
    }

    public function latestErrorRecord(): HasOne
    {
        return $this->hasOne(JobLogRecord::class)
            ->whereNull('job_log_step_id')
            ->where('level', 'error')
            ->orderByDesc('id');
    }

    /** Documents carrying every given tag. */
    public function scopeWithAllTags(Builder $query, array $tags): void
    {
        $query->where('tags', 'all', $tags);
    }

    /** Deletes logs queued before the cutoff. */
    public static function deleteQueuedBefore(Carbon $cutoff): int
    {
        return static::where('queued_at', '<', $cutoff)->delete();
    }
}
