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
    public const DEFAULT_RADIUS_KM = 5;
    public const MIN_RADIUS_KM = 1;
    public const MAX_RADIUS_KM = 5;
    public const ALLOWED_RADII_KM = [1, 2, 3, 4, 5];
    public const ALIJIS_REFERENCE_LAT = 10.6385;
    public const ALIJIS_REFERENCE_LNG = 122.9520;
    private const RADIUS_EPSILON_KM = 1e-6;

    /**
     * Exact Haversine distance in kilometers (unrounded float).
     */
    public static function calculateHaversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $cosVal = cos(deg2rad($lat1))
            * cos(deg2rad($lat2))
            * cos(deg2rad($lng2) - deg2rad($lng1))
            + sin(deg2rad($lat1))
            * sin(deg2rad($lat2));
        $clamped = min(1.0, max(-1.0, $cosVal));

        return 6371.0 * acos($clamped);
    }

    /**
     * Check whether a raw input value is a valid finite number (rejecting NaN, INF, booleans, arrays, empty strings).
     */
    private function isFiniteNumericValue(mixed $value): bool
    {
        if (is_bool($value) || is_array($value) || is_object($value) || $value === null || $value === '') {
            return false;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || preg_match('/^(nan|inf|-inf|infinity|-infinity)$/i', $trimmed)) {
                return false;
            }
        }
        if (!is_numeric($value)) {
            return false;
        }

        return is_finite((float) $value);
    }

    /**
     * Strictly validate that an explicitly supplied radius is an integer in [1, 2, 3, 4, 5].
     * Rejects decimals (1.5, 2.5, 3.75, 5.0, 5.1), 0, negatives, >5, NaN, Infinity, booleans, and malformed strings.
     */
    private function isStrictAllowedRadius(mixed $value): bool
    {
        if (is_int($value)) {
            return in_array($value, self::ALLOWED_RADII_KM, true);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if (preg_match('/^[1-5]$/', $trimmed) !== 1) {
                return false;
            }

            return in_array((int) $trimmed, self::ALLOWED_RADII_KM, true);
        }

        return false;
    }

    /**
     * Detect whether a query/input parameter key was explicitly supplied in the request,
     * even if ConvertEmptyStringsToNull normalized its value to null.
     */
    private function hasExplicitParameter(Request $request, string $key): bool
    {
        $rawQuery = (string) $request->server('QUERY_STRING', '');
        $quotedKey = preg_quote($key, '/');
        if ($rawQuery !== '' && preg_match('/(?:^|&)' . $quotedKey . '(?:=|$|&)/', $rawQuery) === 1) {
            return true;
        }

        return $request->exists($key)
            || $request->query->has($key)
            || array_key_exists($key, $request->all());
    }

    /**
     * Find nearby pharmacies within Barangay Alijis (and within 1-5 km radius when radius is supplied)
     * 
     * GET /api/pharmacies/nearby?lat=10.6385&lng=122.9520&radius=5&medicine=paracetamol&sort=distance
     */
    public function nearby(Request $request): JsonResponse
    {
        $hasLatKey = $this->hasExplicitParameter($request, 'lat');
        $hasLngKey = $this->hasExplicitParameter($request, 'lng');
        $hasRadiusKey = $this->hasExplicitParameter($request, 'radius');

        $request->validate([
            'lat' => [
                $hasLatKey ? 'required' : 'nullable',
                'required_with:lng',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->isFiniteNumericValue($value)) {
                        $fail("The {$attribute} field must be a valid finite number.");
                        return;
                    }
                    $num = (float) $value;
                    if ($num < -90.0 || $num > 90.0) {
                        $fail("The {$attribute} field must be between -90 and 90.");
                    }
                },
            ],
            'lng' => [
                $hasLngKey ? 'required' : 'nullable',
                'required_with:lat',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->isFiniteNumericValue($value)) {
                        $fail("The {$attribute} field must be a valid finite number.");
                        return;
                    }
                    $num = (float) $value;
                    if ($num < -180.0 || $num > 180.0) {
                        $fail("The {$attribute} field must be between -180 and 180.");
                    }
                },
            ],
            'radius' => [
                $hasRadiusKey ? 'required' : 'nullable',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (!$this->isStrictAllowedRadius($value)) {
                        $fail('The radius field must be an integer between 1 and 5 kilometers.');
                    }
                },
            ],
            'medicine' => 'nullable|string|max:100',
            'search_type' => 'nullable|string|in:all,medicine,pharmacy',
            'sort' => 'nullable|string|in:distance,price,availability',
            'category' => 'nullable|string|max:100',
        ]);

        // When radius is explicitly supplied (Medicine Search), enforce integer 1-5 km radius
        // from a verified Barangay Alijis reference point.
        // When radius is omitted (Home Map & Web Portal), preserve legacy Barangay Alijis
        // boundary-only behavior without imposing a 5 km distance cutoff.
        $appliedRadius = $hasRadiusKey ? (int) trim((string) $request->input('radius')) : null;

        $lat = self::ALIJIS_REFERENCE_LAT;
        $lng = self::ALIJIS_REFERENCE_LNG;
        if ($request->filled('lat') && $request->filled('lng')) {
            $candidateLat = (float) $request->lat;
            $candidateLng = (float) $request->lng;
            if ($appliedRadius !== null) {
                if (\App\Services\GeofenceService::isInsideAlijis($candidateLat, $candidateLng)) {
                    $lat = $candidateLat;
                    $lng = $candidateLng;
                }
            } else {
                $lat = $candidateLat;
                $lng = $candidateLng;
            }
        }

        $sortBy = $request->sort ?? 'distance';
        $searchType = $request->get('search_type', $request->get('type', 'all'));

        $driver = \Illuminate\Support\Facades\DB::getDriverName();
        $like = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        $haversineSql = '( 6371 * acos( LEAST(1.0, GREATEST(-1.0, cos( radians(?) ) * cos( radians( latitude ) ) * cos( radians( longitude ) - radians(?) ) + sin( radians(?) ) * sin( radians( latitude ) ) )) ) )';

        // 2. Start Query: Must be Active, Approved by Super Admin, and have non-null coordinates
        $query = Pharmacy::select('pharmacies.*')
            ->selectRaw("{$haversineSql} AS distance", [$lat, $lng, $lat])
            ->where('is_active', true)
            ->where('is_approved', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($appliedRadius !== null) {
            $query->whereRaw("{$haversineSql} <= ?", [$lat, $lng, $lat, $appliedRadius + self::RADIUS_EPSILON_KM]);
        }

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
                $query->orderByRaw('(SELECT MIN(pm.selling_price) FROM pharmacy_medicine pm WHERE pm.pharmacy_id = pharmacies.id AND pm.is_available = true AND pm.quantity_on_hand > 0) ASC');
                break;
            case 'availability':
                // Sort by highest total stock first
                $query->orderByRaw('(SELECT SUM(pm.quantity_on_hand) FROM pharmacy_medicine pm WHERE pm.pharmacy_id = pharmacies.id AND pm.is_available = true) DESC');
                break;
            case 'distance':
            default:
                $query->orderBy('distance', 'asc');
                break;
        }

        // 7. Execute query, then apply Barangay Alijis polygon & radius filtering BEFORE limiting to top 20
        //    so out-of-bound pharmacies can never occupy a limited result slot and exclude valid Alijis pharmacies.
        $pharmacies = $query->get();

        $pharmacies = $pharmacies->filter(function ($pharmacy) use ($lat, $lng, $appliedRadius) {
            if (!$this->isFiniteNumericValue($pharmacy->latitude) || !$this->isFiniteNumericValue($pharmacy->longitude)) {
                return false;
            }

            $pLat = (float) $pharmacy->latitude;
            $pLng = (float) $pharmacy->longitude;

            if ($pLat < -90.0 || $pLat > 90.0 || $pLng < -180.0 || $pLng > 180.0) {
                return false;
            }

            // Rule A: Always enforce Barangay Alijis geographic boundary
            if (!\App\Services\GeofenceService::isInsideAlijis($pLat, $pLng)) {
                return false;
            }

            // Compute exact unrounded Haversine distance in km
            $distanceKm = self::calculateHaversineDistanceKm($lat, $lng, $pLat, $pLng);
            $pharmacy->distance = $distanceKm;

            // Rule B: Enforce selected 1-5 km radius when radius parameter was supplied
            if ($appliedRadius !== null) {
                return $distanceKm <= ($appliedRadius + self::RADIUS_EPSILON_KM);
            }

            return true;
        });

        if ($sortBy === 'distance') {
            $pharmacies = $pharmacies->sortBy('distance');
        }

        $pharmacies = $pharmacies->take(20)->values();

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
            'radius' => $appliedRadius,
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