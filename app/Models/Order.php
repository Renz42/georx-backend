<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // Status Constants
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PENDING = 'pending'; // Ready for external dispatch
    public const STATUS_ACCEPTED = 'accepted'; // Dispatched to external courier
    public const STATUS_AT_PHARMACY = 'at_pharmacy';
    public const STATUS_PICKED_UP = 'picked_up';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    // Provider Constants
    public const PROVIDER_GRAB = 'grab';
    public const PROVIDER_MAXIM = 'maxim';

    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'delivery_partner_id',
        'delivery_provider',
        'delivery_booking_id',
        'delivery_tracking_url',
        'delivery_status',
        'delivery_booked_at',
        'status',
        'is_prepared',
        'total_amount',
        'delivery_fee',
        'actual_courier_fee',
        'payment_method',
        'delivery_address',
        'latitude',
        'longitude',
        'pharmacy_confirmed',
        'confirmed_at',
        'rejected_at',
        'rejection_reason',
        'maxim_booking_id',
        'maxim_tracking_url',
        'driver_name',
        'driver_phone',
        'driver_vehicle',
        'estimated_delivery_at',
        'delivered_at',
        'fifo_deductions',
        'accepted_at',
        'picked_up_at',
        'customer_confirmed_delivery',
        'customer_confirmed_at',
    ];

    protected $casts = [
        'pharmacy_confirmed' => 'boolean',
        'customer_confirmed_delivery' => 'boolean',
        'actual_courier_fee' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivery_booked_at' => 'datetime',
        'customer_confirmed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'fifo_deductions' => 'array',
    ];

    /**
     * Interpret database UTC timestamp and present in application timezone (Asia/Manila).
     */
    public function getCreatedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }
        return \Carbon\Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone', 'Asia/Manila'));
    }

    public function getUpdatedAtAttribute($value)
    {
        if (!$value) {
            return null;
        }
        return \Carbon\Carbon::parse($value, 'UTC')->setTimezone(config('app.timezone', 'Asia/Manila'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function deliveryPartner()
    {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function conversation()
    {
        return $this->hasOne(Conversation::class);
    }
}
