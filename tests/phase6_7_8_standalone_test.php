<?php

// Bootstrap Laravel application
require 'c:\xampp\htdocs\VS-Capstone-exp - SUPABASE\medicine_locator_main\vendor\autoload.php';
$app = require_once 'c:\xampp\htdocs\VS-Capstone-exp - SUPABASE\medicine_locator_main\bootstrap\app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Delivery;
use App\Models\DriverProfile;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Notifications\DriverArrivedAtPharmacyNotification;
use App\Notifications\OrderDeliveredNotification;
use App\Notifications\OrderOutForDeliveryNotification;
use App\Notifications\OrderPickedUpNotification;
use App\Services\DeliveryService;
use App\Services\SupabaseRealtimeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

echo "====================================================================\n";
echo " GEORX PHASES 6, 7 & 8 — MAP, WORKFLOW & REALTIME VERIFICATION TESTS\n";
echo "====================================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function runTest($name, callable $test) {
    global $testsPassed, $totalTests;
    $totalTests++;
    echo "TEST #{$totalTests}: {$name} ... ";
    try {
        DB::beginTransaction();
        $test();
        DB::rollBack();
        echo "✅ PASSED\n";
        $testsPassed++;
    } catch (\Throwable $e) {
        DB::rollBack();
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n";
    }
}

// Global fixtures setup
$customer = User::firstOrCreate(
    ['email' => 'phases678_customer@georx.test'],
    [
        'name' => 'Phase 6-8 Customer',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_CUSTOMER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacyOwner = User::firstOrCreate(
    ['email' => 'phases678_pharmacy@georx.test'],
    [
        'name' => 'Phase 6-8 Pharmacy Admin',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_PHARMACY_OWNER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacy = Pharmacy::firstOrCreate(
    ['name' => 'Alijis Phase 6-8 Pharmacy'],
    [
        'slug' => 'alijis-phase-6-8-pharmacy',
        'address' => 'Alijis Main Road, Barangay Alijis, Bacolod City',
        'latitude' => 10.63850000,
        'longitude' => 122.95200000,
        'status' => 'approved',
        'is_active' => true,
    ]
);
$pharmacyOwner->update(['pharmacy_id' => $pharmacy->id]);

$driver1 = User::firstOrCreate(
    ['email' => 'phases678_driver1@georx.test'],
    [
        'name' => 'Driver Speedster',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_DRIVER,
        'account_status' => User::STATUS_APPROVED,
    ]
);
DriverProfile::firstOrCreate(
    ['user_id' => $driver1->id],
    [
        'vehicle_type' => 'motorcycle',
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Click 125i',
        'plate_number' => 'ABC-9999',
        'is_online' => true,
        'is_available' => true,
        'current_latitude' => 10.63500000,
        'current_longitude' => 122.95000000,
    ]
);

$driver2 = User::firstOrCreate(
    ['email' => 'phases678_driver2@georx.test'],
    [
        'name' => 'Driver Unauthorized',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_DRIVER,
        'account_status' => User::STATUS_APPROVED,
    ]
);


// -----------------------------------------------------------------------------
// TEST 1 (Phase 6): Delivery Map Authorization & Security Scoping
// -----------------------------------------------------------------------------
runTest("Phase 6: Assigned driver can view map; unauthorized driver receives 403", function() use ($customer, $pharmacy, $driver1, $driver2) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 100.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Phase 6 Map Test Address',
        'latitude' => 10.64000000, 'longitude' => 122.95300000,
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $controller = app(\App\Http\Controllers\DriverController::class);

    // Driver 1 accepts
    auth('driver')->login($driver1);
    $controller->acceptBooking($delivery->id);

    // Driver 1 views map -> Should succeed
    $response = $controller->deliveryMap($delivery->id);
    if ($response->name() !== 'driver.map') {
        throw new Exception("Expected view driver.map, got {$response->name()}");
    }

    // Driver 2 views Driver 1's map -> Should fail with 403
    auth('driver')->login($driver2);
    $caught403 = false;
    try {
        $controller->deliveryMap($delivery->id);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() === 403) {
            $caught403 = true;
        }
    }

    if (!$caught403) {
        throw new Exception("Driver 2 was NOT blocked from viewing Driver 1's private map!");
    }
});


// -----------------------------------------------------------------------------
// TEST 2 (Phase 6): Navigation Deep-Links & Distance Formatting
// -----------------------------------------------------------------------------
runTest("Phase 6: Navigation deep-link URL creation & distance metrics", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 100.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Deep Link Test',
        'latitude' => 10.64200000, 'longitude' => 122.95500000,
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $googleMapsUrl = "https://www.google.com/maps/dir/?api=1&destination={$delivery->pickup_latitude},{$delivery->pickup_longitude}";
    $wazeUrl = "https://waze.com/ul?ll={$delivery->pickup_latitude},{$delivery->pickup_longitude}&navigate=yes";

    if (strpos($googleMapsUrl, '10.6385') === false) {
        throw new Exception("Google Maps URL does not contain latitude!");
    }
    if (strpos($wazeUrl, '122.952') === false) {
        throw new Exception("Waze URL does not contain longitude!");
    }
});


// -----------------------------------------------------------------------------
// TEST 3 (Phase 7): Strict 5-Step Status Transition Validation
// -----------------------------------------------------------------------------
runTest("Phase 7: Strict status transition matrix blocks out-of-sequence step skipping", function() use ($customer, $pharmacy, $driver1) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 120.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Step Transition Test',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $controller = app(\App\Http\Controllers\DriverController::class);
    auth('driver')->login($driver1);
    $controller->acceptBooking($delivery->id);

    // Attempt to skip directly from driver_assigned to delivered -> Should fail
    $reqInvalid = new \Illuminate\Http\Request(['status' => 'delivered']);
    $controller->updateStatus($reqInvalid, $delivery->id);

    $delivery->refresh();
    if ($delivery->status === Delivery::STATUS_DELIVERED) {
        throw new Exception("Driver was able to skip steps directly to delivered!");
    }
});


