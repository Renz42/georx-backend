<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Notifications\DriverAssignedNotification;
use App\Notifications\ReviewPrompt;
use App\Services\DeliveryService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DriverController — GEORX Driver Portal
 *
 * Phase 5: Complete Driver Booking System with race-condition atomic locking,
 * Haversine distance calculations, 1 active delivery limit, real-time notifications,
 * and strict privacy/authorization scoping.
 */
class DriverController extends Controller
{
    // =============================================
    // DASHBOARD
    // =============================================

    public function dashboard()
    {
        $user    = Auth::guard('driver')->user();
        $profile = $user->driverProfile;

        // Current driver live coordinates (if set)
        $driverLat = $profile?->current_latitude;
        $driverLng = $profile?->current_longitude;

        // 1. My Active Delivery (1 MAX)
        $activeDelivery = Delivery::activeForDriver($user->id)
            ->with(['order.items.medicine', 'pickupPharmacy', 'customer'])
            ->first();

        if ($activeDelivery) {
            $activeDelivery->pickup_distance = DeliveryService::calculateDistance(
                $driverLat, $driverLng,
                $activeDelivery->pickup_latitude, $activeDelivery->pickup_longitude
            );
            $activeDelivery->delivery_distance = DeliveryService::calculateDistance(
                $activeDelivery->pickup_latitude, $activeDelivery->pickup_longitude,
                $activeDelivery->delivery_latitude, $activeDelivery->delivery_longitude
            );
        }

        // 2. Available Unassigned Bookings (feed)
        $availableDeliveries = Delivery::availableForDrivers()
            ->with(['order.items.medicine', 'pickupPharmacy'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($delivery) use ($driverLat, $driverLng) {
                $delivery->pickup_distance = DeliveryService::calculateDistance(
                    $driverLat, $driverLng,
                    $delivery->pickup_latitude, $delivery->pickup_longitude
                );
                $delivery->delivery_distance = DeliveryService::calculateDistance(
                    $delivery->pickup_latitude, $delivery->pickup_longitude,
                    $delivery->delivery_latitude, $delivery->delivery_longitude
                );
                return $delivery;
            });

        // 3. Completed Deliveries (last 20)
        $completedDeliveries = Delivery::completedForDriver($user->id)
            ->with(['order.items.medicine', 'pickupPharmacy'])
            ->orderByDesc('delivered_at')
            ->limit(20)
            ->get();

        $hasActiveDelivery = !is_null($activeDelivery);

        return view('driver.dashboard', compact(
            'user',
            'profile',
            'activeDelivery',
            'availableDeliveries',
            'completedDeliveries',
            'hasActiveDelivery'
        ));
    }

    // =============================================
    // BOOKINGS FEED
    // =============================================

    /**
     * Available Bookings Feed.
     * SECURITY: Only shows unassigned bookings (status = 'waiting_for_driver', driver_id IS NULL).
     * Privacy: Hides customer contact info (phone/email).
     */
    public function bookings()
    {
        $user    = Auth::guard('driver')->user();
        $profile = $user->driverProfile;

        $driverLat = $profile?->current_latitude;
        $driverLng = $profile?->current_longitude;

        $hasActiveDelivery = Delivery::activeForDriver($user->id)->exists();

        $availableDeliveries = Delivery::availableForDrivers()
            ->with(['order.items.medicine', 'pickupPharmacy'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($delivery) use ($driverLat, $driverLng) {
                $delivery->pickup_distance = DeliveryService::calculateDistance(
                    $driverLat, $driverLng,
                    $delivery->pickup_latitude, $delivery->pickup_longitude
                );
                $delivery->delivery_distance = DeliveryService::calculateDistance(
                    $delivery->pickup_latitude, $delivery->pickup_longitude,
                    $delivery->delivery_latitude, $delivery->delivery_longitude
                );
                return $delivery;
            });

        return view('driver.bookings', compact('user', 'profile', 'availableDeliveries', 'hasActiveDelivery'));
    }

    // =============================================
    // SINGLE BOOKING DETAIL
    // =============================================

    /**
     * Show detail for a single delivery booking.
     * SECURITY & AUTHORIZATION:
     * A driver may ONLY view:
     *   - Unassigned available bookings (status = 'waiting_for_driver', driver_id IS NULL)
     *   - Bookings assigned to THEMSELVES (driver_id === auth()->id())
     * Accessing another driver's private delivery booking returns 403 Forbidden.
     */
    public function showBooking($id)
    {
        $user = Auth::guard('driver')->user();

        // Load delivery by delivery ID or order ID
        $delivery = Delivery::where('id', $id)
            ->orWhere('order_id', $id)
            ->with(['order.items.medicine', 'pickupPharmacy', 'customer', 'driver'])
            ->firstOrFail();

        // AUTHORIZATION CHECK
        $isUnassigned = $delivery->status === Delivery::STATUS_WAITING_FOR_DRIVER && is_null($delivery->driver_id);
        $isMyDelivery = (int) $delivery->driver_id === (int) $user->id;

        if (!$isUnassigned && !$isMyDelivery) {
            abort(403, 'You are not authorized to view this private delivery booking.');
        }

        $profile = $user->driverProfile;
        $delivery->pickup_distance = DeliveryService::calculateDistance(
            $profile?->current_latitude, $profile?->current_longitude,
            $delivery->pickup_latitude, $delivery->pickup_longitude
        );
        $delivery->delivery_distance = DeliveryService::calculateDistance(
            $delivery->pickup_latitude, $delivery->pickup_longitude,
            $delivery->delivery_latitude, $delivery->delivery_longitude
        );

        return view('driver.booking-detail', compact('user', 'delivery'));
    }

    // =============================================
    // DELIVERY MAP VIEW (PHASE 6)
    // =============================================

    /**
     * Interactive Dual-Marker Delivery Map.
     * SECURITY: Strictly limited to the assigned driver or unassigned booking.
     */
    public function deliveryMap($id)
    {
        $user = Auth::guard('driver')->user();

        $delivery = Delivery::where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('order_id', $id);
            })
            ->with(['order.items.medicine', 'pickupPharmacy', 'customer', 'driver'])
            ->firstOrFail();

        // AUTHORIZATION CHECK
        $isUnassigned = $delivery->status === Delivery::STATUS_WAITING_FOR_DRIVER && is_null($delivery->driver_id);
        $isMyDelivery = (int) $delivery->driver_id === (int) $user->id;

        if (!$isUnassigned && !$isMyDelivery) {
            abort(403, 'You are not authorized to view this private delivery map.');
        }

        $profile = $user->driverProfile;
        $delivery->pickup_distance = DeliveryService::calculateDistance(
            $profile?->current_latitude, $profile?->current_longitude,
            $delivery->pickup_latitude, $delivery->pickup_longitude
        );
        $delivery->delivery_distance = DeliveryService::calculateDistance(
            $delivery->pickup_latitude, $delivery->pickup_longitude,
            $delivery->delivery_latitude, $delivery->delivery_longitude
        );

        return view('driver.map', compact('user', 'profile', 'delivery'));
    }

