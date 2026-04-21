<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE job_logs ALTER COLUMN data TYPE jsonb USING data::jsonb');
            DB::statement('ALTER TABLE job_logs ALTER COLUMN args TYPE jsonb USING args::jsonb');
            DB::statement('CREATE INDEX job_logs_data_gin ON job_logs USING GIN (data jsonb_path_ops)');
            DB::statement('CREATE INDEX job_logs_args_gin ON job_logs USING GIN (args jsonb_path_ops)');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS job_logs_data_gin');
            DB::statement('DROP INDEX IF EXISTS job_logs_args_gin');
            DB::statement('ALTER TABLE job_logs ALTER COLUMN data TYPE json USING data::text::json');
            DB::statement('ALTER TABLE job_logs ALTER COLUMN args TYPE json USING args::text::json');
        }
    }
};
