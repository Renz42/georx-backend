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

    public int $tries = 3;
    public array $backoff = [10, 30, 90];

    /**
     * Create a new job instance.
     */
    public function __construct(int $pharmacyId, int $medicineId, array $alertIds)
    {
        $this->pharmacyId = $pharmacyId;
        $this->medicineId = $medicineId;
        $this->alertIds = $alertIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->alertIds)) return;

        // 1. Atomically mark alerts as inactive inside transaction
        $alertsToProcess = [];
        DB::transaction(function () use (&$alertsToProcess) {
            // Lock alerts in chunk & deactivate
            $alertsToProcess = StockAlert::whereIn('id', $this->alertIds)
                ->where('is_active', true)
                ->with(['pharmacy', 'medicine'])
                ->get();

            if ($alertsToProcess->isNotEmpty()) {
                StockAlert::whereIn('id', $alertsToProcess->pluck('id'))->update([
                    'is_active' => false,
                    'notified_at' => now(),
                ]);
            }
        });

        if (empty($alertsToProcess) || $alertsToProcess->isEmpty()) {
            return;
        }

        // 2. Fetch live pivot info
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
        if ($price) $details[] = 'Price: ₱' . number_format($price, 2);
        if ($stock) $details[] = 'Stock: ' . $stock;

        $msg = "{$medicine->generic_name} has been restocked at {$pharmacy->name}.";
        if (!empty($details)) {
            $msg .= ' (' . implode(', ', $details) . ')';
        }

        $now = now();
        $notifications = [];

        // 3. Prepare bulk notification insert payload
        foreach ($alertsToProcess as $alert) {
            $notifications[] = [
                'id' => (string) Str::uuid(),
                'type' => 'App\Notifications\MedicineRestocked',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $alert->user_id,
                'data' => json_encode([
                    'type' => 'stock_alert',
                    'title' => 'Medicine Restocked',
                    'message' => $msg,
                    'pharmacy_id' => $pharmacy->id,
                    'pharmacy_name' => $pharmacy->name,
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medName,
                    'selling_price' => $price,
                    'quantity_on_hand' => $stock,
                    'url' => '/pharmacy/' . $pharmacy->id . '/medicine/' . $medicine->id,
                    'icon' => 'fas fa-box-open text-blue-500'
                ]),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 4. Multi-row SQL bulk insert for high performance
        if (!empty($notifications)) {
            DB::table('notifications')->insert($notifications);
        }

        // 5. Send Mobile Push Notifications to registered device tokens
        $userIds = $alertsToProcess->pluck('user_id')->unique()->toArray();
        $deviceTokens = \App\Models\UserDeviceToken::whereIn('user_id', $userIds)
            ->pluck('device_token')
            ->toArray();

        if (!empty($deviceTokens)) {
            $pushPayloads = [];
            foreach ($deviceTokens as $token) {
                $pushPayloads[] = [
                    'to' => $token,
                    'sound' => 'default',
                    'title' => 'Medicine Restocked',
                    'body' => $msg,
                    'data' => [
                        'type' => 'stock_alert',
                        'pharmacy_id' => $pharmacy->id,
                        'medicine_id' => $medicine->id,
                    ],
                ];
            }

            try {
                \Illuminate\Support\Facades\Http::post('https://exp.host/--/api/v2/push/send', $pushPayloads);
            } catch (\Exception $e) {
                \Log::warning("Expo Push Notification dispatch warning: " . $e->getMessage());
            }
        }
    }
}
