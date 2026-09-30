<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'order_id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE reviews ALTER COLUMN order_id DROP NOT NULL;');
            } else {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->unsignedBigInteger('order_id')->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'order_id')) {
            if (DB::getDriverName() === 'pgsql') {
                // Only restore NOT NULL if there are no existing null rows
                $nullCount = DB::table('reviews')->whereNull('order_id')->count();
                if ($nullCount === 0) {
                    DB::statement('ALTER TABLE reviews ALTER COLUMN order_id SET NOT NULL;');
                }
            } else {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->unsignedBigInteger('order_id')->nullable(false)->change();
                });
            }
        }
    }
};
