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
use App\Notifications\DriverAssignedNotification;
use App\Services\DeliveryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

echo "===============================================================\n";
echo " GEORX PHASE 5 — DRIVER BOOKING SYSTEM VERIFICATION TESTS\n";
echo "===============================================================\n\n";

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
    ['email' => 'phase5_customer@georx.test'],
    [
        'name' => 'Phase 5 Customer',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_CUSTOMER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacyOwner = User::firstOrCreate(
    ['email' => 'phase5_pharmacy_admin@georx.test'],
    [
        'name' => 'Phase 5 Pharmacy Admin',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_PHARMACY_OWNER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacy = Pharmacy::firstOrCreate(
    ['name' => 'Alijis Phase 5 Pharmacy'],
    [
        'slug' => 'alijis-phase-5-pharmacy',
        'address' => 'Alijis Main Road, Barangay Alijis, Bacolod City',
        'latitude' => 10.63850000,
        'longitude' => 122.95200000,
        'status' => 'approved',
        'is_active' => true,
    ]
);
$pharmacyOwner->update(['pharmacy_id' => $pharmacy->id]);

$driver1 = User::firstOrCreate(
    ['email' => 'phase5_driver1@georx.test'],
    [
        'name' => 'Driver Alpha',
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
        'plate_number' => 'ABC-1234',
        'is_online' => true,
        'is_available' => true,
        'account_status' => 'approved',
        'current_latitude' => 10.63500000,
        'current_longitude' => 122.95000000,
    ]
);

$driver2 = User::firstOrCreate(
    ['email' => 'phase5_driver2@georx.test'],
    [
        'name' => 'Driver Bravo',
        'password' => bcrypt('password123'),
        'role' => User::ROLE_DRIVER,
        'account_status' => User::STATUS_APPROVED,
    ]
);
DriverProfile::firstOrCreate(
    ['user_id' => $driver2->id],
    [
        'vehicle_type' => 'motorcycle',
        'vehicle_make' => 'Yamaha',
        'vehicle_model' => 'Mio i125',
        'plate_number' => 'XYZ-5678',
        'is_online' => true,
        'is_available' => true,
        'current_latitude' => 10.64000000,
        'current_longitude' => 122.95500000,
    ]
);


// -----------------------------------------------------------------------------
// TEST 1: Haversine Distance Calculation Accuracy
// -----------------------------------------------------------------------------
runTest("Haversine distance calculation logic", function() {
    // Distance between (10.6385, 122.9520) and (10.6350, 122.9500)
    $distance = DeliveryService::calculateDistance(10.6385, 122.9520, 10.6350, 122.9500);

    if (is_null($distance)) {
        throw new Exception("Distance calculation returned null!");
    }
    if ($distance <= 0 || $distance > 2.0) {
        throw new Exception("Expected distance ~0.4 km, got {$distance} km");
    }
});


// -----------------------------------------------------------------------------
// TEST 2: Booking Card Data Structure
// -----------------------------------------------------------------------------
runTest("Available booking card structure contains required fields", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 125.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Phase 5 Test Delivery Location',
        'latitude' => 10.64100000,
        'longitude' => 122.95400000,
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    if ($delivery->status !== Delivery::STATUS_WAITING_FOR_DRIVER) {
        throw new Exception("Status should be 'waiting_for_driver'");
    }
    if (!is_null($delivery->driver_id)) {
        throw new Exception("driver_id must be NULL for available booking");
    }
    if ($delivery->pickup_pharmacy_id !== $pharmacy->id) {
        throw new Exception("Pickup pharmacy ID mismatch");
    }
});


// -----------------------------------------------------------------------------
// TEST 3: Driver Accepts Booking (Status Change & Driver Assignment)
// -----------------------------------------------------------------------------
runTest("Driver accepts booking: driver_id assigned, status updated, accepted_at recorded", function() use ($customer, $pharmacy, $driver1) {
    Notification::fake();

    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 90.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Acceptance Test Address, Alijis',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $controller = app(\App\Http\Controllers\DriverController::class);
    auth('driver')->login($driver1);

    $response = $controller->acceptBooking($delivery->id);

    $delivery->refresh();
    $order->refresh();

    if ($delivery->driver_id !== $driver1->id) {
        throw new Exception("driver_id was not set to Driver 1!");
    }
    if ($delivery->status !== Delivery::STATUS_DRIVER_ASSIGNED) {
        throw new Exception("Delivery status should be 'driver_assigned', got '{$delivery->status}'");
    }
    if ($order->status !== Order::STATUS_ACCEPTED) {
        throw new Exception("Order status should be 'accepted'");
    }
    if (is_null($delivery->driver_accepted_at)) {
        throw new Exception("driver_accepted_at timestamp was not recorded!");
    }
});


// -----------------------------------------------------------------------------
// TEST 4: Single Active Delivery Limit Enforcement
// -----------------------------------------------------------------------------
runTest("Driver cannot accept 2nd delivery while having 1 active delivery in progress", function() use ($customer, $pharmacy, $driver1) {
    $service = app(DeliveryService::class);

    // Create Order 1 and assign to Driver 1
    $order1 = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 50.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Active Delivery 1',
    ]);
    $delivery1 = $service->createDeliveryForOrder($order1);

    $controller = app(\App\Http\Controllers\DriverController::class);
    auth('driver')->login($driver1);
    $controller->acceptBooking($delivery1->id);

    // Create Order 2 (unassigned)
    $order2 = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 75.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Active Delivery 2',
    ]);
    $delivery2 = $service->createDeliveryForOrder($order2);

    // Driver 1 attempts to accept Order 2 -> Should fail
    $response = $controller->acceptBooking($delivery2->id);

    $delivery2->refresh();
    if (!is_null($delivery2->driver_id)) {
        throw new Exception("Driver was able to accept a 2nd active delivery!");
    }
});


