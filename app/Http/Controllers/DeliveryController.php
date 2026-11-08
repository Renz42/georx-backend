<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        // Available orders (pending, not yet accepted by any delivery partner)
        $availableOrders = Order::where('status', 'pending')
            ->whereNull('delivery_partner_id')
            ->with('pharmacy', 'items.medicine', 'user')
            ->orderByDesc('created_at')
            ->get();

        // My active deliveries (accepted by this partner, not yet delivered/cancelled)
        $activeDeliveries = Order::where('delivery_partner_id', $user->id)
            ->whereIn('status', ['accepted', 'at_pharmacy', 'picked_up'])
            ->with('pharmacy', 'items.medicine', 'user')
            ->orderByDesc('created_at')
            ->get();

        // Completed deliveries
        $completedDeliveries = Order::where('delivery_partner_id', $user->id)
            ->where('status', 'delivered')
            ->with('pharmacy', 'user')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('delivery.dashboard', compact('availableOrders', 'activeDeliveries', 'completedDeliveries'));
    }

    public function acceptOrder($id)
    {
        $order = Order::where('id', $id)->where('status', 'pending')->whereNull('delivery_partner_id')->firstOrFail();

        if (!$order->pharmacy_confirmed) {
            return redirect()->route('delivery.dashboard')->with('error', 'Order must be confirmed by the pharmacy first.');
        }

        $order->update([
            'delivery_partner_id' => Auth::id(),
            'status' => 'accepted',
        ]);

        return redirect()->route('delivery.dashboard')->with('success', 'Order #' . $order->id . ' accepted! Head to the pharmacy.');
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:at_pharmacy,picked_up,delivered',
        ]);

        $order = Order::where('id', $id)->where('delivery_partner_id', Auth::id())->firstOrFail();

        // Enforce status progression
        $allowed = [
            'accepted' => 'at_pharmacy',
            'at_pharmacy' => 'picked_up',
            'picked_up' => 'delivered',
        ];

        if (($allowed[$order->status] ?? null) !== $request->status) {
            return back()->with('error', 'Invalid status transition.');
        }

        $updateData = ['status' => $request->status];
        if ($request->status === 'picked_up') {
            $updateData['picked_up_at'] = $order->picked_up_at ?? now();
            $updateData['delivery_status'] = 'in_transit';
        } elseif ($request->status === 'delivered') {
            $updateData['delivered_at'] = $order->delivered_at ?? now();
            $updateData['delivery_status'] = 'delivered';
        }

        $order->update($updateData);

        if ($request->status === 'delivered') {
            try {
                $order->user?->notify(new \App\Notifications\ReviewPrompt($order));
            } catch (\Throwable $e) {}
        }

        $message = match($request->status) {
            'at_pharmacy' => 'Marked as arrived at pharmacy.',
            'picked_up' => 'Order picked up! Delivering now.',
            'delivered' => 'Order delivered successfully!',
            default => 'Status updated.',
        };

        return redirect()->route('delivery.dashboard')->with('success', $message);
    }

    public function webhook(Request $request, $order)
    {
        // 1. Authenticate webhook request BEFORE order lookup (Fail-closed: secret must be configured)
        $configuredSecret = config('services.maxim.webhook_secret');
        if (empty($configuredSecret) || !is_string($configuredSecret)) {
            return response()->json(['error' => 'Webhook secret is not configured.'], 503);
        }

        // 2. Reject missing or invalid authentication before querying the order
        $signature = $request->header('X-Maxim-Signature');
        if (empty($signature) || !hash_equals($configuredSecret, $signature)) {
            return response()->json(['error' => 'Unauthorized: Invalid or missing webhook signature.'], 401);
        }

        // 3. Only after successful authentication, look up the order using the route identifier
        $orderId = $order instanceof Order ? $order->id : $order;
        $orderRecord = Order::find($orderId);
        if (!$orderRecord) {
            return response()->json(['error' => 'Order not found.'], 404);
        }
        $order = $orderRecord;

        // 4. Provider verification: order must be assigned to Maxim
        if ($order->delivery_provider !== 'maxim') {
            return response()->json(['error' => 'Order is not managed by Maxim delivery provider.'], 422);
        }

        // 5. Active booking verification: order must have an active booking
        if (empty($order->delivery_booking_id)) {
            return response()->json(['error' => 'Order has no active courier booking.'], 422);
        }

        $payload = $request->all();
        $status = $payload['status'] ?? null;

        if (!$status) {
            return response()->json(['error' => 'Invalid payload: status is required.'], 400);
        }

        // 6. Booking reference verification: required and must match order's booking reference
        $rawBookingId = $payload['booking_id'] ?? $payload['booking_number'] ?? null;
        if ($rawBookingId === null || trim((string)$rawBookingId) === '') {
            return response()->json(['error' => 'Booking reference is required.'], 422);
        }
        $bookingId = trim((string)$rawBookingId);

        if ($bookingId !== (string)$order->delivery_booking_id) {
            return response()->json(['error' => 'Booking reference mismatch.'], 422);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($order, $status, $payload, $bookingId) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // Re-verify provider and booking reference against locked database record
            if ($order->delivery_provider !== 'maxim') {
                return response()->json(['error' => 'Order is not managed by Maxim delivery provider.'], 422);
            }

            if (empty($order->delivery_booking_id) || (string)$order->delivery_booking_id !== $bookingId) {
                return response()->json(['error' => 'Booking reference mismatch.'], 422);
            }

            // Pharmacy confirmation check: unconfirmed orders cannot be dispatched/completed
            if (!$order->pharmacy_confirmed) {
                return response()->json(['error' => 'Order has not been confirmed by pharmacy.'], 422);
            }

            // Terminal state check
            if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true)) {
                return response()->json(['error' => 'Order is in a terminal cancelled/rejected state.'], 422);
            }

            // Eager load relationships to avoid N+1 queries inside the switch
            $order->load('user', 'pharmacy');

            switch ($status) {
                case 'driver_assigned':
                    if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_PICKED_UP], true)) {
                        return response()->json(['success' => true, 'ignored' => 'Order is already picked up or delivered.']);
                    }
                    $order->update([
                        'status' => Order::STATUS_ACCEPTED,
                        'driver_name' => $payload['driver']['name'] ?? ($order->driver_name ?: 'Driver'),
                        'driver_phone' => $payload['driver']['phone'] ?? ($order->driver_phone ?: ''),
                        'driver_vehicle' => $payload['driver']['vehicle'] ?? ($order->driver_vehicle ?: ''),
                        'estimated_delivery_at' => isset($payload['eta']) ? \Carbon\Carbon::parse($payload['eta']) : $order->estimated_delivery_at,
                    ]);
                    try {
                        $order->user?->notify(new \App\Notifications\DriverAssigned($order));
                        $pharmacyUser = \App\Models\User::where('pharmacy_id', $order->pharmacy_id)->first();
                        if ($pharmacyUser) {
                            $pharmacyUser->notify(new \App\Notifications\DeliveryAcceptedByDriver($order));
                        }
                    } catch (\Throwable $e) {}
                    break;

                case 'arrived_at_pickup':
                    if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_PICKED_UP], true)) {
                        return response()->json(['success' => true, 'ignored' => 'Order is already picked up or delivered.']);
                    }
                    $order->update(['status' => Order::STATUS_AT_PHARMACY]);
                    break;

                case 'picked_up':
                    if ($order->status === Order::STATUS_DELIVERED || $order->delivery_status === 'delivered') {
                        return response()->json(['success' => true, 'ignored' => 'Order is already delivered.']);
                    }
                    $order->update([
                        'status' => Order::STATUS_PICKED_UP,
                        'delivery_status' => 'in_transit',
                        'picked_up_at' => $order->picked_up_at ?? now(),
                    ]);
                    break;

                case 'arrived_at_dropoff':
                    try {
                        $order->user?->notify(new \App\Notifications\DriverArrived($order));
                    } catch (\Throwable $e) {}
                    break;

                case 'completed':
                    // Idempotent duplicate event check
                    if ($order->status === Order::STATUS_DELIVERED || $order->delivery_status === 'delivered') {
                        return response()->json(['success' => true, 'already_delivered' => true]);
                    }

                    // Verify dispatch / pickup requirement: Cannot complete an undispatched order
                    $isDispatched = in_array($order->status, [Order::STATUS_PICKED_UP, Order::STATUS_DELIVERED], true)
                        || in_array($order->delivery_status, ['in_transit', 'delivered'], true)
                        || !empty($order->picked_up_at);

                    if (!$isDispatched) {
                        return response()->json(['error' => 'Cannot mark order completed without prior dispatch/pickup.'], 422);
                    }

                    $order->update([
                        'status' => Order::STATUS_DELIVERED,
                        'delivery_status' => 'delivered',
                        'delivered_at' => $order->delivered_at ?? now(),
                    ]);

                    try {
                        $order->user?->notify(new \App\Notifications\OrderDelivered($order));
                        $order->user?->notify(new \App\Notifications\ReviewPrompt($order));
                    } catch (\Throwable $e) {}
                    break;

                case 'cancelled':
                    if ($order->status === Order::STATUS_DELIVERED || $order->delivery_status === 'delivered') {
                        return response()->json(['error' => 'Cannot cancel an order that has already been delivered.'], 422);
                    }
                    $order->update([
                        'status' => Order::STATUS_CANCELLED,
                        'delivery_status' => 'cancelled',
                    ]);
                    break;

                default:
                    return response()->json(['error' => 'Unsupported webhook status: ' . $status], 422);
            }

            return response()->json(['success' => true]);
        });
    }
}
