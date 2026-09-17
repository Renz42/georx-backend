<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Deduct quantity from inventory using FIFO (First-In, First-Out)
     * based on expiration date.
     * 
     * @return array The batches that were deducted from
     */
    public function deductFIFO(int $pharmacyId, int $medicineId, int $quantityToDeduct): array
    {
        if ($quantityToDeduct <= 0) return [];

        $deductions = [];
        $remainingToDeduct = $quantityToDeduct;

        DB::transaction(function () use ($pharmacyId, $medicineId, &$remainingToDeduct, &$deductions) {
            // Get all batches ordered by expiration date (earliest first)
            $batches = InventoryBatch::where('pharmacy_id', $pharmacyId)
                ->where('medicine_id', $medicineId)
                ->inStock()
                ->orderByExpiration()
                ->lockForUpdate() // Prevent concurrent modification
                ->get();

            foreach ($batches as $batch) {
                if ($remainingToDeduct <= 0) break;

                $deductAmount = min($batch->quantity, $remainingToDeduct);
                
                $batch->quantity -= $deductAmount;
                $batch->save();

                $deductions[] = [
                    'batch_id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $deductAmount,
                ];

                $remainingToDeduct -= $deductAmount;
            }

            // Sync the pivot table aggregate
            $this->syncAggregate($pharmacyId, $medicineId);
        });

        if ($remainingToDeduct > 0) {
            // Not enough stock in batches, this shouldn't happen if validation was correct,
            // but log it just in case.
            \Log::warning("FIFO Deduction: Could not fulfill entirely. Short by {$remainingToDeduct} for Pharmacy {$pharmacyId}, Med {$medicineId}");
        }

        return $deductions;
    }

    /**
     * Restore stock that was previously deducted (e.g., if order is cancelled)
     */
    public function restoreFIFO(int $pharmacyId, int $medicineId, array $deductions): void
    {
        if (empty($deductions)) return;

        DB::transaction(function () use ($pharmacyId, $medicineId, $deductions) {
            foreach ($deductions as $deduction) {
                $batch = InventoryBatch::find($deduction['batch_id']);
                if ($batch) {
                    $batch->quantity += $deduction['quantity'];
                    $batch->save();
                } else {
                    // Batch was deleted? Recreate it
                    InventoryBatch::create([
                        'pharmacy_id' => $pharmacyId,
                        'medicine_id' => $medicineId,
                        'batch_number' => $deduction['batch_number'] ?? 'RESTORED',
                        'quantity' => $deduction['quantity'],
                    ]);
                }
            }

            $this->syncAggregate($pharmacyId, $medicineId);
        });
    }

    /**
     * Recalculate total quantity for the pharmacy_medicine pivot table
     * based on all active, non-expired current batches.
     */
    public function syncAggregate(int $pharmacyId, int $medicineId): void
    {
        DB::transaction(function () use ($pharmacyId, $medicineId) {
            $currentPivot = DB::table('pharmacy_medicine')
                ->where('pharmacy_id', $pharmacyId)
                ->where('medicine_id', $medicineId)
                ->lockForUpdate()
                ->first();

            $wasAvailable = $currentPivot ? (bool) $currentPivot->is_available : false;
            $wasZeroStock = $currentPivot ? ((int)$currentPivot->quantity_on_hand === 0) : true;

            $totalQuantity = (int) InventoryBatch::where('pharmacy_id', $pharmacyId)
                ->where('medicine_id', $medicineId)
                ->active()
                ->notExpired()
                ->sum('quantity');

            $earliestActiveBatch = InventoryBatch::where('pharmacy_id', $pharmacyId)
                ->where('medicine_id', $medicineId)
                ->active()
                ->inStock()
                ->orderByExpiration()
                ->first();

            $pharmacy = Pharmacy::find($pharmacyId);
            if ($pharmacy) {
                $pharmacy->medicines()->updateExistingPivot($medicineId, [
                    'quantity_on_hand' => $totalQuantity,
                    'is_available' => $totalQuantity > 0,
                    'batch_number' => $earliestActiveBatch?->batch_number,
                    'expiration_date' => $earliestActiveBatch?->expiration_date,
                ]);

                // ✅ RESTOCK ALERT: Single source of truth for restock notification
                if ((!$wasAvailable || $wasZeroStock) && $totalQuantity > 0) {
                    if (class_exists(\App\Models\StockAlert::class)) {
                        \App\Models\StockAlert::checkAndNotify($pharmacyId, $medicineId);
                    }
                }

                // Check if stock fell below reorder level (low stock warning for pharmacy owner)
                $pivot = DB::table('pharmacy_medicine')
                    ->where('pharmacy_id', $pharmacyId)
                    ->where('medicine_id', $medicineId)
                    ->first();

                if ($pivot && $pivot->reorder_level && $totalQuantity <= $pivot->reorder_level) {
                    // Notify pharmacy owner user(s)
                    $medicine = \App\Models\Medicine::find($medicineId);
                    $owner = \App\Models\User::where('pharmacy_id', $pharmacyId)
                        ->where('role', \App\Models\User::ROLE_PHARMACY_OWNER)
                        ->first();
                    if ($owner && $medicine) {
                        $owner->notify(new \App\Notifications\LowStockTriggered(
                            $medicine->brand_name ?? $medicine->generic_name,
                            $totalQuantity,
                            $pivot->reorder_level
                        ));
                    }
                }
            }
        });
    }

    /**
     * Add a new inventory batch for a medicine and update aggregate totals
     */
    public function addBatch(int $pharmacyId, int $medicineId, array $batchData): InventoryBatch
    {
        $batch = InventoryBatch::create([
            'pharmacy_id' => $pharmacyId,
            'medicine_id' => $medicineId,
            'batch_number' => $batchData['batch_number'] ?? ('BATCH-' . strtoupper(\Illuminate\Support\Str::random(6))),
            'expiration_date' => $batchData['expiration_date'] ?? null,
            'quantity' => $batchData['quantity'] ?? 0,
            'status' => $batchData['status'] ?? 'active',
            'purchase_price' => $batchData['purchase_price'] ?? null,
            'storage_condition' => $batchData['storage_condition'] ?? null,
            'supplier' => $batchData['supplier'] ?? null,
            'received_at' => $batchData['received_at'] ?? now(),
        ]);

        $this->syncAggregate($pharmacyId, $medicineId);

        return $batch;
    }

    /**
     * Update an existing inventory batch and recalculate aggregates
     */
    public function updateBatch(int $batchId, array $batchData): InventoryBatch
    {
        $batch = InventoryBatch::findOrFail($batchId);
        $batch->update(array_filter([
            'batch_number' => $batchData['batch_number'] ?? $batch->batch_number,
            'expiration_date' => $batchData['expiration_date'] ?? $batch->expiration_date,
            'quantity' => isset($batchData['quantity']) ? (int)$batchData['quantity'] : $batch->quantity,
            'status' => $batchData['status'] ?? $batch->status,
            'purchase_price' => $batchData['purchase_price'] ?? $batch->purchase_price,
            'storage_condition' => $batchData['storage_condition'] ?? $batch->storage_condition,
            'supplier' => $batchData['supplier'] ?? $batch->supplier,
        ], fn($v) => !is_null($v)));

        $this->syncAggregate($batch->pharmacy_id, $batch->medicine_id);

        return $batch;
    }

    /**
     * Toggle status of a batch (active / quarantined / expired / depleted)
     */
    public function toggleBatchStatus(int $batchId, string $status): InventoryBatch
    {
        $batch = InventoryBatch::findOrFail($batchId);
        $batch->update(['status' => $status]);

        $this->syncAggregate($batch->pharmacy_id, $batch->medicine_id);

        return $batch;
    }

    /**
     * Fetch lightweight operational summary metrics for pharmacy admin dashboard
     */
    public function getOperationalSummary(int $pharmacyId): array
    {
        $totalActiveStock = (int) InventoryBatch::where('pharmacy_id', $pharmacyId)
            ->active()
            ->notExpired()
            ->sum('quantity');

        $lowStockCount = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $pharmacyId)
            ->whereRaw('quantity_on_hand <= reorder_level')
            ->count();

        $expiringBatchesCount = InventoryBatch::where('pharmacy_id', $pharmacyId)
            ->active()
            ->where('expiration_date', '<=', now()->addDays(90))
            ->where('expiration_date', '>=', now())
            ->count();

        $pendingOrdersCount = \App\Models\Order::where('pharmacy_id', $pharmacyId)
            ->whereIn('status', [\App\Models\Order::STATUS_PENDING, \App\Models\Order::STATUS_PENDING_CONFIRMATION])
            ->count();

        return [
            'total_active_stock' => $totalActiveStock,
            'low_stock_count' => $lowStockCount,
            'expiring_batches_count' => $expiringBatchesCount,
            'pending_orders_count' => $pendingOrdersCount,
        ];
    }
}
