<?php

use ArtemYurov\JobLog\Database\JobLogStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return JobLogStorage::connectionName();
    }

    public function up(): void
    {
        // The foreign key has no index of its own: step records and the cascade from a deleted step scan the table without it.
        Schema::table('job_log_records', function (Blueprint $table) {
            $table->index(['job_log_step_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('job_log_records', function (Blueprint $table) {
            $table->dropIndex(['job_log_step_id', 'created_at']);
        });
    }
};
