<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Models\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileOrderDeliveryLifecycleSecurityTest extends TestCase
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
    protected User $otherCustomer;
    protected Pharmacy $pharmacy;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        // 1. Create primary customer
        $this->customer = User::create([
            'name' => 'Alice Customer',
            'email' => 'alice@test.com',
            'phone' => '09171112222',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // 2. Create another customer to test authorization isolation
        $this->otherCustomer = User::create([
            'name' => 'Bob Unauthorized',
            'email' => 'bob@test.com',
            'phone' => '09173334444',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // 3. Create approved active pharmacy
        $this->pharmacy = Pharmacy::create([
            'name' => 'Alijis Community Pharmacy',
            'slug' => 'alijis-community-pharmacy',
            'address' => 'Alijis Road, Bacolod City',
            'latitude' => 10.63850000,
            'longitude' => 122.95200000,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create an order with given attributes
     */
    protected function createOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'order_number' => 'ORD-' . uniqid(),
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'pharmacy_confirmed' => false,
            'delivery_address' => 'Block 1, Lot 2, Alijis, Bacolod City',
            'subtotal' => 150.00,
            'delivery_fee' => 50.00,
            'total_amount' => 200.00,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ], $attributes));
    }

    /**
     * 1. Unconfirmed Order: Cannot be marked delivered.
     */
    public function test_unconfirmed_order_cannot_be_marked_delivered(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'pharmacy_confirmed' => false,
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'PHARMACY_NOT_CONFIRMED',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'customer_confirmed_delivery' => false,
        ]);
    }

    /**
     * 2. Preparing Order: Confirmed by pharmacy but still preparing cannot be marked delivered.
     */
    public function test_preparing_order_cannot_be_marked_delivered(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_PENDING,
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'ORDER_NOT_DISPATCHED',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_confirmed_delivery' => false,
        ]);
    }

    /**
     * 3. External Courier Booked but Not Picked Up: Cannot be marked delivered.
     */
    public function test_courier_booked_without_pickup_cannot_be_marked_delivered(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_CONFIRMED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => 'grab',
            'delivery_booking_id' => 'GRAB-12345',
            'delivery_status' => 'booked', // Booked alone is NOT dispatch
            'picked_up_at' => null,
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'COURIER_NOT_DISPATCHED',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_confirmed_delivery' => false,
        ]);
    }

    /**
     * 4. Forgery Resistance: Client-supplied payload cannot forge dispatch evidence.
     */
    public function test_client_supplied_fields_cannot_forge_dispatch_evidence(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_CONFIRMED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => 'grab',
            'delivery_booking_id' => 'GRAB-12345',
            'delivery_status' => 'booked',
            'picked_up_at' => null,
        ]);

        // Attempt to pass forged parameters in request body
        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery", [
            'delivery_status' => 'in_transit',
            'status' => 'picked_up',
            'picked_up_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'COURIER_NOT_DISPATCHED',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'delivery_status' => 'booked',
            'customer_confirmed_delivery' => false,
        ]);
    }

    /**
     * 5. Authorization: Another customer cannot confirm someone else's order.
     */
    public function test_customer_cannot_confirm_another_customers_order(): void
    {
        // Logged in as Bob
        Sanctum::actingAs($this->otherCustomer);

        // Order belongs to Alice
        $order = $this->createOrder([
            'user_id' => $this->customer->id,
            'status' => Order::STATUS_PICKED_UP,
            'pharmacy_confirmed' => true,
            'picked_up_at' => now(),
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        // Must return 404 ModelNotFound because order query scopes by user_id
        $response->assertStatus(404);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_confirmed_delivery' => false,
        ]);
    }

    /**
     * 6. Terminal State: Cancelled order cannot be confirmed.
     */
    public function test_cancelled_order_cannot_be_confirmed(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_CANCELLED,
            'pharmacy_confirmed' => true,
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'ORDER_TERMINAL',
            ]);
    }

    /**
     * 7. Terminal State: Rejected order cannot be confirmed.
     */
    public function test_rejected_order_cannot_be_confirmed(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_REJECTED,
            'pharmacy_confirmed' => false,
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'code' => 'ORDER_TERMINAL',
            ]);
    }

    /**
     * 8. Legitimate Dispatch: Dispatched order is successfully confirmed.
     */
    public function test_legitimately_dispatched_courier_order_can_be_confirmed(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_PICKED_UP,
            'pharmacy_confirmed' => true,
            'delivery_provider' => 'grab',
            'delivery_booking_id' => 'GRAB-998877',
            'delivery_status' => 'in_transit',
            'picked_up_at' => now(),
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'order' => [
                    'id' => $order->id,
                    'status' => Order::STATUS_DELIVERED,
                    'delivery_status' => 'delivered',
                    'customer_confirmed_delivery' => true,
                ],
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => Order::STATUS_DELIVERED,
            'customer_confirmed_delivery' => true,
        ]);
    }

    /**
     * 9. Idempotency: Repeated confirmation does not re-notify or duplicate side effects.
     */
    public function test_repeated_confirmation_is_idempotent(): void
    {
        Sanctum::actingAs($this->customer);

        $order = $this->createOrder([
            'status' => Order::STATUS_PICKED_UP,
            'pharmacy_confirmed' => true,
            'delivery_provider' => 'grab',
            'delivery_booking_id' => 'GRAB-998877',
            'delivery_status' => 'in_transit',
            'picked_up_at' => now(),
        ]);

        // First call
        $firstResponse = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");
        $firstResponse->assertStatus(200);

        // Second call (duplicate/retry)
        $secondResponse = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");
        $secondResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'already_confirmed' => true,
            ]);
    }

    /**
     * 10. Handover after Driver Dropoff: When driver marks order delivered, customer confirm records handover.
     */
    public function test_customer_confirms_after_driver_marks_delivered(): void
    {
        Sanctum::actingAs($this->customer);

        // Driver marked status=delivered, but customer_confirmed_delivery is still false
        $order = $this->createOrder([
            'status' => Order::STATUS_DELIVERED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => 'grab',
            'delivery_status' => 'delivered',
            'customer_confirmed_delivery' => false,
            'picked_up_at' => now(),
            'delivered_at' => now(),
        ]);

        $response = $this->postJson("/api/user/orders/{$order->id}/confirm-delivery");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'order' => [
                    'id' => $order->id,
                    'status' => Order::STATUS_DELIVERED,
                    'customer_confirmed_delivery' => true,
                ],
            ]);

        // Must now be customer_confirmed_delivery = true in database
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'customer_confirmed_delivery' => true,
        ]);
    }
}
