<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    use HasFactory;

    // account_status constants (mirrors User status constants)
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_SUSPENDED      = 'suspended';
    public const STATUS_REJECTED       = 'rejected';

    // vehicle_type allowed values
    public const VEHICLE_TYPES = [
        'motorcycle' => 'Motorcycle',
        'bicycle'    => 'Bicycle',
        'e-bike'     => 'E-Bike',
        'car'        => 'Car',
    ];

    protected $fillable = [
        'user_id',
        'vehicle_type',
        'vehicle_make',
        'vehicle_model',
        'plate_number',
        'is_online',
        'is_available',
        'account_status',
        'current_latitude',
        'current_longitude',
        'last_location_at',
        'license_number',
        'profile_photo',
    ];

    protected $casts = [
        'is_online'         => 'boolean',
        'is_available'      => 'boolean',
        'current_latitude'  => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'last_location_at'  => 'datetime',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    /**
     * The user this driver profile belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // =============================================
    // SCOPES
    // =============================================

    /**
     * Scope: Drivers who are approved and ready to receive bookings.
     */
    public function scopeApproved($query)
    {
        return $query->where('account_status', self::STATUS_APPROVED);
    }

    /**
     * Scope: Drivers who are currently online and available (not on a delivery).
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_online', true)->where('is_available', true);
    }

    /**
     * Scope: Pending approval — for admin review queue.
     */
    public function scopePendingReview($query)
    {
        return $query->where('account_status', self::STATUS_PENDING_REVIEW);
    }

    // =============================================
    // HELPERS
    // =============================================

    /**
     * Check if this driver is cleared to accept deliveries.
     * Must be: approved account + online + available.
     */
    public function isReadyForBooking(): bool
    {
        return $this->account_status === self::STATUS_APPROVED
            && $this->is_online
            && $this->is_available;
    }

    /**
     * Get the human-readable vehicle label.
     */
    public function getVehicleLabelAttribute(): string
    {
        return self::VEHICLE_TYPES[$this->vehicle_type] ?? 'Unknown';
    }

    /**
     * Get full vehicle description for display on order cards.
     * e.g. "Honda Click 125i (ABC-1234)"
     */
    public function getVehicleDescriptionAttribute(): string
    {
        $parts = array_filter([
            $this->vehicle_make,
            $this->vehicle_model,
            $this->plate_number ? "({$this->plate_number})" : null,
        ]);

        return implode(' ', $parts) ?: 'No vehicle on file';
    }
}
