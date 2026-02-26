<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_log_steps')) {
            return;
        }

        Schema::create('job_log_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('job_log_id')->constrained('job_logs')->cascadeOnDelete();
            $table->string('step_key');
            $table->string('step_name')->nullable();

            $table->tinyInteger('progress')->default(0);
            $table->string('status')->default('processing');
            $table->string('custom_status')->nullable();

            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->integer('runtime_seconds')->nullable();

            $table->json('data')->nullable();

            $table->timestamps(3);

            $table->index(['job_log_id', 'step_key']);
            $table->index(['job_log_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_log_steps');
    }
};
