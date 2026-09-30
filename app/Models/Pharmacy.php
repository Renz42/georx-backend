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
     * The accessors to append to the model's array and JSON form.
     */
    protected $appends = [
        'average_rating',
        'logo_url',
        'cover_photo_url',
        'is_currently_open',
    ];

    /**
     * Compute the effective open/closed/offline status.
     *
     * Combines the manual is_active toggle with operating_hours
     * and the current server time to produce a 3-state result:
     *   'online'  – is_active AND within operating hours (or hours not set)
     *   'closed'  – is_active BUT outside operating hours
     *   'offline' – is_active is false (manual override)
     */
    public function getIsCurrentlyOpenAttribute(): string
    {
        // Manual override takes priority
        if (!$this->is_active) {
            return 'offline';
        }

        // If operating hours are configured, check the current time
        if (is_array($this->operating_hours)
            && isset($this->operating_hours['open'])
            && isset($this->operating_hours['close'])) {
            try {
                $tz        = config('app.timezone') && config('app.timezone') !== 'UTC' ? config('app.timezone') : 'Asia/Manila';
                $now       = now($tz)->format('H:i');
                $openTime  = \Carbon\Carbon::parse($this->operating_hours['open'])->format('H:i');
                $closeTime = \Carbon\Carbon::parse($this->operating_hours['close'])->format('H:i');

                // Standard daytime schedule (e.g. 08:00 to 21:00)
                if ($openTime <= $closeTime) {
                    if ($now >= $openTime && $now <= $closeTime) {
                        return 'online';
                    }
                } else {
                    // Overnight schedule crossing midnight (e.g. 20:00 to 06:00)
                    if ($now >= $openTime || $now <= $closeTime) {
                        return 'online';
                    }
                }

                return 'closed';
            } catch (\Exception $e) {
                // If parsing fails, fall back to online (is_active is true)
                return 'online';
            }
        }

        // No operating hours configured – rely on is_active only
        return 'online';
    }

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
     * Get the full resolved URL for the pharmacy logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo)) {
            return null;
        }

        $logo = trim($this->logo);

        // Case A: Full URL (Supabase storage or external HTTPS)
        if (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://')) {
            return $logo;
        }

        // Case B: Relative or local storage path
        $cleanPath = ltrim(str_replace('storage/', '', $logo), '/');
        $bucket = \App\Services\SupabaseStorageService::BUCKET_PHARMACY_LOGOS;
        $relative = preg_replace('/^' . preg_quote($bucket, '/') . '\//', '', $cleanPath);

        if (class_exists(\App\Services\SupabaseStorageService::class)) {
            return app(\App\Services\SupabaseStorageService::class)->getPublicUrl($bucket, $relative);
        }

        return asset('storage/' . $cleanPath);
    }

    /**
     * Get the full resolved URL for the pharmacy storefront / cover photo.
     */
    public function getCoverPhotoUrlAttribute(): ?string
    {
        if (empty($this->cover_photo)) {
            return null;
        }

        $cover = trim($this->cover_photo);

        // Case A: Full URL (Supabase storage or external HTTPS)
        if (str_starts_with($cover, 'http://') || str_starts_with($cover, 'https://')) {
            return $cover;
        }

        // Case B: Relative or local storage path
        $cleanPath = ltrim(str_replace('storage/', '', $cover), '/');
        $bucket = \App\Services\SupabaseStorageService::BUCKET_PHARMACY_COVERS;
        $relative = preg_replace('/^' . preg_quote($bucket, '/') . '\//', '', $cleanPath);

        if (class_exists(\App\Services\SupabaseStorageService::class)) {
            return app(\App\Services\SupabaseStorageService::class)->getPublicUrl($bucket, $relative);
        }

        return asset('storage/' . $cleanPath);
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