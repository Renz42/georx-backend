<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pharmacy extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'address',
        'phone',
        'email',
        'latitude',
        'longitude',
        'owner_name',
        'theme_color',
        'description',
        'license_number',
        'logo',
        'cover_photo',
        'operating_hours',
        'is_active',
        'is_approved', 
        'status',
        'business_permit',
        'lto_number',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'operating_hours' => 'array',
        'is_active' => 'boolean',
        'is_approved' => 'boolean', 
    ];

    /**
     * Medicines stocked by this pharmacy (with inventory pivot data)
     */
    public function medicines(): BelongsToMany
    {
        return $this->belongsToMany(Medicine::class, 'pharmacy_medicine')
            ->withPivot([
                'quantity_on_hand', 
                'reorder_level', 
                'is_available', 
                'batch_number', 
                'expiration_date', 
                'purchase_price', 
                'selling_price', 
                'storage_condition',
                'unit', // ✅ FIX: Added unit here so Laravel can read it!
                'supplier',
                'last_restocked_at'
            ])
            ->withTimestamps();
    }

    public function inventoryBatches()
    {
        return $this->hasMany(InventoryBatch::class);
    }

    /**
     * Conversations with customers
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Reviews left by customers
     */
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get average overall rating for this pharmacy.
     */
    public function getAverageRatingAttribute(): float
    {
        $avg = $this->reviews()->avg('pharmacy_overall');
        if (!$avg) {
            $avg = $this->reviews()->avg('rating');
        }
        return round($avg ?? 0, 1);
    }

    /**
     * Scope: Only active pharmacies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Only approved pharmacies
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope: Find pharmacies within radius (km) using Haversine formula
     * Clamped to LEAST(1.0, GREATEST(-1.0, ...)) for PostgreSQL compatibility
     */
    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 15) 
    {
        $haversine = "(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) 
                     * cos(radians(latitude)) 
                     * cos(radians(longitude) - radians(?)) 
                     + sin(radians(?)) 
                     * sin(radians(latitude))))))";

        return $query
            ->selectRaw("*, {$haversine} AS distance", [$lat, $lng, $lat])
            ->whereRaw("{$haversine} < ?", [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance');
    }
}