<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase4DeliveryBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;
    protected User $pharmacyOwner;
    protected Pharmacy $pharmacy;
    protected Medicine $medicine;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create customer
        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        // 2. Create pharmacy & owner in Barangay Alijis
        $this->pharmacyOwner = User::factory()->create([
            'role' => User::ROLE_PHARMACY,
            'account_status' => User::STATUS_ACTIVE,
        ]);

        $this->pharmacy = Pharmacy::create([
            'name' => 'Alijis Test Pharmacy',
            'address' => 'Alijis Road, Barangay Alijis, Bacolod City',
            'latitude' => 10.63850000,
            'longitude' => 122.95200000, // Inside Alijis boundary
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->pharmacyOwner->update(['pharmacy_id' => $this->pharmacy->id]);

        // 3. Create medicine and link to pharmacy with stock
        $this->medicine = Medicine::create([
            'generic_name' => 'Paracetamol 500mg',
            'brand_name' => 'Biogesic',
            'category' => 'Analgesic',
            'requires_prescription' => false,
        ]);

        $this->pharmacy->medicines()->attach($this->medicine->id, [
            'selling_price' => 15.00,
            'aggregate_stock' => 100,
        ]);
    }

    /** @test */
    public function test_customer_can_place_order_with_valid_delivery_address()
    {
        $this->actingAs($this->customer);

        CartItem::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'medicine_id' => $this->medicine->id,
            'quantity' => 2,
        ]);

        $response = $this->post(route('orders.place'), [
            'delivery_address' => 'Block 5, Lot 12, Alijis Heights, Barangay Alijis',
            'latitude' => 10.63900000,
            'longitude' => 122.95300000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'delivery_address' => 'Block 5, Lot 12, Alijis Heights, Barangay Alijis',
        ]);
    }

    /** @test */
    public function test_customer_can_place_order_even_if_gps_permission_is_denied()
    {
        $this->actingAs($this->customer);

        CartItem::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'medicine_id' => $this->medicine->id,
            'quantity' => 1,
        ]);

        // Blank lat/lng simulates GPS denied
        $response = $this->post(route('orders.place'), [
            'delivery_address' => 'Manual Text Address, Barangay Alijis',
            'latitude' => '',
            'longitude' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'delivery_address' => 'Manual Text Address, Barangay Alijis',
            'latitude' => null,
            'longitude' => null,
        ]);
    }

    /** @test */
    public function test_delivery_booking_is_automatically_created_when_pharmacy_confirms_order()
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => 80.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Phase 2 Alijis Subdivision',
            'latitude' => 10.63800000,
            'longitude' => 122.95100000,
        ]);

        $order->items()->create([
            'medicine_id' => $this->medicine->id,
            'quantity' => 2,
            'price' => 15.00,
        ]);

        $this->actingAs($this->pharmacyOwner);

        $response = $this->post(route('pharmacy.orders.confirm', $order->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'pickup_pharmacy_id' => $this->pharmacy->id,
            'pickup_latitude' => 10.63850000,
            'pickup_longitude' => 122.95200000,
            'delivery_address' => 'Phase 2 Alijis Subdivision',
            'driver_id' => null,
            'status' => Delivery::STATUS_WAITING_FOR_DRIVER,
        ]);
    }

    /** @test */
    public function test_delivery_service_prevents_duplicate_delivery_bookings()
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => Order::STATUS_PENDING,
            'pharmacy_confirmed' => true,
            'total_amount' => 50.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Test Address, Alijis',
        ]);

        $service = app(DeliveryService::class);

        $delivery1 = $service->createDeliveryForOrder($order);
        $delivery2 = $service->createDeliveryForOrder($order);

        $this->assertEquals($delivery1->id, $delivery2->id);
        $this->assertEquals(1, Delivery::where('order_id', $order->id)->count());
    }

    /** @test */
    public function test_driver_cannot_alter_official_pickup_pharmacy()
    {
        $order = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'status' => Order::STATUS_PENDING,
            'pharmacy_confirmed' => true,
            'total_amount' => 50.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Test Address, Alijis',
        ]);

        $service = app(DeliveryService::class);
        $delivery = $service->createDeliveryForOrder($order);

        // Verification: Delivery record locks pickup pharmacy ID and coords to pharmacy selected by customer
        $this->assertEquals($this->pharmacy->id, $delivery->pickup_pharmacy_id);
        $this->assertEquals($this->pharmacy->latitude, $delivery->pickup_latitude);
        $this->assertEquals($this->pharmacy->longitude, $delivery->pickup_longitude);
    }
}
