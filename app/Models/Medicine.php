<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Medicine extends Model
{
    // Updated to match our new Master Data migration
    protected $fillable = [
        'slug',
        'barcode',
        'medicine_code',
        'brand_name',
        'generic_name',
        'dosage_form',
        'strength',
        'unit_of_measure',
        'pack_size',
        'therapeutic_class',
        'drug_category',
        'primary_use',             // <-- Added
        'description',
        'prescription_required',
        'controlled_substance_flag',
        'fda_registration_no',
        'image',
    ];

    protected $casts = [
        'prescription_required' => 'boolean',
        'controlled_substance_flag' => 'boolean', // Added the new boolean flag
    ];

    /**
     * The accessors to append to the model's array and JSON form.
     */
    protected $appends = [
        'image_url',
    ];

    /**
     * Get the canonical resolved public URL for the medicine image.
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image) || trim($this->image) === '') {
            return null;
        }

        $image = trim($this->image);

        // Case A: Explicit scheme present - only accept http:// and https://; reject unsupported/malformed schemes
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $image)) {
            if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
                return filter_var($image, FILTER_VALIDATE_URL) ? $image : null;
            }
            return null; // Reject javascript:, data:, ftp:, file:, mailto:, etc.
        }

        // Case B: Deliberately strip leading slash and optional leading storage/ prefix
        $cleanPath = preg_replace('/^\/?(storage\/)?/', '', $image);

        // Case C: Legacy local storage path (medicines/...)
        // Only return an asset URL if the file actually exists on the public disk.
        // If missing or location unknown, return null so UI shows fallback rather than a dead 404 URL.
        if (str_starts_with($cleanPath, 'medicines/')) {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($cleanPath)) {
                return asset('storage/' . $cleanPath);
            }
            return null;
        }

        // Case D: Supabase Storage bucket path or bare filename
        $bucket = \App\Services\SupabaseStorageService::BUCKET_MEDICINE_IMAGES;
        $relative = preg_replace('/^' . preg_quote($bucket, '/') . '\//', '', $cleanPath);

        if (class_exists(\App\Services\SupabaseStorageService::class)) {
            return app(\App\Services\SupabaseStorageService::class)->getPublicUrl($bucket, $relative);
        }

        return asset('storage/' . $cleanPath);
    }

    /**
     * Pharmacies that stock this medicine (with inventory pivot data)
     */
    public function pharmacies(): BelongsToMany
    {
        return $this->belongsToMany(Pharmacy::class, 'pharmacy_medicine')
            // CRITICAL: We must tell Laravel to load all our new inventory fields!
            ->withPivot([
                'quantity_on_hand', 
                'reorder_level', 
                'is_available', 
                'batch_number', 
                'expiration_date', 
                'purchase_price', 
                'selling_price', 
                'storage_condition',
                'unit',
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
     * Scope: Search by brand name or generic name
     */
    public function scopeSearch($query, string $term)
    {
        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        return $query->where('brand_name', $like, "%{$term}%")
            ->orWhere('generic_name', $like, "%{$term}%");
    }
}