<?php

// Bootstrap Laravel application
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Support\Facades\DB;

echo "===============================================================\n";
echo " GEORX PHASE 4 — CUSTOMER ORDER TO DELIVERY BOOKING VERIFICATION \n";
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

// Setup test environment data helpers
$customer = User::firstOrCreate(
    ['email' => 'phase4_customer_test@georx.test'],
    [
        'name' => 'Phase 4 Test Customer',
        'password' => bcrypt('password'),
        'role' => User::ROLE_CUSTOMER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacyOwner = User::firstOrCreate(
    ['email' => 'phase4_pharmacy_test@georx.test'],
    [
        'name' => 'Phase 4 Pharmacy Admin',
        'password' => bcrypt('password'),
        'role' => User::ROLE_PHARMACY_OWNER,
        'account_status' => User::STATUS_ACTIVE,
    ]
);

$pharmacy = Pharmacy::firstOrCreate(
    ['name' => 'Alijis Official Test Pharmacy'],
    [
        'slug' => 'alijis-official-test-pharmacy',
        'address' => 'Alijis Main Road, Barangay Alijis, Bacolod City',
        'latitude' => 10.63850000,
        'longitude' => 122.95200000,
        'status' => 'approved',
        'is_active' => true,
    ]
);

$pharmacyOwner->update(['pharmacy_id' => $pharmacy->id]);

$medicine = Medicine::firstOrCreate(
    ['generic_name' => 'Amoxicillin 500mg (Phase4)'],
    [
        'slug' => 'amoxicillin-500mg-phase4',
        'brand_name' => 'Amoxil Test',
        'category' => 'Antibiotic',
        'requires_prescription' => true,
    ]
);

DB::table('pharmacy_medicine')->updateOrInsert(
    ['pharmacy_id' => $pharmacy->id, 'medicine_id' => $medicine->id],
    ['selling_price' => 25.00, 'quantity_on_hand' => 100]
);


// -----------------------------------------------------------------------------
// TEST 1: Pharmacy Coordinates are Locked as Official Pickup Location
// -----------------------------------------------------------------------------
runTest("Customer selects pharmacy; that pharmacy is locked as official pickup location", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 100.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'House 42, Alijis Village, Bacolod City',
        'latitude' => 10.63900000,
        'longitude' => 122.95300000,
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    if ($delivery->pickup_pharmacy_id !== $pharmacy->id) {
        throw new Exception("Pickup pharmacy ID mismatch!");
    }
    if ((float)$delivery->pickup_latitude !== 10.63850000 || (float)$delivery->pickup_longitude !== 122.95200000) {
        throw new Exception("Pickup latitude/longitude snapshot does not match pharmacy's stored location!");
    }
});


// -----------------------------------------------------------------------------
// TEST 2: Delivery Record Data Integrity & Initial Status
// -----------------------------------------------------------------------------
runTest("Delivery record contains required fields (order_id, driver_id=NULL, status=waiting_for_driver)", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 150.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Street 10, Barangay Alijis',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    if (!is_null($delivery->driver_id)) {
        throw new Exception("driver_id must be NULL initially!");
    }
    if ($delivery->status !== Delivery::STATUS_WAITING_FOR_DRIVER) {
        throw new Exception("Delivery status must be 'waiting_for_driver' initially!");
    }
    if ($delivery->order_id !== $order->id) {
        throw new Exception("Delivery order_id mismatch!");
    }
});


// -----------------------------------------------------------------------------
// TEST 3: Duplicate Delivery Booking Prevention
// -----------------------------------------------------------------------------
runTest("Prevent duplicate delivery bookings for the same order", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 75.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Corner Alijis Road',
    ]);

    $service = app(DeliveryService::class);
    $delivery1 = $service->createDeliveryForOrder($order);
    $delivery2 = $service->createDeliveryForOrder($order);

    if ($delivery1->id !== $delivery2->id) {
        throw new Exception("Duplicate delivery booking was created!");
    }

    $count = Delivery::where('order_id', $order->id)->count();
    if ($count !== 1) {
        throw new Exception("Expected 1 delivery record, found {$count}");
    }
});


