<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchLog extends Model
{
    protected $fillable = ['term', 'search_type', 'user_id', 'results_count'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the most searched terms (for admin reporting & popular medicines).
     */
    public static function mostSearched($limit = 10)
    {
        return static::select('term')
            ->selectRaw('COUNT(*) as search_count')
            ->groupBy('term')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('search_count', 'term');
    }

    /**
     * Get recent searches for a specific user.
     */
    public static function recentForUser($userId, $limit = 5)
    {
        return static::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->pluck('term')
            ->unique()
            ->values();
    }
}
