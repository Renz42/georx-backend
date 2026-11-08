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

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE deliveries REPLICA IDENTITY FULL;');
        DB::statement('ALTER TABLE driver_profiles REPLICA IDENTITY FULL;');

        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime'
                          AND schemaname = current_schema()
                          AND tablename = 'deliveries'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime ADD TABLE deliveries;
                    END IF;

                    IF NOT EXISTS (
                        SELECT 1
                        FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime'
                          AND schemaname = current_schema()
                          AND tablename = 'driver_profiles'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime ADD TABLE driver_profiles;
                    END IF;
                END IF;
            END $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
                    IF EXISTS (
                        SELECT 1
                        FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime'
                          AND schemaname = current_schema()
                          AND tablename = 'deliveries'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime DROP TABLE deliveries;
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM pg_publication_tables
                        WHERE pubname = 'supabase_realtime'
                          AND schemaname = current_schema()
                          AND tablename = 'driver_profiles'
                    ) THEN
                        ALTER PUBLICATION supabase_realtime DROP TABLE driver_profiles;
                    END IF;
                END IF;
            END $$;
        ");
    }
};
