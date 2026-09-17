<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'pharmacy_id',
        'driver_id',
        'rating',
        'comment',
        // Delivery ratings
        'delivery_speed',
        'driver_professionalism',
        'medicine_condition',
        'delivery_overall',
        // Pharmacy ratings
        'medicine_availability',
        'price_rating',
        'customer_service',
        'accuracy',
        'pharmacy_overall',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    /**
     * Calculate the overall computed rating (average of all categories).
     */
    public function getComputedRatingAttribute(): float
    {
        $ratings = array_filter([
            $this->delivery_overall,
            $this->pharmacy_overall,
        ]);

        if (empty($ratings)) {
            return $this->rating ?? 0;
        }

        return round(array_sum($ratings) / count($ratings), 1);
    }

    /**
     * Get aggregate ratings for a pharmacy.
     */
    public static function aggregateForPharmacy($pharmacyId): array
    {
        $reviews = static::where('pharmacy_id', $pharmacyId)->get();

        if ($reviews->isEmpty()) {
            return [
                'count' => 0,
                'overall' => 0,
                'delivery_speed' => 0,
                'driver_professionalism' => 0,
                'medicine_condition' => 0,
                'delivery_overall' => 0,
                'medicine_availability' => 0,
                'price_rating' => 0,
                'customer_service' => 0,
                'accuracy' => 0,
                'pharmacy_overall' => 0,
            ];
        }

        return [
            'count' => $reviews->count(),
            'overall' => round($reviews->avg('pharmacy_overall') ?? $reviews->avg('rating') ?? 0, 1),
            'delivery_speed' => round($reviews->avg('delivery_speed') ?? 0, 1),
            'driver_professionalism' => round($reviews->avg('driver_professionalism') ?? 0, 1),
            'medicine_condition' => round($reviews->avg('medicine_condition') ?? 0, 1),
            'delivery_overall' => round($reviews->avg('delivery_overall') ?? 0, 1),
            'medicine_availability' => round($reviews->avg('medicine_availability') ?? 0, 1),
            'price_rating' => round($reviews->avg('price_rating') ?? 0, 1),
            'customer_service' => round($reviews->avg('customer_service') ?? 0, 1),
            'accuracy' => round($reviews->avg('accuracy') ?? 0, 1),
            'pharmacy_overall' => round($reviews->avg('pharmacy_overall') ?? $reviews->avg('rating') ?? 0, 1),
        ];
    }
}
