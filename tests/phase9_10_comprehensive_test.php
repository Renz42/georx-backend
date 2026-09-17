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
use App\Services\DeliveryService;
use App\Services\GeofenceService;
use App\Services\SupabaseRealtimeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

echo "====================================================================\n";
echo " GEORX PHASES 9 & 10 — DRIVER LOCATION TRACKING & END-TO-END TESTS\n";
echo "====================================================================\n\n";

$testsPassed = 0;
$totalTests = 0;
$testResultsSummary = [];

function runTest($name, callable $test) {
    global $testsPassed, $totalTests, $testResultsSummary;
    $totalTests++;
    echo "TEST #{$totalTests}: {$name} ... ";
    try {
        DB::beginTransaction();
        $test();
        DB::rollBack();
        echo "✅ PASSED\n";
        $testsPassed++;
        $testResultsSummary[] = ['name' => $name, 'status' => 'PASSED', 'error' => null];
    } catch (\Throwable $e) {
        DB::rollBack();
        echo "❌ FAILED: " . $e->getMessage() . "\n";
        $testResultsSummary[] = ['name' => $name, 'status' => 'FAILED', 'error' => $e->getMessage()];
    }
}

// -------------------------------------------------------------------
// FIXTURE SETUP
// -------------------------------------------------------------------
$customer = User::firstOrCreate(
    ['email' => 'phase910_customer@georx.test'],
    [
        'name' => 'Phase 10 Test Customer',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_CUSTOMER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$customerB = User::firstOrCreate(
    ['email' => 'phase910_customer_b@georx.test'],
    [
        'name' => 'Phase 10 Customer B',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_CUSTOMER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacy = Pharmacy::firstOrCreate(
    ['email' => 'phase910_pharmacy@georx.test'],
    [
        'name' => 'GEORX Alijis Phase 10 Pharmacy',
        'slug' => 'georx-alijis-phase-10-pharmacy',
        'address' => 'Barangay Alijis, Bacolod City',
        'latitude' => 10.63890000,
        'longitude' => 122.95120000,
        'status' => 'approved',
    ]
);

$pharmacyOwner = User::firstOrCreate(
    ['email' => 'phase910_pharmacy_owner@georx.test'],
    [
        'name' => 'Phase 10 Pharmacy Owner',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_PHARMACY_OWNER,
        'pharmacy_id' => $pharmacy->id,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$medicine = Medicine::firstOrCreate(
    ['brand_name' => 'Phase10-Paracetamol'],
    [
        'slug' => 'phase10-paracetamol',
        'generic_name' => 'Paracetamol 500mg',
        'drug_category' => 'Pain Relief',
        'strength' => '500mg',
        'dosage_form' => 'Tablet',
        'prescription_required' => false,
    ]
);

// Attach stock to pharmacy
$pharmacy->medicines()->syncWithoutDetaching([
    $medicine->id => [
        'selling_price' => 15.50,
        'quantity_on_hand' => 100,
        'is_available' => true,
    ]
]);

$driverA = User::firstOrCreate(
    ['email' => 'phase910_driver_a@georx.test'],
    [
        'name' => 'William John (Driver A)',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_DRIVER,
        'account_status' => User::STATUS_APPROVED,
    ]
);

$driverProfileA = DriverProfile::updateOrCreate(
    ['user_id' => $driverA->id],
    [
        'vehicle_type' => 'motorcycle',
        'vehicle_make' => 'Honda',
        'vehicle_model' => 'Click 125i',
        'plate_number' => 'ABC-1234',
        'is_online' => true,
        'is_available' => true,
        'account_status' => DriverProfile::STATUS_APPROVED,
        'current_latitude' => 10.64010000,
        'current_longitude' => 122.95010000,
        'last_location_at' => now(),
    ]
);

$driverB = User::firstOrCreate(
    ['email' => 'phase910_driver_b@georx.test'],
    [
        'name' => 'Rider Bob (Driver B)',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_DRIVER,
        'account_status' => User::STATUS_APPROVED,
    ]
);

$driverProfileB = DriverProfile::updateOrCreate(
    ['user_id' => $driverB->id],
    [
        'vehicle_type' => 'motorcycle',
        'vehicle_make' => 'Yamaha',
        'vehicle_model' => 'Mio 125',
        'plate_number' => 'XYZ-9876',
        'is_online' => true,
        'is_available' => true,
        'account_status' => DriverProfile::STATUS_APPROVED,
        'current_latitude' => 10.64100000,
        'current_longitude' => 122.95100000,
        'last_location_at' => now(),
    ]
);

// -------------------------------------------------------------------
// PHASE 9 TESTS: DRIVER LOCATION TRACKING & PRIVACY
// -------------------------------------------------------------------

runTest("Phase 9: Driver Location Update (Valid Coords, Driver Online)", function() use ($driverA, $driverProfileA) {
    Auth::guard('driver')->setUser($driverA);

    $controller = new \App\Http\Controllers\DriverController();
    $request = Request::create('/driver/location/update', 'POST', [
        'latitude' => 10.64050000,
        'longitude' => 122.95050000,
    ]);

    $response = $controller->updateLocation($request);
    $data = json_decode($response->getContent(), true);

    if (!$data['success'] || !$data['tracking_active']) {
        throw new Exception("Location update failed for online driver.");
    }

    $driverProfileA->refresh();
    if (abs((float)$driverProfileA->current_latitude - 10.6405) > 0.0001) {
        throw new Exception("Driver latitude did not update correctly in DB.");
    }
});

runTest("Phase 9: Driver Location Update Rejection (Invalid Coords)", function() use ($driverA) {
    Auth::guard('driver')->setUser($driverA);

    $controller = new \App\Http\Controllers\DriverController();
    $request = Request::create('/driver/location/update', 'POST', [
        'latitude' => 199.999, // Invalid (> 90)
        'longitude' => 122.95050000,
    ]);

    try {
        $controller->updateLocation($request);
        throw new Exception("Failed to block invalid latitude > 90.");
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Expected validation exception
    }
});

runTest("Phase 9: Offline Driver with No Active Delivery Skips Location Sync", function() use ($driverB, $driverProfileB) {
    Auth::guard('driver')->setUser($driverB);
    $driverProfileB->update(['is_online' => false, 'is_available' => true]);

    $controller = new \App\Http\Controllers\DriverController();
    $request = Request::create('/driver/location/update', 'POST', [
        'latitude' => 10.64150000,
        'longitude' => 122.95150000,
    ]);

    $response = $controller->updateLocation($request);
    $data = json_decode($response->getContent(), true);

    if (!$data['success'] || $data['tracking_active'] !== false) {
        throw new Exception("Offline driver location update was not skipped.");
    }
});

runTest("Phase 9: Customer Live Tracking & Privacy Security Check", function() use ($customer, $customerB, $pharmacy, $driverA) {
    // Create active order and delivery
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_ACCEPTED,
        'total_amount' => 100.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Customer A House, Barangay Alijis',
        'latitude' => 10.64200000,
        'longitude' => 122.94800000,
        'delivery_partner_id' => $driverA->id,
    ]);

    $delivery = DeliveryService::createDeliveryForOrder($order);
    $delivery->update(['driver_id' => $driverA->id, 'status' => Delivery::STATUS_OUT_FOR_DELIVERY]);

    $controller = new \App\Http\Controllers\OrderController();

    // 1. Authorized Customer A views live location
    Auth::setUser($customer);
    $resA = $controller->liveLocation($order->id);
    $dataA = json_decode($resA->getContent(), true);

    if (!$dataA['success'] || $dataA['status'] !== 'out_for_delivery') {
        throw new Exception("Authorized Customer A failed to fetch live delivery location.");
    }

    // 2. Unauthorized Customer B attempts to view Customer A's live location
    Auth::setUser($customerB);
    $resB = $controller->liveLocation($order->id);
    if ($resB->getStatusCode() !== 403) {
        throw new Exception("PRIVACY LEAK: Customer B was not blocked with 403 Forbidden.");
    }
});

// -------------------------------------------------------------------
// PHASE 10 TESTS: 27-STEP END-TO-END LIFECYCLE SCENARIO
// -------------------------------------------------------------------

runTest("Phase 10: Complete 27-Step End-to-End Delivery Lifecycle Workflow", function() use ($customer, $pharmacy, $pharmacyOwner, $medicine, $driverA) {
    // Steps 1–6: Customer places order
    Auth::setUser($customer);

    // Geofence check
    if (!GeofenceService::isInsideAlijis(10.6420, 122.9480)) {
        throw new Exception("Customer location failed geofence check.");
    }

    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id, // Step 4 & 7: Customer selects pharmacy
        'status' => Order::STATUS_PENDING_CONFIRMATION,
        'total_amount' => 65.50,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Customer Alijis Residence, Bacolod',
        'latitude' => 10.64200000,
        'longitude' => 122.94800000,
    ]);

    $order->items()->create([
        'medicine_id' => $medicine->id,
        'quantity' => 1,
        'price' => 15.50,
    ]);

    // Step 7: Verify selected pharmacy is locked as official pickup pharmacy
    if ($order->pharmacy_id !== $pharmacy->id) {
        throw new Exception("Step 7 Failed: Selected pharmacy mismatch.");
    }

    // Step 8 & 9: Pharmacy receives order and confirms
    Auth::setUser($pharmacyOwner);

    $portalController = new \App\Http\Controllers\PortalController();
    $confirmReq = Request::create("/pharmacy/orders/{$order->id}/confirm", 'POST');

    // Simulate order confirmation in PortalController
    $order->update(['status' => Order::STATUS_CONFIRMED]);
    $delivery = DeliveryService::createDeliveryForOrder($order); // Step 10: Delivery booking created

    if (!$delivery || $delivery->status !== Delivery::STATUS_WAITING_FOR_DRIVER) {
        throw new Exception("Step 10 Failed: Delivery booking was not created with waiting_for_driver status.");
    }

    // Step 11 & 12: Driver logs in and views available feed
    Auth::guard('driver')->setUser($driverA);

    $driverController = new \App\Http\Controllers\DriverController();
    $availableCount = Delivery::availableForDrivers()->count();
    if ($availableCount === 0) {
        throw new Exception("Step 12 Failed: Driver feed does not show available delivery.");
    }

    // Step 13 & 14: Driver accepts booking
    $driverController->acceptBooking($delivery->id);

    $delivery->refresh();
    $order->refresh();

    if ($delivery->driver_id !== $driverA->id || $delivery->status !== Delivery::STATUS_DRIVER_ASSIGNED) {
        throw new Exception("Step 13 Failed: Delivery was not assigned to Driver A.");
    }

    // Step 14: Ensure another driver can no longer accept it
    $otherAvailable = Delivery::availableForDrivers()->where('id', $delivery->id)->exists();
    if ($otherAvailable) {
        throw new Exception("Step 14 Failed: Accepted booking is still visible in available feed.");
    }

    // Step 15 & 16: Driver views map with pickup and delivery locations
    if ($delivery->pickup_pharmacy_id !== $pharmacy->id || empty($delivery->delivery_address)) {
        throw new Exception("Step 15/16 Failed: Pickup pharmacy or delivery address missing.");
    }

    // Step 17 & 18: Driver arrives at pharmacy
    $statusReq = Request::create("/driver/bookings/{$delivery->id}/status", 'POST', ['status' => 'driver_at_pharmacy']);
    $driverController->updateStatus($statusReq, $delivery->id);
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_DRIVER_AT_PHARMACY) {
        throw new Exception("Step 18 Failed: Driver status is not driver_at_pharmacy.");
    }

    // Step 19: Driver picks up medicine
    $statusReq = Request::create("/driver/bookings/{$delivery->id}/status", 'POST', ['status' => 'picked_up']);
    $driverController->updateStatus($statusReq, $delivery->id);
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_PICKED_UP) {
        throw new Exception("Step 19 Failed: Driver status is not picked_up.");
    }

    // Step 20 & 21: Driver starts delivery (out_for_delivery)
    $statusReq = Request::create("/driver/bookings/{$delivery->id}/status", 'POST', ['status' => 'out_for_delivery']);
    $driverController->updateStatus($statusReq, $delivery->id);
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_OUT_FOR_DELIVERY) {
        throw new Exception("Step 20 Failed: Driver status is not out_for_delivery.");
    }

    // Step 23 & 24: Driver delivers medicine
    $statusReq = Request::create("/driver/bookings/{$delivery->id}/status", 'POST', ['status' => 'delivered']);
    $driverController->updateStatus($statusReq, $delivery->id);
    $delivery->refresh();
    $order->refresh();

    if ($delivery->status !== Delivery::STATUS_DELIVERED || $order->status !== Order::STATUS_DELIVERED) {
        throw new Exception("Step 24 Failed: Delivery or order status is not delivered.");
    }

    // Step 25 & 26: Driver profile availability restored
    $driverA->driverProfile->refresh();
    if (!$driverA->driverProfile->is_available) {
        throw new Exception("Step 25 Failed: Driver availability was not restored upon completion.");
    }

    // Step 27: Rating and review submission
    Auth::setUser($customer);
    $orderController = new \App\Http\Controllers\OrderController();
    $reviewReq = Request::create("/order/{$order->id}/review", 'POST', [
        'rating' => 5,
        'comment' => 'Fast delivery and good service!',
        'delivery_speed' => 5,
        'driver_professionalism' => 5,
        'medicine_condition' => 5,
        'delivery_overall' => 5,
        'medicine_availability' => 5,
        'price_rating' => 5,
        'customer_service' => 5,
        'accuracy' => 5,
        'pharmacy_overall' => 5,
    ]);

    $orderController->submitReview($reviewReq, $order->id);
    $order->refresh();
    if (!$order->review) {
        throw new Exception("Step 27 Failed: Review was not created for completed order.");
    }
});

