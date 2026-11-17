<?php

namespace App\Http\Controllers;

use Illuminate\Notifications\DatabaseNotification;
use App\Models\StockAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // Get user notifications
    public function index(Request $request)
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->limit(50)
            ->get();

        $unreadCount = $user->unreadNotifications()->count();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'notifications' => $notifications->map(function ($n) {
                    $nData = is_array($n->data) ? $n->data : (json_decode(is_string($n->data) ? $n->data : '{}', true) ?: []);
                    return [
                        'id' => $n->id,
                        'type' => $n->type,
                        'title' => $nData['title'] ?? null,
                        'message' => $nData['message'] ?? 'You have a new notification.',
                        'is_read' => !is_null($n->read_at),
                        'read_at' => $n->read_at ? $n->read_at->toIso8601String() : null,
                        'created_at' => $n->created_at ? $n->created_at->toIso8601String() : null,
                        'created_at_human' => $n->created_at ? $n->created_at->diffForHumans() : 'Recently',
                        'data' => $nData,
                    ];
                }),
                'unread_count' => $unreadCount,
            ]);
        }

        return view('user.notifications', compact('notifications', 'unreadCount'));
    }

    // Get notifications for header dropdown (AJAX)
    public function dropdown()
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->limit(10)
            ->get();

        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'notifications' => $notifications->map(function ($n) {
                $nData = is_array($n->data) ? $n->data : (json_decode(is_string($n->data) ? $n->data : '{}', true) ?: []);
                return [
                    'id' => $n->id,
                    'type' => $n->type,
                    'title' => $nData['title'] ?? 'Notification',
                    'message' => $nData['message'] ?? 'You have a new notification.',
                    'is_read' => !is_null($n->read_at),
                    'created_at' => $n->created_at->diffForHumans(),
                ];
            }),
            'unread_count' => $unreadCount,
        ]);
    }

    // Mark notification as read
    public function markAsRead(DatabaseNotification $notification)
    {
        $user = Auth::user();

        if ((int) $notification->notifiable_id !== (int) $user->id || $notification->notifiable_type !== $user->getMorphClass()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    // Mark all notifications as read
    public function markAllAsRead()
    {
        $user = Auth::user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'unread_count' => 0,
        ]);
    }

    // Delete all notifications
    public function destroyAll()
    {
        $user = Auth::user();

        $user->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted.',
            'unread_count' => 0,
        ]);
    }

    // Delete notification
    public function destroy(DatabaseNotification $notification)
    {
        $user = Auth::user();

        if ((int) $notification->notifiable_id !== (int) $user->id || $notification->notifiable_type !== $user->getMorphClass()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    // Subscribe to stock alert
    public function subscribeStockAlert(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'nullable|exists:pharmacies,id',
            'medicine_id' => 'required|exists:medicines,id',
        ]);

        $user = Auth::user();
        $pharmacyId = $request->filled('pharmacy_id') ? (int) $request->pharmacy_id : null;

        // Check if alert already exists (handle PostgreSQL NULL semantics explicitly)
        $query = StockAlert::where('user_id', $user->id)
            ->where('medicine_id', $request->medicine_id);

        if ($pharmacyId === null) {
            $query->whereNull('pharmacy_id');
        } else {
            $query->where('pharmacy_id', $pharmacyId);
        }

        $existing = $query->first();

        if ($existing) {
            // Reactivate if inactive
            if (!$existing->is_active) {
                $existing->update(['is_active' => true, 'notified_at' => null]);
                return response()->json([
                    'success' => true,
                    'message' => 'Stock alert reactivated!'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'You already have an alert for this medicine.'
            ], 400);
        }

        StockAlert::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacyId,
            'medicine_id' => $request->medicine_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'You will be notified when this medicine is back in stock!'
        ]);
    }

    // Unsubscribe from stock alert
    public function unsubscribeStockAlert(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'nullable|exists:pharmacies,id',
            'medicine_id' => 'required|exists:medicines,id',
        ]);

        $user = Auth::user();
        $pharmacyId = $request->filled('pharmacy_id') ? (int) $request->pharmacy_id : null;

        $query = StockAlert::where('user_id', $user->id)
            ->where('medicine_id', $request->medicine_id);

        if ($pharmacyId === null) {
            $query->whereNull('pharmacy_id');
        } else {
            $query->where('pharmacy_id', $pharmacyId);
        }

        $alert = $query->first();

        if ($alert) {
            $alert->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock alert removed.'
        ]);
    }

    // Get user's active stock alerts (JSON)
    public function stockAlerts()
    {
        $user = Auth::user();

        $alerts = $user->stockAlerts()
            ->with(['pharmacy', 'medicine'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'alerts' => $alerts->map(function ($a) {
                // Real-time pharmacy-specific or global inventory lookup
                if ($a->pharmacy_id !== null) {
                    $pivot = \Illuminate\Support\Facades\DB::table('pharmacy_medicine')
                        ->where('pharmacy_id', $a->pharmacy_id)
                        ->where('medicine_id', $a->medicine_id)
                        ->first();
                    $qty = $pivot ? (int) $pivot->quantity_on_hand : null;
                    $isAvailable = $pivot ? ((bool) ($pivot->is_available ?? true) && ($qty !== null && $qty > 0)) : false;
                    $price = $pivot && isset($pivot->selling_price) ? (float) $pivot->selling_price : null;
                    $pharmacyName = $a->pharmacy->name ?? 'Partner Pharmacy';
                } else {
                    $pivots = \Illuminate\Support\Facades\DB::table('pharmacy_medicine')
                        ->where('medicine_id', $a->medicine_id)
                        ->where('quantity_on_hand', '>', 0)
                        ->get();
                    $qty = $pivots->isNotEmpty() ? (int) $pivots->sum('quantity_on_hand') : 0;
                    $isAvailable = $qty > 0;
                    $price = $pivots->isNotEmpty() ? (float) $pivots->min('selling_price') : null;
                    $pharmacyName = 'Any Partner Pharmacy';
                }

                return [
                    'id' => $a->id,
                    'pharmacy_id' => $a->pharmacy_id,
                    'medicine_id' => $a->medicine_id,
                    'pharmacy_name' => $pharmacyName,
                    'medicine_name' => $a->medicine->brand_name ?? $a->medicine->generic_name ?? 'Medicine',
                    'generic_name' => $a->medicine->generic_name ?? null,
                    'created_at' => $a->created_at ? $a->created_at->diffForHumans() : 'Recently',
                    'created_at_iso' => $a->created_at ? $a->created_at->toIso8601String() : null,
                    'quantity_on_hand' => $qty,
                    'is_in_stock' => $isAvailable,
                    'selling_price' => $price,
                ];
            }),
        ]);
    }

    // Render "My Restock Alerts" Blade view
    public function indexRestockAlerts()
    {
        $user = Auth::user();

        $alerts = StockAlert::where('user_id', $user->id)
            ->with(['pharmacy', 'medicine'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($alert) {
                // Get current live stock from pharmacy_medicine pivot
                $pivot = \Illuminate\Support\Facades\DB::table('pharmacy_medicine')
                    ->where('pharmacy_id', $alert->pharmacy_id)
                    ->where('medicine_id', $alert->medicine_id)
                    ->first();

                $alert->live_stock = $pivot?->quantity_on_hand ?? 0;
                $alert->live_price = $pivot?->selling_price ?? 0;
                $alert->is_in_stock = ($alert->live_stock > 0);
                return $alert;
            });

        return view('user.restock_alerts', compact('alerts'));
    }
}
