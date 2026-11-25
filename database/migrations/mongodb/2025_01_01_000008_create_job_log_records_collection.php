<?php

use ArtemYurov\JobLog\Database\JobLogStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(JobLogStorage::connectionName())->create('job_log_records', function (Blueprint $collection) {
            $collection->index(['job_log_id' => 1, 'level' => 1, 'job_log_step_id' => 1]);
            $collection->index(['job_log_id' => 1, 'created_at' => 1]);
            // Step records: JobLogStep::records() and the per-step last error in the step list.
            $collection->index(['job_log_step_id' => 1, 'created_at' => 1]);
        });
    }

    public function down(): void
    {
        Schema::connection(JobLogStorage::connectionName())->drop('job_log_records');
    }
};
