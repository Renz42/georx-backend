<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Record actual external courier booking fee (Grab / Maxim) distinct from GEORX customer delivery estimate.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders') && !Schema::hasColumn('orders', 'actual_courier_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('actual_courier_fee', 10, 2)->nullable()->after('delivery_fee');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'actual_courier_fee')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('actual_courier_fee');
            });
        }
    }
};
