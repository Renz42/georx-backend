<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\DeliveryService;
use App\Services\SupabaseRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobileDriverController extends Controller
{
    /**
     * Available unassigned delivery bookings feed for drivers.
     */
    public function availableBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        $driverLat = $profile?->current_latitude;
        $driverLng = $profile?->current_longitude;

        $available = Delivery::availableForDrivers()
            ->with(['order.items.medicine', 'pickupPharmacy'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($delivery) use ($driverLat, $driverLng) {
                $pickupDist = DeliveryService::calculateDistance(
                    $driverLat, $driverLng,
                    $delivery->pickup_latitude, $delivery->pickup_longitude
                );
                $deliveryDist = DeliveryService::calculateDistance(
                    $delivery->pickup_latitude, $delivery->pickup_longitude,
                    $delivery->delivery_latitude, $delivery->delivery_longitude
                );

                $medicineSummary = $delivery->order?->items->map(function ($item) {
                    return ($item->medicine->brand_name ?? $item->medicine->generic_name) . ' x ' . $item->quantity;
                })->join(', ') ?? 'Medicine Order';

                return [
                    'id' => $delivery->id,
                    'booking_number' => $delivery->booking_number,
                    'order_number' => '#' . (10000 + $delivery->order_id),
                    'status' => $delivery->status,
                    'created_at' => $delivery->created_at->format('M d, Y g:i A'),
                    'created_time' => $delivery->created_at->diffForHumans(),
                    'pickup_pharmacy_name' => $delivery->pickupPharmacy?->name ?? 'Pharmacy',
                    'pickup_pharmacy_address' => $delivery->pickupPharmacy?->address ?? 'Barangay Alijis',
                    'pickup_distance' => $pickupDist !== null ? round($pickupDist, 1) . ' km' : 'N/A',
                    'delivery_area' => 'Barangay Alijis, Bacolod City',
                    'delivery_distance' => $deliveryDist !== null ? round($deliveryDist, 1) . ' km' : 'N/A',
                    'medicine_summary' => $medicineSummary,
                    'order_amount' => (float)($delivery->order?->total_amount ?? 0),
                    'delivery_fee' => 30.00,
                ];
            });

        $activeDelivery = Delivery::activeForDriver($user->id)
            ->with(['order.items.medicine', 'pickupPharmacy', 'customer'])
            ->first();

        return response()->json([
            'status' => 'success',
            'is_online' => (bool)($profile?->is_online),
            'has_active_delivery' => !is_null($activeDelivery),
            'active_delivery' => $activeDelivery ? [
                'id' => $activeDelivery->id,
                'booking_number' => $activeDelivery->booking_number,
                'order_id' => $activeDelivery->order_id,
                'status' => $activeDelivery->status,
                'status_label' => ucwords(str_replace('_', ' ', $activeDelivery->status)),
                'verification_code' => $activeDelivery->verification_code 
                    ? (str_starts_with($activeDelivery->verification_code, 'GRX-') 
                        ? $activeDelivery->verification_code 
                        : 'GRX-' . $activeDelivery->verification_code)
                    : 'GRX-8492',
                'pickup_pharmacy' => [
                    'name' => $activeDelivery->pickupPharmacy?->name ?? 'TGP Pharmacy',
                    'address' => $activeDelivery->pickupPharmacy?->address ?? 'Alijis Road, Olympia Village, Singcang-Airport, Alijis, Bacolod City',
                    'latitude' => (float)($activeDelivery->pickup_latitude ?? 10.644508),
                    'longitude' => (float)($activeDelivery->pickup_longitude ?? 122.940537),
                ],
                'delivery_location' => [
                    'address' => $activeDelivery->delivery_address ?? 'Barangay Alijis, Bacolod City',
                    'latitude' => (float)($activeDelivery->delivery_latitude ?? 10.6385),
                    'longitude' => (float)($activeDelivery->delivery_longitude ?? 122.9520),
                    'customer_name' => $activeDelivery->customer?->name ?? 'Patient Customer',
                    'customer_phone' => $activeDelivery->customer?->phone ?? '+63 917 000 0000',
                ],
                'driver_location' => [
                    'latitude' => (float)($profile?->current_latitude ?? 10.6415),
                    'longitude' => (float)($profile?->current_longitude ?? 122.9460),
                ],
                'timestamps' => [
                    'accepted_at' => $activeDelivery->driver_accepted_at ? $activeDelivery->driver_accepted_at->format('M d, Y g:i A') : null,
                    'picked_up_at' => $activeDelivery->picked_up_at ? $activeDelivery->picked_up_at->format('M d, Y g:i A') : null,
                    'delivered_at' => $activeDelivery->delivered_at ? $activeDelivery->delivered_at->format('M d, Y g:i A') : null,
                ],
            ] : null,
            'available_bookings' => $available,
        ]);
    }

    /**
     * Accept a delivery booking atomically (DB lock).
     */
    public function acceptBooking(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // 1. Single Active Delivery Rule Check
        if (Delivery::activeForDriver($user->id)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have an active delivery in progress. Complete it first before accepting another booking.',
            ], 422);
        }

        try {
            $delivery = DB::transaction(function () use ($id, $user) {
                // Lock row to prevent race conditions
                $targetDelivery = Delivery::where('id', $id)->lockForUpdate()->first();

                if (!$targetDelivery) {
                    throw new \Exception('Booking not found.');
                }

                if ($targetDelivery->driver_id !== null || $targetDelivery->status !== Delivery::STATUS_WAITING_FOR_DRIVER) {
                    throw new \Exception('This booking has already been accepted by another driver.');
                }

                // Ensure verification code exists
                if (!$targetDelivery->verification_code) {
                    $targetDelivery->verification_code = 'GRX-' . rand(1000, 9999);
                }

                // Claim delivery
                $targetDelivery->driver_id = $user->id;
                $targetDelivery->status = Delivery::STATUS_DRIVER_ASSIGNED;
                $targetDelivery->driver_accepted_at = now();
                $targetDelivery->save();

                // Update order status & driver details
                $order = Order::find($targetDelivery->order_id);
                if ($order) {
                    $order->status = 'driver_assigned';
                    $order->driver_name = $user->name;
                    $order->driver_phone = $user->phone ?? '09171234567';
                    $order->driver_vehicle = 'Yamaha NMAX (Motorcycle)';
                    $order->save();
                }

                return $targetDelivery;
            });

            // Create Notification for Patient
            $pharmacyName = $delivery->pickupPharmacy?->name ?? 'TGP Pharmacy';
            $this->createPatientNotification(
                $delivery->customer_id ?? $delivery->order?->user_id,
                $delivery->order_id,
                'Booking Accepted',
                "Your medicine order #{$delivery->order_id} has been accepted by driver {$user->name}! The driver is heading to {$pharmacyName} for pickup.",
                $user,
                Delivery::STATUS_DRIVER_ASSIGNED
            );

            // Supabase Realtime update broadcast
            SupabaseRealtimeService::broadcastDeliveryStatusChange($delivery, Delivery::STATUS_DRIVER_ASSIGNED, [
                'driver_name' => $user->name,
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Booking accepted successfully! Navigating to pickup location.',
                'delivery' => [
                    'id' => $delivery->id,
                    'booking_number' => $delivery->booking_number,
                    'status' => $delivery->status,
                    'pickup_latitude' => (float)$delivery->pickup_latitude,
                    'pickup_longitude' => (float)$delivery->pickup_longitude,
                    'delivery_latitude' => (float)$delivery->delivery_latitude,
                    'delivery_longitude' => (float)$delivery->delivery_longitude,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Helper to create patient database notification.
     */
    private function createPatientNotification($customerId, $orderId, $title, $message, $driverUser, $status)
    {
        if (!$customerId) return;

        try {
            DB::table('notifications')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\DeliveryStatusNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $customerId,
                'data' => json_encode([
                    'title' => $title,
                    'message' => $message,
                    'order_id' => (int)$orderId,
                    'status' => $status,
                    'driver_name' => $driverUser?->name ?? 'William John',
                    'driver_phone' => $driverUser?->phone ?? '09171234567',
                    'driver_vehicle' => 'Yamaha NMAX (Motorcycle)',
                    'plate_number' => 'ABC-1234',
                ]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Patient notification creation error: ' . $e->getMessage());
        }
    }

    /**
     * Update delivery workflow status state machine.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:driver_at_pharmacy,picked_up,out_for_delivery,delivered',
        ]);

        $user = $request->user();
        $delivery = Delivery::where('id', $id)->where('driver_id', $user->id)->first();

        if (!$delivery) {
            return response()->json(['status' => 'error', 'message' => 'Active delivery not found.'], 404);
        }

        $nextStatus = $request->status;
        $currentStatus = $delivery->status;

        // State Machine Strict Validation
        $allowedTransitions = [
            Delivery::STATUS_DRIVER_ASSIGNED => [Delivery::STATUS_DRIVER_AT_PHARMACY],
            Delivery::STATUS_DRIVER_AT_PHARMACY => [Delivery::STATUS_PICKED_UP],
            Delivery::STATUS_PICKED_UP => [Delivery::STATUS_OUT_FOR_DELIVERY],
            Delivery::STATUS_OUT_FOR_DELIVERY => [Delivery::STATUS_DELIVERED],
        ];

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($nextStatus, $allowedTransitions[$currentStatus])) {
            return response()->json([
                'status' => 'error',
                'message' => "Invalid status transition from '{$currentStatus}' to '{$nextStatus}'.",
            ], 422);
        }

        // Apply Status Change
        $delivery->status = $nextStatus;

        if ($nextStatus === Delivery::STATUS_PICKED_UP) {
            $delivery->picked_up_at = now();
        } elseif ($nextStatus === Delivery::STATUS_DELIVERED) {
            $delivery->delivered_at = now();
        }

        $delivery->save();

        // Sync associated order
        $order = Order::find($delivery->order_id);
        if ($order) {
            $orderStatusMap = [
                Delivery::STATUS_DRIVER_AT_PHARMACY => Order::STATUS_DRIVER_AT_PHARMACY,
                Delivery::STATUS_PICKED_UP => Order::STATUS_PICKED_UP,
                Delivery::STATUS_OUT_FOR_DELIVERY => Order::STATUS_OUT_FOR_DELIVERY,
                Delivery::STATUS_DELIVERED => Order::STATUS_DELIVERED,
            ];
            $order->status = $orderStatusMap[$nextStatus] ?? $order->status;
            $order->save();
        }

        // Create Patient Notification
        $statusMessages = [
            Delivery::STATUS_DRIVER_AT_PHARMACY => [
                'title' => 'Driver at Pharmacy',
                'msg'   => "Driver {$user->name} has arrived at the pharmacy to pick up your prescription package.",
            ],
            Delivery::STATUS_PICKED_UP => [
                'title' => 'Order Picked Up',
                'msg'   => "Driver {$user->name} has picked up your medicine package from the pharmacy cashier.",
            ],
            Delivery::STATUS_OUT_FOR_DELIVERY => [
                'title' => 'Out for Delivery',
                'msg'   => "Driver {$user->name} is now on the way to deliver your order to Barangay Alijis.",
            ],
            Delivery::STATUS_DELIVERED => [
                'title' => 'Order Delivered',
                'msg'   => "Your prescription medicine order has been successfully delivered by {$user->name}.",
            ],
        ];

        if (isset($statusMessages[$nextStatus])) {
            $this->createPatientNotification(
                $delivery->customer_id ?? $order?->user_id,
                $delivery->order_id,
                $statusMessages[$nextStatus]['title'],
                $statusMessages[$nextStatus]['msg'],
                $user,
                $nextStatus
            );
        }

        // Supabase Realtime broadcast
        SupabaseRealtimeService::broadcastDeliveryStatusChange($delivery, $nextStatus, [
            'driver_name' => $user->name,
            'timestamp' => now()->toIso8601String(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery status updated to ' . ucwords(str_replace('_', ' ', $nextStatus)),
            'current_status' => $nextStatus,
            'status_label' => ucwords(str_replace('_', ' ', $nextStatus)),
        ]);
    }

    /**
     * Driver Live GPS Location Update.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return response()->json(['status' => 'error', 'message' => 'Driver profile missing.'], 404);
        }

        $hasActiveDelivery = Delivery::activeForDriver($user->id)->exists();

        // Only update if driver is online OR actively delivering
        if (!$profile->is_online && !$hasActiveDelivery) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Location tracking disabled when offline with no active delivery.'
            ]);
        }

        $profile->current_latitude = $request->latitude;
        $profile->current_longitude = $request->longitude;
        $profile->last_location_at = now();
        $profile->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Location synchronized',
            'last_location_at' => $profile->last_location_at->toDateTimeString(),
        ]);
    }

    /**
     * Driver completed deliveries history & earnings.
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        $completed = Delivery::completedForDriver($user->id)
            ->with(['order', 'pickupPharmacy'])
            ->orderByDesc('delivered_at')
            ->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'booking_number' => $delivery->booking_number,
                    'order_number' => '#' . (10000 + $delivery->order_id),
                    'status' => $delivery->status,
                    'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('M d, Y g:i A') : null,
                    'pharmacy_name' => $delivery->pickupPharmacy?->name ?? 'Pharmacy',
                    'earnings' => 30.00, // Flat delivery fee per trip
                ];
            });

        $totalEarnings = $completed->count() * 30.00;

        return response()->json([
            'status' => 'success',
            'total_deliveries' => $completed->count(),
            'total_earnings' => $totalEarnings,
            'deliveries' => $completed,
        ]);
    }

    /**
     * Driver Patient Reviews & Feedback feed.
     */
    public function reviews(Request $request): JsonResponse
    {
        $user = $request->user();

        $reviews = \App\Models\Review::where('driver_id', $user->id)
            ->with(['user:id,name', 'order:id,created_at'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($rev) {
                return [
                    'id' => $rev->id,
                    'rating' => (float)$rev->rating,
                    'comment' => $rev->comment ?? 'Great delivery service!',
                    'patient_name' => $rev->user?->name ?? 'Patient Customer',
                    'delivery_speed' => (int)($rev->delivery_speed ?? $rev->rating),
                    'driver_professionalism' => (int)($rev->driver_professionalism ?? $rev->rating),
                    'created_at' => $rev->created_at ? $rev->created_at->format('M d, Y g:i A') : 'Recently',
                ];
            });

        $avgRating = $reviews->count() > 0 ? $reviews->avg('rating') : 4.9;

        return response()->json([
            'status' => 'success',
            'average_rating' => round($avgRating, 1),
            'total_reviews' => $reviews->count(),
            'reviews' => $reviews,
        ]);
    }
}
