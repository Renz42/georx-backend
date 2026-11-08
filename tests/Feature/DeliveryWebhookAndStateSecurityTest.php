<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Models\Delivery;
use App\Models\DriverProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DeliveryWebhookAndStateSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement("ATTACH ':memory:' AS information_schema");
                \Illuminate\Support\Facades\DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {}
        }
    }

    protected User $customer;
    protected User $pharmacyOwner;
    protected User $otherPharmacyOwner;
    protected Pharmacy $pharmacy;
    protected Pharmacy $otherPharmacy;
    protected User $deliveryPartner;
    protected DriverProfile $driverProfile;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // 1. Customer
        $this->customer = User::create([
            'name' => 'Charlie Customer',
            'email' => 'charlie@test.com',
            'phone' => '09171113333',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // 2. Pharmacy 1
        $this->pharmacy = Pharmacy::create([
            'name' => 'Alijis Primary Pharmacy',
            'slug' => 'alijis-primary-pharmacy',
            'address' => 'Alijis Road, Bacolod City',
            'latitude' => 10.63850000,
            'longitude' => 122.95200000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->pharmacyOwner = User::create([
            'name' => 'Dr. Pharmacy Owner',
            'email' => 'owner@pharmacy.com',
            'phone' => '09172223333',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->pharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // 3. Pharmacy 2 (Isolation)
        $this->otherPharmacy = Pharmacy::create([
            'name' => 'Other Branch Pharmacy',
            'slug' => 'other-branch-pharmacy',
            'address' => 'Lacson St, Bacolod City',
            'latitude' => 10.67000000,
            'longitude' => 122.95000000,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->otherPharmacyOwner = User::create([
            'name' => 'Other Branch Owner',
            'email' => 'other@pharmacy.com',
            'phone' => '09174445555',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->otherPharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // 4. Web Delivery Partner + Approved Profile
        $this->deliveryPartner = User::create([
            'name' => 'Dave Driver',
            'email' => 'dave@rider.com',
            'phone' => '09175556666',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_DELIVERY_PARTNER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $this->driverProfile = DriverProfile::create([
            'user_id' => $this->deliveryPartner->id,
            'vehicle_type' => 'motorcycle',
            'account_status' => DriverProfile::STATUS_APPROVED,
            'is_online' => true,
            'is_available' => true,
        ]);
    }

    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'order_number' => 'ORD-' . uniqid(),
            'status' => Order::STATUS_ACCEPTED,
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
            'delivery_address' => 'Block 5, Alijis, Bacolod City',
            'subtotal' => 200.00,
            'delivery_fee' => 50.00,
            'total_amount' => 250.00,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'delivery_provider' => 'maxim',
            'delivery_booking_id' => 'MAX-BOOKING-999',
            'delivery_status' => 'booked',
        ], $attributes));
    }

    /*
    |--------------------------------------------------------------------------
    | 1. External Courier Webhook Security Tests
    |--------------------------------------------------------------------------
    */

    public function test_webhook_fails_closed_when_secret_is_unconfigured(): void
    {
        Config::set('services.maxim.webhook_secret', null);
        $order = $this->createOrder();

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
        ]);

        $response->assertStatus(503);
        $response->assertJson(['error' => 'Webhook secret is not configured.']);
    }

    public function test_webhook_missing_configuration_fails_closed_before_order_lookup_even_for_nonexistent_order(): void
    {
        Config::set('services.maxim.webhook_secret', null);

        // Proves auth check precedes route model resolution: does not return 404
        $response = $this->postJson("/api/delivery/webhook/999999999", [
            'status' => 'picked_up',
        ]);

        $response->assertStatus(503);
        $response->assertJson(['error' => 'Webhook secret is not configured.']);
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder();

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized: Invalid or missing webhook signature.']);
    }

    public function test_webhook_rejects_invalid_or_forged_signature(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder();

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
        ], [
            'X-Maxim-Signature' => 'forged-invalid-secret',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Unauthorized: Invalid or missing webhook signature.']);
    }

    public function test_webhook_missing_or_invalid_signature_is_rejected_before_order_lookup_even_for_nonexistent_order(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');

        // Missing signature on nonexistent ID returns 401, not 404
        $resMissing = $this->postJson("/api/delivery/webhook/999999999", [
            'status' => 'picked_up',
        ]);
        $resMissing->assertStatus(401);
        $resMissing->assertJson(['error' => 'Unauthorized: Invalid or missing webhook signature.']);

        // Invalid signature on nonexistent ID returns 401, not 404 (prevents order enumeration oracle)
        $resInvalid = $this->postJson("/api/delivery/webhook/999999999", [
            'status' => 'picked_up',
        ], [
            'X-Maxim-Signature' => 'forged-invalid-secret',
        ]);
        $resInvalid->assertStatus(401);
        $resInvalid->assertJson(['error' => 'Unauthorized: Invalid or missing webhook signature.']);
    }

    public function test_webhook_nonexistent_order_with_valid_signature_returns_404_not_bypass(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');

        $response = $this->postJson("/api/delivery/webhook/999999999", [
            'status' => 'picked_up',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Order not found.']);
    }

    public function test_webhook_invalid_authentication_cannot_mutate_order_state(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder();

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'completed',
        ], [
            'X-Maxim-Signature' => 'forged-invalid-secret',
        ]);

        $response->assertStatus(401);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
        $this->assertEquals('booked', $order->delivery_status);
        $this->assertNull($order->delivered_at);
        $this->assertNull($order->picked_up_at);
    }

    public function test_webhook_rejects_order_not_managed_by_maxim(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'delivery_provider' => 'grab',
            'delivery_booking_id' => 'GRAB-BOOKING-111',
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'GRAB-BOOKING-111',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Order is not managed by Maxim delivery provider.']);
    }

    public function test_webhook_rejects_missing_booking_reference(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder();

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Booking reference is required.']);
    }

    public function test_webhook_rejects_empty_or_whitespace_booking_reference(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder();

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => '   ',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Booking reference is required.']);
    }

    public function test_webhook_rejects_booking_reference_mismatch(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'delivery_booking_id' => 'MAX-BOOKING-999',
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-WRONG-BOOKING',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Booking reference mismatch.']);
    }

    public function test_webhook_rejects_booking_reference_belonging_to_another_order(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $orderA = $this->createOrder(['delivery_booking_id' => 'MAX-ORDER-AAA']);
        $orderB = $this->createOrder(['delivery_booking_id' => 'MAX-ORDER-BBB']);

        $response = $this->postJson("/api/delivery/webhook/{$orderA->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-ORDER-BBB',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Booking reference mismatch.']);
    }

    public function test_webhook_rejects_order_without_active_booking(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'delivery_booking_id' => null,
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-ANY-BOOKING',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Order has no active courier booking.']);
    }

    public function test_webhook_rejects_unconfirmed_order(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'pharmacy_confirmed' => false,
            'delivery_booking_id' => 'MAX-BOOKING-999',
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Order has not been confirmed by pharmacy.']);
    }

    public function test_webhook_rejects_cancelled_or_rejected_order(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'status' => Order::STATUS_CANCELLED,
            'delivery_booking_id' => 'MAX-BOOKING-999',
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Order is in a terminal cancelled/rejected state.']);
    }

    public function test_webhook_rejects_completed_without_prior_dispatch(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'status' => Order::STATUS_ACCEPTED,
            'delivery_status' => 'booked',
            'picked_up_at' => null,
            'delivery_booking_id' => 'MAX-BOOKING-999',
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'completed',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Cannot mark order completed without prior dispatch/pickup.']);
        $this->assertNotEquals(Order::STATUS_DELIVERED, $order->fresh()->status);
    }

    public function test_webhook_revalidates_provider_and_booking_reference_inside_transaction(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'delivery_provider' => 'maxim',
            'delivery_booking_id' => 'MAX-BOOKING-ORIGINAL',
        ]);

        // Intercept query inside transaction: when lockForUpdate executes, simulate another process having modified booking ID
        \Illuminate\Support\Facades\DB::listen(function ($query) use ($order) {
            static $alreadyAltered = false;
            if (!$alreadyAltered && (str_contains(strtolower($query->sql), 'orders') && str_contains(strtolower($query->sql), 'where') && (str_contains(strtolower($query->sql), 'for update') || str_contains(strtolower($query->sql), 'limit 1')))) {
                $alreadyAltered = true;
                \Illuminate\Support\Facades\DB::table('orders')->where('id', $order->id)->update([
                    'delivery_booking_id' => 'MAX-BOOKING-MUTATED-BY-RACE',
                ]);
            }
        });

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-ORIGINAL',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Booking reference mismatch.']);
    }

    public function test_webhook_invalid_request_produces_no_state_mutations_or_notifications(): void
    {
        Notification::fake();
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'delivery_booking_id' => 'MAX-BOOKING-999',
            'status' => Order::STATUS_ACCEPTED,
            'delivery_status' => 'booked',
            'picked_up_at' => null,
            'delivered_at' => null,
        ]);

        // Send request with mismatched booking reference
        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'completed',
            'booking_id' => 'FORGED-BOOKING-ID',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $order->refresh();
        $this->assertEquals(Order::STATUS_ACCEPTED, $order->status);
        $this->assertEquals('booked', $order->delivery_status);
        $this->assertNull($order->picked_up_at);
        $this->assertNull($order->delivered_at);
        Notification::assertNothingSent();
    }

    public function test_webhook_picked_up_establishes_dispatch_evidence(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'status' => Order::STATUS_ACCEPTED,
            'delivery_status' => 'booked',
            'picked_up_at' => null,
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_PICKED_UP, $order->status);
        $this->assertEquals('in_transit', $order->delivery_status);
        $this->assertNotNull($order->picked_up_at);
    }

    public function test_webhook_completed_after_dispatch_is_successful_and_idempotent(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'status' => Order::STATUS_PICKED_UP,
            'delivery_status' => 'in_transit',
            'picked_up_at' => now()->subMinutes(15),
        ]);

        // First completed event
        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'completed',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertEquals('delivered', $order->delivery_status);
        $this->assertNotNull($order->delivered_at);

        // Replayed duplicate completed event
        $duplicateResponse = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'completed',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $duplicateResponse->assertStatus(200);
        $duplicateResponse->assertJson(['success' => true, 'already_delivered' => true]);
    }

    public function test_webhook_replayed_picked_up_after_completion_does_not_regress_order(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $deliveredTime = now()->subMinutes(10);
        $order = $this->createOrder([
            'status' => Order::STATUS_DELIVERED,
            'delivery_status' => 'delivered',
            'picked_up_at' => now()->subMinutes(30),
            'delivered_at' => $deliveredTime,
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'ignored' => 'Order is already delivered.']);

        $order->refresh();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertEquals('delivered', $order->delivery_status);
        $this->assertEquals($deliveredTime->toDateTimeString(), $order->delivered_at->toDateTimeString());
    }

    public function test_webhook_delayed_picked_up_cannot_revive_cancelled_or_rejected_order(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder([
            'status' => Order::STATUS_CANCELLED,
            'delivery_status' => 'cancelled',
        ]);

        $response = $this->postJson("/api/delivery/webhook/{$order->id}", [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Order is in a terminal cancelled/rejected state.']);

        $order->refresh();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $this->assertEquals('cancelled', $order->delivery_status);
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Pharmacy Portal Delivery Transition Authorization Tests
    |--------------------------------------------------------------------------
    */

    public function test_pharmacy_cannot_modify_orders_of_another_pharmacy(): void
    {
        $order = $this->createOrder([
            'pharmacy_id' => $this->pharmacy->id,
        ]);

        // Act as other pharmacy owner
        $response = $this->actingAs($this->otherPharmacyOwner, 'pharmacy')
            ->from('/pharmacy/orders')
            ->post("/pharmacy/orders/{$order->id}/delivery-status", [
                'status' => 'in_transit',
            ]);

        $response->assertStatus(404);
    }

    public function test_pharmacy_cannot_update_delivery_status_of_unconfirmed_order(): void
    {
        $order = $this->createOrder([
            'pharmacy_confirmed' => false,
        ]);

        $response = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->from('/pharmacy/orders')
            ->post("/pharmacy/orders/{$order->id}/delivery-status", [
                'status' => 'in_transit',
            ]);

        $response->assertSessionHas('error');
        $this->assertNotEquals('in_transit', $order->fresh()->delivery_status);
    }

    public function test_pharmacy_cannot_bypass_dispatch_to_mark_booked_order_delivered(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_ACCEPTED,
            'delivery_status' => 'booked',
            'picked_up_at' => null,
        ]);

        $response = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->from('/pharmacy/orders')
            ->post("/pharmacy/orders/{$order->id}/delivery-status", [
                'status' => 'delivered',
            ]);

        $response->assertSessionHas('error');
        $order->refresh();
        $this->assertNotEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotEquals('delivered', $order->delivery_status);
        $this->assertNull($order->delivered_at);
    }

    public function test_pharmacy_valid_workflow_progression(): void
    {
        $order = $this->createOrder([
            'status' => Order::STATUS_ACCEPTED,
            'delivery_status' => 'booked',
            'picked_up_at' => null,
        ]);

        // Step 1: Handover to rider (in_transit)
        $inTransitResponse = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->from('/pharmacy/orders')
            ->post("/pharmacy/orders/{$order->id}/delivery-status", [
                'status' => 'in_transit',
            ]);
        $inTransitResponse->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('in_transit', $order->delivery_status);
        $this->assertEquals(Order::STATUS_PICKED_UP, $order->status);
        $this->assertNotNull($order->picked_up_at);

        // Step 2: Mark delivered after in-transit
        $deliveredResponse = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->from('/pharmacy/orders')
            ->post("/pharmacy/orders/{$order->id}/delivery-status", [
                'status' => 'delivered',
            ]);
        $deliveredResponse->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals('delivered', $order->delivery_status);
        $this->assertEquals(Order::STATUS_DELIVERED, $order->status);
        $this->assertNotNull($order->delivered_at);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Internal Delivery Partner Authorization & Progression Tests
    |--------------------------------------------------------------------------
    */

    public function test_delivery_partner_cannot_modify_unassigned_order(): void
    {
        $order = $this->createOrder([
            'delivery_partner_id' => null,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->deliveryPartner, 'web')
            ->from('/delivery/dashboard')
            ->post("/delivery/orders/{$order->id}/status", [
                'status' => 'at_pharmacy',
            ]);

        $response->assertStatus(404);
    }

    public function test_delivery_partner_enforces_status_progression(): void
    {
        $order = $this->createOrder([
            'delivery_partner_id' => $this->deliveryPartner->id,
            'status' => 'accepted',
        ]);

        // Attempting to jump directly from accepted to delivered is rejected
        $jumpResponse = $this->actingAs($this->deliveryPartner, 'web')
            ->from('/delivery/dashboard')
            ->post("/delivery/orders/{$order->id}/status", [
                'status' => 'delivered',
            ]);
        $jumpResponse->assertSessionHas('error');

        // Legitimate progression: accepted -> at_pharmacy
        $atPharmResponse = $this->actingAs($this->deliveryPartner, 'web')
            ->from('/delivery/dashboard')
            ->post("/delivery/orders/{$order->id}/status", [
                'status' => 'at_pharmacy',
            ]);
        $atPharmResponse->assertSessionHas('success');
        $this->assertEquals('at_pharmacy', $order->fresh()->status);

        // at_pharmacy -> picked_up
        $pickupResponse = $this->actingAs($this->deliveryPartner, 'web')
            ->from('/delivery/dashboard')
            ->post("/delivery/orders/{$order->id}/status", [
                'status' => 'picked_up',
            ]);
        $pickupResponse->assertSessionHas('success');
        $order->refresh();
        $this->assertEquals('picked_up', $order->status);
        $this->assertEquals('in_transit', $order->delivery_status);
        $this->assertNotNull($order->picked_up_at);

        // picked_up -> delivered
        $delivResponse = $this->actingAs($this->deliveryPartner, 'web')
            ->from('/delivery/dashboard')
            ->post("/delivery/orders/{$order->id}/status", [
                'status' => 'delivered',
            ]);
        $delivResponse->assertSessionHas('success');
        $order->refresh();
        $this->assertEquals('delivered', $order->status);
        $this->assertEquals('delivered', $order->delivery_status);
        $this->assertNotNull($order->delivered_at);
    }

    public function test_webhook_is_stateless_and_does_not_fail_from_missing_csrf(): void
    {
        Config::set('services.maxim.webhook_secret', 'test-secure-maxim-secret');
        $order = $this->createOrder();

        // Perform raw POST without CSRF token or session
        $response = $this->post(route('api.delivery.webhook', $order->id), [
            'status' => 'picked_up',
            'booking_id' => 'MAX-BOOKING-999',
        ], [
            'X-Maxim-Signature' => 'test-secure-maxim-secret',
            'Accept' => 'application/json',
        ]);

        // Asserts route does not return 419 CSRF Token Mismatch
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals('picked_up', $order->fresh()->status);
    }
}

