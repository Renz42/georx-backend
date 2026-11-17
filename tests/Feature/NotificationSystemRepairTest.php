<?php

namespace Tests\Feature;

use App\Jobs\DispatchRestockAlertsJob;
use App\Models\Delivery;
use App\Models\DriverProfile;
use App\Models\InventoryBatch;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\StockAlert;
use App\Models\User;
use App\Models\UserDeviceToken;
use App\Models\UserFavorite;
use App\Notifications\DriverAssignedNotification;
use App\Services\InventoryService;
use App\Services\PatientNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationSystemRepairTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement("ATTACH ':memory:' AS information_schema");
                DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {
            }
        }
    }

    private function createActors(): array
    {
        $customer = User::create([
            'name' => 'Patient Juan',
            'email' => 'patient@test.com',
            'phone' => '09171110001',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $otherCustomer = User::create([
            'name' => 'Patient Maria',
            'email' => 'maria@test.com',
            'phone' => '09171110002',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $pharmacy = Pharmacy::create([
            'name' => 'Alijis Care Pharmacy',
            'slug' => 'alijis-care-pharmacy',
            'address' => 'Brgy. Alijis, Bacolod City',
            'latitude' => 10.63850000,
            'longitude' => 122.95200000,
            'status' => 'approved',
            'is_active' => true,
            'operating_hours' => ['open' => '00:00', 'close' => '23:59'],
        ]);

        $pharmacyOwner = User::create([
            'name' => 'Pharmacist Elena',
            'email' => 'owner@alijis.test',
            'phone' => '09172220001',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $pharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $medicine = Medicine::create([
            'slug' => 'biogesic-500mg-test',
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Biogesic 500mg',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'prescription_required' => false,
        ]);

        return compact('customer', 'otherCustomer', 'pharmacy', 'pharmacyOwner', 'medicine');
    }

    public function test_scenario_a_order_lifecycle_notifications_cod_and_webhook_idempotency(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-maxim-secret');
        Config::set('services.notifications.expo_push_enabled', true);

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [['status' => 'ok', 'id' => 'expo-ticket-1']],
            ], 200),
        ]);

        $actors = $this->createActors();
        $customer = $actors['customer'];
        $pharmacy = $actors['pharmacy'];
        $pharmacyOwner = $actors['pharmacyOwner'];
        $medicine = $actors['medicine'];

        $pharmacy->medicines()->attach($medicine->id, [
            'unit' => 'Tablet',
            'selling_price' => 100.00,
            'quantity_on_hand' => 0,
            'reorder_level' => 5,
            'is_available' => false,
        ]);
        app(InventoryService::class)->addBatch($pharmacy->id, $medicine->id, [
            'batch_number' => 'BCH-TEST-1',
            'quantity' => 50,
            'expiration_date' => now()->addYear()->toDateString(),
        ]);

        UserDeviceToken::create([
            'user_id' => $customer->id,
            'device_token' => 'ExponentPushToken[customer-token-123]',
            'device_type' => 'android',
        ]);

        // 1. Place order via mobile API -> customer + pharmacy both notified
        $placeRes = $this->actingAs($customer, 'sanctum')->postJson('/api/orders', [
            'pharmacy_id' => $pharmacy->id,
            'delivery_address' => 'Purok 1, Brgy. Alijis, Bacolod City',
            'latitude' => 10.6386,
            'longitude' => 122.9522,
            'items' => [
                ['medicine_id' => $medicine->id, 'quantity' => 2],
            ],
        ]);

        $placeRes->assertStatus(201);
        $orderId = (int) $placeRes->json('order.id');
        $order = Order::findOrFail($orderId);
        $originalTotal = (float) $order->total_amount;

        $this->assertGreaterThan(0, $customer->notifications()->count());
        $this->assertSame(1, $pharmacyOwner->notifications()->count());

        $placedNotif = $customer->notifications()->latest()->first();
        $this->assertSame($orderId, $placedNotif->data['order_id']);
        $this->assertSame("/orders/{$orderId}", $placedNotif->data['action_url']);
        $this->assertSame("order:{$orderId}:placed", $placedNotif->data['event_key']);

        // 2. Pharmacy confirms, prepares, and books external delivery
        $this->actingAs($pharmacyOwner, 'pharmacy')
            ->post("/portal/orders/{$orderId}/confirm")
            ->assertRedirect();

        $this->actingAs($pharmacyOwner, 'pharmacy')
            ->post("/portal/orders/{$orderId}/prepare")
            ->assertRedirect();

        $this->actingAs($pharmacyOwner, 'pharmacy')
            ->post("/portal/orders/{$orderId}/book-delivery", [
                'delivery_provider' => 'maxim',
                'delivery_booking_id' => 'MAXIM-TEST-991',
                'actual_courier_fee' => 85.00,
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertEquals($originalTotal, (float) $order->total_amount);

        // 3. Maxim webhook transitions (plus duplicate retries -> must deduplicate!)
        $headers = ['X-Maxim-Signature' => 'test-maxim-secret'];
        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'driver_assigned',
            'booking_id' => 'MAXIM-TEST-991',
            'driver' => ['name' => 'Ramon Rider', 'phone' => '09170001111', 'vehicle' => 'Honda Click'],
        ], $headers)->assertStatus(200);

        // Duplicate driver_assigned retry -> must NOT create duplicate notification
        $countBeforeDupDriver = $customer->notifications()->count();
        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'driver_assigned',
            'booking_id' => 'MAXIM-TEST-991',
            'driver' => ['name' => 'Ramon Rider', 'phone' => '09170001111', 'vehicle' => 'Honda Click'],
        ], $headers)->assertStatus(200);
        $this->assertSame($countBeforeDupDriver, $customer->notifications()->count());

        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'arrived_at_pickup',
            'booking_id' => 'MAXIM-TEST-991',
        ], $headers)->assertStatus(200);

        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'picked_up',
            'booking_id' => 'MAXIM-TEST-991',
        ], $headers)->assertStatus(200);

        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'arrived_at_dropoff',
            'booking_id' => 'MAXIM-TEST-991',
        ], $headers)->assertStatus(200);

        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'completed',
            'booking_id' => 'MAXIM-TEST-991',
        ], $headers)->assertStatus(200);

        // Duplicate completed webhook + late customer confirm-delivery -> ReviewPrompt sent ONCE
        $countAfterComplete = $customer->notifications()->count();
        $this->postJson("/api/delivery/webhook/{$orderId}", [
            'status' => 'completed',
            'booking_id' => 'MAXIM-TEST-991',
        ], $headers)->assertStatus(200);
        $this->assertSame($countAfterComplete, $customer->notifications()->count());

        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/user/orders/{$orderId}/confirm-delivery")
            ->assertStatus(200);
        $this->assertSame($countAfterComplete, $customer->notifications()->count());

        $eventKeys = $customer->notifications()->get()->pluck('data.event_key')->all();
        $this->assertContains("order:{$orderId}:placed", $eventKeys);
        $this->assertContains("order:{$orderId}:confirmed", $eventKeys);
        $this->assertContains("order:{$orderId}:prepared", $eventKeys);
        $this->assertContains("order:{$orderId}:courier_booked:MAXIM-TEST-991", $eventKeys);
        $this->assertContains("order:{$orderId}:driver_assigned:ramon rider", $eventKeys);
        $this->assertContains("order:{$orderId}:arrived_at_pickup", $eventKeys);
        $this->assertContains("order:{$orderId}:picked_up", $eventKeys);
        $this->assertContains("order:{$orderId}:arrived_at_dropoff", $eventKeys);
        $this->assertContains("order:{$orderId}:delivered", $eventKeys);
        $this->assertContains("order:{$orderId}:review_prompt", $eventKeys);

        // Verify ReviewPrompt contains non-null pharmacy name + /orders/{id} url + order_id
        $reviewNotif = $customer->notifications()->get()->first(
            fn ($n) => ($n->data['event_key'] ?? null) === "order:{$orderId}:review_prompt"
        );
        $this->assertNotNull($reviewNotif);
        $this->assertStringContainsString('Alijis Care Pharmacy', $reviewNotif->data['message']);
        $this->assertSame($orderId, $reviewNotif->data['order_id']);
        $this->assertSame("/orders/{$orderId}", $reviewNotif->data['action_url']);

        // Verify Expo push was triggered and did not expose password/PII
        Http::assertSent(function ($request) use ($orderId) {
            $body = $request->data();
            $first = $body[0] ?? [];
            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && ($first['data']['order_id'] ?? null) === $orderId
                && !array_key_exists('delivery_address', $first['data'])
                && !array_key_exists('password', $first['data']);
        });
    }

    public function test_scenario_a2_cancellation_and_internal_driver_notifications(): void
    {
        Config::set('services.notifications.expo_push_enabled', false);
        $actors = $this->createActors();
        $customer = $actors['customer'];
        $pharmacy = $actors['pharmacy'];
        $pharmacyOwner = $actors['pharmacyOwner'];

        // Customer cancellation
        $orderToCancel = Order::create([
            'user_id' => $customer->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => 250.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis, Bacolod',
            'pharmacy_confirmed' => false,
        ]);

        $this->actingAs($customer, 'web')
            ->post("/order/{$orderToCancel->id}/cancel")
            ->assertRedirect();

        $this->assertSame(Order::STATUS_CANCELLED, $orderToCancel->fresh()->status);
        $this->assertSame(
            "order:{$orderToCancel->id}:cancelled",
            $customer->notifications()->latest()->first()->data['event_key'] ?? null
        );
        $this->assertSame(
            "pharmacy_order:{$orderToCancel->id}:cancelled",
            $pharmacyOwner->notifications()->latest()->first()->data['event_key'] ?? null
        );

        // Mobile driver lifecycle must persist valid Notification classes (not missing DeliveryStatusNotification)
        $driver = User::create([
            'name' => 'Driver Pedro',
            'email' => 'pedro.driver@test.com',
            'phone' => '09173330001',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_DELIVERY_PARTNER,
            'account_status' => User::STATUS_ACTIVE,
        ]);
        DriverProfile::create([
            'user_id' => $driver->id,
            'vehicle_type' => 'Motorcycle',
            'vehicle_make' => 'Yamaha',
            'vehicle_model' => 'NMAX',
            'plate_number' => 'GEO-101',
            'is_available' => true,
            'is_online' => true,
        ]);

        $driverOrder = Order::create([
            'user_id' => $customer->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 300.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis, Bacolod',
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $delivery = Delivery::create([
            'order_id' => $driverOrder->id,
            'pickup_pharmacy_id' => $pharmacy->id,
            'pickup_latitude' => 10.6385,
            'pickup_longitude' => 122.9520,
            'customer_id' => $customer->id,
            'delivery_address' => 'Alijis, Bacolod',
            'delivery_latitude' => 10.6390,
            'delivery_longitude' => 122.9525,
            'status' => Delivery::STATUS_WAITING_FOR_DRIVER,
        ]);

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/driver/bookings/{$delivery->id}/accept")
            ->assertStatus(200);

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/driver/status/update/{$delivery->id}", ['status' => 'driver_at_pharmacy'])
            ->assertStatus(200);

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/driver/status/update/{$delivery->id}", ['status' => 'picked_up'])
            ->assertStatus(200);

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/driver/status/update/{$delivery->id}", ['status' => 'out_for_delivery'])
            ->assertStatus(200);

        $this->actingAs($driver, 'sanctum')
            ->postJson("/api/driver/status/update/{$delivery->id}", ['status' => 'delivered'])
            ->assertStatus(200);

        $types = $customer->notifications()->pluck('type')->all();
        $this->assertNotContains('App\Notifications\DeliveryStatusNotification', $types);
        $this->assertContains(DriverAssignedNotification::class, $types);
        $this->assertContains(\App\Notifications\OrderDeliveredNotification::class, $types);
        $this->assertContains(\App\Notifications\ReviewPrompt::class, $types);
    }

    public function test_scenario_b_read_unread_ownership_and_badge_counts(): void
    {
        Config::set('services.notifications.expo_push_enabled', false);
        $actors = $this->createActors();
        $customer = $actors['customer'];
        $otherCustomer = $actors['otherCustomer'];
        $pharmacy = $actors['pharmacy'];

        $order = Order::create([
            'user_id' => $customer->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => 150.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis',
        ]);

        $svc = app(PatientNotificationService::class);
        $svc->notifyOrderCustomer($order, 'placed', new \App\Notifications\OrderStatusChanged($order, 'Placed #1'));
        $svc->notifyOrderCustomer($order, 'confirmed', new \App\Notifications\OrderStatusChanged($order, 'Confirmed #1'));

        $listRes = $this->actingAs($customer, 'sanctum')->getJson('/api/notifications');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('unread_count', 2);

        $first = $listRes->json('notifications.0');
        $this->assertFalse($first['is_read']);
        $this->assertNull($first['read_at']);

        // Cross-user isolation: Maria cannot mark or delete Juan's notification
        $this->actingAs($otherCustomer, 'sanctum')
            ->postJson("/api/notifications/{$first['id']}/read")
            ->assertStatus(403);

        $this->actingAs($otherCustomer, 'sanctum')
            ->deleteJson("/api/notifications/{$first['id']}")
            ->assertStatus(403);

        // Juan marks 1 as read -> unread_count becomes 1
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/notifications/{$first['id']}/read")
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 1);

        // Read all -> unread_count becomes 0
        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 0);

        // Delete one notification -> succeeds cleanly
        $this->actingAs($customer, 'sanctum')
            ->deleteJson("/api/notifications/{$first['id']}")
            ->assertStatus(200)
            ->assertJsonPath('unread_count', 0);
    }

    public function test_scenario_c_d_e_watch_favorites_global_recurring_and_no_false_restock(): void
    {
        Config::set('services.notifications.expo_push_enabled', false);
        Config::set('services.notifications.sync_restock_dispatch', true);

        $actors = $this->createActors();
        $specificWatcher = $actors['customer'];
        $globalWatcher = $actors['otherCustomer'];
        $pharmacy = $actors['pharmacy'];
        $pharmacyOwner = $actors['pharmacyOwner'];
        $medicine = $actors['medicine'];

        $favUser = User::create([
            'name' => 'Favorite Fan',
            'email' => 'fan@test.com',
            'phone' => '09174440001',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // Unrelated outsider should never be notified
        $outsider = User::create([
            'name' => 'Outsider',
            'email' => 'outsider@test.com',
            'phone' => '09174440002',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $pharmacy->medicines()->attach($medicine->id, [
            'unit' => 'Tablet',
            'selling_price' => 20.00,
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'is_available' => false,
        ]);

        // 1. Specific watch + pharmacy favorite on SAME user (must deduplicate to 1 notif!), global watch, and favorite-only user
        $this->actingAs($specificWatcher, 'sanctum')->postJson('/api/stock-alerts', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ])->assertStatus(200);

        UserFavorite::create([
            'user_id' => $specificWatcher->id,
            'pharmacy_id' => $pharmacy->id,
        ]);

        $this->actingAs($globalWatcher, 'sanctum')->postJson('/api/stock-alerts', [
            'pharmacy_id' => null,
            'medicine_id' => $medicine->id,
        ])->assertStatus(200);

        UserFavorite::create([
            'user_id' => $favUser->id,
            'pharmacy_id' => $pharmacy->id,
        ]);

        // Restock 0 -> 25 via Portal updateInventoryItem (runs synchronously cleanly)
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 20.00,
            'quantity_on_hand' => 25,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->assertSame(1, $specificWatcher->notifications()->count());
        $this->assertSame(1, $globalWatcher->notifications()->count());
        $this->assertSame(1, $favUser->notifications()->count());
        $this->assertSame(0, $outsider->notifications()->count());

        // Price-only edit (25 -> 25) or non-low decrease (25 -> 20) must NOT trigger restock alerts
        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 25,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 20,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->assertSame(1, $specificWatcher->notifications()->count());
        $this->assertSame(1, $globalWatcher->notifications()->count());
        $this->assertSame(1, $favUser->notifications()->count());

        // 2. Out-of-stock 20 -> 0 notifies watchers & favorites, keeps StockAlert is_active = true
        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->assertSame(2, $specificWatcher->notifications()->count());
        $this->assertSame(2, $globalWatcher->notifications()->count());
        $this->assertSame(2, $favUser->notifications()->count());
        $this->assertTrue(StockAlert::where('user_id', $specificWatcher->id)->first()->is_active);
        $this->assertTrue(StockAlert::where('user_id', $globalWatcher->id)->first()->is_active);

        // 3. Recurring cycle: restock again 0 -> 12 without re-subscribing -> still alerts!
        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 12,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->assertSame(3, $specificWatcher->notifications()->count());
        $this->assertSame(3, $globalWatcher->notifications()->count());
        $this->assertSame(3, $favUser->notifications()->count());

        // 4. Low-stock replenishment (e.g. drops to 5 <= 10 reorder_level, then replenished to 30)
        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 5,
            'reorder_level' => 10,
        ])->assertRedirect();
        $this->assertSame(3, $specificWatcher->notifications()->count());

        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 30,
            'reorder_level' => 10,
        ])->assertRedirect();
        $this->assertSame(4, $specificWatcher->notifications()->count());

        // 5. Adding new global medicine via storeNewMedicine with qty > 0 notifies favorite pharmacy fan
        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->post('/portal/inventory/new-medicine', [
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil 500mg',
            'unit' => 'Piece / Tablet',
            'selling_price' => 15.00,
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->assertSame(5, $favUser->notifications()->count());
        $this->assertSame(5, $specificWatcher->notifications()->count());

        // 6. Unsubscribe stops future stock alerts for that watch (remove favorite too to isolate alert behavior)
        UserFavorite::where('user_id', $specificWatcher->id)->delete();
        $this->actingAs($specificWatcher, 'sanctum')->deleteJson('/api/stock-alerts', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
        ])->assertStatus(200);

        Cache::flush();
        $this->actingAs($pharmacyOwner, 'pharmacy')->put("/portal/inventory/{$medicine->id}", [
            'unit' => 'Piece / Tablet',
            'selling_price' => 22.00,
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
        ])->assertRedirect();

        $this->assertSame(5, $specificWatcher->notifications()->count()); // unchanged after unsubscribe
        $this->assertSame(5, $globalWatcher->notifications()->count());   // 3 -> 4 (low-stock replenish) -> 5 (out-of-stock)
    }

    public function test_scenario_f_device_token_lifecycle_and_expo_push_failure_resilience(): void
    {
        Config::set('services.notifications.expo_push_enabled', true);

        $actors = $this->createActors();
        $userA = $actors['customer'];
        $userB = $actors['otherCustomer'];
        $pharmacy = $actors['pharmacy'];

        // User A registers device token
        $this->actingAs($userA, 'sanctum')->postJson('/api/device-tokens', [
            'device_token' => 'ExponentPushToken[shared-device-999]',
            'device_type' => 'android',
        ])->assertStatus(200);

        $this->assertSame(1, UserDeviceToken::where('user_id', $userA->id)->count());

        // User B signs into same device -> token must be reassigned from User A to User B
        $this->actingAs($userB, 'sanctum')->postJson('/api/device-tokens', [
            'device_token' => 'ExponentPushToken[shared-device-999]',
            'device_type' => 'android',
        ])->assertStatus(200);

        $this->assertSame(0, UserDeviceToken::where('user_id', $userA->id)->count());
        $this->assertSame(1, UserDeviceToken::where('user_id', $userB->id)->count());

        // Expo returns DeviceNotRegistered -> backend prunes dead token AND keeps DB notification
        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [[
                    'status' => 'error',
                    'message' => 'not registered',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ]],
            ], 200),
        ]);

        $order = Order::create([
            'user_id' => $userB->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => 180.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis',
        ]);

        $sent = app(PatientNotificationService::class)->notifyOrderCustomer(
            $order,
            'confirmed',
            new \App\Notifications\OrderStatusChanged($order, 'Confirmed by pharmacy')
        );

        $this->assertTrue($sent);
        $this->assertSame(1, $userB->notifications()->count());
        $this->assertSame(0, UserDeviceToken::where('device_token', 'ExponentPushToken[shared-device-999]')->count());

        // Simulated Expo HTTP 500 failure must NEVER fail order notification DB persistence
        UserDeviceToken::create([
            'user_id' => $userB->id,
            'device_token' => 'ExponentPushToken[temp-error-device]',
            'device_type' => 'android',
        ]);

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response(['error' => 'down'], 500),
        ]);

        $sent2 = app(PatientNotificationService::class)->notifyOrderCustomer(
            $order,
            'prepared',
            new \App\Notifications\OrderStatusChanged($order, 'Prepared by pharmacy')
        );

        $this->assertTrue($sent2);
        $this->assertSame(2, $userB->notifications()->count());

        // Logout cleanup endpoint deletes active device token
        $this->actingAs($userB, 'sanctum')->deleteJson('/api/device-tokens', [
            'device_token' => 'ExponentPushToken[temp-error-device]',
        ])->assertStatus(200);

        $this->assertSame(0, UserDeviceToken::where('user_id', $userB->id)->count());
    }

    public function test_scenario_f2_expo_push_transient_retry_and_receipts(): void
    {
        Config::set('services.notifications.expo_push_enabled', true);

        $actors = $this->createActors();
        $user = $actors['customer'];
        $pharmacy = $actors['pharmacy'];

        $order = Order::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => 180.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis',
        ]);

        UserDeviceToken::create([
            'user_id' => $user->id,
            'device_token' => 'ExponentPushToken[retry-device-1]',
            'device_type' => 'android',
        ]);

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::sequence()
                ->push(['error' => 'upstream'], 503)
                ->push(['data' => [['status' => 'ok', 'id' => 'ticket-retry-1']]], 200),
            'https://exp.host/--/api/v2/push/getReceipts' => Http::response([
                'data' => ['ticket-retry-1' => ['status' => 'ok']],
            ], 200),
        ]);

        $retryResult = app(\App\Services\ExpoPushService::class)->sendToUser(
            $user->id,
            'Retry Test',
            'Recovered after transient failure',
            ['order_id' => $order->id],
            'orders'
        );
        $this->assertSame(1, $retryResult['sent']);

        $receipts = app(\App\Services\ExpoPushService::class)->checkReceipts(['ticket-retry-1']);
        $this->assertSame('ok', $receipts['ticket-retry-1']['status'] ?? null);
    }
}
