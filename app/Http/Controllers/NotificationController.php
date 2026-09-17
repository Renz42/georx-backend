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
                        'created_at' => $n->created_at,
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

        if ($notification->notifiable_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.'
        ]);
    }

    // Mark all notifications as read
    public function markAllAsRead()
    {
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
    }

    // Delete all notifications
    public function destroyAll()
    {
        $user = Auth::user();

        $user->notifications()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All notifications deleted.'
        ]);
    }

    // Delete notification
    public function destroy(DatabaseNotification $notification)
    {
        $user = Auth::user();

        if ($notification->notifiable_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.'
        ]);
    }

    // Subscribe to stock alert
    public function subscribeStockAlert(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'medicine_id' => 'required|exists:medicines,id',
        ]);

        $user = Auth::user();

        // Check if alert already exists
        $existing = StockAlert::where('user_id', $user->id)
            ->where('pharmacy_id', $request->pharmacy_id)
            ->where('medicine_id', $request->medicine_id)
            ->first();

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
            'pharmacy_id' => $request->pharmacy_id,
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
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'medicine_id' => 'required|exists:medicines,id',
        ]);

        $user = Auth::user();

        $alert = StockAlert::where('user_id', $user->id)
            ->where('pharmacy_id', $request->pharmacy_id)
            ->where('medicine_id', $request->medicine_id)
            ->first();

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
                return [
                    'id' => $a->id,
                    'pharmacy_id' => $a->pharmacy_id,
                    'medicine_id' => $a->medicine_id,
                    'pharmacy_name' => $a->pharmacy->name,
                    'medicine_name' => $a->medicine->brand_name ?? $a->medicine->generic_name,
                    'generic_name' => $a->medicine->generic_name,
                    'created_at' => $a->created_at->diffForHumans(),
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