    // =============================================
    // ACCEPT A BOOKING (ATOMIC TRANSACTION)
    // =============================================

    /**
     * Driver accepts an available booking.
     *
     * BUSINESS & SECURITY RULES:
     * 1. ONE active delivery per driver limit.
     * 2. Atomic claim via DB transaction with lockForUpdate() to prevent race conditions.
     * 3. Assigns driver_id, updates status to DRIVER_ASSIGNED, records accepted_at.
     * 4. Removes booking from other drivers' available feeds.
     * 5. Sends real-time notifications to Customer & Pharmacy Admin.
     */
    public function acceptBooking($id)
    {
        $user = Auth::guard('driver')->user();

        try {
            DB::transaction(function () use ($id, $user) {
                // 1. DRIVER LIMIT RULE: Max 1 active delivery per driver
                $hasActive = Delivery::activeForDriver($user->id)
                    ->lockForUpdate()
                    ->exists();

                if ($hasActive) {
                    throw new Exception('You already have an active delivery in progress. Please complete your current delivery before accepting another.');
                }

                // 2. ATOMIC CLAIM: Pessimistically lock target delivery row
                $delivery = Delivery::where(function ($q) use ($id) {
                        $q->where('id', $id)->orWhere('order_id', $id);
                    })
                    ->where('status', Delivery::STATUS_WAITING_FOR_DRIVER)
                    ->whereNull('driver_id')
                    ->lockForUpdate()
                    ->first();

                if (!$delivery) {
                    throw new Exception('This booking has already been accepted by another driver or is no longer available.');
                }

                // 3. Update Delivery record
                $delivery->update([
                    'driver_id'          => $user->id,
                    'status'             => Delivery::STATUS_DRIVER_ASSIGNED,
                    'driver_accepted_at' => now(),
                ]);

                // 4. Update Order record
                $order = Order::lockForUpdate()->find($delivery->order_id);
                if ($order) {
                    $order->update([
                        'delivery_partner_id' => $user->id,
                        'status'              => Order::STATUS_ACCEPTED,
                        'accepted_at'         => now(),
                    ]);
                }

                // 5. Set driver availability to false (on active delivery)
                $user->driverProfile?->update(['is_available' => false]);

                // 6. Notify Customer & Pharmacy Admin
                if ($order && $order->user) {
                    $order->user->notify(new DriverAssignedNotification($order, $user, 'customer'));
                }

                if ($order) {
                    $pharmacyOwner = User::where('pharmacy_id', $order->pharmacy_id)->first();
                    if ($pharmacyOwner) {
                        $pharmacyOwner->notify(new DriverAssignedNotification($order, $user, 'pharmacy'));
                    }
                }
            });

            return redirect()->route('driver.bookings.show', $id)
                ->with('success', 'Booking accepted! Proceed to the pharmacy to pick up the order.');

        } catch (Exception $e) {
            Log::warning("Driver #{$user->id} failed to accept booking #{$id}: " . $e->getMessage());
            return redirect()->route('driver.dashboard')->with('error', $e->getMessage());
        }
    }

