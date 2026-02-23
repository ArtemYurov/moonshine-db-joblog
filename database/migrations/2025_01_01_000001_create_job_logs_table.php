<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('joblog.tables.job_logs', 'job_logs');

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('connection')->default('database');
            $table->string('queue')->default('default')->nullable();
            $table->uuid('job_uuid')->nullable();
            $table->string('job_class');

            // Timestamps
            $table->timestamp('queued_at', 3)->useCurrent();
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->integer('runtime_seconds')->nullable();

            $table->tinyInteger('progress')->default(0);
            $table->string('status')->default('queued');
            $table->unsignedInteger('pid')->nullable();

            // Polymorphic relation (related model)
            $table->nullableMorphs('related');

            // Additional data
            $table->json('args')->nullable();
            $table->json('tags')->nullable();
            $table->json('data')->nullable();

            $table->timestamps(3);

            $table->index(['job_class', 'status']);
            $table->index(['queued_at', 'finished_at']);
        });
    }

    public function down(): void
    {
        $tableName = config('joblog.tables.job_logs', 'job_logs');
        Schema::dropIfExists($tableName);
    }
};
