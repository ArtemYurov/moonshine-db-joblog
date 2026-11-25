<?php

use ArtemYurov\JobLog\Database\JobLogDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!JobLogDatabase::shouldRunSqlSchema()) {
            return;
        }

        Schema::table('job_logs', function (Blueprint $table) {
            $table->string('custom_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        if (!JobLogDatabase::shouldRunSqlSchema()) {
            return;
        }

        Schema::table('job_logs', function (Blueprint $table) {
            $table->dropColumn('custom_status');
        });
    }
};
