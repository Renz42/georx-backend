<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'medicine_id',
        'is_active',
        'notified_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'notified_at' => 'datetime',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Static helper to check and notify when stock becomes available
    public static function checkAndNotify($pharmacyId, $medicineId)
    {
        $hasActiveAlerts = self::where(function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId)->orWhereNull('pharmacy_id');
            })
            ->where('medicine_id', $medicineId)
            ->where('is_active', true)
            ->exists();

        $hasFavorites = UserFavorite::where('pharmacy_id', $pharmacyId)->exists();

        if (!$hasActiveAlerts && !$hasFavorites) {
            return 0;
        }

        // Asynchronously dispatch chunked background queue job
        \App\Jobs\DispatchRestockAlertsJob::dispatch($pharmacyId, $medicineId);

        return true;
    }

    // Static helper to notify when a medicine goes out of stock
    public static function notifyOutOfStock($pharmacyId, $medicineId)
    {
        $pharmacy = Pharmacy::find($pharmacyId);
        $medicine = Medicine::find($medicineId);
        if (!$pharmacy || !$medicine) return false;

        $alertUserIds = self::where('medicine_id', $medicineId)
            ->where(function ($q) use ($pharmacyId) {
                $q->where('pharmacy_id', $pharmacyId)->orWhereNull('pharmacy_id');
            })
            ->where('is_active', true)
            ->pluck('user_id')
            ->toArray();

        $favoriteUserIds = UserFavorite::where('pharmacy_id', $pharmacyId)
            ->pluck('user_id')
            ->toArray();

        $allUserIds = array_unique(array_merge($alertUserIds, $favoriteUserIds));
        if (empty($allUserIds)) return false;

        $medName = $medicine->brand_name ?? $medicine->generic_name;
        $msg = "{$medName} is now out of stock at {$pharmacy->name}. You will be notified when it is restocked.";
        $now = now();
        $notifications = [];

        foreach ($allUserIds as $userId) {
            $notifications[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\MedicineOutOfStock',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $userId,
                'data' => json_encode([
                    'type' => 'stock_alert',
                    'status' => 'out_of_stock',
                    'title' => 'Medicine Out of Stock',
                    'message' => $msg,
                    'pharmacy_id' => $pharmacy->id,
                    'pharmacy_name' => $pharmacy->name,
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medName,
                    'url' => '/pharmacy/' . $pharmacy->id . '/medicine/' . $medicine->id,
                    'icon' => 'alert-circle-outline'
                ]),
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($notifications)) {
            \Illuminate\Support\Facades\DB::table('notifications')->insert($notifications);
        }

        $deviceTokens = \App\Models\UserDeviceToken::whereIn('user_id', $allUserIds)
            ->pluck('device_token')
            ->toArray();

        if (!empty($deviceTokens)) {
            $pushPayloads = [];
            foreach ($deviceTokens as $token) {
                $pushPayloads[] = [
                    'to' => $token,
                    'sound' => 'default',
                    'title' => 'Medicine Out of Stock',
                    'body' => $msg,
                    'data' => [
                        'type' => 'stock_alert',
                        'status' => 'out_of_stock',
                        'pharmacy_id' => $pharmacy->id,
                        'medicine_id' => $medicine->id,
                    ],
                ];
            }

            try {
                \Illuminate\Support\Facades\Http::post('https://exp.host/--/api/v2/push/send', $pushPayloads);
            } catch (\Exception $e) {
                \Log::warning("Expo Push OutOfStock dispatch warning: " . $e->getMessage());
            }
        }

        return true;
    }
}
