<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 8 — Supabase Realtime Delivery Updates
     */
    public function up(): void
    {
        // Execute Postgres / Supabase specific realtime commands if running on PostgreSQL / Supabase
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            try {
                DB::statement('ALTER TABLE deliveries REPLICA IDENTITY FULL;');
                DB::statement('ALTER TABLE driver_profiles REPLICA IDENTITY FULL;');
                DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE deliveries, driver_profiles;');
            } catch (\Throwable $e) {
                // Ignore if publication already contains tables or if permissions restricted
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            try {
                DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE deliveries, driver_profiles;');
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }
};
