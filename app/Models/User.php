<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable; // Notifiable handles all the notification magic automatically!

    public const ROLE_CUSTOMER          = 'customer';
    public const ROLE_PHARMACIST        = 'pharmacist';
    public const ROLE_PHARMACY_STAFF    = 'pharmacy_staff';
    public const ROLE_PHARMACY_OWNER    = 'pharmacy_owner';
    public const ROLE_DELIVERY_PARTNER  = 'delivery_partner'; // legacy alias
    public const ROLE_DRIVER            = 'driver';           // canonical driver role
    public const ROLE_ADMINISTRATOR     = 'administrator';

    // account_status values (used by drivers + future approval workflows)
    public const STATUS_ACTIVE         = 'active';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_SUSPENDED      = 'suspended';
    public const STATUS_REJECTED       = 'rejected';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'account_status',
        'pharmacy_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function stockAlerts()
    {
        return $this->hasMany(StockAlert::class);
    }

    public function favorites()
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function favoritePharmacies()
    {
        return $this->belongsToMany(Pharmacy::class, 'user_favorites');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    // =============================================
    // ROLE HELPERS
    // =============================================

    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    public function isPharmacyOwner(): bool
    {
        return $this->role === self::ROLE_PHARMACY_OWNER;
    }
    
    public function isPharmacist(): bool
    {
        return $this->role === self::ROLE_PHARMACIST;
    }
    
    public function isPharmacyStaff(): bool
    {
        return $this->role === self::ROLE_PHARMACY_STAFF;
    }

    /**
     * isDriver() — canonical check for the new GEORX driver role.
     * Also accepts the legacy 'delivery_partner' role so existing seeded
     * records remain functional during the transition.
     */
    public function isDriver(): bool
    {
        return in_array($this->role, [self::ROLE_DRIVER, self::ROLE_DELIVERY_PARTNER]);
    }

    /**
     * isDeliveryPartner() — kept for backward compatibility.
     * Points to isDriver() so both forms work identically.
     */
    public function isDeliveryPartner(): bool
    {
        return $this->isDriver();
    }

    public function isDriverApproved(): bool
    {
        return $this->isDriver()
            && $this->driverProfile
            && $this->driverProfile->account_status === self::STATUS_APPROVED;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }
}