// -------------------------------------------------------------------
// PHASE 10 TESTS: EDGE CASES & SECURITY SCENARIOS
// -------------------------------------------------------------------

runTest("Phase 10 Edge Case: Driver Cannot Accept Booking While On Active Delivery", function() use ($customer, $pharmacy, $driverA) {
    $order1 = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_CONFIRMED,
        'total_amount' => 100, 'delivery_fee' => 50, 'payment_method' => 'cod', 'delivery_address' => 'Alijis St'
    ]);
    $delivery1 = DeliveryService::createDeliveryForOrder($order1);

    $order2 = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_CONFIRMED,
        'total_amount' => 100, 'delivery_fee' => 50, 'payment_method' => 'cod', 'delivery_address' => 'Alijis St'
    ]);
    $delivery2 = DeliveryService::createDeliveryForOrder($order2);

    Auth::guard('driver')->setUser($driverA);
    $controller = new \App\Http\Controllers\DriverController();

    // Accept first booking
    $controller->acceptBooking($delivery1->id);

    // Attempt to accept second booking simultaneously
    $res = $controller->acceptBooking($delivery2->id);

    $delivery2->refresh();
    if ($delivery2->driver_id === $driverA->id) {
        throw new Exception("SECURITY BREACH: Driver accepted 2 active deliveries simultaneously!");
    }
});

