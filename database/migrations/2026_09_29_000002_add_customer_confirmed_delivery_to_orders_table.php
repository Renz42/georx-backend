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
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'customer_confirmed_delivery')) {
                    $table->boolean('customer_confirmed_delivery')->default(false)->after('status');
                }
                if (!Schema::hasColumn('orders', 'customer_confirmed_at')) {
                    $table->timestamp('customer_confirmed_at')->nullable()->after('customer_confirmed_delivery');
                }
            });
        }

        if (Schema::hasTable('deliveries')) {
            Schema::table('deliveries', function (Blueprint $table) {
                if (!Schema::hasColumn('deliveries', 'customer_confirmed_at')) {
                    $table->timestamp('customer_confirmed_at')->nullable();
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
                if (Schema::hasColumn('orders', 'customer_confirmed_at')) {
                    $table->dropColumn('customer_confirmed_at');
                }
                if (Schema::hasColumn('orders', 'customer_confirmed_delivery')) {
                    $table->dropColumn('customer_confirmed_delivery');
                }
            });
        }

        if (Schema::hasTable('deliveries')) {
            Schema::table('deliveries', function (Blueprint $table) {
                if (Schema::hasColumn('deliveries', 'customer_confirmed_at')) {
                    $table->dropColumn('customer_confirmed_at');
                }
            });
        }
    }
};
