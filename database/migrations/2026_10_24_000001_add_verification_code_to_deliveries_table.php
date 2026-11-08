<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ensures deliveries table has verification_code VARCHAR(10) NULL.
     * Fully idempotent: checks Schema::hasColumn to prevent errors on environments
     * where the column was previously introduced via operator script.
     */
    public function up(): void
    {
        if (Schema::hasTable('deliveries') && !Schema::hasColumn('deliveries', 'verification_code')) {
            Schema::table('deliveries', function (Blueprint $table) {
                $table->string('verification_code', 10)->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Non-destructive rollback policy: The 'verification_code' column on the
     * 'deliveries' table may have pre-existed this migration (e.g. introduced via
     * operational script in production) and stores active delivery security codes.
     * Dropping this column during a rollback would cause catastrophic data loss
     * and break live driver/patient workflows. Therefore, down() is intentionally
     * non-destructive and preserves the column and all associated data.
     */
    public function down(): void
    {
        // Intentionally non-destructive to prevent data loss.
    }
};
