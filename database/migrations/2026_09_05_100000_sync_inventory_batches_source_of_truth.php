<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ensure inventory_batches has status column if not present
        if (Schema::hasTable('inventory_batches') && !Schema::hasColumn('inventory_batches', 'status')) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->string('status')->default('active')->after('quantity');
            });
        }

        // 2. Backfill inventory_batches from existing pharmacy_medicine pivot records
        $pivotRecords = DB::table('pharmacy_medicine')->get();

        foreach ($pivotRecords as $pivot) {
            $hasBatches = DB::table('inventory_batches')
                ->where('pharmacy_id', $pivot->pharmacy_id)
                ->where('medicine_id', $pivot->medicine_id)
                ->exists();

            if (!$hasBatches && $pivot->quantity_on_hand > 0) {
                DB::table('inventory_batches')->insert([
                    'pharmacy_id' => $pivot->pharmacy_id,
                    'medicine_id' => $pivot->medicine_id,
                    'batch_number' => $pivot->batch_number ?? ('INIT-' . strtoupper(Str::random(6))),
                    'expiration_date' => $pivot->expiration_date,
                    'quantity' => $pivot->quantity_on_hand,
                    'status' => 'active',
                    'purchase_price' => $pivot->purchase_price,
                    'storage_condition' => $pivot->storage_condition,
                    'supplier' => $pivot->supplier,
                    'received_at' => $pivot->last_restocked_at ?? now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_batches') && Schema::hasColumn('inventory_batches', 'status')) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
