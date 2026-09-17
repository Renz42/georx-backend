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
                'unit'
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