<?php

namespace ArtemYurov\JobLog\Models;

use ArtemYurov\JobLog\Database\JobLogStorage;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\Concerns\HasJobLogBehavior;
use ArtemYurov\JobLog\Models\Contracts\JobLogContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JobLog extends Model implements JobLogContract
{
    use HasJobLogBehavior;

    protected $table = 'job_logs';

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
        return $this->hasOne(JobLogStep::class)->latestOfMany();
    }

    public function latestErrorRecord(): HasOne
    {
        // The filter belongs inside ofMany(): outside it, it would test only the newest record of any level.
        return $this->hasOne(JobLogRecord::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereNull('job_log_step_id')->where('level', 'error'),
        );
    }

    /** Rows carrying every given tag. */
    public function scopeWithAllTags(Builder $query, array $tags): void
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
    }

    /** Deletes logs queued before the cutoff; steps and records go with them by the foreign key cascade. */
    public static function deleteQueuedBefore(Carbon $cutoff): int
    {
        return static::where('queued_at', '<', $cutoff)->delete();
    }

    /** Distinct non-null values of a column. */
    public static function distinctValues(string $column): array
    {
        return static::whereNotNull($column)->distinct()->pluck($column)->all();
    }
}