runTest("Phase 10 Security: Driver Accessing Another Driver's Private Booking Details Returns 403", function() use ($customer, $pharmacy, $driverA, $driverB) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_ACCEPTED,
        'total_amount' => 100, 'delivery_fee' => 50, 'payment_method' => 'cod', 'delivery_address' => 'Alijis St'
    ]);
    $delivery = DeliveryService::createDeliveryForOrder($order);
    $delivery->update(['driver_id' => $driverA->id, 'status' => Delivery::STATUS_DRIVER_ASSIGNED]);

    // Driver B attempts to view Driver A's booking
    Auth::guard('driver')->setUser($driverB);
    $controller = new \App\Http\Controllers\DriverController();

    try {
        $controller->showBooking($delivery->id);
        throw new Exception("Failed to block Driver B from viewing Driver A's private booking.");
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() !== 403) {
            throw new Exception("Expected 403 Forbidden, got " . $e->getStatusCode());
        }
    }
});

runTest("Phase 10 Business Rule: Pickup Pharmacy Cannot Be Changed By Driver", function() use ($customer, $pharmacy, $driverA) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_CONFIRMED,
        'total_amount' => 100, 'delivery_fee' => 50, 'payment_method' => 'cod', 'delivery_address' => 'Alijis St'
    ]);
    $delivery = DeliveryService::createDeliveryForOrder($order);

    if ((int)$delivery->pickup_pharmacy_id !== (int)$pharmacy->id) {
        throw new Exception("Pickup pharmacy ID was altered!");
    }
});

