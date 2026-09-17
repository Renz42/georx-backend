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
        Schema::table('stock_alerts', function (Blueprint $table) {
            if (!Schema::hasIndex('stock_alerts', 'idx_stock_alerts_restock_lookup')) {
                $table->index(['pharmacy_id', 'medicine_id', 'is_active'], 'idx_stock_alerts_restock_lookup');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_alerts', function (Blueprint $table) {
            $table->dropIndex('idx_stock_alerts_restock_lookup');
        });
    }
};
