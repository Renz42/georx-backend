<?php

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use App\Models\User;
use App\Models\DriverProfile;
use App\Models\Pharmacy;
use Illuminate\Support\Facades\Hash;

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

echo "====================================================\n";
echo "  GEORX MOBILE APP ROUTE & LOGIC VERIFICATION       \n";
echo "====================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest($condition, $description) {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo "✅ PASS: {$description}\n";
    } else {
        echo "❌ FAIL: {$description}\n";
    }
}

try {
    // 1. Verify Geofence Validation Route logic
    $req = \Illuminate\Http\Request::create('/api/geofence/validate', 'POST', [
        'latitude' => 10.6402,
        'longitude' => 122.9460,
    ]);
    $req->headers->set('Accept', 'application/json');
    $response = $kernel->handle($req);
    assertTest($response->getStatusCode() === 200, "POST /api/geofence/validate returns 200 OK");
    $data = json_decode($response->getContent(), true);
    assertTest(($data['is_valid'] ?? false) === true, "Alijis coordinates validated as TRUE inside boundary");
    $kernel->terminate($req, $response);

    // 2. Out of boundary check
    $req = \Illuminate\Http\Request::create('/api/geofence/validate', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
    ]);
    $req->headers->set('Accept', 'application/json');
    $response = $kernel->handle($req);
    $data = json_decode($response->getContent(), true);
    assertTest(($data['is_valid'] ?? true) === false, "Out of boundary coordinates validated as FALSE");
    $kernel->terminate($req, $response);

    // 3. Verify mobile controllers exist and can be instantiated
    $authCtrl = new \App\Http\Controllers\Api\AuthController();
    assertTest(method_exists($authCtrl, 'login'), "Api\\AuthController has login method");
    assertTest(method_exists($authCtrl, 'toggleOnline'), "Api\\AuthController has toggleOnline method");

    $mobileOrderCtrl = new \App\Http\Controllers\Api\MobileOrderController();
    assertTest(method_exists($mobileOrderCtrl, 'index'), "Api\\MobileOrderController has index method");
    assertTest(method_exists($mobileOrderCtrl, 'show'), "Api\\MobileOrderController has show method");
    assertTest(method_exists($mobileOrderCtrl, 'liveTracking'), "Api\\MobileOrderController has liveTracking method");

    $mobileDriverCtrl = new \App\Http\Controllers\Api\MobileDriverController();
    assertTest(method_exists($mobileDriverCtrl, 'availableBookings'), "Api\\MobileDriverController has availableBookings method");
    assertTest(method_exists($mobileDriverCtrl, 'acceptBooking'), "Api\\MobileDriverController has acceptBooking method");
    assertTest(method_exists($mobileDriverCtrl, 'updateStatus'), "Api\\MobileDriverController has updateStatus method");
    assertTest(method_exists($mobileDriverCtrl, 'updateLocation'), "Api\\MobileDriverController has updateLocation method");
    assertTest(method_exists($mobileDriverCtrl, 'history'), "Api\\MobileDriverController has history method");

    echo "\n----------------------------------------------------\n";
    echo "SUMMARY: {$testsPassed} / {$totalTests} TESTS PASSED\n";
    echo "====================================================\n";

} catch (\Exception $e) {
    echo "💥 EXCEPTION: " . $e->getMessage() . "\n";
}
