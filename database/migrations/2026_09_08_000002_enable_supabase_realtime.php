<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations: Enable Supabase Realtime publication for inventory tables.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // 1. Set Replica Identity to FULL so payload contains complete OLD and NEW row data
        DB::statement("ALTER TABLE pharmacy_medicine REPLICA IDENTITY FULL;");
        DB::statement("ALTER TABLE medicines REPLICA IDENTITY FULL;");
        DB::statement("ALTER TABLE pharmacies REPLICA IDENTITY FULL;");

        // 2. Add tables to supabase_realtime publication
        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
                    ALTER PUBLICATION supabase_realtime ADD TABLE pharmacy_medicine, medicines, pharmacies;
                END IF;
            EXCEPTION
                WHEN duplicate_object THEN NULL;
            END $$;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_publication WHERE pubname = 'supabase_realtime') THEN
                    ALTER PUBLICATION supabase_realtime DROP TABLE pharmacy_medicine, medicines, pharmacies;
                END IF;
            EXCEPTION
                WHEN OTHERS THEN NULL;
            END $$;
        ");
    }
};
