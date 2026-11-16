<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\GlobalSetting;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryFareAndBookingIntegrityTest extends TestCase
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

    protected User $superAdmin;
    protected User $pharmacyOwner;
    protected User $customer;
    protected Pharmacy $pharmacy;
    protected Medicine $neozep;
    protected Medicine $catapres;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->superAdmin = User::create([
            'name' => 'Super Admin QA',
            'email' => 'admin.fareqa@georx.com',
            'phone' => '09170001111',
            'password' => Hash::make('AdminPass123!'),
            'role' => User::ROLE_ADMINISTRATOR,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Inside Barangay Alijis, Bacolod City
        $this->pharmacy = Pharmacy::create([
            'name' => 'Alijis Care Pharmacy',
            'slug' => 'alijis-care-pharmacy',
            'address' => 'Alijis Road, Barangay Alijis, Bacolod City',
            'phone' => '09171112233',
            'email' => 'alijis.care@georx.com',
            'latitude' => 10.6407,
            'longitude' => 122.9463,
            'owner_name' => 'Gian Garcia',
            'license_number' => 'LTO-ALIJIS-777',
            'operating_hours' => ['open' => '00:00', 'close' => '23:59'],
            'is_approved' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $this->pharmacyOwner = User::create([
            'name' => 'Gian Garcia',
            'email' => 'alijis.care@georx.com',
            'phone' => '09171112233',
            'password' => Hash::make('OwnerPass123!'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->pharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->customer = User::create([
            'name' => 'Thea Faye',
            'email' => 'thea.fareqa@georx.com',
            'phone' => '0951573123',
            'password' => Hash::make('PatientPass123!'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->neozep = Medicine::create([
            'slug' => 'neozep-forte-tablet',
            'brand_name' => 'Neozep Forte',
            'generic_name' => 'Paracetamol + Phenylephrine HCl',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'drug_category' => 'Decongestant',
            'prescription_required' => false,
        ]);

        $this->catapres = Medicine::create([
            'slug' => 'catapres-tablet',
            'brand_name' => 'Catapres',
            'generic_name' => 'Clonidine',
            'dosage_form' => 'Tablet',
            'strength' => '75mcg',
            'drug_category' => 'Cardiovascular',
            'prescription_required' => false,
        ]);

        $this->pharmacy->medicines()->attach($this->neozep->id, [
            'quantity_on_hand' => 50,
            'reorder_level' => 10,
            'selling_price' => 8.60,
            'is_available' => true,
        ]);

        $this->pharmacy->medicines()->attach($this->catapres->id, [
            'quantity_on_hand' => 40,
            'reorder_level' => 10,
            'selling_price' => 50.00,
            'is_available' => true,
        ]);

        GlobalSetting::set('delivery_base_fee', '50.00');
        GlobalSetting::set('base_delivery_fee', '50.00');
        GlobalSetting::set('delivery_per_km_fee', '0.00');
        GlobalSetting::set('delivery_minimum_fee', '50.00');
        GlobalSetting::set('delivery_maximum_estimate', '150.00');
        GlobalSetting::set('delivery_estimate_enabled', '1');
    }

    public function test_estimated_delivery_fee_is_50_and_delivery_estimate_endpoint_works(): void
    {
        $response = $this->getJson('/api/delivery/estimate?pharmacy_id=' . $this->pharmacy->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'estimated_delivery_fee' => 50,
                'base_delivery_fee' => 50,
                'delivery_per_km_fee' => 0,
                'is_estimate' => true,
                'currency' => 'PHP',
            ]);
    }

    public function test_small_medicine_order_like_8_60_places_cleanly_without_minimum_order_restriction(): void
    {
        Sanctum::actingAs($this->customer);

        $placeResponse = $this->postJson('/api/orders', [
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'items' => [
                ['medicine_id' => $this->neozep->id, 'quantity' => 1],
            ],
        ]);

        $placeResponse->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'pricing' => [
                    'medicine_subtotal' => 8.6,
                    'estimated_delivery_fee' => 50,
                    'actual_courier_fee' => null,
                    'has_actual_courier_fee' => false,
                    'courier_fee_status' => 'pending',
                    'customer_payable_amount' => 58.6,
                ],
            ]);

        $orderId = $placeResponse->json('order.id');
        $this->assertNotNull($orderId);

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => null,
        ]);

        $listResponse = $this->getJson('/api/user/orders');
        $listResponse->assertStatus(200)
            ->assertJsonFragment([
                'id' => $orderId,
                'medicine_subtotal' => 8.6,
                'estimated_delivery_fee' => 50,
                'actual_courier_fee' => null,
                'has_actual_courier_fee' => false,
                'courier_fee_status' => 'pending',
                'customer_payable_amount' => 58.6,
            ]);

        $showResponse = $this->getJson("/api/user/orders/{$orderId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('order.medicine_subtotal', 8.6)
            ->assertJsonPath('order.estimated_delivery_fee', 50)
            ->assertJsonPath('order.actual_courier_fee', null)
            ->assertJsonPath('order.has_actual_courier_fee', false)
            ->assertJsonPath('order.customer_payable_amount', 58.6);
    }

    public function test_actual_courier_fee_variants_never_double_charge_and_historical_orders_stay_frozen(): void
    {
        $orderPending = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => null,
        ]);
        OrderItem::create([
            'order_id' => $orderPending->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        $orderZero = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'delivery_fee' => 50.00,
            'total_amount' => 150.00,
            'actual_courier_fee' => 0.00,
        ]);
        OrderItem::create([
            'order_id' => $orderZero->id,
            'medicine_id' => $this->catapres->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $orderLower = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'delivery_fee' => 50.00,
            'total_amount' => 150.00,
            'actual_courier_fee' => 38.00,
        ]);

        $orderHigher = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'delivery_fee' => 50.00,
            'total_amount' => 150.00,
            'actual_courier_fee' => 65.00,
        ]);

        $p = $orderPending->fresh('items')->pricingSummary();
        $this->assertSame(8.6, $p['medicine_subtotal']);
        $this->assertSame(50.0, $p['estimated_delivery_fee']);
        $this->assertNull($p['actual_courier_fee']);
        $this->assertFalse($p['has_actual_courier_fee']);
        $this->assertSame(58.6, $p['customer_payable_amount']);

        $z = $orderZero->fresh('items')->pricingSummary();
        $this->assertTrue($z['has_actual_courier_fee']);
        $this->assertSame(0.0, $z['actual_courier_fee']);
        $this->assertSame(50.0, $z['effective_delivery_fee']);
        $this->assertSame(150.0, $z['customer_payable_amount']);
        $this->assertSame(-50.0, $z['delivery_fee_variance']);

        $l = $orderLower->fresh()->pricingSummary();
        $this->assertSame(100.0, $l['medicine_subtotal']);
        $this->assertSame(50.0, $l['effective_delivery_fee']);
        $this->assertSame(150.0, $l['customer_payable_amount']);
        $this->assertSame(-12.0, $l['delivery_fee_variance']);

        $h = $orderHigher->fresh()->pricingSummary();
        $this->assertSame(100.0, $h['medicine_subtotal']);
        $this->assertSame(50.0, $h['effective_delivery_fee']);
        $this->assertSame(150.0, $h['customer_payable_amount']);
        $this->assertSame(15.0, $h['delivery_fee_variance']);

        // Update global settings after historical orders were saved
        GlobalSetting::set('delivery_base_fee', '75.00');
        GlobalSetting::set('base_delivery_fee', '75.00');
        GlobalSetting::set('delivery_minimum_fee', '75.00');

        $this->getJson('/api/delivery/estimate')
            ->assertStatus(200)
            ->assertJsonPath('estimated_delivery_fee', 75);

        // Historical order keeps its stored 50.00 delivery fee and 58.60 payable amount
        $frozenSummary = $orderPending->fresh('items')->pricingSummary();
        $this->assertSame(50.0, $frozenSummary['estimated_delivery_fee']);
        $this->assertSame(58.6, $frozenSummary['customer_payable_amount']);
    }

    public function test_web_checkout_displays_real_item_prices_and_tracking_and_pdf_receipt_distinguish_fees(): void
    {
        CartItem::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 2,
        ]);

        $checkoutRes = $this->actingAs($this->customer, 'web')
            ->get(route('orders.checkout'));

        $checkoutRes->assertStatus(200);
        $checkoutRes->assertSee('2x Neozep Forte');
        $checkoutRes->assertSee('₱17.20');
        $checkoutRes->assertSee('Medicine Subtotal');
        $checkoutRes->assertSee('Delivery Fee');
        $checkoutRes->assertSee('Customer Payable (COD)');
        $checkoutRes->assertDontSee('Pending courier booking');
        $checkoutRes->assertSee('₱50.00');
        $checkoutRes->assertSee('₱67.20');

        $pendingOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_ACCEPTED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => Order::PROVIDER_GRAB,
            'delivery_booking_id' => 'GRAB-94821',
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => null,
        ]);
        OrderItem::create([
            'order_id' => $pendingOrder->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        $actualOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => Order::PROVIDER_MAXIM,
            'delivery_booking_id' => 'MAXIM-1024',
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => 65.00,
        ]);
        OrderItem::create([
            'order_id' => $actualOrder->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        $pendingTracking = $this->actingAs($this->customer, 'web')
            ->get(route('orders.show', $pendingOrder->id));
        $pendingTracking->assertStatus(200);
        $pendingTracking->assertSee('₱8.60');
        $pendingTracking->assertSee('₱50.00');
        $pendingTracking->assertSee('Customer Payable Amount (COD):');
        $pendingTracking->assertSee('₱58.60');

        $actualTracking = $this->actingAs($this->customer, 'web')
            ->get(route('orders.show', $actualOrder->id));
        $actualTracking->assertStatus(200);
        $actualTracking->assertSee('₱8.60');
        $actualTracking->assertSee('₱50.00');
        $actualTracking->assertSee('₱58.60');
        $actualTracking->assertDontSee('₱65.00');
        $actualTracking->assertDontSee('₱73.60');
        $actualTracking->assertDontSee('₱123.60');

        $pdfDownload = $this->actingAs($this->customer, 'web')
            ->get(route('orders.receipt', $actualOrder->id));
        $pdfDownload->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $pdfDownload->headers->get('content-type'));

        $actualReceiptHtml = view('user.reports.receipt-pdf', [
            'order' => $actualOrder->fresh(['items.medicine', 'pharmacy']),
            'pricing' => $actualOrder->fresh('items')->pricingSummary(),
            'date' => 'Nov 17, 2026',
        ])->render();
        $this->assertStringContainsString('P8.60', $actualReceiptHtml);
        $this->assertStringContainsString('P50.00', $actualReceiptHtml);
        $this->assertStringContainsString('P58.60', $actualReceiptHtml);
        $this->assertStringContainsString('Customer Payable Amount (COD):', $actualReceiptHtml);
        $this->assertStringNotContainsString('P65.00', $actualReceiptHtml);
        $this->assertStringNotContainsString('P73.60', $actualReceiptHtml);
        $this->assertStringNotContainsString('P123.60', $actualReceiptHtml);

        $pendingReceiptHtml = view('user.reports.receipt-pdf', [
            'order' => $pendingOrder->fresh(['items.medicine', 'pharmacy']),
            'pricing' => $pendingOrder->fresh('items')->pricingSummary(),
            'date' => 'Nov 17, 2026',
        ])->render();
        $this->assertStringContainsString('Customer Payable Amount (COD):', $pendingReceiptHtml);
        $this->assertStringContainsString('P58.60', $pendingReceiptHtml);
        $this->assertStringNotContainsString('Customer Payable Amount (Est. COD):', $pendingReceiptHtml);
    }

    public function test_courier_provider_booking_validation_and_overwrite_protection(): void
    {
        // Direct service validation: valid Grab & Maxim formats including neutral numeric IDs
        $this->assertTrue(DeliveryService::validateCourierBooking('grab', 'GRAB-94821', 'https://express.grab.com/track/123')['valid']);
        $this->assertTrue(DeliveryService::validateCourierBooking('grab', '99182736')['valid']);
        $this->assertTrue(DeliveryService::validateCourierBooking('grab', 'BK-00123')['valid']);
        $this->assertTrue(DeliveryService::validateCourierBooking('maxim', 'MAXIM-1024')['valid']);
        $this->assertTrue(DeliveryService::validateCourierBooking('maxim', 'MAX-BOOKING-999')['valid']);
        $this->assertTrue(DeliveryService::validateCourierBooking('maxim', '445566')['valid']);

        // Mismatch rejections
        $grabWithMaximRef = DeliveryService::validateCourierBooking('grab', 'MAXIM-1024');
        $this->assertFalse($grabWithMaximRef['valid']);
        $this->assertArrayHasKey('delivery_booking_id', $grabWithMaximRef['errors']);

        $grabWithMaximUrl = DeliveryService::validateCourierBooking('grab', '99182736', 'https://taximaxim.com/order/1');
        $this->assertFalse($grabWithMaximUrl['valid']);
        $this->assertArrayHasKey('delivery_tracking_url', $grabWithMaximUrl['errors']);

        $maximWithGrabRef = DeliveryService::validateCourierBooking('maxim', 'GRAB-94821');
        $this->assertFalse($maximWithGrabRef['valid']);
        $this->assertArrayHasKey('delivery_booking_id', $maximWithGrabRef['errors']);

        $maximWithGrabUrl = DeliveryService::validateCourierBooking('maxim', '445566', 'https://express.grab.com/track/1');
        $this->assertFalse($maximWithGrabUrl['valid']);
        $this->assertArrayHasKey('delivery_tracking_url', $maximWithGrabUrl['errors']);

        // HTTP controller enforcement & overwrite protection
        $order = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_CONFIRMED,
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
        ]);

        // Mismatch MAXIM-1024 under grab must fail and not persist
        $badMaximRef = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.book-delivery', $order->id), [
                'delivery_provider' => 'grab',
                'delivery_booking_id' => 'MAXIM-1024',
            ]);
        $badMaximRef->assertSessionHasErrors('delivery_booking_id');
        $this->assertNull($order->fresh()->delivery_provider);
        $this->assertNull($order->fresh()->delivery_booking_id);

        // Mismatch Maxim URL under grab must fail
        $badMaximUrl = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.book-delivery', $order->id), [
                'delivery_provider' => 'grab',
                'delivery_booking_id' => '991827',
                'delivery_tracking_url' => 'https://taximaxim.com/track/1',
            ]);
        $badMaximUrl->assertSessionHasErrors('delivery_tracking_url');
        $this->assertNull($order->fresh()->delivery_booking_id);

        // Mismatch GRAB-94821 under maxim must fail
        $badGrabRef = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.book-delivery', $order->id), [
                'delivery_provider' => 'maxim',
                'delivery_booking_id' => 'GRAB-94821',
            ]);
        $badGrabRef->assertSessionHasErrors('delivery_booking_id');
        $this->assertNull($order->fresh()->delivery_booking_id);

        // Empty ref rejected
        $emptyRef = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.book-delivery', $order->id), [
                'delivery_provider' => 'grab',
                'delivery_booking_id' => '   ',
            ]);
        $emptyRef->assertSessionHasErrors('delivery_booking_id');

        // Valid Grab Express booking succeeds
        $validBook = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.book-delivery', $order->id), [
                'delivery_provider' => 'grab',
                'delivery_booking_id' => 'GRAB-94821',
                'delivery_tracking_url' => 'https://express.grab.com/track/94821',
            ]);
        $validBook->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('grab', $order->delivery_provider);
        $this->assertSame('GRAB-94821', $order->delivery_booking_id);

        // Attempting to overwrite confirmed booking must be rejected cleanly
        $overwriteAttempt = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.book-delivery', $order->id), [
                'delivery_provider' => 'maxim',
                'delivery_booking_id' => 'MAXIM-1024',
            ]);
        $overwriteAttempt->assertSessionHasErrors('delivery_booking_id');

        $order->refresh();
        $this->assertSame('grab', $order->delivery_provider);
        $this->assertSame('GRAB-94821', $order->delivery_booking_id);
        $this->assertSame('https://express.grab.com/track/94821', $order->delivery_tracking_url);
    }

    public function test_pharmacy_orders_view_and_inline_actual_courier_fee_update(): void
    {
        $unbookedOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_CONFIRMED,
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
        ]);

        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $unbookedOrder->id), ['actual_courier_fee' => 55.00])
            ->assertSessionHas('error');

        $bookedOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_ACCEPTED,
            'pharmacy_confirmed' => true,
            'confirmed_at' => now(),
            'delivery_provider' => 'grab',
            'delivery_booking_id' => 'GRAB-94821',
            'delivery_status' => 'booked',
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => null,
        ]);
        OrderItem::create([
            'order_id' => $bookedOrder->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        $initialView = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->get(route('pharmacy.orders'));
        $initialView->assertStatus(200);
        $initialView->assertSee('GRAB-94821');
        $initialView->assertSee('Pending / Not yet recorded');

        // Recording 0.00 must be treated as recorded (not pending)
        $zeroUpdate = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $bookedOrder->id), [
                'actual_courier_fee' => 0,
            ]);
        $zeroUpdate->assertSessionHas('success');
        $this->assertEquals(0.00, (float) $bookedOrder->fresh()->actual_courier_fee);
        $this->assertSame('GRAB-94821', $bookedOrder->fresh()->delivery_booking_id);

        $zeroView = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->get(route('pharmacy.orders'));
        $zeroView->assertStatus(200);
        $zeroView->assertSee('₱0.00');
        $zeroView->assertSee('Courier Exp. (Info): ₱0.00');
        $zeroView->assertSee('Customer Payable (COD): ₱58.60');
        $this->assertEquals(50.00, (float) $bookedOrder->fresh()->delivery_fee);
        $this->assertEquals(58.60, (float) $bookedOrder->fresh()->total_amount);

        // Updating to 68.50 via courier-fee route preserves booking ID, stored delivery fee, and order total
        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $bookedOrder->id), [
                'actual_courier_fee' => 68.50,
            ])
            ->assertSessionHas('success');
        $bookedOrder->refresh();
        $this->assertEquals(68.50, (float) $bookedOrder->actual_courier_fee);
        $this->assertEquals(50.00, (float) $bookedOrder->delivery_fee);
        $this->assertEquals(58.60, (float) $bookedOrder->total_amount);
        $this->assertSame(58.6, $bookedOrder->fresh('items')->pricingSummary()['customer_payable_amount']);

        // Updating delivery status to in_transit with actual_courier_fee works seamlessly without changing order total
        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.delivery-status', $bookedOrder->id), [
                'status' => 'in_transit',
                'actual_courier_fee' => 72.00,
            ])
            ->assertSessionHas('success');

        $bookedOrder->refresh();
        $this->assertSame(Order::STATUS_PICKED_UP, $bookedOrder->status);
        $this->assertEquals(72.00, (float) $bookedOrder->actual_courier_fee);
        $this->assertEquals(50.00, (float) $bookedOrder->delivery_fee);
        $this->assertEquals(58.60, (float) $bookedOrder->total_amount);
        $this->assertSame('grab', $bookedOrder->delivery_provider);
        $this->assertSame('GRAB-94821', $bookedOrder->delivery_booking_id);
    }

    public function test_admin_dashboard_and_reports_reconcile_estimated_and_actual_courier_fees(): void
    {
        $orderWithActual = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'delivery_fee' => 50.00,
            'total_amount' => 150.00,
            'actual_courier_fee' => 65.00,
        ]);
        OrderItem::create([
            'order_id' => $orderWithActual->id,
            'medicine_id' => $this->catapres->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        $orderPendingActual = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => null,
        ]);
        OrderItem::create([
            'order_id' => $orderPendingActual->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        Cache::flush();

        $dashboardRes = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.dashboard'));
        $dashboardRes->assertStatus(200);
        $dashboardRes->assertSee('Total Platform Revenue');
        $dashboardRes->assertViewHas('completedOrderValue', 208.60);
        $dashboardRes->assertViewHas('medicineSalesGmv', 108.60);
        $dashboardRes->assertViewHas('estimatedDeliveryFees', 100.00);
        $dashboardRes->assertViewHas('recordedActualCourierFees', 65.00);
        $dashboardRes->assertViewHas('recordedActualCourierCount', 1);

        $reportsRes = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.reports'));
        $reportsRes->assertStatus(200);
        $reportsRes->assertViewHas('recordedActualCourierFees', 65.00);
        $reportsRes->assertViewHas('recordedActualCourierCount', 1);
    }

    public function test_final_polish_my_orders_cache_invalidation_and_scoped_form_old_inputs(): void
    {
        // 1. Web My Orders (/my-orders) distinguishes estimated vs recorded actual courier fare
        $pendingOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_ACCEPTED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => Order::PROVIDER_GRAB,
            'delivery_booking_id' => 'GRAB-94821',
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => null,
        ]);
        OrderItem::create([
            'order_id' => $pendingOrder->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        $actualOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_PICKED_UP,
            'pharmacy_confirmed' => true,
            'delivery_provider' => Order::PROVIDER_MAXIM,
            'delivery_booking_id' => 'MAXIM-1024',
            'delivery_status' => 'in_transit',
            'picked_up_at' => now(),
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
            'actual_courier_fee' => 65.00,
        ]);
        OrderItem::create([
            'order_id' => $actualOrder->id,
            'medicine_id' => $this->neozep->id,
            'quantity' => 1,
            'price' => 8.60,
        ]);

        $myOrdersRes = $this->actingAs($this->customer, 'web')
            ->get(route('user.orders'));
        $myOrdersRes->assertStatus(200);
        $myOrdersRes->assertSee('Customer Payable (COD)');
        $myOrdersRes->assertSee('Med ₱8.60 · Delivery ₱50.00');
        $myOrdersRes->assertSee('₱58.60');
        $myOrdersRes->assertDontSee('Final Payable (COD)');
        $myOrdersRes->assertDontSee('Est. Payable (COD)');
        $myOrdersRes->assertDontSee('₱65.00');
        $myOrdersRes->assertDontSee('₱73.60');

        // 2. Warm Admin Dashboard cache BEFORE pharmacy updates actual_courier_fee & marks delivered
        $warmDashboard = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.dashboard'));
        $warmDashboard->assertStatus(200);
        $warmDashboard->assertViewHas('recordedActualCourierFees', 0.0);
        $warmDashboard->assertViewHas('recordedActualCourierCount', 0);

        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $actualOrder->id), [
                '_fee_order_id' => $actualOrder->id,
                'actual_courier_fee' => 72.50,
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.delivery-status', $actualOrder->id), [
                'status' => 'delivered',
            ])
            ->assertSessionHas('success');

        // Admin dashboard reflects new totals immediately without Cache::flush() while keeping order COD at 58.60
        $refreshedDashboard = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.dashboard'));
        $refreshedDashboard->assertStatus(200);
        $refreshedDashboard->assertViewHas('recordedActualCourierFees', 72.50);
        $refreshedDashboard->assertViewHas('recordedActualCourierCount', 1);
        $refreshedDashboard->assertSee('Courier Exp ₱72.50');
        $refreshedDashboard->assertDontSee('₱81.10');

        // 3. Scoped old() inputs on /pharmacy/orders only affect the submitted failing order card & enforce max="10000"
        $readyOrderOne = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_PENDING,
            'pharmacy_confirmed' => true,
            'is_prepared' => true,
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
        ]);
        $readyOrderTwo = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_PENDING,
            'pharmacy_confirmed' => true,
            'is_prepared' => true,
            'delivery_fee' => 50.00,
            'total_amount' => 58.60,
        ]);

        $failedBook = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->from(route('pharmacy.orders'))
            ->followingRedirects()
            ->post(route('pharmacy.orders.book-delivery', $readyOrderOne->id), [
                '_booking_order_id' => $readyOrderOne->id,
                'delivery_provider' => 'grab',
                'delivery_booking_id' => 'MAXIM-9988',
            ]);

        $failedBook->assertStatus(200);
        $failedBook->assertSee('max="10000"', false);
        // Only one card receives the rejected MAXIM-9988 old value, not readyOrderTwo
        $this->assertSame(1, substr_count($failedBook->getContent(), 'value="MAXIM-9988"'));
        // Only readyOrderOne expands on validation bounce-back while readyOrderTwo stays collapsed
        $this->assertStringContainsString('aria-controls="order-details-' . $readyOrderOne->id . '"', $failedBook->getContent());
        $failedBook->assertSee('id="order-details-' . $readyOrderOne->id . '" class="order-details-panel  border-t', false);
        $failedBook->assertSee('id="order-details-' . $readyOrderTwo->id . '" class="order-details-panel hidden border-t', false);

        // Schemeless tracking URL mismatch rejected safely too
        $schemelessUrl = DeliveryService::validateCourierBooking('grab', '99182736', 'taximaxim.com/track/1');
        $this->assertFalse($schemelessUrl['valid']);
        $this->assertArrayHasKey('delivery_tracking_url', $schemelessUrl['errors']);
    }

    public function test_cod_option1_scenarios_a_through_f_keep_customer_payable_anchored_to_order_total(): void
    {
        // Scenario C: Actual courier fee is null -> customer payable remains ₱550.00
        $order = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_ACCEPTED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => Order::PROVIDER_GRAB,
            'delivery_booking_id' => 'GRAB-55001',
            'delivery_status' => 'booked',
            'delivery_fee' => 50.00,
            'total_amount' => 550.00,
            'actual_courier_fee' => null,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'medicine_id' => $this->catapres->id,
            'quantity' => 10,
            'price' => 50.00,
        ]);

        $summaryC = $order->fresh('items')->pricingSummary();
        $this->assertSame(500.0, $summaryC['medicine_subtotal']);
        $this->assertSame(50.0, $summaryC['estimated_delivery_fee']);
        $this->assertSame(50.0, $summaryC['effective_delivery_fee']);
        $this->assertNull($summaryC['actual_courier_fee']);
        $this->assertSame(550.0, $summaryC['customer_payable_amount']);

        // Scenario A & D: Record actual_courier_fee = ₱80.00 (> ₱50) -> customer payable remains ₱550.00
        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $order->id), [
                '_fee_order_id' => $order->id,
                'actual_courier_fee' => 80.00,
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(550.00, (float) $order->total_amount);
        $this->assertEquals(50.00, (float) $order->delivery_fee);
        $this->assertEquals(80.00, (float) $order->actual_courier_fee);

        $summaryA = $order->fresh('items')->pricingSummary();
        $this->assertSame(80.0, $summaryA['actual_courier_fee']);
        $this->assertSame(50.0, $summaryA['effective_delivery_fee']);
        $this->assertSame(550.0, $summaryA['customer_payable_amount']);
        $this->assertSame(30.0, $summaryA['delivery_fee_variance']);

        // Scenario B: Update actual_courier_fee from ₱80.00 to ₱100.00 -> customer payable remains ₱550.00
        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $order->id), [
                '_fee_order_id' => $order->id,
                'actual_courier_fee' => 100.00,
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertEquals(550.00, (float) $order->total_amount);
        $this->assertEquals(50.00, (float) $order->delivery_fee);
        $this->assertEquals(100.00, (float) $order->actual_courier_fee);
        $this->assertSame(550.0, $order->fresh('items')->pricingSummary()['customer_payable_amount']);

        // Mobile API responses keep customer_payable_amount = 550.00 while exposing 100.00 informational expense
        Sanctum::actingAs($this->customer);
        $this->getJson('/api/user/orders')
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => $order->id,
                'total_amount' => 550,
                'delivery_fee' => 50,
                'actual_courier_fee' => 100,
                'customer_payable_amount' => 550,
            ]);

        $this->getJson("/api/user/orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('order.total_amount', 550)
            ->assertJsonPath('order.delivery_fee', 50)
            ->assertJsonPath('order.effective_delivery_fee', 50)
            ->assertJsonPath('order.actual_courier_fee', 100)
            ->assertJsonPath('order.customer_payable_amount', 550);

        // Scenario E: Actual courier fee lower than stored delivery fee (₱35.00 < ₱50.00) -> payable unchanged (₱550.00)
        $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->post(route('pharmacy.orders.courier-fee', $order->id), [
                '_fee_order_id' => $order->id,
                'actual_courier_fee' => 35.00,
            ])
            ->assertSessionHas('success');

        $summaryE = $order->fresh('items')->pricingSummary();
        $this->assertSame(35.0, $summaryE['actual_courier_fee']);
        $this->assertSame(550.0, $summaryE['customer_payable_amount']);
        $this->assertSame(-15.0, $summaryE['delivery_fee_variance']);

        // Scenario F: Existing historical delivered order with actual_courier_fee retains stored order total (₱550.00)
        $historicalOrder = Order::create([
            'user_id' => $this->customer->id,
            'pharmacy_id' => $this->pharmacy->id,
            'delivery_address' => 'Barangay Alijis, Bacolod City',
            'status' => Order::STATUS_DELIVERED,
            'pharmacy_confirmed' => true,
            'delivery_provider' => Order::PROVIDER_MAXIM,
            'delivery_booking_id' => 'MAXIM-55099',
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'delivery_fee' => 50.00,
            'total_amount' => 550.00,
            'actual_courier_fee' => 92.00,
        ]);
        OrderItem::create([
            'order_id' => $historicalOrder->id,
            'medicine_id' => $this->catapres->id,
            'quantity' => 10,
            'price' => 50.00,
        ]);

        // Cross-check Web My Orders, Tracking, Receipt PDF, Pharmacy Orders, Admin Dashboard Recent Orders & Admin Reports
        $myOrdersRes = $this->actingAs($this->customer, 'web')->get(route('user.orders'));
        $myOrdersRes->assertStatus(200);
        $myOrdersRes->assertSee('₱550.00');
        $myOrdersRes->assertDontSee('₱592.00');
        $myOrdersRes->assertDontSee('₱585.00');

        $trackingRes = $this->actingAs($this->customer, 'web')->get(route('orders.show', $historicalOrder->id));
        $trackingRes->assertStatus(200);
        $trackingRes->assertSee('₱550.00');
        $trackingRes->assertDontSee('₱92.00');
        $trackingRes->assertDontSee('₱592.00');

        $receiptHtml = view('user.reports.receipt-pdf', [
            'order' => $historicalOrder->fresh(['items.medicine', 'pharmacy']),
            'pricing' => $historicalOrder->fresh('items')->pricingSummary(),
            'date' => 'Nov 17, 2026',
        ])->render();
        $this->assertStringContainsString('P550.00', $receiptHtml);
        $this->assertStringNotContainsString('P92.00', $receiptHtml);
        $this->assertStringNotContainsString('P592.00', $receiptHtml);

        $pharmacyRes = $this->actingAs($this->pharmacyOwner, 'pharmacy')->get(route('pharmacy.orders'));
        $pharmacyRes->assertStatus(200);
        $pharmacyRes->assertSee('Customer Payable (COD): ₱550.00');
        $pharmacyRes->assertSee('Courier Exp. (Info): ₱92.00');
        $pharmacyRes->assertDontSee('₱592.00');

        Cache::flush();
        $adminDashboardRes = $this->actingAs($this->superAdmin, 'admin')->get(route('admin.dashboard'));
        $adminDashboardRes->assertStatus(200);
        $adminDashboardRes->assertViewHas('completedOrderValue', 550.0);
        $adminDashboardRes->assertViewHas('estimatedDeliveryFees', 50.0);
        $adminDashboardRes->assertViewHas('recordedActualCourierFees', 92.0);
        $adminDashboardRes->assertSee('₱550.00');
        $adminDashboardRes->assertSee('Courier Exp ₱92.00');
        $adminDashboardRes->assertDontSee('₱592.00');

        $adminReportsRes = $this->actingAs($this->superAdmin, 'admin')->get(route('admin.reports'));
        $adminReportsRes->assertStatus(200);
        $adminReportsRes->assertViewHas('completedOrderValue', 550.0);
        $adminReportsRes->assertViewHas('recordedActualCourierFees', 92.0);
    }
}
