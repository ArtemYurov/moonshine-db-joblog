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
        Schema::table('job_logs', function (Blueprint $table) {
            $table->string('custom_status')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('job_logs', function (Blueprint $table) {
            $table->dropColumn('custom_status');
        });
    }
};
