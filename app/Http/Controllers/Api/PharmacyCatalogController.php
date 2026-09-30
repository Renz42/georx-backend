<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PharmacyCatalogController extends Controller
{
    /**
     * Fetch all active and approved pharmacies to display on the mobile map/list.
     */
    public function index(): JsonResponse
    {
        // Leveraging the active() and approved() scopes already in your model
        $pharmacies = Pharmacy::active()
            ->approved()
            ->select('id', 'name', 'slug', 'address', 'latitude', 'longitude', 'phone', 'operating_hours', 'theme_color', 'logo', 'cover_photo')
            ->get();

        return response()->json([
            'status' => 'success',
            'pharmacies' => $pharmacies
        ], 200);
    }

    /**
     * Fetch a specific pharmacy and its available medicine catalog.
     */
    public function catalog(Request $request, $id): JsonResponse
    {
        $search = trim($request->get('search', ''));
        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        // Eager load medicines, but ONLY those actually in stock and available
        $pharmacy = Pharmacy::with(['medicines' => function($query) use ($search, $like) {
            if ($search !== '') {
                $query->where(function($q) use ($search, $like) {
                    $q->where('medicines.brand_name', $like, "%{$search}%")
                      ->orWhere('medicines.generic_name', $like, "%{$search}%");
                });
            }
            $query->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                  ->where('pharmacy_medicine.is_available', true);
        }])
        ->active()
        ->approved()
        ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'pharmacy' => $pharmacy,
            'medicines' => $pharmacy->medicines,
            'catalog' => $pharmacy->medicines
        ], 200);
    }
}
