<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'medicine_id',
        'batch_number',
        'expiration_date',
        'quantity',
        'status',
        'purchase_price',
        'storage_condition',
        'supplier',
        'received_at',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'received_at' => 'datetime',
        'purchase_price' => 'decimal:2',
    ];

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Order by expiration date ascending (FIFO — earliest expiry first)
     */
    public function scopeOrderByExpiration($query)
    {
        return $query->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END, expiration_date ASC');
    }

    /**
     * Only batches with remaining stock
     */
    public function scopeInStock($query)
    {
        return $query->where('quantity', '>', 0);
    }

    /**
     * Only non-expired batches
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expiration_date')
              ->orWhere('expiration_date', '>', now());
        });
    }
}
