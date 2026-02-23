<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('joblog.tables.job_log_records', 'job_log_records');
        $jobLogsTable = config('joblog.tables.job_logs', 'job_logs');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use ($jobLogsTable) {
            $table->id();

            $table->foreignId('job_log_id')->constrained($jobLogsTable)->cascadeOnDelete();
            $table->string('step_key')->nullable();

            // Log level (emergency, alert, critical, error, warning, notice, info, debug)
            $table->string('level', 20);

            $table->text('message');

            $table->json('context')->nullable();

            // Error trace (null for other levels)
            $table->longText('trace')->nullable();

            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['job_log_id', 'level', 'step_key']);
            $table->index(['job_log_id', 'created_at']);
        });
    }

    public function down(): void
    {
        $tableName = config('joblog.tables.job_log_records', 'job_log_records');
        Schema::dropIfExists($tableName);
    }
};
