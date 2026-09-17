<?php

namespace App\Http\Controllers;

use App\Models\UserFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    // Toggle favorite status
    public function toggle(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $user = Auth::user();
        $result = UserFavorite::toggle($user->id, $request->pharmacy_id);

        return response()->json([
            'success' => true,
            'is_favorite' => $result['is_favorite'],
            'message' => $result['action'] === 'added' 
                ? 'Pharmacy added to favorites!' 
                : 'Pharmacy removed from favorites.',
        ]);
    }

    // Get user's favorites
    public function index(Request $request)
    {
        $user = Auth::user();

        $favorites = $user->favoritePharmacies()
            ->with('medicines')
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'favorites' => $favorites->map(function ($pharmacy) {
                    return [
                        'id' => $pharmacy->id,
                        'name' => $pharmacy->name,
                        'address' => $pharmacy->address,
                        'phone' => $pharmacy->phone,
                        'latitude' => $pharmacy->latitude,
                        'longitude' => $pharmacy->longitude,
                        'medicines_count' => $pharmacy->medicines->count(),
                    ];
                }),
            ]);
        }

        return view('user.favorites', compact('favorites'));
    }

    // Check if pharmacy is favorited
    public function check(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $user = Auth::user();
        $isFavorite = UserFavorite::isFavorite($user->id, $request->pharmacy_id);

        return response()->json([
            'success' => true,
            'is_favorite' => $isFavorite,
        ]);
    }

    // Check multiple pharmacies
    public function checkMultiple(Request $request)
    {
        $request->validate([
            'pharmacy_ids' => 'required|array',
            'pharmacy_ids.*' => 'exists:pharmacies,id',
        ]);

        $user = Auth::user();

        $favorites = UserFavorite::where('user_id', $user->id)
            ->whereIn('pharmacy_id', $request->pharmacy_ids)
            ->pluck('pharmacy_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'favorites' => $favorites,
        ]);
    }

    // Remove from favorites
    public function remove(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
        ]);

        $user = Auth::user();

        UserFavorite::where('user_id', $user->id)
            ->where('pharmacy_id', $request->pharmacy_id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pharmacy removed from favorites.',
        ]);
    }
}
