<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\Medicine;
use App\Models\SearchLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PharmacySearchController extends Controller
{
    /**
     * Find nearby pharmacies within radius
     * 
     * GET /api/pharmacies/nearby?lat=10.3157&lng=123.8854&radius=5&medicine=paracetamol&sort=distance
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'medicine' => 'nullable|string|max:100',
            'search_type' => 'nullable|string|in:all,medicine,pharmacy',
            'sort' => 'nullable|string|in:distance,price,availability',
            'category' => 'nullable|string|max:100',
        ]);

        $lat = (float) ($request->lat ?? 10.6385);
        $lng = (float) ($request->lng ?? 122.9520);
        $sortBy = $request->sort ?? 'distance';
        $searchType = $request->get('search_type', $request->get('type', 'all'));

        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        // 2. Start Query: Must be Active AND Approved by Super Admin
        $query = Pharmacy::select('pharmacies.*')
            ->selectRaw(
                '( 6371 * acos( LEAST(1.0, GREATEST(-1.0, cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) )) ) ) AS distance',
                [$lat, $lng, $lat]
            )
            ->where('is_active', true)
            ->where('is_approved', true);

        // 3. Filter by search term
        if ($request->filled('medicine')) {
            $searchTerm = trim($request->medicine);

            // Log search for analytics
            SearchLog::create([
                'term' => $searchTerm,
                'search_type' => $searchType,
                'user_id' => Auth::id(),
                'results_count' => 0,
            ]);

            if ($searchType === 'pharmacy') {
                // Strict Pharmacy Name & Address search
                $query->where(function($q) use ($searchTerm, $like) {
                    $q->where('name', $like, "%{$searchTerm}%")
                      ->orWhere('address', $like, "%{$searchTerm}%");
                });
            } elseif ($searchType === 'medicine') {
                // Strict Medicine Name search
                $query->whereHas('medicines', function ($medQuery) use ($searchTerm, $like) {
                    $medQuery->where(function($nameQuery) use ($searchTerm, $like) {
                        $nameQuery->where('brand_name', $like, "%{$searchTerm}%")
                                  ->orWhere('generic_name', $like, "%{$searchTerm}%")
                                  ->orWhere('primary_use', $like, "%{$searchTerm}%")
                                  ->orWhere('therapeutic_class', $like, "%{$searchTerm}%")
                                  ->orWhere('drug_category', $like, "%{$searchTerm}%");
                    })
                    ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                    ->where('pharmacy_medicine.is_available', true);
                });
            } else {
                // Dual Search: Match pharmacy name/address OR medicine name
                $query->where(function($q) use ($searchTerm, $like) {
                    $q->where('name', $like, "%{$searchTerm}%")
                      ->orWhere('address', $like, "%{$searchTerm}%")
                      ->orWhereHas('medicines', function ($medQuery) use ($searchTerm, $like) {
                          $medQuery->where(function($nameQuery) use ($searchTerm, $like) {
                              $nameQuery->where('brand_name', $like, "%{$searchTerm}%")
                                        ->orWhere('generic_name', $like, "%{$searchTerm}%")
                                        ->orWhere('primary_use', $like, "%{$searchTerm}%")
                                        ->orWhere('therapeutic_class', $like, "%{$searchTerm}%")
                                        ->orWhere('drug_category', $like, "%{$searchTerm}%");
                          })
                          ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                          ->where('pharmacy_medicine.is_available', true);
                      });
                });
            }

            // Eager Loading: Load matching medicines
            $query->with(['medicines' => function ($q) use ($searchTerm, $like) {
                $q->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                  ->where('pharmacy_medicine.is_available', true)
                  ->orderByRaw("CASE WHEN generic_name {$like} ? OR brand_name {$like} ? THEN 0 ELSE 1 END", ["%{$searchTerm}%", "%{$searchTerm}%"])
                  ->take(10);
            }]);

        } else {
            $query->with(['medicines' => function ($q) {
                $q->where('pharmacy_medicine.is_available', true)
                  ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                  ->take(5);
            }]);
        }

        // 5. Category filter (if specified separately)
        if ($request->filled('category')) {
            $category = $request->category;
            $query->whereHas('medicines', function ($q) use ($category) {
                $q->where('drug_category', $category)
                  ->where('pharmacy_medicine.quantity_on_hand', '>', 0)
                  ->where('pharmacy_medicine.is_available', true);
            });
        }

        // 6. Apply sorting
        switch ($sortBy) {
            case 'price':
                // Sort pharmacies by lowest average medicine price
                $query->orderByRaw('(SELECT MIN(pm.selling_price) FROM pharmacy_medicine pm WHERE pm.pharmacy_id = pharmacies.id AND pm.is_available = 1 AND pm.quantity_on_hand > 0) ASC');
                break;
            case 'availability':
                // Sort by highest total stock first
                $query->orderByRaw('(SELECT SUM(pm.quantity_on_hand) FROM pharmacy_medicine pm WHERE pm.pharmacy_id = pharmacies.id AND pm.is_available = 1) DESC');
                break;
            case 'distance':
            default:
                $query->orderBy('distance', 'asc');
                break;
        }

        // 7. Execute query, limiting to top 20 closest
        $pharmacies = $query->limit(20)->get();

        // Filter out any pharmacies outside Barangay Alijis boundary
        $pharmacies = $pharmacies->filter(function($pharmacy) {
            return \App\Services\GeofenceService::isInsideAlijis((float)$pharmacy->latitude, (float)$pharmacy->longitude);
        })->values();

        // Update search log with result count
        if ($request->filled('medicine')) {
            SearchLog::where('user_id', Auth::id())
                ->where('term', $request->medicine)
                ->latest()
                ->first()
                ?->update(['results_count' => $pharmacies->count()]);
        }

        return response()->json([
            'success' => true,
            'count' => $pharmacies->count(),
            'sort' => $sortBy,
            'pharmacies' => $pharmacies,
        ]);
    }

    /**
     * Search suggestions (autocomplete)
     * GET /api/search/suggestions?q=para
     */
    public function suggestions(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        // Search medicines by brand, generic, or primary use
        $medicines = Medicine::where('brand_name', $like, "%{$q}%")
            ->orWhere('generic_name', $like, "%{$q}%")
            ->orWhere('primary_use', $like, "%{$q}%")
            ->orWhere('therapeutic_class', $like, "%{$q}%")
            ->select('id', 'brand_name', 'generic_name', 'primary_use', 'drug_category')
            ->limit(8)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'type' => 'medicine',
                    'label' => ($m->brand_name ?? $m->generic_name),
                    'generic' => $m->generic_name,
                    'category' => $m->drug_category,
                    'use' => $m->primary_use,
                ];
            });

        // Also suggest pharmacy names
        $pharmacies = Pharmacy::where('name', $like, "%{$q}%")
            ->where('is_active', true)
            ->where('is_approved', true)
            ->select('id', 'name', 'address', 'latitude', 'longitude')
            ->limit(5)
            ->get()
            ->filter(fn($p) => \App\Services\GeofenceService::isInsideAlijis((float)$p->latitude, (float)$p->longitude))
            ->map(fn($p) => [
                'type' => 'pharmacy',
                'id' => $p->id,
                'label' => $p->name,
                'address' => $p->address,
            ])
            ->values();

        return response()->json([
            'suggestions' => [
                'medicines' => $medicines,
                'pharmacies' => $pharmacies,
            ],
        ]);
    }

    /**
     * Popular medicines (most ordered)
     * GET /api/search/popular
     */
    public function popular(): JsonResponse
    {
        // Most ordered medicines
        $popular = \App\Models\OrderItem::select('medicine_id')
            ->selectRaw('COUNT(*) as order_count')
            ->groupBy('medicine_id')
            ->orderByDesc('order_count')
            ->limit(8)
            ->with('medicine:id,brand_name,generic_name,primary_use,drug_category')
            ->get()
            ->map(fn($item) => [
                'id' => $item->medicine_id,
                'name' => $item->medicine->brand_name ?? $item->medicine->generic_name,
                'generic' => $item->medicine->generic_name,
                'category' => $item->medicine->drug_category,
                'order_count' => $item->order_count,
            ]);

        // Most searched terms
        $trending = SearchLog::mostSearched(5);

        return response()->json([
            'popular_medicines' => $popular,
            'trending_searches' => $trending,
        ]);
    }

    /**
     * Recent searches for the logged-in user
     * GET /api/search/recent
     */
    public function recent(): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['recent' => []]);
        }

        $recent = SearchLog::recentForUser($userId, 5);

        return response()->json([
            'recent' => $recent,
        ]);
    }
}