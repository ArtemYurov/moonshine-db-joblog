<?php

use ArtemYurov\JobLog\Database\JobLogStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(JobLogStorage::connectionName())->create('job_logs', function (Blueprint $collection) {
            // The unique index is what makes JobLog::firstOrCreate/findByJobUuid reliable.
            $collection->unique('job_uuid');

            $collection->index('status');
            $collection->index('queue');
            $collection->index(['job_class' => 1, 'status' => 1]);
            $collection->index('queued_at');
            $collection->index('started_at');
            $collection->index('pid');
            $collection->index(['related_type' => 1, 'related_id' => 1]);
            // Multikey index: serves the $all overlap lookups by tags.
            $collection->index('tags');
        });
    }

    public function down(): void
    {
        Schema::connection(JobLogStorage::connectionName())->drop('job_logs');
    }
};
