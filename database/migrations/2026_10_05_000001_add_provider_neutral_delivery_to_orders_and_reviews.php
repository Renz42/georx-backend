<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add provider-neutral delivery tracking fields for Grab Express / Maxim external logistics.
     */
    public function up(): void
    {
        // 1. ORDERS TABLE — Provider-Neutral Delivery Fields
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'delivery_provider')) {
                    $table->string('delivery_provider', 30)->nullable()->after('rejection_reason');
                }
                if (!Schema::hasColumn('orders', 'delivery_booking_id')) {
                    $table->string('delivery_booking_id', 100)->nullable()->after('delivery_provider');
                }
                if (!Schema::hasColumn('orders', 'delivery_tracking_url')) {
                    $table->text('delivery_tracking_url')->nullable()->after('delivery_booking_id');
                }
                if (!Schema::hasColumn('orders', 'delivery_status')) {
                    $table->string('delivery_status', 30)->nullable()->after('delivery_tracking_url');
                }
                if (!Schema::hasColumn('orders', 'delivery_booked_at')) {
                    $table->timestamp('delivery_booked_at')->nullable()->after('delivery_status');
                }
            });
        }

        // 2. REVIEWS TABLE — Provider-Neutral Review Attribution
        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                if (!Schema::hasColumn('reviews', 'delivery_provider')) {
                    $table->string('delivery_provider', 30)->nullable()->after('driver_id');
                }
                if (!Schema::hasColumn('reviews', 'delivery_booking_id')) {
                    $table->string('delivery_booking_id', 100)->nullable()->after('delivery_provider');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $columns = ['delivery_provider', 'delivery_booking_id', 'delivery_tracking_url', 'delivery_status', 'delivery_booked_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('reviews')) {
            Schema::table('reviews', function (Blueprint $table) {
                $columns = ['delivery_provider', 'delivery_booking_id'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('reviews', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
