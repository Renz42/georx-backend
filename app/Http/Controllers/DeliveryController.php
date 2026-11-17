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

        $driver = Auth::user();
        $driverName = $driver?->name ?? 'A delivery partner';
        $order->driver_name = $order->driver_name ?: $driverName;

        $notificationService = app(\App\Services\PatientNotificationService::class);
        $notificationService->notifyOrderCustomer(
            $order,
            'driver_assigned:' . (Auth::id() ?? 'partner'),
            new \App\Notifications\OrderStatusChanged(
                $order,
                "{$driverName} accepted delivery for Order #{$order->id} and is heading to the pharmacy.",
                'fas fa-motorcycle text-blue-500'
            )
        );
        $notificationService->notifyOrderPharmacy(
            $order,
            'driver_assigned:' . (Auth::id() ?? 'partner'),
            new \App\Notifications\DeliveryAcceptedByDriver($order),
            null,
            false
        );

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

        $driver = Auth::user();
        $notificationService = app(\App\Services\PatientNotificationService::class);

        if ($request->status === 'at_pharmacy' && $driver) {
            $notificationService->notifyOrderCustomer(
                $order,
                'arrived_at_pickup',
                new \App\Notifications\DriverArrivedAtPharmacyNotification($order, $driver, 'customer')
            );
            $notificationService->notifyOrderPharmacy(
                $order,
                'arrived_at_pickup',
                new \App\Notifications\DriverArrivedAtPharmacyNotification($order, $driver, 'pharmacy'),
                null,
                false
            );
        } elseif ($request->status === 'picked_up' && $driver) {
            $notificationService->notifyOrderCustomer(
                $order,
                'picked_up',
                new \App\Notifications\OrderPickedUpNotification($order, $driver)
            );
        } elseif ($request->status === 'delivered') {
            try {
                \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v3');
                \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v4');
            } catch (\Throwable) {
            }

            if ($driver) {
                $notificationService->notifyOrderCustomer(
                    $order,
                    'delivered',
                    new \App\Notifications\OrderDeliveredNotification($order, $driver)
                );
            } else {
                $notificationService->notifyOrderCustomer(
                    $order,
                    'delivered',
                    new \App\Notifications\OrderDelivered($order)
                );
            }
            $notificationService->notifyOrderCustomer(
                $order,
                'review_prompt',
                new \App\Notifications\ReviewPrompt($order)
            );
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

        $txResult = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $status, $payload, $bookingId) {
            $order = Order::where('id', $order->id)->lockForUpdate()->firstOrFail();

            // Re-verify provider and booking reference against locked database record
            if ($order->delivery_provider !== 'maxim') {
                return ['response' => response()->json(['error' => 'Order is not managed by Maxim delivery provider.'], 422)];
            }

            if (empty($order->delivery_booking_id) || (string)$order->delivery_booking_id !== $bookingId) {
                return ['response' => response()->json(['error' => 'Booking reference mismatch.'], 422)];
            }

            // Pharmacy confirmation check: unconfirmed orders cannot be dispatched/completed
            if (!$order->pharmacy_confirmed) {
                return ['response' => response()->json(['error' => 'Order has not been confirmed by pharmacy.'], 422)];
            }

            // Terminal state check
            if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REJECTED], true)) {
                return ['response' => response()->json(['error' => 'Order is in a terminal cancelled/rejected state.'], 422)];
            }

            // Eager load relationships to avoid N+1 queries inside the switch
            $order->load('user', 'pharmacy');
            $pharmacyName = $order->pharmacy?->name ?? 'the pharmacy';
            $events = [];

            switch ($status) {
                case 'driver_assigned':
                    if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_PICKED_UP], true)) {
                        return ['response' => response()->json(['success' => true, 'ignored' => 'Order is already picked up or delivered.'])];
                    }
                    $order->update([
                        'status' => Order::STATUS_ACCEPTED,
                        'driver_name' => $payload['driver']['name'] ?? ($order->driver_name ?: 'Driver'),
                        'driver_phone' => $payload['driver']['phone'] ?? ($order->driver_phone ?: ''),
                        'driver_vehicle' => $payload['driver']['vehicle'] ?? ($order->driver_vehicle ?: ''),
                        'estimated_delivery_at' => isset($payload['eta']) ? \Carbon\Carbon::parse($payload['eta']) : $order->estimated_delivery_at,
                    ]);
                    $driverKey = strtolower(trim((string) ($order->driver_name ?: 'driver')));
                    $events[] = ['target' => 'customer', 'suffix' => "driver_assigned:{$driverKey}", 'notification' => new \App\Notifications\DriverAssigned($order)];
                    $events[] = ['target' => 'pharmacy', 'suffix' => "driver_assigned:{$driverKey}", 'notification' => new \App\Notifications\DeliveryAcceptedByDriver($order)];
                    break;

                case 'arrived_at_pickup':
                    if (in_array($order->status, [Order::STATUS_DELIVERED, Order::STATUS_PICKED_UP], true)) {
                        return ['response' => response()->json(['success' => true, 'ignored' => 'Order is already picked up or delivered.'])];
                    }
                    $order->update(['status' => Order::STATUS_AT_PHARMACY]);
                    $events[] = [
                        'target' => 'customer',
                        'suffix' => 'arrived_at_pickup',
                        'notification' => new \App\Notifications\OrderStatusChanged(
                            $order,
                            "Your courier driver has arrived at {$pharmacyName} to pick up Order #{$order->id}.",
                            'fas fa-store text-blue-500'
                        ),
                    ];
                    break;

                case 'picked_up':
                    if ($order->status === Order::STATUS_DELIVERED || $order->delivery_status === 'delivered') {
                        return ['response' => response()->json(['success' => true, 'ignored' => 'Order is already delivered.'])];
                    }
                    $order->update([
                        'status' => Order::STATUS_PICKED_UP,
                        'delivery_status' => 'in_transit',
                        'picked_up_at' => $order->picked_up_at ?? now(),
                    ]);
                    $events[] = [
                        'target' => 'customer',
                        'suffix' => 'picked_up',
                        'notification' => new \App\Notifications\OrderStatusChanged(
                            $order,
                            "Your order #{$order->id} has been picked up from {$pharmacyName} and is now out for delivery!",
                            'fas fa-shipping-fast text-indigo-500'
                        ),
                    ];
                    break;

                case 'arrived_at_dropoff':
                    $events[] = ['target' => 'customer', 'suffix' => 'arrived_at_dropoff', 'notification' => new \App\Notifications\DriverArrived($order)];
                    break;

                case 'completed':
                    // Idempotent duplicate event check
                    if ($order->status === Order::STATUS_DELIVERED || $order->delivery_status === 'delivered') {
                        return ['response' => response()->json(['success' => true, 'already_delivered' => true])];
                    }

                    // Verify dispatch / pickup requirement: Cannot complete an undispatched order
                    $isDispatched = in_array($order->status, [Order::STATUS_PICKED_UP, Order::STATUS_DELIVERED], true)
                        || in_array($order->delivery_status, ['in_transit', 'delivered'], true)
                        || !empty($order->picked_up_at);

                    if (!$isDispatched) {
                        return ['response' => response()->json(['error' => 'Cannot mark order completed without prior dispatch/pickup.'], 422)];
                    }

                    $order->update([
                        'status' => Order::STATUS_DELIVERED,
                        'delivery_status' => 'delivered',
                        'delivered_at' => $order->delivered_at ?? now(),
                    ]);

                    try {
                        \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v3');
                        \Illuminate\Support\Facades\Cache::forget('admin_dashboard_data_v4');
                    } catch (\Throwable) {
                    }

                    $events[] = ['target' => 'customer', 'suffix' => 'delivered', 'notification' => new \App\Notifications\OrderDelivered($order)];
                    $events[] = ['target' => 'customer', 'suffix' => 'review_prompt', 'notification' => new \App\Notifications\ReviewPrompt($order)];
                    break;

                case 'cancelled':
                    if ($order->status === Order::STATUS_DELIVERED || $order->delivery_status === 'delivered') {
                        return ['response' => response()->json(['error' => 'Cannot cancel an order that has already been delivered.'], 422)];
                    }
                    $order->update([
                        'status' => Order::STATUS_CANCELLED,
                        'delivery_status' => 'cancelled',
                    ]);
                    $events[] = [
                        'target' => 'customer',
                        'suffix' => 'courier_cancelled',
                        'notification' => new \App\Notifications\OrderStatusChanged(
                            $order,
                            "Delivery for Order #{$order->id} was cancelled by the courier.",
                            'fas fa-exclamation-triangle text-rose-500'
                        ),
                    ];
                    $events[] = [
                        'target' => 'pharmacy',
                        'suffix' => 'courier_cancelled',
                        'notification' => new \App\Notifications\OrderStatusChanged(
                            $order,
                            "Courier cancelled delivery for Order #{$order->id}. Please review and rebook if needed.",
                            'fas fa-exclamation-triangle text-amber-500'
                        ),
                    ];
                    break;

                default:
                    return ['response' => response()->json(['error' => 'Unsupported webhook status: ' . $status], 422)];
            }

            return [
                'response' => response()->json(['success' => true]),
                'order' => $order,
                'events' => $events,
            ];
        });

        if (!empty($txResult['events']) && isset($txResult['order'])) {
            $notificationService = app(\App\Services\PatientNotificationService::class);
            $updatedOrder = $txResult['order'];
            foreach ($txResult['events'] as $evt) {
                if ($evt['target'] === 'pharmacy') {
                    $notificationService->notifyOrderPharmacy($updatedOrder, $evt['suffix'], $evt['notification'], null, false);
                } else {
                    $notificationService->notifyOrderCustomer($updatedOrder, $evt['suffix'], $evt['notification']);
                }
            }
        }

        return $txResult['response'];
    }
}
