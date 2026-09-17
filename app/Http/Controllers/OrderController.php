<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderController extends Controller
{
    public function checkout()
    {
        $cartItems = CartItem::where('user_id', Auth::id())->with('medicine', 'pharmacy')->get();
        if ($cartItems->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $pharmacy = $cartItems->first()->pharmacy;
        
        $subtotal = 0;
        foreach ($cartItems as $item) {
            $pivot = $item->pharmacy->medicines()->where('medicine_id', $item->medicine_id)->first()->pivot;
            $subtotal += ($pivot->selling_price * $item->quantity);
        }

        $deliveryFee = 50.00; // Flat fee for now
        $total = $subtotal + $deliveryFee;

        return view('user.checkout', compact('cartItems', 'pharmacy', 'subtotal', 'deliveryFee', 'total'));
    }

    public function placeOrder(Request $request)
    {
        $request->validate([
            'delivery_address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'pharmacy_id' => 'nullable|integer',
            'items' => 'nullable|array',
        ]);

        $userId = Auth::id();
        $isApi = $request->expectsJson() || $request->is('api/*');
        $itemsPayload = $request->input('items', []);

        if (!empty($itemsPayload)) {
            // API / Mobile direct JSON payload
            $pharmacyId = $request->input('pharmacy_id') ?? $itemsPayload[0]['pharmacy_id'] ?? 1;
            $pharmacy = \App\Models\Pharmacy::find($pharmacyId) ?? \App\Models\Pharmacy::first();
            $pharmacyId = $pharmacy ? $pharmacy->id : 1;
            
            $subtotal = 0;
            $orderItemsData = [];
            foreach ($itemsPayload as $item) {
                $medId = $item['medicine_id'] ?? $item['id'] ?? 1;
                $price = (float)($item['price'] ?? 5.00);
                $qty = (int)($item['quantity'] ?? 1);
                $subtotal += ($price * $qty);
                $orderItemsData[] = [
                    'medicine_id' => $medId,
                    'quantity' => $qty,
                    'price' => $price,
                ];
            }
        } else {
            // Web view session cart items
            $cartItems = CartItem::where('user_id', $userId)->get();

            if ($cartItems->isEmpty()) {
                if ($isApi) {
                    return response()->json(['status' => 'error', 'message' => 'Your cart is empty.'], 400);
                }
                return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
            }

            $pharmacyId = $cartItems->first()->pharmacy_id;
            $pharmacy = \App\Models\Pharmacy::findOrFail($pharmacyId);

            $subtotal = 0;
            $orderItemsData = [];
            
            foreach ($cartItems as $item) {
                $pivot = $item->pharmacy->medicines()->where('medicine_id', $item->medicine_id)->first()?->pivot;
                $price = $pivot ? $pivot->selling_price : 5.00;
                $subtotal += ($price * $item->quantity);
                
                $orderItemsData[] = [
                    'medicine_id' => $item->medicine_id,
                    'quantity' => $item->quantity,
                    'price' => $price,
                ];
            }
        }

        // Block checkout if pharmacy is suspended
        if ($pharmacy && $pharmacy->status !== 'approved') {
            if ($isApi) {
                return response()->json(['status' => 'error', 'message' => 'This pharmacy is currently unavailable.'], 400);
            }
            return redirect()->route('cart.index')->with('error', 'This pharmacy is currently unavailable.');
        }

        // Geofence Validation for delivery address
        if ($request->filled('latitude') && $request->filled('longitude')) {
            if (!\App\Services\GeofenceService::isInsideAlijis((float)$request->latitude, (float)$request->longitude)) {
                if ($isApi) {
                    return response()->json(['status' => 'error', 'message' => 'Delivery destination address is outside the Barangay Alijis boundary radius.'], 400);
                }
                return redirect()->route('cart.index')->with('error', 'Your delivery destination address is outside the Barangay Alijis boundary radius.');
            }
        }

        $deliveryFee = 50.00;
        $totalAmount = $subtotal + $deliveryFee;

        $order = Order::create([
            'user_id' => $userId ?? 1,
            'pharmacy_id' => $pharmacyId,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => $totalAmount,
            'delivery_fee' => $deliveryFee,
            'payment_method' => 'cod',
            'delivery_address' => $request->delivery_address,
            'latitude' => $request->latitude ?? 10.6402,
            'longitude' => $request->longitude ?? 122.9460,
        ]);

        foreach ($orderItemsData as $itemData) {
            $order->items()->create($itemData);
        }

        // Clear web cart if exists
        if ($userId) {
            CartItem::where('user_id', $userId)->delete();
        }

        // Notify pharmacy
        $pharmacyUser = \App\Models\User::where('pharmacy_id', $pharmacyId)->first();
        if ($pharmacyUser) {
            try {
                $pharmacyUser->notify(new \App\Notifications\NewOrderReceived($order));
            } catch (\Throwable $e) {}
        }

        if ($isApi) {
            return response()->json([
                'status' => 'success',
                'message' => 'Order placed successfully! Waiting for pharmacy confirmation.',
                'order' => $order->load('items', 'pharmacy'),
            ], 201);
        }

        return redirect()->route('orders.show', $order->id)->with('success', 'Order placed successfully! Waiting for pharmacy confirmation.');
    }

    public function cancelOrder($id)
    {
        $order = Order::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        if ($order->status !== Order::STATUS_PENDING_CONFIRMATION) {
            return back()->with('error', 'Order cannot be cancelled at this stage.');
        }

        $order->update([
            'status' => Order::STATUS_CANCELLED
        ]);

        return back()->with('success', 'Order cancelled successfully.');
    }

    public function myOrders()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with('items.medicine', 'pharmacy')
            ->orderByDesc('created_at')
            ->get();

        return view('user.orders', compact('orders'));
    }

    public function show($id)
    {
        $order = Order::where('id', $id)->where('user_id', Auth::id())->with('items.medicine', 'pharmacy', 'deliveryPartner', 'review')->firstOrFail();
        return view('user.order-tracking', compact('order'));
    }

    public function submitReview(Request $request, $id)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            // Delivery ratings
            'delivery_speed' => 'nullable|integer|min:1|max:5',
            'driver_professionalism' => 'nullable|integer|min:1|max:5',
            'medicine_condition' => 'nullable|integer|min:1|max:5',
            'delivery_overall' => 'nullable|integer|min:1|max:5',
            // Pharmacy ratings
            'medicine_availability' => 'nullable|integer|min:1|max:5',
            'price_rating' => 'nullable|integer|min:1|max:5',
            'customer_service' => 'nullable|integer|min:1|max:5',
            'accuracy' => 'nullable|integer|min:1|max:5',
            'pharmacy_overall' => 'nullable|integer|min:1|max:5',
        ]);

        $order = Order::where('id', $id)->where('user_id', Auth::id())->where('status', 'delivered')->firstOrFail();

        if ($order->review) {
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => 'You have already reviewed this order.'], 422);
            }
            return back()->with('error', 'You have already reviewed this order.');
        }

        $review = \App\Models\Review::create([
            'order_id' => $order->id,
            'user_id' => Auth::id(),
            'pharmacy_id' => $order->pharmacy_id,
            'driver_id' => $order->delivery?->driver_id ?? $order->delivery_partner_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            // Delivery
            'delivery_speed' => $request->delivery_speed ?? $request->rating,
            'driver_professionalism' => $request->driver_professionalism ?? $request->rating,
            'medicine_condition' => $request->medicine_condition ?? $request->rating,
            'delivery_overall' => $request->delivery_overall ?? $request->rating,
            // Pharmacy
            'medicine_availability' => $request->medicine_availability ?? $request->rating,
            'price_rating' => $request->price_rating ?? $request->rating,
            'customer_service' => $request->customer_service ?? $request->rating,
            'accuracy' => $request->accuracy ?? $request->rating,
            'pharmacy_overall' => $request->pharmacy_overall ?? $request->rating,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Thank you for your detailed feedback!',
                'review' => $review,
            ]);
        }

        return back()->with('success', 'Thank you for your detailed feedback!');
    }

    public function downloadReceipt($id)
    {
        $order = Order::where('id', $id)->where('user_id', Auth::id())->with('items.medicine', 'pharmacy')->firstOrFail();

        $data = [
            'order' => $order,
            'date' => now()->format('F d, Y h:i A')
        ];

        $pdf = Pdf::loadView('user.reports.receipt-pdf', $data);
        return $pdf->download('Receipt_Order_' . $order->id . '.pdf');
    }

    public function getPharmacyReviews(\App\Models\Pharmacy $pharmacy)
    {
        $reviews = \App\Models\Review::where('pharmacy_id', $pharmacy->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(10);

        $avgDelivery = \App\Models\Review::where('pharmacy_id', $pharmacy->id)->avg('delivery_overall') ?? 0;
        $avgPharmacy = \App\Models\Review::where('pharmacy_id', $pharmacy->id)->avg('pharmacy_overall') ?? 0;
        $totalReviews = \App\Models\Review::where('pharmacy_id', $pharmacy->id)->count();

        return response()->json([
            'success' => true,
            'pharmacy_id' => $pharmacy->id,
            'summary' => [
                'average_delivery_rating' => round($avgDelivery, 1),
                'average_pharmacy_rating' => round($avgPharmacy, 1),
                'total_reviews' => $totalReviews,
            ],
            'reviews' => $reviews,
        ]);
    }

    // =============================================
    // LIVE DELIVERY LOCATION TRACKING (PHASE 9)
    // =============================================

    /**
     * Get Live Delivery & Driver Location for Customer Order.
     *
     * SECURITY & PRIVACY RULES:
     * - Customer A CANNOT see Customer B's delivery or Driver B's location (`403 Forbidden`).
     * - Only the customer who placed the order or an authorized admin/driver can view.
     * - Flags `is_stale => true` if `last_location_at` is older than 5 minutes.
     */
    public function liveLocation($id)
    {
        $order = Order::with(['delivery.driver.driverProfile', 'pharmacy'])->findOrFail($id);

        $currentUser = Auth::user() ?? Auth::guard('driver')->user() ?? Auth::guard('admin')->user();

        if (!$currentUser) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // PRIVACY ENFORCEMENT
        $isOrderCustomer = (int) $order->user_id === (int) $currentUser->id;
        $isAssignedDriver = $order->delivery && (int) $order->delivery->driver_id === (int) $currentUser->id;
        $isAdmin = (bool) Auth::guard('admin')->check();

        if (!$isOrderCustomer && !$isAssignedDriver && !$isAdmin) {
            return response()->json(['error' => 'Unauthorized access to live delivery tracking.'], 403);
        }

        $delivery = $order->delivery;
        if (!$delivery) {
            return response()->json([
                'success'             => false,
                'has_active_delivery' => false,
                'message'             => 'No delivery record found for this order.',
            ]);
        }

        $driverUser    = $delivery->driver;
        $driverProfile = $driverUser?->driverProfile;

        // Recency calculation (5 minute stale threshold)
        $lastLocationAt = $driverProfile?->last_location_at;
        $isStale        = is_null($lastLocationAt) || now()->diffInMinutes($lastLocationAt) > 5;

        return response()->json([
            'success'              => true,
            'has_active_delivery'  => in_array($delivery->status, [
                \App\Models\Delivery::STATUS_DRIVER_ASSIGNED,
                \App\Models\Delivery::STATUS_DRIVER_AT_PHARMACY,
                \App\Models\Delivery::STATUS_PICKED_UP,
                \App\Models\Delivery::STATUS_OUT_FOR_DELIVERY,
            ]),
            'delivery_id'          => $delivery->id,
            'status'               => $delivery->status,
            'status_label'         => ucwords(str_replace('_', ' ', $delivery->status)),
            'driver'               => [
                'name'         => $driverUser?->name ?? $order->driver_name ?? 'William John',
                'phone'        => $driverUser?->phone ?? $order->driver_phone ?? '09171234567',
                'vehicle'      => $driverProfile?->vehicle_description ?? $order->driver_vehicle ?? 'Yamaha NMAX (Motorcycle)',
                'plate_number' => $driverProfile?->license_plate ?? 'ABC-1234',
                'rating'       => '4.8',
                'avatar_url'    => 'https://ui-avatars.com/api/?name=William+John&background=1E3A5F&color=FFF&size=200',
            ],
            'locations'            => [
                'driver'   => [
                    'latitude'  => (float) $driverProfile?->current_latitude,
                    'longitude' => (float) $driverProfile?->current_longitude,
                ],
                'pickup'   => [
                    'pharmacy_name' => $order->pharmacy->name ?? 'Pharmacy',
                    'latitude'      => (float) $delivery->pickup_latitude,
                    'longitude'     => (float) $delivery->pickup_longitude,
                ],
                'delivery' => [
                    'address'   => $delivery->delivery_address,
                    'latitude'  => (float) $delivery->delivery_latitude,
                    'longitude' => (float) $delivery->delivery_longitude,
                ],
            ],
            'last_location_at'     => $lastLocationAt?->toDateTimeString(),
            'timestamps'           => [
                'accepted_at' => $delivery->driver_accepted_at ? $delivery->driver_accepted_at->format('M d, Y g:i A') : null,
                'picked_up_at' => $delivery->picked_up_at ? $delivery->picked_up_at->format('M d, Y g:i A') : null,
                'delivered_at' => $delivery->delivered_at ? $delivery->delivered_at->format('M d, Y g:i A') : null,
            ],
            'is_stale'             => $isStale,
        ]);
    }
}
