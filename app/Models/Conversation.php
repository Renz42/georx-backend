<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pharmacy_id',
        'order_id',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // =============================================
    // RELATIONSHIPS
    // =============================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // =============================================
    // HELPERS
    // =============================================

    /**
     * Count unread messages for a specific user in this conversation
     */
    public function unreadCountFor(int $userId): int
    {
        return $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Mark all messages as read for a specific user
     */
    public function markAsReadFor(int $userId): void
    {
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Get or create a conversation between a user and pharmacy.
     * Concurrency-safe against race conditions and database uniqueness constraints.
     */
    public static function findOrStart(int $userId, int $pharmacyId, ?int $orderId = null): self
    {
        $findQuery = function () use ($userId, $pharmacyId, $orderId) {
            $q = static::where('user_id', $userId)
                ->where('pharmacy_id', $pharmacyId);

            if ($orderId === null) {
                $q->whereNull('order_id');
            } else {
                $q->where('order_id', $orderId);
            }

            return $q->first();
        };

        // 1. Fast read without write lock
        $existing = $findQuery();
        if ($existing) {
            return $existing;
        }

        // 2. Concurrency-safe insert with fallback on duplicate race
        try {
            return static::create([
                'user_id' => $userId,
                'pharmacy_id' => $pharmacyId,
                'order_id' => $orderId,
                'last_message_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Concurrent race condition caught by database unique index
            $raceExisting = $findQuery();
            if ($raceExisting) {
                return $raceExisting;
            }

            throw $e;
        }
    }
}

