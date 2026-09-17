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
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasIndex('notifications', 'idx_notifications_user_lookup')) {
                $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_user_lookup');
            }
        });

        Schema::table('pharmacy_medicine', function (Blueprint $table) {
            if (!Schema::hasIndex('pharmacy_medicine', 'idx_pharmacy_medicine_available')) {
                $table->index(['pharmacy_id', 'medicine_id', 'is_available'], 'idx_pharmacy_medicine_available');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_lookup');
        });

        Schema::table('pharmacy_medicine', function (Blueprint $table) {
            $table->dropIndex('idx_pharmacy_medicine_available');
        });
    }
};
