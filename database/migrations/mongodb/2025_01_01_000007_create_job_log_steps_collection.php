<?php

use ArtemYurov\JobLog\Database\JobLogStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection(JobLogStorage::connectionName())->create('job_log_steps', function (Blueprint $collection) {
            $collection->index(['job_log_id' => 1, 'step_key' => 1]);
            $collection->index(['job_log_id' => 1, 'created_at' => 1]);
        });
    }

    public function down(): void
    {
        Schema::connection(JobLogStorage::connectionName())->drop('job_log_steps');
    }
};