// -----------------------------------------------------------------------------
// TEST 5: Race Condition Prevention (Prevent Double Assignment)
// -----------------------------------------------------------------------------
runTest("Atomic locking prevents 2nd driver from claiming already assigned booking", function() use ($customer, $pharmacy, $driver1, $driver2) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 150.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Race Condition Test Location',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $controller = app(\App\Http\Controllers\DriverController::class);

    // Driver 1 accepts first
    auth('driver')->login($driver1);
    $controller->acceptBooking($delivery->id);

    // Driver 2 attempts to accept same booking
    auth('driver')->login($driver2);
    $controller->acceptBooking($delivery->id);

    $delivery->refresh();
    if ($delivery->driver_id !== $driver1->id) {
        throw new Exception("Driver 2 overwrote Driver 1's claim!");
    }
});


// -----------------------------------------------------------------------------
// TEST 6: Notifications Dispatched to Customer & Pharmacy Admin
// -----------------------------------------------------------------------------
runTest("Notifications sent to customer and pharmacy on driver acceptance", function() use ($customer, $pharmacy, $pharmacyOwner, $driver1) {
    Notification::fake();

    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 60.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Notification Test',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $controller = app(\App\Http\Controllers\DriverController::class);
    auth('driver')->login($driver1);
    $controller->acceptBooking($delivery->id);

    Notification::assertSentTo($customer, DriverAssignedNotification::class);
    Notification::assertSentTo($pharmacyOwner, DriverAssignedNotification::class);
});


// -----------------------------------------------------------------------------
// TEST 7: Security Scoping (Private Delivery Authorization Check)
// -----------------------------------------------------------------------------
runTest("Driver cannot access another driver's private active delivery (403 returned)", function() use ($customer, $pharmacy, $driver1, $driver2) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 110.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Private Security Test',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    // Driver 1 accepts
    auth('driver')->login($driver1);
    $controller = app(\App\Http\Controllers\DriverController::class);
    $controller->acceptBooking($delivery->id);

    // Driver 2 attempts to view Driver 1's accepted booking
    auth('driver')->login($driver2);

    $caught403 = false;
    try {
        $controller->showBooking($delivery->id);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        if ($e->getStatusCode() === 403) {
            $caught403 = true;
        }
    }

    if (!$caught403) {
        throw new Exception("Driver 2 was not blocked from viewing Driver 1's private delivery!");
    }
});


// -----------------------------------------------------------------------------
// TEST 8: Full Delivery Progression (Assigned -> At Pharmacy -> Picked Up -> Delivered)
// -----------------------------------------------------------------------------
runTest("Full delivery status progression restores driver availability upon completion", function() use ($customer, $pharmacy, $driver1) {
    $order = Order::create([
        'user_id' => $customer->id, 'pharmacy_id' => $pharmacy->id, 'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true, 'total_amount' => 200.00, 'delivery_fee' => 50.00, 'payment_method' => 'cod',
        'delivery_address' => 'Progression Test Location',
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
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_DRIVER_AT_PHARMACY) {
        throw new Exception("Expected status driver_at_pharmacy, got {$delivery->status}");
    }

    // 3. Picked Up
    $req2 = new \Illuminate\Http\Request(['status' => 'picked_up']);
    $controller->updateStatus($req2, $delivery->id);
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_PICKED_UP) {
        throw new Exception("Expected status picked_up, got {$delivery->status}");
    }

    // 4. Out for Delivery
    $reqOut = new \Illuminate\Http\Request(['status' => 'out_for_delivery']);
    $controller->updateStatus($reqOut, $delivery->id);
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_OUT_FOR_DELIVERY) {
        throw new Exception("Expected status out_for_delivery, got {$delivery->status}");
    }

    // 5. Delivered
    $req3 = new \Illuminate\Http\Request(['status' => 'delivered']);
    $controller->updateStatus($req3, $delivery->id);
    $delivery->refresh();
    if ($delivery->status !== Delivery::STATUS_DELIVERED) {
        throw new Exception("Expected status delivered, got {$delivery->status}");
    }

    // Availability restored
    $driver1->driverProfile->refresh();
    if (!$driver1->driverProfile->is_available) {
        throw new Exception("Driver availability was not restored after delivery completion!");
    }
});


echo "\n===============================================================\n";
echo " RESULT: {$testsPassed} / {$totalTests} TESTS PASSED!\n";
echo "===============================================================\n";

if ($testsPassed !== $totalTests) {
    exit(1);
}
