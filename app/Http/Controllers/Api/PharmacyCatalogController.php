<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;

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
            ->select('id', 'name', 'address', 'latitude', 'longitude', 'phone', 'operating_hours', 'logo') // Fetch only what the mobile map needs
            ->get();

        return response()->json([
            'status' => 'success',
            'pharmacies' => $pharmacies
        ], 200);
    }

    /**
     * Fetch a specific pharmacy and its available medicine catalog.
     */
    public function catalog($id): JsonResponse
    {
        // Eager load medicines, but ONLY those actually in stock and available
        $pharmacy = Pharmacy::with(['medicines' => function($query) {
            $query->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                  ->where('pharmacy_medicine.is_available', true);
        }])
        ->active()
        ->approved()
        ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'pharmacy' => $pharmacy,
            'catalog' => $pharmacy->medicines
        ], 200);
    }
}
