<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFavorite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pharmacy_id',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacy::class);
    }

    // Static helpers
    public static function isFavorite($userId, $pharmacyId): bool
    {
        return self::where('user_id', $userId)
            ->where('pharmacy_id', $pharmacyId)
            ->exists();
    }

    public static function toggle($userId, $pharmacyId): array
    {
        $existing = self::where('user_id', $userId)
            ->where('pharmacy_id', $pharmacyId)
            ->first();

        if ($existing) {
            $existing->delete();
            return ['action' => 'removed', 'is_favorite' => false];
        }

        self::create([
            'user_id' => $userId,
            'pharmacy_id' => $pharmacyId,
        ]);

        return ['action' => 'added', 'is_favorite' => true];
    }
}
