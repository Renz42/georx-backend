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

    /**
     * Calculate authoritative estimated delivery fee for GEORX order.
     * Uses configurable Superadmin parameters from GlobalSetting.
     *
     * @param float|null $pickupLat
     * @param float|null $pickupLng
     * @param float|null $dropoffLat
     * @param float|null $dropoffLng
     * @return float Authoritative estimated delivery fee in PHP
     */
    public static function calculateEstimatedDeliveryFee(?float $pickupLat = null, ?float $pickupLng = null, ?float $dropoffLat = null, ?float $dropoffLng = null): float
    {
        $settings = \App\Models\GlobalSetting::getSettings();

        $baseFee = (float)($settings->delivery_base_fee ?? $settings->base_delivery_fee ?? 50.00);
        $perKmFee = (float)($settings->delivery_per_km_fee ?? 0.00);
        $minFee = (float)($settings->delivery_minimum_fee ?? 50.00);
        $maxFee = (float)($settings->delivery_maximum_estimate ?? 150.00);
        $enabled = (bool)($settings->delivery_estimate_enabled ?? true);

        if (!$enabled) {
            return $minFee;
        }

        $distance = static::calculateDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        // Standard Barangay Alijis base coverage is 2.0 km
        if ($distance !== null && $distance > 2.0 && $perKmFee > 0) {
            $extraDistance = $distance - 2.0;
            $calculatedFee = $baseFee + ($extraDistance * $perKmFee);
        } else {
            $calculatedFee = $baseFee;
        }

        // Clamp between minimum and maximum bounds
        return round(min($maxFee, max($minFee, $calculatedFee)), 2);
    }

    /**
     * Compute consistent, non-destructive order pricing breakdown keeping customer COD
     * anchored to the confirmed order total (medicine subtotal + stored delivery fee)
     * while exposing recorded actual courier fare solely for internal logistics tracking.
     *
     * @param Order $order
     * @return array<string, mixed>
     */
    public static function getOrderPricingSummary(Order $order): array
    {
        $estimatedDeliveryFee = round(max(0.0, (float) ($order->delivery_fee ?? 0.0)), 2);
        $storedTotalAmount = round(max(0.0, (float) ($order->total_amount ?? 0.0)), 2);

        $items = $order->relationLoaded('items') ? $order->items : null;
        if ($items && $items->isNotEmpty()) {
            $medicineSubtotal = round((float) $items->sum(function ($item) {
                return (float) $item->price * (int) $item->quantity;
            }), 2);
        } else {
            // Historical fallback when items are absent or intentionally omitted in fixtures
            $medicineSubtotal = round(max(0.0, $storedTotalAmount - $estimatedDeliveryFee), 2);
        }

        $hasActualCourierFee = $order->actual_courier_fee !== null;
        $actualCourierFee = $hasActualCourierFee ? round((float) $order->actual_courier_fee, 2) : null;
        $effectiveDeliveryFee = $estimatedDeliveryFee;
        $confirmedTotal = $order->total_amount !== null
            ? $storedTotalAmount
            : round($medicineSubtotal + $estimatedDeliveryFee, 2);

        return [
            'medicine_subtotal'       => $medicineSubtotal,
            'estimated_delivery_fee'  => $estimatedDeliveryFee,
            'actual_courier_fee'      => $actualCourierFee,
            'has_actual_courier_fee'  => $hasActualCourierFee,
            'courier_fee_status'      => $hasActualCourierFee ? 'recorded' : 'pending',
            'effective_delivery_fee'  => $effectiveDeliveryFee,
            'estimated_total'         => $confirmedTotal,
            'customer_payable_amount' => $confirmedTotal,
            'delivery_fee_variance'   => $hasActualCourierFee ? round($actualCourierFee - $estimatedDeliveryFee, 2) : null,
            'courier_fee_notice'      => 'Customer COD payable remains fixed at the confirmed order total (Medicine Subtotal + Delivery Fee). Recorded actual courier fare is for internal logistics tracking only.',
        ];
    }

    /**
     * Validate assisted external courier provider against booking reference and optional tracking URL
     * without silently mutating the booking reference or assuming one strict prefix format.
     *
     * @return array{valid: bool, errors: array<string, string>, normalized_booking_id: ?string}
     */
    public static function validateCourierBooking(?string $provider, ?string $bookingId, ?string $trackingUrl = null, bool $requireBookingId = true): array
    {
        $errors = [];
        $normalizedProvider = strtolower(trim((string) ($provider ?? '')));

        if (!in_array($normalizedProvider, [Order::PROVIDER_GRAB, Order::PROVIDER_MAXIM], true)) {
            $errors['delivery_provider'] = 'Please select a valid courier provider (Grab Express or Maxim Delivery).';
            return [
                'valid' => false,
                'errors' => $errors,
                'normalized_booking_id' => $bookingId !== null ? trim($bookingId) : null,
            ];
        }

        $trimmedBookingId = $bookingId !== null ? trim($bookingId) : null;

        if ($trimmedBookingId === null || $trimmedBookingId === '') {
            if ($requireBookingId) {
                $errors['delivery_booking_id'] = 'Booking reference ID is required for external courier dispatch.';
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors,
                'normalized_booking_id' => null,
            ];
        }

        if (preg_match('/[\r\n\t]/', $trimmedBookingId) || strlen($trimmedBookingId) < 3 || strlen($trimmedBookingId) > 100 || !preg_match('/[A-Za-z0-9]/', $trimmedBookingId)) {
            $errors['delivery_booking_id'] = 'Enter a valid booking reference ID (3 to 100 alphanumeric/symbol characters).';
        }

        $looksLikeMaximRef = (bool) preg_match('/(?:^|[^A-Za-z0-9])MAXIM(?:[0-9]|[^A-Za-z0-9]|$)|^MAX[-_#:\s][A-Za-z0-9]/i', $trimmedBookingId);
        $looksLikeGrabRef = (bool) preg_match('/(?:^|[^A-Za-z0-9])GRAB(?:EXPRESS|EX)?(?:[0-9]|[^A-Za-z0-9]|$)|^GE[-_#:\s][A-Za-z0-9]/i', $trimmedBookingId);

        if ($normalizedProvider === Order::PROVIDER_GRAB && $looksLikeMaximRef && !$looksLikeGrabRef) {
            $errors['delivery_booking_id'] = 'This booking reference appears to belong to Maxim. Please switch Provider to Maxim Delivery or enter a valid Grab Express booking reference.';
        } elseif ($normalizedProvider === Order::PROVIDER_MAXIM && $looksLikeGrabRef && !$looksLikeMaximRef) {
            $errors['delivery_booking_id'] = 'This booking reference appears to belong to Grab Express. Please switch Provider to Grab Express or enter a valid Maxim booking reference.';
        }

        $trimmedUrl = $trackingUrl !== null ? trim($trackingUrl) : '';
        if ($trimmedUrl !== '') {
            $parsedUrl = preg_match('#^[a-z][a-z0-9+.-]*://#i', $trimmedUrl)
                ? $trimmedUrl
                : 'https://' . ltrim($trimmedUrl, '/');
            $host = strtolower((string) parse_url($parsedUrl, PHP_URL_HOST));
            if ($normalizedProvider === Order::PROVIDER_GRAB && preg_match('/(^|\.)(taximaxim\.com|maxim\.[a-z.]+)$/i', $host)) {
                $errors['delivery_tracking_url'] = 'This tracking URL points to Maxim, which conflicts with the selected Grab Express provider.';
            } elseif ($normalizedProvider === Order::PROVIDER_MAXIM && preg_match('/(^|\.)(grab\.com|grab\.onelink\.me)$/i', $host)) {
                $errors['delivery_tracking_url'] = 'This tracking URL points to Grab Express, which conflicts with the selected Maxim Delivery provider.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'normalized_booking_id' => $trimmedBookingId,
        ];
    }
}
