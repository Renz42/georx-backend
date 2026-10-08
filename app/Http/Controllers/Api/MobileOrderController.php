<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileOrderController extends Controller
{
    /**
     * Get patient's orders list (active & historical).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $orders = Order::where('user_id', $user->id)
            ->with(['pharmacy:id,name,address,phone', 'items.medicine:id,generic_name,brand_name', 'delivery'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => '#' . (10000 + $order->id),
                    'status' => $order->status,
                    'status_label' => ucwords(str_replace('_', ' ', $order->status)),
                    'total_amount' => (float)$order->total_amount,
                    'delivery_address' => $order->delivery_address,
                    'created_at' => $order->created_at ? $order->created_at->format('M d, Y g:i A') : null,
                    'created_at_iso' => $order->created_at ? $order->created_at->toIso8601String() : null,
                    'pharmacy' => [
                        'id' => $order->pharmacy->id ?? null,
                        'name' => $order->pharmacy->name ?? 'Pharmacy',
                        'address' => $order->pharmacy->address ?? '',
                    ],
                    'items_count' => $order->items->count(),
                    'delivery_status' => $order->delivery_status ?? ($order->delivery?->status ?? null),
                    'delivery_provider' => $order->delivery_provider,
                    'delivery_provider_label' => $order->delivery_provider === 'grab' ? 'Grab Express' : ($order->delivery_provider === 'maxim' ? 'Maxim Delivery' : null),
                    'delivery_booking_id' => $order->delivery_booking_id,
                ];
            });

        return response()->json([
            'status' => 'success',
            'orders' => $orders,
        ]);
    }

    /**
     * Get details for a specific order.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $order = Order::where('user_id', $user->id)
            ->with(['pharmacy', 'items.medicine', 'delivery.driver.driverProfile', 'review'])
            ->findOrFail($id);

        $isDelivered = $order->status === Order::STATUS_DELIVERED;
        $review = $order->review;

        return response()->json([
            'status' => 'success',
            'order' => [
                'id' => $order->id,
                'order_number' => '#' . (10000 + $order->id),
                'status' => $order->status,
                'status_label' => ucwords(str_replace('_', ' ', $order->status)),
                'delivery_status' => $isDelivered ? 'delivered' : ($order->delivery_status ?? ($order->delivery?->status ?? null)),
                'delivery_provider' => $order->delivery_provider,
                'delivery_booking_id' => $order->delivery_booking_id,
                'delivery_tracking_url' => $order->delivery_tracking_url,
                'customer_confirmed_delivery' => (bool)$order->customer_confirmed_delivery,
                'customer_confirmed_at' => $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : null,
                'delivered_at' => $order->delivered_at ? $order->delivered_at->format('M d, Y g:i A') : null,
                'has_review' => (bool)$review,
                'review_rating' => $review ? (int)$review->rating : null,
                'total_amount' => (float)$order->total_amount,
                'delivery_fee' => (float)$order->delivery_fee,
                'subtotal' => round((float)$order->total_amount - (float)$order->delivery_fee, 2),
                'delivery_address' => $order->delivery_address,
                'latitude' => (float)($order->latitude ?? 10.6385),
                'longitude' => (float)($order->longitude ?? 122.9520),
                'created_at' => $order->created_at ? $order->created_at->format('M d, Y g:i A') : null,
                'created_at_iso' => $order->created_at ? $order->created_at->toIso8601String() : null,
                'pharmacy' => $order->pharmacy,
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'medicine_name' => $item->medicine->brand_name ?? $item->medicine->generic_name,
                        'dosage' => $item->medicine->dosage ?? '',
                        'quantity' => $item->quantity,
                        'price' => (float)$item->price,
                        'subtotal' => (float)($item->price * $item->quantity),
                    ];
                }),
                'delivery' => $order->delivery_provider ? [
                    'id' => null,
                    'is_external' => true,
                    'provider' => $order->delivery_provider,
                    'provider_label' => $order->delivery_provider === 'grab' ? 'Grab Express' : 'Maxim',
                    'booking_number' => $order->delivery_booking_id,
                    'tracking_url' => $order->delivery_tracking_url,
                    'status' => $order->delivery_status ?? $order->status,
                    'status_label' => ucwords(str_replace('_', ' ', $order->delivery_status ?? $order->status)),
                    'driver' => ($order->driver_name || $order->driver_phone) ? [
                        'name' => $order->driver_name,
                        'phone' => $order->driver_phone,
                        'vehicle' => $order->driver_vehicle ?? 'Motorcycle',
                        'rating' => 5.0,
                    ] : null,
                ] : ($order->delivery ? [
                    'id' => $order->delivery->id,
                    'is_external' => false,
                    'booking_number' => $order->delivery->booking_number,
                    'status' => $order->delivery->status,
                    'status_label' => ucwords(str_replace('_', ' ', $order->delivery->status)),
                    'driver' => $order->delivery->driver ? [
                        'name' => $order->delivery->driver->name,
                        'phone' => $order->delivery->driver->phone,
                        'vehicle' => $order->delivery->driver->driverProfile?->vehicle_description,
                        'rating' => 4.8,
                    ] : null,
                ] : null),
            ],
        ]);
    }

    /**
     * Get live delivery tracking coordinates, ETAs, and recency status for mobile map.
     */
    public function liveTracking(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::with(['pharmacy', 'delivery.driver.driverProfile'])->findOrFail($id);

        // Security check
        if ((int)$order->user_id !== (int)$user->id) {
            return response()->json(['error' => 'Unauthorized access.'], 403);
        }

        // 1. External Delivery Provider (Grab Express / Maxim)
        if ($order->delivery_provider) {
            $isGrab = $order->delivery_provider === 'grab';
            $providerLabel = $isGrab ? 'Grab Express' : 'Maxim';
            $pickupLat = (float)($order->pharmacy->latitude ?? 10.644508);
            $pickupLng = (float)($order->pharmacy->longitude ?? 122.940537);
            $deliveryLat = (float)($order->latitude ?? 10.6385);
            $deliveryLng = (float)($order->longitude ?? 122.9520);

            $dist = \App\Services\DeliveryService::calculateDistance($pickupLat, $pickupLng, $deliveryLat, $deliveryLng);
            $deliveryMins = $dist !== null ? max(3, (int)round(($dist / 25) * 60 + 5)) : 10;

            return response()->json([
                'status' => 'success',
                'is_external_provider' => true,
                'has_active_delivery' => in_array($order->status, [
                    Order::STATUS_ACCEPTED,
                    Order::STATUS_AT_PHARMACY,
                    Order::STATUS_PICKED_UP,
                    'out_for_delivery',
                ]),
                'order_id' => $order->id,
                'order_number' => '#' . (10000 + $order->id),
                'pharmacy_confirmed' => (bool)$order->pharmacy_confirmed,
                'confirmed_at' => $order->confirmed_at ? $order->confirmed_at->format('M d, Y g:i A') : null,
                'is_prepared' => (bool)$order->is_prepared,
                'customer_confirmed_delivery' => (bool)$order->customer_confirmed_delivery,
                'customer_confirmed_at' => $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : null,
                'delivery_provider' => $order->delivery_provider,
                'delivery_provider_label' => $providerLabel,
                'delivery_booking_id' => $order->delivery_booking_id,
                'delivery_tracking_url' => $order->delivery_tracking_url,
                'delivery_status' => $order->status === Order::STATUS_DELIVERED ? 'delivered' : ($order->delivery_status ?? $order->status),
                'order_status' => $order->status,
                'status_label' => ucwords(str_replace('_', ' ', $order->status === Order::STATUS_DELIVERED ? 'delivered' : ($order->delivery_status ?? $order->status))),
                'eta' => [
                    'to_patient_mins' => $deliveryMins,
                    'to_patient_label' => "{$deliveryMins} mins (" . now()->addMinutes($deliveryMins)->format('g:i A') . ")",
                ],
                'driver' => ($order->driver_name || $order->driver_phone) ? [
                    'name' => $order->driver_name ?? "{$providerLabel} Courier Rider",
                    'phone' => $order->driver_phone,
                    'vehicle' => $order->driver_vehicle ?? 'Motorcycle',
                    'rating' => 5.0,
                    'is_external' => true,
                ] : null,
                'locations' => [
                    'driver' => null, // No raw GPS coordinates for external providers
                    'pickup' => [
                        'pharmacy_name' => $order->pharmacy->name ?? 'Pharmacy',
                        'latitude' => $pickupLat,
                        'longitude' => $pickupLng,
                    ],
                    'delivery' => [
                        'address' => $order->delivery_address,
                        'latitude' => $deliveryLat,
                        'longitude' => $deliveryLng,
                    ],
                ],
                'last_location_at' => null,
                'is_stale' => false,
            ]);
        }

        // 2. Legacy Internal Delivery
        $delivery = $order->delivery;
        if (!$delivery) {
            return response()->json([
                'status' => 'error',
                'has_active_delivery' => false,
                'is_prepared' => (bool)$order->is_prepared,
                'pharmacy_confirmed' => (bool)$order->pharmacy_confirmed,
                'message' => 'No delivery record assigned yet.',
            ]);
        }

        $driverUser = $delivery->driver;
        $driverProfile = $driverUser?->driverProfile;
        $lastLocationAt = $driverProfile?->last_location_at;
        $isStale = is_null($lastLocationAt) || now()->diffInMinutes($lastLocationAt) > 5;

        // Dynamic ETA Calculation (Barangay Alijis ~25 km/h motorcycle speed)
        $driverLat = (float)($driverProfile?->current_latitude ?? 0);
        $driverLng = (float)($driverProfile?->current_longitude ?? 0);
        $pickupLat = (float)$delivery->pickup_latitude;
        $pickupLng = (float)$delivery->pickup_longitude;
        $deliveryLat = (float)$delivery->delivery_latitude;
        $deliveryLng = (float)$delivery->delivery_longitude;

        $distToPickup = ($driverLat != 0 && $driverLng != 0) 
            ? \App\Services\DeliveryService::calculateDistance($driverLat, $driverLng, $pickupLat, $pickupLng) 
            : null;

        $distToDelivery = ($driverLat != 0 && $driverLng != 0)
            ? \App\Services\DeliveryService::calculateDistance($driverLat, $driverLng, $deliveryLat, $deliveryLng)
            : \App\Services\DeliveryService::calculateDistance($pickupLat, $pickupLng, $deliveryLat, $deliveryLng);

        $pickupMins = $distToPickup !== null ? max(2, (int)round(($distToPickup / 25) * 60 + 3)) : 5;
        $deliveryMins = $distToDelivery !== null ? max(3, (int)round(($distToDelivery / 25) * 60 + 5)) : 10;

        return response()->json([
            'status' => 'success',
            'has_active_delivery' => in_array($delivery->status, [
                Delivery::STATUS_DRIVER_ASSIGNED,
                Delivery::STATUS_DRIVER_AT_PHARMACY,
                Delivery::STATUS_PICKED_UP,
                Delivery::STATUS_OUT_FOR_DELIVERY,
            ]),
            'order_id' => $order->id,
            'order_number' => '#' . (10000 + $order->id),
            'pharmacy_confirmed' => (bool)$order->pharmacy_confirmed,
            'confirmed_at' => $order->confirmed_at ? $order->confirmed_at->format('M d, Y g:i A') : null,
            'is_prepared' => (bool)$order->is_prepared,
            'picked_up_at' => $delivery->picked_up_at ? $delivery->picked_up_at->format('M d, Y g:i A') : null,
            'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('M d, Y g:i A') : null,
            'customer_confirmed_delivery' => (bool)$order->customer_confirmed_delivery,
            'customer_confirmed_at' => $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : null,
            'delivery_id' => $delivery->id,
            'delivery_status' => $delivery->status,
            'status_label' => ucwords(str_replace('_', ' ', $delivery->status)),
            'verification_code' => $delivery->verification_code ?? 'GRX-' . rand(100000, 999999),
            'eta' => [
                'to_pharmacy_mins' => $pickupMins,
                'to_pharmacy_label' => "{$pickupMins} mins (" . now()->addMinutes($pickupMins)->format('g:i A') . ")",
                'to_patient_mins' => $deliveryMins,
                'to_patient_label' => "{$deliveryMins} mins (" . now()->addMinutes($deliveryMins)->format('g:i A') . ")",
            ],
            'driver' => $driverUser ? [
                'name' => $driverUser->name,
                'phone' => $driverUser->phone,
                'vehicle' => $driverProfile?->vehicle_description ?? 'Motorcycle',
                'plate_number' => $driverProfile?->plate_number ?? 'N/A',
                'rating' => 4.8,
            ] : null,
            'locations' => [
                'driver' => [
                    'latitude' => $driverLat,
                    'longitude' => $driverLng,
                ],
                'pickup' => [
                    'pharmacy_name' => $order->pharmacy->name ?? 'Pharmacy',
                    'latitude' => $pickupLat,
                    'longitude' => $pickupLng,
                ],
                'delivery' => [
                    'address' => $delivery->delivery_address,
                    'latitude' => $deliveryLat,
                    'longitude' => $deliveryLng,
                ],
            ],
            'last_location_at' => $lastLocationAt?->toDateTimeString(),
            'is_stale' => $isStale,
        ]);
    }

    /**
     * Customer Approves / Confirms Prescription Medicine Handover.
     */
    public function confirmDelivery(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $order = Order::where('id', $id)->where('user_id', $user->id)->with('delivery')->firstOrFail();

        // Cancelled / rejected orders can never be confirmed as delivered.
        if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This order was cancelled or rejected and cannot be confirmed as delivered.',
            ], 422);
        }

        $now = now();

        // Idempotency: a repeated confirm must not re-notify the patient/pharmacy.
        $alreadyConfirmed = (bool)$order->customer_confirmed_delivery;

        $order->status = Order::STATUS_DELIVERED;
        $order->delivered_at = $order->delivered_at ?? $now;
        $order->customer_confirmed_delivery = true;
        $order->customer_confirmed_at = $order->customer_confirmed_at ?? $now;
        if ($order->delivery_provider) {
            // Keep external courier status in sync so tracking never reports a stale 'in_transit'.
            $order->delivery_status = 'delivered';
        }
        $order->save();

        if ($alreadyConfirmed) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delivery was already confirmed. Thank you!',
                'already_confirmed' => true,
                'order' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'status_label' => 'Delivered (Approved)',
                    'delivery_status' => $order->delivery_provider ? 'delivered' : ($order->delivery?->status ?? 'delivered'),
                    'customer_confirmed_delivery' => true,
                    'customer_confirmed_at' => $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : $now->format('M d, Y g:i A'),
                ],
            ]);
        }

        if ($order->delivery) {
            $order->delivery->status = Delivery::STATUS_DELIVERED;
            $order->delivery->delivered_at = $order->delivery->delivered_at ?? $now;
            $order->delivery->save();

            // Supabase Realtime broadcast
            try {
                \App\Services\SupabaseRealtimeService::broadcastDeliveryStatusChange($order->delivery, Delivery::STATUS_DELIVERED, [
                    'customer_confirmed' => true,
                    'timestamp' => $now->toIso8601String(),
                ]);
            } catch (\Throwable $e) {}
        }

        // Trigger Notifications (Customer Review Prompt & Pharmacy Admin Alert)
        try {
            $user->notify(new \App\Notifications\ReviewPrompt($order));

            $pharmacyUser = \App\Models\User::where('pharmacy_id', $order->pharmacy_id)->first();
            if ($pharmacyUser) {
                $pharmacyUser->notify(new \App\Notifications\OrderStatusChanged(
                    $order,
                    "Order #{$order->id} has been delivered & approved by customer {$user->name}!",
                    "fas fa-check-circle text-emerald-500"
                ));
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery approved! Thank you for confirming receipt of your prescription order.',
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'status_label' => 'Delivered (Approved)',
                'delivery_status' => 'delivered',
                'customer_confirmed_delivery' => true,
                'customer_confirmed_at' => $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : $now->format('M d, Y g:i A'),
            ],
        ]);
    }
}