    // =============================================
    // UPDATE DELIVERY STATUS (PHASE 7 STATE MACHINE)
    // =============================================

    /**
     * Driver updates delivery status.
     * SECURITY: Strictly limited to the assigned driver's own delivery.
     * Transitions: driver_assigned → driver_at_pharmacy → picked_up → out_for_delivery → delivered
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:driver_at_pharmacy,picked_up,out_for_delivery,delivered',
        ]);

        $user = Auth::guard('driver')->user();
        $targetStatus = $request->status;

        try {
            $message = DB::transaction(function () use ($id, $user, $targetStatus) {
                // SECURITY CHECK: Only assigned driver can update
                $delivery = Delivery::where(function ($q) use ($id) {
                        $q->where('id', $id)->orWhere('order_id', $id);
                    })
                    ->where('driver_id', $user->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $order = Order::lockForUpdate()->findOrFail($delivery->order_id);

                // Enforce strict progression step matrix
                $allowed = [
                    Delivery::STATUS_DRIVER_ASSIGNED   => ['driver_at_pharmacy'],
                    Delivery::STATUS_DRIVER_AT_PHARMACY => ['picked_up'],
                    Delivery::STATUS_PICKED_UP         => ['out_for_delivery'],
                    Delivery::STATUS_OUT_FOR_DELIVERY   => ['delivered'],
                ];

                if (!in_array($targetStatus, $allowed[$delivery->status] ?? [])) {
                    throw new Exception("Invalid status transition step. You cannot skip steps in the delivery workflow.");
                }

                $now = now();
                $msg = 'Status updated.';

                if ($targetStatus === 'driver_at_pharmacy') {
                    $delivery->update([
                        'status'                => Delivery::STATUS_DRIVER_AT_PHARMACY,
                        'driver_at_pharmacy_at' => $now,
                    ]);
                    $order->update(['status' => Order::STATUS_AT_PHARMACY]);

                    // Notifications
                    if ($order->user) {
                        $order->user->notify(new \App\Notifications\DriverArrivedAtPharmacyNotification($order, $user, 'customer'));
                    }
                    $pharmacyOwner = User::where('pharmacy_id', $order->pharmacy_id)->first();
                    if ($pharmacyOwner) {
                        $pharmacyOwner->notify(new \App\Notifications\DriverArrivedAtPharmacyNotification($order, $user, 'pharmacy'));
                    }

                    $msg = 'Arrived at pharmacy! Please collect and verify the medicine.';

                } elseif ($targetStatus === 'picked_up') {
                    $delivery->update([
                        'status'       => Delivery::STATUS_PICKED_UP,
                        'picked_up_at' => $now,
                    ]);
                    $order->update([
                        'status'       => Order::STATUS_PICKED_UP,
                        'picked_up_at' => $now,
                    ]);

                    if ($order->user) {
                        $order->user->notify(new \App\Notifications\OrderPickedUpNotification($order, $user));
                    }

                    $msg = 'Medicine verified & picked up from pharmacy!';

                } elseif ($targetStatus === 'out_for_delivery') {
                    $delivery->update([
                        'status'              => Delivery::STATUS_OUT_FOR_DELIVERY,
                        'out_for_delivery_at' => $now,
                    ]);

                    if ($order->user) {
                        $order->user->notify(new \App\Notifications\OrderOutForDeliveryNotification($order, $user));
                    }

                    $msg = 'You are now out for delivery heading to the customer location!';

                } elseif ($targetStatus === 'delivered') {
                    $delivery->update([
                        'status'       => Delivery::STATUS_DELIVERED,
                        'delivered_at' => $now,
                    ]);
                    $order->update([
                        'status'       => Order::STATUS_DELIVERED,
                        'delivered_at' => $now,
                    ]);

                    // Restore driver availability for next booking
                    $user->driverProfile?->update(['is_available' => true]);

                    if ($order->user) {
                        $order->user->notify(new \App\Notifications\OrderDeliveredNotification($order, $user));
                        $order->user->notify(new ReviewPrompt($order));
                    }

                    $msg = 'Delivery completed successfully! You are now available for new bookings.';
                }

                return $msg;
            });

            return redirect()->route('driver.dashboard')->with('success', $message);

        } catch (Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // =============================================
    // TOGGLE ONLINE / OFFLINE AVAILABILITY
    // =============================================

    public function toggleAvailability(Request $request)
    {
        $user    = Auth::guard('driver')->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return back()->with('error', 'Driver profile not found.');
        }

        $profile->update([
            'is_online' => !$profile->is_online,
        ]);

        $statusText = $profile->is_online ? 'ONLINE' : 'OFFLINE';

        return back()->with('success', "Status updated: You are now {$statusText}.");
    }

    // =============================================
    // DRIVER LOCATION TRACKING UPDATE (PHASE 9)
    // =============================================

    /**
     * Update Driver GPS Location.
     *
     * BUSINESS & PRIVACY RULES:
     * - Only process/record location when driver is ONLINE OR has an ACTIVE DELIVERY.
     * - Do NOT continuously track drivers when they are completely offline and have no active delivery.
     * - Validates numeric coordinates (latitude: -90 to 90, longitude: -180 to 180).
     */
    public function updateLocation(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $user    = Auth::guard('driver')->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found.'], 404);
        }

        $activeDelivery = Delivery::activeForDriver($user->id)->first();
        $isOnline       = (bool) $profile->is_online;
        $hasActive      = !is_null($activeDelivery);

        // RULE: Only track when driver is online OR has active delivery
        if (!$isOnline && !$hasActive) {
            return response()->json([
                'success'         => true,
                'tracking_active' => false,
                'message'         => 'Location update skipped. Driver is offline with no active delivery.',
            ]);
        }

        $now = now();
        $profile->update([
            'current_latitude'  => $request->latitude,
            'current_longitude' => $request->longitude,
            'last_location_at'  => $now,
        ]);

        $payload = \App\Services\SupabaseRealtimeService::buildDriverLocationPayload($profile, $activeDelivery);

        return response()->json([
            'success'          => true,
            'tracking_active'  => true,
            'last_location_at'  => $now->toDateTimeString(),
            'payload'          => $payload,
        ]);
    }
}
