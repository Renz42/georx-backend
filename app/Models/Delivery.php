<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    use HasFactory;

    // Delivery Status Constants
    const STATUS_WAITING_FOR_DRIVER = 'waiting_for_driver';
    const STATUS_DRIVER_ASSIGNED   = 'driver_assigned';
    const STATUS_DRIVER_AT_PHARMACY = 'driver_at_pharmacy';
    const STATUS_PICKED_UP         = 'picked_up';
    const STATUS_OUT_FOR_DELIVERY   = 'out_for_delivery';
    const STATUS_DELIVERED          = 'delivered';
    const STATUS_CANCELLED          = 'cancelled';
    const STATUS_DELIVERY_FAILED    = 'delivery_failed';

    protected $fillable = [
        'order_id',
        'customer_id',
        'driver_id',
        'pickup_pharmacy_id',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',
        'status',
        'verification_code',
        'driver_accepted_at',
        'driver_at_pharmacy_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'customer_confirmed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'failure_reason',
        'driver_notes',
    ];

    protected $casts = [
        'pickup_latitude'       => 'float',
        'pickup_longitude'      => 'float',
        'delivery_latitude'     => 'float',
        'delivery_longitude'    => 'float',
        'driver_accepted_at'    => 'datetime',
        'driver_at_pharmacy_at' => 'datetime',
        'picked_up_at'          => 'datetime',
        'out_for_delivery_at'   => 'datetime',
        'delivered_at'          => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'cancelled_at'          => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function pickupPharmacy()
    {
        return $this->belongsTo(Pharmacy::class, 'pickup_pharmacy_id');
    }

    // --- SCOPES ---

    public function scopeAvailableForDrivers($query)
    {
        return $query->where('status', self::STATUS_WAITING_FOR_DRIVER)->whereNull('driver_id');
    }

    public function scopeActiveForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId)
            ->whereIn('status', [
                self::STATUS_DRIVER_ASSIGNED,
                self::STATUS_DRIVER_AT_PHARMACY,
                self::STATUS_PICKED_UP,
                self::STATUS_OUT_FOR_DELIVERY,
            ]);
    }

    public function scopeCompletedForDriver($query, $driverId)
    {
        return $query->where('driver_id', $driverId)
            ->where('status', self::STATUS_DELIVERED);
    }
}