// -----------------------------------------------------------------------------
// TEST 4: Safe Manual Delivery Address Workflow when GPS Perms Denied
// -----------------------------------------------------------------------------
runTest("Support manual delivery address workflow when GPS is missing/denied", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 120.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Alijis Manual Text Address Only (No GPS)',
        'latitude' => null,
        'longitude' => null,
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    if (empty($delivery->delivery_address)) {
        throw new Exception("Delivery address must not be empty!");
    }
    if (!is_null($delivery->delivery_latitude) || !is_null($delivery->delivery_longitude)) {
        throw new Exception("Expected NULL latitude/longitude when GPS is denied!");
    }
    if ($delivery->status !== Delivery::STATUS_WAITING_FOR_DRIVER) {
        throw new Exception("Delivery creation failed for GPS-less order!");
    }
});


// -----------------------------------------------------------------------------
// TEST 5: Geofence Enforcement (Barangay Alijis Service Area)
// -----------------------------------------------------------------------------
runTest("Geofence enforces Barangay Alijis geographic boundary", function() {
    // Coords inside Barangay Alijis (e.g. 10.6385, 122.9520)
    $inside = \App\Services\GeofenceService::isInsideAlijis(10.6385, 122.9520);
    if (!$inside) {
        throw new Exception("Point (10.6385, 122.9520) should be inside Barangay Alijis!");
    }

    // Coords far outside (e.g. Manila 14.5995, 120.9842 or Tacloban)
    $outside = \App\Services\GeofenceService::isInsideAlijis(14.5995, 120.9842);
    if ($outside) {
        throw new Exception("Manila coords (14.5995, 120.9842) should be OUTSIDE Barangay Alijis boundary!");
    }
});


// -----------------------------------------------------------------------------
// TEST 6: PortalController Confirmation Hook Creates Delivery Booking
// -----------------------------------------------------------------------------
runTest("Pharmacy confirmation hook in PortalController creates delivery booking", function() use ($customer, $pharmacy, $pharmacyOwner, $medicine) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING_CONFIRMATION,
        'total_amount' => 100.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Barangay Alijis Center Street',
        'latitude' => 10.63850000,
        'longitude' => 122.95200000,
    ]);

    $order->items()->create([
        'medicine_id' => $medicine->id,
        'quantity' => 1,
        'price' => 25.00,
    ]);

    // Simulate calling PortalController confirmOrder
    $controller = app(\App\Http\Controllers\PortalController::class);
    
    // Auth as pharmacy owner
    auth()->login($pharmacyOwner);

    $request = new \Illuminate\Http\Request();
    $inventoryService = app(\App\Services\InventoryService::class);
    $maximService = app(\App\Services\MaximDeliveryService::class);
    $deliveryService = app(\App\Services\DeliveryService::class);

    $controller->confirmOrder($request, $inventoryService, $maximService, $deliveryService, $order->id);

    $order->refresh();
    if ($order->status !== Order::STATUS_PENDING) {
        throw new Exception("Order status should be 'pending' after confirmation!");
    }

    $delivery = Delivery::where('order_id', $order->id)->first();
    if (!$delivery) {
        throw new Exception("Delivery record was not created on pharmacy confirmation!");
    }
    if ($delivery->pickup_pharmacy_id !== $pharmacy->id) {
        throw new Exception("Delivery pickup pharmacy mismatch!");
    }
});


// -----------------------------------------------------------------------------
// TEST 7: Order -> Delivery Relationship
// -----------------------------------------------------------------------------
runTest("Order model hasOne Delivery relationship works seamlessly", function() use ($customer, $pharmacy) {
    $order = Order::create([
        'user_id' => $customer->id,
        'pharmacy_id' => $pharmacy->id,
        'status' => Order::STATUS_PENDING,
        'pharmacy_confirmed' => true,
        'total_amount' => 100.00,
        'delivery_fee' => 50.00,
        'payment_method' => 'cod',
        'delivery_address' => 'Relationship Test Address',
    ]);

    $service = app(DeliveryService::class);
    $delivery = $service->createDeliveryForOrder($order);

    $loadedDelivery = $order->fresh()->delivery;
    if (!$loadedDelivery || $loadedDelivery->id !== $delivery->id) {
        throw new Exception("Order->delivery relationship failed to load delivery!");
    }
});


echo "\n===============================================================\n";
echo " RESULT: {$testsPassed} / {$totalTests} TESTS PASSED!\n";
echo "===============================================================\n";

if ($testsPassed !== $totalTests) {
    exit(1);
}
