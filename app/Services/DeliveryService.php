<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use Exception;
use Illuminate\Support\Facades\Log;

class DeliveryService
{
    /**
     * Create a new delivery booking for a confirmed order.
     *
     * Business Rules Enforced:
     * 1. The customer's selected pharmacy becomes the official pickup pharmacy.
     * 2. Pickup location uses pharmacy's actual stored latitude/longitude.
     * 3. Prevents duplicate delivery bookings for the same order.
     * 4. Validates order state (pharmacy confirmed, delivery address present).
     *
     * @param Order $order
     * @return Delivery
     * @throws Exception
     */
    public static function createDeliveryForOrder(Order $order): Delivery
    {
        // 1. Prevent duplicate delivery bookings
        $existing = Delivery::where('order_id', $order->id)->first();
        if ($existing) {
            Log::info("Delivery booking already exists for order #{$order->id}", ['delivery_id' => $existing->id]);
            return $existing;
        }

        // 2. Validate Order eligibility
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REJECTED])) {
            throw new Exception("Cannot create delivery for cancelled or rejected order #{$order->id}.");
        }

        // 3. Ensure pickup pharmacy exists and has coordinates
        $pharmacy = $order->pharmacy;
        if (!$pharmacy) {
            throw new Exception("Order #{$order->id} has no valid pickup pharmacy associated.");
        }

        if (is_null($pharmacy->latitude) || is_null($pharmacy->longitude)) {
            throw new Exception("Pickup pharmacy #{$pharmacy->id} ({$pharmacy->name}) does not have valid GPS coordinates.");
        }

        // 4. Ensure customer delivery address is provided
        if (empty($order->delivery_address)) {
            throw new Exception("Order #{$order->id} is missing a required customer delivery address.");
        }

        // 5. Create official delivery booking record
        $delivery = Delivery::create([
            'order_id'           => $order->id,
            'customer_id'        => $order->user_id,
            'driver_id'          => null, // Driver assigned when accepted in Phase 5
            'pickup_pharmacy_id' => $pharmacy->id,
            'pickup_latitude'    => $pharmacy->latitude,
            'pickup_longitude'   => $pharmacy->longitude,
            'delivery_address'   => $order->delivery_address,
            'delivery_latitude'  => $order->latitude,
            'delivery_longitude' => $order->longitude,
            'status'             => Delivery::STATUS_WAITING_FOR_DRIVER,
            'verification_code'  => (string)rand(100000, 999999),
        ]);

        Log::info("Delivery booking #{$delivery->id} created successfully for order #{$order->id}", [
            'pickup_pharmacy_id' => $pharmacy->id,
            'pickup_coords'      => "{$pharmacy->latitude},{$pharmacy->longitude}",
            'delivery_address'   => $order->delivery_address,
        ]);

        return $delivery;
    }

    /**
     * Calculate distance in kilometers between two GPS coordinates using Haversine formula.
     * Returns null if any coordinate is missing.
     *
     * @param float|null $lat1
     * @param float|null $lng1
     * @param float|null $lat2
     * @param float|null $lng2
     * @return float|null Distance in kilometers rounded to 1 decimal place
     */
    public static function calculateDistance(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if (is_null($lat1) || is_null($lng1) || is_null($lat2) || is_null($lng2)) {
            return null;
        }

        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadiusKm * $c, 1);
    }
}
