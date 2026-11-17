<?php

namespace App\Jobs;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\StockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendRestockChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $pharmacyId;
    public int $medicineId;
    public array $alertIds;
    public array $recipientUserIds;

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    /**
     * Create a new job instance.
     */
    public function __construct(int $pharmacyId, int $medicineId, array $alertIds = [], array $recipientUserIds = [])
    {
        $this->pharmacyId = $pharmacyId;
        $this->medicineId = $medicineId;
        $this->alertIds = $alertIds;
        $this->recipientUserIds = $recipientUserIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $this->recipientUserIds), fn (int $id) => $id > 0)));

        // Legacy compatibility path when invoked directly with StockAlert IDs:
        // update notified_at while keeping is_active = true for recurring watch cycles.
        if (empty($userIds) && !empty($this->alertIds)) {
            $alertsToProcess = StockAlert::whereIn('id', $this->alertIds)
                ->where('is_active', true)
                ->get(['id', 'user_id']);

            if ($alertsToProcess->isNotEmpty()) {
                StockAlert::whereIn('id', $alertsToProcess->pluck('id'))->update([
                    'notified_at' => now(),
                ]);
                $userIds = $alertsToProcess->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        if (empty($userIds)) {
            return;
        }

        // Fetch live pivot info
        $pivot = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $this->pharmacyId)
            ->where('medicine_id', $this->medicineId)
            ->first();

        $price = $pivot?->selling_price;
        $stock = $pivot?->quantity_on_hand;

        $pharmacy = Pharmacy::find($this->pharmacyId);
        $medicine = Medicine::find($this->medicineId);

        if (!$pharmacy || !$medicine) return;

        $medName = $medicine->brand_name ?? $medicine->generic_name;
        $details = [];
        if ($price) $details[] = 'Price: ₱' . number_format((float) $price, 2);
        if ($stock !== null) $details[] = 'Stock: ' . $stock;

        $msg = "{$medName} is now back in stock at {$pharmacy->name}.";
        if (!empty($details)) {
            $msg .= ' (' . implode(', ', $details) . ')';
        }

        $actionUrl = '/pharmacy/' . $pharmacy->id . '/medicine/' . $medicine->id;
        $now = now();
        $notifications = [];

        // Prepare bulk notification insert payload (one row per unique user)
        foreach ($userIds as $userId) {
            $notifications[] = [
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\MedicineRestocked',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $userId,
                'data' => json_encode([
                    'type' => 'stock_alert',
                    'status' => 'restocked',
                    'title' => 'Medicine Restocked',
                    'message' => $msg,
                    'pharmacy_id' => (int) $pharmacy->id,
                    'pharmacy_name' => $pharmacy->name,
                    'medicine_id' => (int) $medicine->id,
                    'medicine_name' => $medName,
                    'selling_price' => $price !== null ? (float) $price : null,
                    'quantity_on_hand' => $stock !== null ? (int) $stock : null,
                    'action_url' => $actionUrl,
                    'url' => $actionUrl,
                    'icon' => 'fas fa-box-open text-blue-500',
                ]),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($notifications)) {
            DB::table('notifications')->insert($notifications);
        }

        app(\App\Services\ExpoPushService::class)->sendToUserIds(
            $userIds,
            'Medicine Restocked',
            $msg,
            [
                'type' => 'stock_alert',
                'status' => 'restocked',
                'pharmacy_id' => (int) $pharmacy->id,
                'medicine_id' => (int) $medicine->id,
                'action_url' => $actionUrl,
                'url' => $actionUrl,
            ],
            'stock_alerts',
            'default'
        );
    }
}
