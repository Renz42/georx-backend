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

        $order->update(['status' => $request->status]);

        if ($request->status === 'delivered') {
            $order->user?->notify(new \App\Notifications\ReviewPrompt($order));
        }

        $message = match($request->status) {
            'at_pharmacy' => 'Marked as arrived at pharmacy.',
            'picked_up' => 'Order picked up! Delivering now.',
            'delivered' => 'Order delivered successfully!',
            default => 'Status updated.',
        };

        return redirect()->route('delivery.dashboard')->with('success', $message);
    }
    public function webhook(Request $request, Order $order)
    {
        // Simple authentication (e.g. check a secret token from headers)
        $secret = $request->header('X-Maxim-Signature');
        if ($secret !== config('services.maxim.webhook_secret', 'secret')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->all();
        $status = $payload['status'] ?? null;

        if (!$status) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Eager load relationships to avoid N+1 queries inside the switch
        $order->load('user', 'pharmacy');

        switch ($status) {
            case 'driver_assigned':
                $order->update([
                    'status' => Order::STATUS_ACCEPTED,
                    'driver_name' => $payload['driver']['name'] ?? 'Driver',
                    'driver_phone' => $payload['driver']['phone'] ?? '',
                    'driver_vehicle' => $payload['driver']['vehicle'] ?? '',
                    'estimated_delivery_at' => isset($payload['eta']) ? \Carbon\Carbon::parse($payload['eta']) : null,
                ]);
                // Notify customer that driver is assigned
                $order->user->notify(new \App\Notifications\DriverAssigned($order));
                // Notify pharmacy that delivery was accepted by a driver
                $pharmacyUser = \App\Models\User::where('pharmacy_id', $order->pharmacy_id)->first();
                if ($pharmacyUser) {
                    $pharmacyUser->notify(new \App\Notifications\DeliveryAcceptedByDriver($order));
                }
                break;

            case 'arrived_at_pickup':
                $order->update(['status' => Order::STATUS_AT_PHARMACY]);
                break;

            case 'picked_up':
                $order->update(['status' => Order::STATUS_PICKED_UP]);
                break;

            case 'arrived_at_dropoff':
                $order->user->notify(new \App\Notifications\DriverArrived($order));
                break;

            case 'completed':
                $order->update([
                    'status' => Order::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ]);
                $order->user->notify(new \App\Notifications\OrderDelivered($order));
                $order->user?->notify(new \App\Notifications\ReviewPrompt($order));
                break;

            case 'cancelled':
                $order->update(['status' => Order::STATUS_CANCELLED]);
                // Inventory should theoretically be restored here if cancelled by driver
                // But for now, just update status
                break;
        }

        return response()->json(['success' => true]);
    }
}