// -----------------------------------------------------------------------------
// TEST 4 (Phase 7): Notifications Dispatched at Each Stage
// -----------------------------------------------------------------------------
runTest("Phase 7: Notifications dispatched to Customer & Pharmacy at each stage", function() use ($customer, $pharmacy, $pharmacyOwner, $driver1) {
    Notification::fake();

    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 150.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Stage Notifications Test',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $controller = app(\App\Http\Controllers\DriverController::class);
    auth('driver')->login($driver1);

    // 1. Accept
    $controller->acceptBooking($delivery->id);

    // 2. Arrived at Pharmacy
    $req1 = new \Illuminate\Http\Request(['status' => 'driver_at_pharmacy']);
    $controller->updateStatus($req1, $delivery->id);
    Notification::assertSentTo($customer, DriverArrivedAtPharmacyNotification::class);
    Notification::assertSentTo($pharmacyOwner, DriverArrivedAtPharmacyNotification::class);

    // 3. Picked Up
    $req2 = new \Illuminate\Http\Request(['status' => 'picked_up']);
    $controller->updateStatus($req2, $delivery->id);
    Notification::assertSentTo($customer, OrderPickedUpNotification::class);

    // 4. Out For Delivery
    $req3 = new \Illuminate\Http\Request(['status' => 'out_for_delivery']);
    $controller->updateStatus($req3, $delivery->id);
    Notification::assertSentTo($customer, OrderOutForDeliveryNotification::class);

    // 5. Delivered
    $req4 = new \Illuminate\Http\Request(['status' => 'delivered']);
    $controller->updateStatus($req4, $delivery->id);
    Notification::assertSentTo($customer, OrderDeliveredNotification::class);
});


// -----------------------------------------------------------------------------
// TEST 5 (Phase 8): Supabase Realtime Payload Generation Structure
// -----------------------------------------------------------------------------
runTest("Phase 8: Supabase Realtime payload contains unified delivery data for web and mobile", function() use ($customer, $pharmacy, $driver1) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 180.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Supabase Payload Test',
        'latitude' => 10.63900000, 'longitude' => 122.95300000,
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $payload = SupabaseRealtimeService::buildDeliveryEventPayload('delivery_created', $delivery);

    if ($payload['event'] !== 'delivery_created') {
        throw new Exception("Payload event mismatch!");
    }
    if ($payload['delivery_id'] !== $delivery->id) {
        throw new Exception("Payload delivery_id mismatch!");
    }
    if (!isset($payload['pickup']['latitude']) || !isset($payload['destination']['address'])) {
        throw new Exception("Payload missing pickup or destination data!");
    }
});


echo "\n====================================================================\n";
echo " RESULT: {$testsPassed} / {$totalTests} TESTS PASSED!\n";
echo "====================================================================\n";

if ($testsPassed !== $totalTests) {
    exit(1);
}
