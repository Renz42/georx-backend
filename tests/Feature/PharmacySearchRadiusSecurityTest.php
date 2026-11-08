<?php

namespace Tests\Feature;

use App\Http\Controllers\PharmacySearchController;
use App\Models\Medicine;
use App\Models\Pharmacy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PharmacySearchRadiusSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerSqliteTrigFunctionsForTestEnvironment();
    }

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement("ATTACH ':memory:' AS information_schema");
                DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Test-only helper: registers PostgreSQL trigonometric functions on the in-memory SQLite PDO connection.
     */
    private function registerSqliteTrigFunctionsForTestEnvironment(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = DB::connection()->getPdo();
        if ($pdo && method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('acos', static fn ($v) => $v === null ? null : acos((float) $v), 1);
            $pdo->sqliteCreateFunction('cos', static fn ($v) => $v === null ? null : cos((float) $v), 1);
            $pdo->sqliteCreateFunction('sin', static fn ($v) => $v === null ? null : sin((float) $v), 1);
            $pdo->sqliteCreateFunction('radians', static fn ($v) => $v === null ? null : deg2rad((float) $v), 1);
            $pdo->sqliteCreateFunction('LEAST', static fn (...$args) => min(array_map('floatval', $args)));
            $pdo->sqliteCreateFunction('GREATEST', static fn (...$args) => max(array_map('floatval', $args)));
        }
    }

    /**
     * Compute a destination latitude directly North of ($originLat, $originLng) at an exact distance $distanceKm.
     * Because Δlng = 0, cos(Δlng) = 1, so:
     * cos(d / 6371) = cos(lat1)cos(lat2) + sin(lat1)sin(lat2) = cos(lat2 - lat1)
     * => lat2 = lat1 + rad2deg(d / 6371).
     */
    private function latAtExactDistanceNorth(float $originLat, float $distanceKm): float
    {
        return $originLat + rad2deg($distanceKm / 6371.0);
    }

    public function test_omitted_radius_preserves_legacy_alijis_boundary_behavior_for_home_map_and_web_portal(): void
    {
        // Pharmacy 1: Near Alijis center (10.6390, 122.9525)
        Pharmacy::create([
            'name' => 'Alijis Center Pharmacy',
            'slug' => 'alijis-center-pharmacy',
            'address' => 'Alijis Road, Bacolod City',
            'latitude' => 10.6390,
            'longitude' => 122.9525,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Pharmacy 2: Far southern corner of Alijis service area (10.6060, 122.9210)
        // More than 5 km (~7.8 km) away from a user in downtown Bacolod (10.6765, 122.9509)
        Pharmacy::create([
            'name' => 'Southern Alijis Corner Pharmacy',
            'slug' => 'southern-alijis-corner-pharmacy',
            'address' => 'South Alijis, Bacolod City',
            'latitude' => 10.6060,
            'longitude' => 122.9210,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // 1. Mobile HomeScreen call: GET /api/pharmacies/nearby (no radius parameter)
        $homeMapResponse = $this->getJson('/api/pharmacies/nearby');

        $homeMapResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'count',
                'sort',
                'radius',
                'pharmacies',
            ])
            ->assertJson([
                'success' => true,
                'count' => 2,
                'sort' => 'distance',
                'radius' => null,
            ]);

        // 2. Web Portal call from user in downtown Bacolod (>5 km away from Southern Alijis pharmacy) without radius parameter:
        //    Must NOT silently apply a 5 km cutoff; both Barangay Alijis pharmacies must be returned.
        $webPortalResponse = $this->getJson('/api/pharmacies/nearby?lat=10.6765&lng=122.9509&sort=distance');

        $webPortalResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
                'radius' => null,
            ]);

        $returnedNames = collect($webPortalResponse->json('pharmacies'))->pluck('name')->all();
        $this->assertContains('Alijis Center Pharmacy', $returnedNames);
        $this->assertContains('Southern Alijis Corner Pharmacy', $returnedNames);
        $this->assertGreaterThan(5.0, $webPortalResponse->json('pharmacies.1.distance'));
    }

    public function test_valid_discrete_integer_radii_1_2_3_4_and_5km_are_accepted(): void
    {
        Pharmacy::create([
            'name' => 'Central Alijis Drug',
            'slug' => 'central-alijis-drug',
            'address' => 'Brgy. Alijis, Bacolod City',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3, 4, 5] as $validRadius) {
            $res = $this->getJson("/api/pharmacies/nearby?lat=10.6385&lng=122.9520&radius={$validRadius}");
            $res->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'radius' => $validRadius,
                    'count' => 1,
                ]);
            $this->assertSame($validRadius, $res->json('radius'));
        }
    }

    public function test_decimal_fractional_radii_are_strictly_rejected(): void
    {
        $decimalRadii = [
            '0.5',
            '0.99',
            '1.0',
            '1.5',
            '2.5',
            '3.75',
            '4.99',
            '5.0',
            '5.1',
        ];

        foreach ($decimalRadii as $decimal) {
            $response = $this->getJson('/api/pharmacies/nearby?radius=' . urlencode($decimal));
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['radius']);
        }
    }

    public function test_radius_above_5km_below_1km_and_malformed_or_empty_values_are_rejected(): void
    {
        $invalidRadii = [
            '0',
            '-1',
            '-5',
            '6',
            '15',
            '20',
            '999',
            'NaN',
            'Infinity',
            '-Infinity',
            'abc',
            '3km',
            '',
            '   ',
        ];

        foreach ($invalidRadii as $invalid) {
            $response = $this->getJson('/api/pharmacies/nearby?radius=' . urlencode($invalid));
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['radius']);
        }

        // Bare ?radius parameter with no '=' sign must also be rejected as an empty explicit radius
        $bareResponse = $this->getJson('/api/pharmacies/nearby?radius');
        $bareResponse->assertStatus(422)
            ->assertJsonValidationErrors(['radius']);
    }

    public function test_invalid_origin_coordinates_are_rejected(): void
    {
        $invalidCoords = [
            ['lat' => '95', 'lng' => '122.9520'],
            ['lat' => '-91', 'lng' => '122.9520'],
            ['lat' => '10.6385', 'lng' => '185'],
            ['lat' => '10.6385', 'lng' => '-181'],
            ['lat' => 'NaN', 'lng' => '122.9520'],
            ['lat' => '10.6385', 'lng' => 'Infinity'],
            ['lat' => 'invalid', 'lng' => '122.9520'],
            ['lat' => '', 'lng' => '122.9520'],
            ['lat' => '10.6385'], // missing lng
            ['lng' => '122.9520'], // missing lat
        ];

        foreach ($invalidCoords as $params) {
            $query = http_build_query($params);
            $response = $this->getJson("/api/pharmacies/nearby?{$query}");
            $response->assertStatus(422);
        }
    }

    public function test_out_of_scope_origin_falls_back_to_verified_alijis_reference_point(): void
    {
        // Pharmacy right at Alijis reference point (10.6385, 122.9520)
        Pharmacy::create([
            'name' => 'Alijis Hub Pharmacy',
            'slug' => 'alijis-hub-pharmacy',
            'address' => 'Brgy. Alijis, Bacolod City',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Supply Manila coordinates (outside Barangay Alijis) with 1 km radius
        $response = $this->getJson('/api/pharmacies/nearby?lat=14.5995&lng=120.9842&radius=1');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);

        // Distance must be ~0 km from the verified Alijis reference point, not ~500 km from Manila
        $distance = $response->json('pharmacies.0.distance');
        $this->assertLessThan(0.01, $distance);
    }

    public function test_pharmacy_inside_selected_radius_at_exact_boundary_and_outside_radius(): void
    {
        $originLat = 10.6100;
        $originLng = 122.9500;

        // 1. Inside 1 km (0.5 km North)
        $latInside1Km = round($this->latAtExactDistanceNorth($originLat, 0.5), 8);
        Pharmacy::create([
            'name' => 'Inside 1km Pharmacy',
            'slug' => 'inside-1km-pharmacy',
            'address' => 'Alijis',
            'latitude' => $latInside1Km,
            'longitude' => $originLng,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // 2. Exactly at 1.0 km boundary
        $latAt1Km = round($this->latAtExactDistanceNorth($originLat, 1.0), 8);
        Pharmacy::create([
            'name' => 'Boundary 1km Pharmacy',
            'slug' => 'boundary-1km-pharmacy',
            'address' => 'Alijis',
            'latitude' => $latAt1Km,
            'longitude' => $originLng,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // 3. Outside 1 km (2.5 km North, still inside Alijis)
        $latAt2_5Km = round($this->latAtExactDistanceNorth($originLat, 2.5), 8);
        Pharmacy::create([
            'name' => 'Mid 2.5km Pharmacy',
            'slug' => 'mid-2-5km-pharmacy',
            'address' => 'Alijis',
            'latitude' => $latAt2_5Km,
            'longitude' => $originLng,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // 4. Outside 3 km (4.2 km North, still inside Alijis [10.6050..10.6750])
        $latAt4_2Km = round($this->latAtExactDistanceNorth($originLat, 4.2), 8);
        Pharmacy::create([
            'name' => 'Far 4.2km Pharmacy',
            'slug' => 'far-4-2km-pharmacy',
            'address' => 'Alijis',
            'latitude' => $latAt4_2Km,
            'longitude' => $originLng,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Query with radius = 1 km: should include Inside 1km and Boundary 1km, exclude 2.5km and 4.2km
        $res1Km = $this->getJson("/api/pharmacies/nearby?lat={$originLat}&lng={$originLng}&radius=1");
        $res1Km->assertStatus(200);
        $names1Km = collect($res1Km->json('pharmacies'))->pluck('name')->all();
        $this->assertContains('Inside 1km Pharmacy', $names1Km);
        $this->assertContains('Boundary 1km Pharmacy', $names1Km);
        $this->assertNotContains('Mid 2.5km Pharmacy', $names1Km);
        $this->assertNotContains('Far 4.2km Pharmacy', $names1Km);

        // Query with radius = 3 km: should include 0.5km, 1.0km, 2.5km, exclude 4.2km
        $res3Km = $this->getJson("/api/pharmacies/nearby?lat={$originLat}&lng={$originLng}&radius=3");
        $res3Km->assertStatus(200);
        $names3Km = collect($res3Km->json('pharmacies'))->pluck('name')->all();
        $this->assertCount(3, $names3Km);
        $this->assertNotContains('Far 4.2km Pharmacy', $names3Km);

        // Query with radius = 5 km: should include all 4 pharmacies
        $res5Km = $this->getJson("/api/pharmacies/nearby?lat={$originLat}&lng={$originLng}&radius=5");
        $res5Km->assertStatus(200);
        $this->assertCount(4, $res5Km->json('pharmacies'));
    }

    public function test_pharmacy_outside_alijis_is_excluded_even_when_within_selected_radius(): void
    {
        // Origin near the northern edge of Barangay Alijis (10.6700, 122.9500)
        $originLat = 10.6700;
        $originLng = 122.9500;

        // Pharmacy inside Alijis (0.2 km South -> 10.6682, 122.9500)
        Pharmacy::create([
            'name' => 'Inside Alijis Border Pharmacy',
            'slug' => 'inside-alijis-border-pharmacy',
            'address' => 'Brgy. Alijis, Bacolod City',
            'latitude' => 10.6682,
            'longitude' => 122.9500,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Pharmacy outside Alijis (1.0 km North -> 10.6790, 122.9500, outside 10.6750 boundary, even though < 5 km)
        Pharmacy::create([
            'name' => 'Outside Alijis Neighboring Barangay Pharmacy',
            'slug' => 'outside-alijis-pharmacy',
            'address' => 'Downtown Bacolod City',
            'latitude' => 10.6790,
            'longitude' => 122.9500,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/pharmacies/nearby?lat={$originLat}&lng={$originLng}&radius=5");
        $response->assertStatus(200);

        $names = collect($response->json('pharmacies'))->pluck('name')->all();
        $this->assertContains('Inside Alijis Border Pharmacy', $names);
        $this->assertNotContains('Outside Alijis Neighboring Barangay Pharmacy', $names);
    }

    public function test_inactive_unapproved_and_missing_coordinate_pharmacies_are_excluded(): void
    {
        // Valid active + approved
        Pharmacy::create([
            'name' => 'Valid Active Approved Pharmacy',
            'slug' => 'valid-active-approved',
            'address' => 'Alijis',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        // Inactive pharmacy
        Pharmacy::create([
            'name' => 'Inactive Pharmacy',
            'slug' => 'inactive-pharmacy',
            'address' => 'Alijis',
            'latitude' => 10.6386,
            'longitude' => 122.9521,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => false,
        ]);

        // Unapproved pharmacy
        Pharmacy::create([
            'name' => 'Unapproved Pharmacy',
            'slug' => 'unapproved-pharmacy',
            'address' => 'Alijis',
            'latitude' => 10.6387,
            'longitude' => 122.9522,
            'status' => 'pending',
            'is_approved' => false,
            'is_active' => true,
        ]);

        // Invalid/zeroed coordinates pharmacy (0.0, 0.0 and out-of-range 99.9, 250.0)
        Pharmacy::create([
            'name' => 'Zero Coordinate Pharmacy',
            'slug' => 'zero-coord-pharmacy',
            'address' => 'Alijis',
            'latitude' => 0.0,
            'longitude' => 0.0,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        Pharmacy::create([
            'name' => 'Out Of Range Coordinate Pharmacy',
            'slug' => 'out-of-range-coord-pharmacy',
            'address' => 'Alijis',
            'latitude' => 99.9999,
            'longitude' => 250.0000,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/pharmacies/nearby?radius=5');
        $response->assertStatus(200);

        $names = collect($response->json('pharmacies'))->pluck('name')->all();
        $this->assertSame(['Valid Active Approved Pharmacy'], $names);
    }

    public function test_medicine_availability_and_sorting_are_preserved_with_radius(): void
    {
        $p1 = Pharmacy::create([
            'name' => 'Closer Pricier Pharmacy',
            'slug' => 'closer-pricier',
            'address' => 'Alijis Road',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $p2 = Pharmacy::create([
            'name' => 'Slightly Further Cheaper Pharmacy',
            'slug' => 'further-cheaper',
            'address' => 'Alijis Road',
            'latitude' => 10.6420,
            'longitude' => 122.9540,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $p3 = Pharmacy::create([
            'name' => 'Out Of Stock Pharmacy',
            'slug' => 'out-of-stock-pharmacy',
            'address' => 'Alijis Road',
            'latitude' => 10.6390,
            'longitude' => 122.9525,
            'status' => 'approved',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $med = Medicine::create([
            'brand_name' => 'Biogesic',
            'generic_name' => 'Paracetamol',
            'slug' => 'biogesic-paracetamol-500mg',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'drug_category' => 'Pain Relief',
        ]);

        $p1->medicines()->attach($med->id, [
            'selling_price' => 10.00,
            'quantity_on_hand' => 20,
            'is_available' => true,
        ]);

        $p2->medicines()->attach($med->id, [
            'selling_price' => 5.50,
            'quantity_on_hand' => 100,
            'is_available' => true,
        ]);

        $p3->medicines()->attach($med->id, [
            'selling_price' => 4.00,
            'quantity_on_hand' => 0,
            'is_available' => false,
        ]);

        // Distance sort (default)
        $resDist = $this->getJson('/api/pharmacies/nearby?medicine=Paracetamol&radius=3&sort=distance');
        $resDist->assertStatus(200);
        $this->assertSame(
            ['Closer Pricier Pharmacy', 'Slightly Further Cheaper Pharmacy'],
            collect($resDist->json('pharmacies'))->pluck('name')->all()
        );

        // Price sort
        $resPrice = $this->getJson('/api/pharmacies/nearby?medicine=Paracetamol&radius=3&sort=price');
        $resPrice->assertStatus(200);
        $this->assertSame(
            ['Slightly Further Cheaper Pharmacy', 'Closer Pricier Pharmacy'],
            collect($resPrice->json('pharmacies'))->pluck('name')->all()
        );

        // Availability sort
        $resAvail = $this->getJson('/api/pharmacies/nearby?medicine=Paracetamol&radius=3&sort=availability');
        $resAvail->assertStatus(200);
        $this->assertSame(
            ['Slightly Further Cheaper Pharmacy', 'Closer Pricier Pharmacy'],
            collect($resAvail->json('pharmacies'))->pluck('name')->all()
        );
    }

    public function test_result_limiting_does_not_allow_out_of_bound_pharmacies_to_exclude_valid_alijis_pharmacies(): void
    {
        $originLat = 10.6700;
        $originLng = 122.9500;

        // Seed 25 active & approved pharmacies OUTSIDE Barangay Alijis (at 10.6790, ~1.0 km from origin)
        // These are closer to $originLat than the valid Alijis pharmacies below!
        for ($i = 1; $i <= 25; $i++) {
            Pharmacy::create([
                'name' => "Out Of Bound Pharmacy {$i}",
                'slug' => "out-of-bound-pharmacy-{$i}",
                'address' => 'Outside Alijis, Bacolod City',
                'latitude' => 10.6790 + ($i * 0.0001),
                'longitude' => 122.9500,
                'status' => 'approved',
                'is_approved' => true,
                'is_active' => true,
            ]);
        }

        // Seed 3 valid pharmacies INSIDE Barangay Alijis (~3.5 km South of origin at 10.6385, 122.9520)
        for ($j = 1; $j <= 3; $j++) {
            Pharmacy::create([
                'name' => "Valid Alijis Pharmacy {$j}",
                'slug' => "valid-alijis-pharmacy-{$j}",
                'address' => 'Brgy. Alijis, Bacolod City',
                'latitude' => 10.6385 + ($j * 0.0005),
                'longitude' => 122.9520,
                'status' => 'approved',
                'is_approved' => true,
                'is_active' => true,
            ]);
        }

        // 1. With explicit radius=5: none of the 25 closer out-of-bound pharmacies may consume the 20-item limit
        $resWithRadius = $this->getJson("/api/pharmacies/nearby?lat={$originLat}&lng={$originLng}&radius=5");
        $resWithRadius->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
                'radius' => 5,
            ]);

        $namesWithRadius = collect($resWithRadius->json('pharmacies'))->pluck('name')->all();
        $this->assertSame([
            'Valid Alijis Pharmacy 3',
            'Valid Alijis Pharmacy 2',
            'Valid Alijis Pharmacy 1',
        ], $namesWithRadius);

        // 2. With omitted radius (Home Map / Web Portal): all 3 valid Alijis pharmacies are still returned
        $resLegacy = $this->getJson("/api/pharmacies/nearby?lat={$originLat}&lng={$originLng}");
        $resLegacy->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 3,
                'radius' => null,
            ]);
    }
}
