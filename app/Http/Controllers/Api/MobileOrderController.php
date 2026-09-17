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
                    'created_at' => $order->created_at->format('M d, Y g:i A'),
                    'pharmacy' => [
                        'id' => $order->pharmacy->id ?? null,
                        'name' => $order->pharmacy->name ?? 'Pharmacy',
                        'address' => $order->pharmacy->address ?? '',
                    ],
                    'items_count' => $order->items->count(),
                    'delivery_status' => $order->delivery?->status ?? null,
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
            ->with(['pharmacy', 'items.medicine', 'delivery.driver.driverProfile'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'order' => [
                'id' => $order->id,
                'order_number' => '#' . (10000 + $order->id),
                'status' => $order->status,
                'status_label' => ucwords(str_replace('_', ' ', $order->status)),
                'total_amount' => (float)$order->total_amount,
                'delivery_address' => $order->delivery_address,
                'created_at' => $order->created_at->format('M d, Y g:i A'),
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
                'delivery' => $order->delivery ? [
                    'id' => $order->delivery->id,
                    'booking_number' => $order->delivery->booking_number,
                    'status' => $order->delivery->status,
                    'status_label' => ucwords(str_replace('_', ' ', $order->delivery->status)),
                    'driver' => $order->delivery->driver ? [
                        'name' => $order->delivery->driver->name,
                        'phone' => $order->delivery->driver->phone,
                        'vehicle' => $order->delivery->driver->driverProfile?->vehicle_description,
                        'rating' => 4.8,
                    ] : null,
                ] : null,
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

        $now = now();
        $order->status = Order::STATUS_DELIVERED;
        $order->customer_confirmed_delivery = true;
        $order->customer_confirmed_at = $now;
        $order->delivered_at = $order->delivered_at ?? $now;
        $order->save();

        if ($order->delivery) {
            $order->delivery->status = Delivery::STATUS_DELIVERED;
            $order->delivery->customer_confirmed_at = $now;
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
                'customer_confirmed_delivery' => true,
                'customer_confirmed_at' => $now->format('M d, Y g:i A'),
            ],
        ]);
    }
}
