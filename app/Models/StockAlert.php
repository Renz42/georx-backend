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
        $hasActiveAlerts = self::where('pharmacy_id', $pharmacyId)
            ->where('medicine_id', $medicineId)
            ->where('is_active', true)
            ->exists();

        if (!$hasActiveAlerts) {
            return 0;
        }

        // Asynchronously dispatch chunked background queue job
        \App\Jobs\DispatchRestockAlertsJob::dispatch($pharmacyId, $medicineId);

        return true;
    }
}
