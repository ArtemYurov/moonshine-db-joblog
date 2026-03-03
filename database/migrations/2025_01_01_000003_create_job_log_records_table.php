<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_log_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_log_id')->constrained('job_logs')->cascadeOnDelete();
            $table->foreignId('job_log_step_id')->nullable()->constrained('job_log_steps')->cascadeOnDelete();

            // Log level (emergency, alert, critical, error, warning, notice, info, debug)
            $table->string('level', 20);

            $table->text('message');

            $table->json('context')->nullable();

            // Error trace (null for other levels)
            $table->longText('trace')->nullable();

            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['job_log_id', 'level', 'job_log_step_id']);
            $table->index(['job_log_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_log_records');
    }
};
