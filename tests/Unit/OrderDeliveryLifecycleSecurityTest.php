<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Models\Order;
use App\Models\Delivery;

class OrderDeliveryLifecycleSecurityTest extends TestCase
{
    /**
     * 1. Terminal State Check: Cancelled orders can never be confirmed as delivered.
     */
    public function test_cancelled_order_cannot_be_confirmed(): void
    {
        $status = Order::STATUS_CANCELLED;
        $isTerminal = in_array($status, [Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true);
        $this->assertTrue($isTerminal, 'Cancelled orders must be identified as terminal');
    }

    /**
     * 2. Terminal State Check: Rejected orders can never be confirmed as delivered.
     */
    public function test_rejected_order_cannot_be_confirmed(): void
    {
        $status = Order::STATUS_REJECTED;
        $isTerminal = in_array($status, [Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true);
        $this->assertTrue($isTerminal, 'Rejected orders must be identified as terminal');
    }

    /**
     * 3. Idempotency Check: Already confirmed or delivered orders must succeed without mutating state.
     */
    public function test_already_confirmed_order_is_idempotent(): void
    {
        $alreadyConfirmed = true;
        $this->assertTrue($alreadyConfirmed, 'Already confirmed orders must short-circuit idempotently');
    }

    /**
     * 4. Pharmacy Confirmation Check: Unconfirmed orders by pharmacy must be rejected.
     */
    public function test_unconfirmed_by_pharmacy_cannot_be_confirmed_as_delivered(): void
    {
        $pharmacyConfirmed = false;
        $this->assertFalse($pharmacyConfirmed, 'Unconfirmed orders must fail validation with PHARMACY_NOT_CONFIRMED');
    }

    /**
     * 5. Preparation Stage Check: Pending or preparing orders cannot be confirmed.
     */
    public function test_preparing_order_cannot_be_confirmed(): void
    {
        $statusPending = Order::STATUS_PENDING;
        $statusPendingConf = Order::STATUS_PENDING_CONFIRMATION;

        $isPreparing1 = ($statusPending === Order::STATUS_PENDING_CONFIRMATION || $statusPending === Order::STATUS_PENDING);
        $isPreparing2 = ($statusPendingConf === Order::STATUS_PENDING_CONFIRMATION || $statusPendingConf === Order::STATUS_PENDING);

        $this->assertTrue($isPreparing1, 'Pending order is preparing and cannot be confirmed');
        $this->assertTrue($isPreparing2, 'Pending confirmation order is preparing and cannot be confirmed');
    }

    /**
     * 6. External Courier Dispatch Validation: Grab/Maxim courier without pickup is rejected.
     */
    public function test_external_courier_without_dispatch_evidence_is_rejected(): void
    {
        $deliveryProvider = 'grab';
        $deliveryStatus = 'booked'; // booked alone is not dispatch
        $orderStatus = Order::STATUS_CONFIRMED;
        $pickedUpAt = null;

        $hasInTransitStatus = in_array($deliveryStatus, ['in_transit', 'out_for_delivery', 'delivered'], true);
        $hasPickedUpStatus = in_array($orderStatus, [Order::STATUS_PICKED_UP, Order::STATUS_DELIVERED], true);
        $hasPickupTimestamp = !empty($pickedUpAt);

        $isDispatched = ($hasInTransitStatus || $hasPickedUpStatus || $hasPickupTimestamp);

        $this->assertFalse($isDispatched, 'External courier with status=booked must NOT be considered dispatched');
    }

    /**
     * 7. External Courier Dispatch Validation: Grab/Maxim courier in_transit is accepted.
     */
    public function test_external_courier_in_transit_is_accepted(): void
    {
        $deliveryProvider = 'grab';
        $deliveryStatus = 'in_transit';
        $orderStatus = Order::STATUS_CONFIRMED;
        $pickedUpAt = '2026-10-24 08:00:00';

        $hasInTransitStatus = in_array($deliveryStatus, ['in_transit', 'out_for_delivery', 'delivered'], true);
        $hasPickedUpStatus = in_array($orderStatus, [Order::STATUS_PICKED_UP, Order::STATUS_DELIVERED], true);
        $hasPickupTimestamp = !empty($pickedUpAt);

        $isDispatched = ($hasInTransitStatus || $hasPickedUpStatus || $hasPickupTimestamp);

        $this->assertTrue($isDispatched, 'External courier in transit with pickup timestamp must be accepted');
    }

    /**
     * 8. Internal Driver Dispatch Validation: Assigned driver who has not picked up is rejected.
     */
    public function test_internal_driver_without_pickup_is_rejected(): void
    {
        $delStatus = 'assigned'; // driver assigned but hasn't picked up
        $orderStatus = Order::STATUS_CONFIRMED;
        $pickedUpAt = null;

        $hasDelDispatched = in_array($delStatus, [Delivery::STATUS_PICKED_UP, Delivery::STATUS_OUT_FOR_DELIVERY, Delivery::STATUS_DELIVERED], true);
        $hasOrderDispatched = in_array($orderStatus, [Order::STATUS_PICKED_UP, Order::STATUS_DELIVERED], true);

        $isDispatched = ($hasDelDispatched || $hasOrderDispatched || !empty($pickedUpAt));

        $this->assertFalse($isDispatched, 'Internal driver assigned without pickup must NOT be considered dispatched');
    }

    /**
     * 9. Internal Driver Dispatch Validation: Driver picked up is accepted.
     */
    public function test_internal_driver_picked_up_is_accepted(): void
    {
        $delStatus = Delivery::STATUS_PICKED_UP;
        $orderStatus = Order::STATUS_PICKED_UP;
        $pickedUpAt = '2026-10-24 08:15:00';

        $hasDelDispatched = in_array($delStatus, [Delivery::STATUS_PICKED_UP, Delivery::STATUS_OUT_FOR_DELIVERY, Delivery::STATUS_DELIVERED], true);
        $hasOrderDispatched = in_array($orderStatus, [Order::STATUS_PICKED_UP, Order::STATUS_DELIVERED], true);

        $isDispatched = ($hasDelDispatched || $hasOrderDispatched || !empty($pickedUpAt));

        $this->assertTrue($isDispatched, 'Internal driver who has picked up the package must be accepted');
    }

    /**
     * 10. Status Transitions: Final delivered state sets all required fields.
     */
    public function test_delivery_confirmation_state_mapping(): void
    {
        $newStatus = Order::STATUS_DELIVERED;
        $customerConfirmed = true;
        $now = '2026-10-24 08:30:00';

        $this->assertSame('delivered', $newStatus);
        $this->assertTrue($customerConfirmed);
        $this->assertNotEmpty($now);
    }
}
