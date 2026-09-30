<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('reviews') && !Schema::hasColumn('reviews', 'driver_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('driver_id')
                      ->nullable()
                      ->after('pharmacy_id')
                      ->constrained('users')
                      ->nullOnDelete();

                $table->index('driver_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'driver_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropForeign(['driver_id']);
                $table->dropIndex(['driver_id']);
                $table->dropColumn('driver_id');
            });
        }
    }
};