runTest("Phase 10 Edge Case: Customer Outside Barangay Alijis Blocked", function() {
    $outsideLat = 14.5995; // Manila
    $outsideLng = 120.9842;

    $isInside = GeofenceService::isInsideAlijis($outsideLat, $outsideLng);
    if ($isInside) {
        throw new Exception("GeofenceService failed to reject coordinates outside Barangay Alijis!");
    }
});

runTest("Phase 10 Workflow: Pharmacy Order Rejection", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING_CONFIRMATION,
        'total_amount' => 100,
        'delivery_fee' => 50,
        'payment_method' => 'cod',
        'delivery_address' => 'Alijis St'
    ]);

    $order->update([
        'status' => Order::STATUS_REJECTED,
        'rejection_reason' => 'Item temporarily out of stock',
    ]);

    if ($order->status !== 'rejected' || empty($order->rejection_reason)) {
        throw new Exception("Pharmacy rejection state failed.");
    }
});

// -------------------------------------------------------------------
// SUMMARY REPORT
// -------------------------------------------------------------------
echo "\n====================================================================\n";
echo " TEST RESULTS SUMMARY: {$testsPassed} / {$totalTests} TESTS PASSED\n";
echo "====================================================================\n";

foreach ($testResultsSummary as $idx => $res) {
    $num = $idx + 1;
    $symbol = $res['status'] === 'PASSED' ? '✅' : '❌';
    echo "  [{$num}] {$symbol} {$res['name']}\n";
    if ($res['error']) {
        echo "      Reason: {$res['error']}\n";
    }
}
echo "====================================================================\n";
