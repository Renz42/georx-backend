<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $pivot = DB::table('pharmacy_medicine')
                ->where('pharmacy_id', $item->pharmacy_id)
                ->where('medicine_id', $item->medicine_id)
                ->first();
            $price = $pivot ? (float)$pivot->selling_price : 0.00;
            $subtotal += ($price * $item->quantity);
        }

        $deliveryFee = \App\Services\DeliveryService::calculateEstimatedDeliveryFee(
            $pharmacy->latitude,
            $pharmacy->longitude
        );
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
            'items.*.medicine_id' => 'required_with:items|integer',
            'items.*.quantity' => 'required_with:items|integer|min:1|max:1000',
        ]);

        $user = $request->user() ?? Auth::user();
        $userId = $user ? $user->id : null;
        $isApi = $request->expectsJson() || $request->is('api/*');

        if (!$userId) {
            if ($isApi) {
                return response()->json(['status' => 'error', 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login')->with('error', 'Please log in to place an order.');
        }

        $itemsPayload = $request->input('items', []);

        // 1. Resolve selected pharmacy
        if (!empty($itemsPayload)) {
            $pharmacyId = $request->input('pharmacy_id') ?? ($itemsPayload[0]['pharmacy_id'] ?? null);
            $pharmacy = $pharmacyId ? Pharmacy::find($pharmacyId) : null;
        } else {
            $cartItems = CartItem::where('user_id', $userId)->get();
            if ($cartItems->isEmpty()) {
                if ($isApi) {
                    return response()->json(['status' => 'error', 'message' => 'Your cart is empty.'], 400);
                }
                return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
            }
            $pharmacyId = $cartItems->first()->pharmacy_id;
            $pharmacy = Pharmacy::find($pharmacyId);
        }

        if (!$pharmacy || $pharmacy->status !== 'approved') {
            if ($isApi) {
                return response()->json(['status' => 'error', 'message' => 'This pharmacy is currently unavailable.'], 400);
            }
            return redirect()->route('cart.index')->with('error', 'This pharmacy is currently unavailable.');
        }

        // 2. Geofence Validation for delivery address
        if ($request->filled('latitude') && $request->filled('longitude')) {
            if (!\App\Services\GeofenceService::isInsideAlijis((float)$request->latitude, (float)$request->longitude)) {
                if ($isApi) {
                    return response()->json(['status' => 'error', 'message' => 'Delivery destination address is outside the Barangay Alijis boundary radius.'], 400);
                }
                return redirect()->route('cart.index')->with('error', 'Your delivery destination address is outside the Barangay Alijis boundary radius.');
            }
        }

        // 3. Atomically validate stock, resolve authoritative price, and create order inside DB::transaction
        try {
            $order = DB::transaction(function () use ($request, $itemsPayload, $userId, $pharmacy) {
                $subtotal = 0;
                $orderItemsData = [];

                if (!empty($itemsPayload)) {
                    foreach ($itemsPayload as $item) {
                        $medId = (int)($item['medicine_id'] ?? $item['id'] ?? 0);
                        $qty = (int)($item['quantity'] ?? 1);

                        if ($qty < 1 || $qty > 1000) {
                            throw new \InvalidArgumentException("Invalid quantity ({$qty}) requested for medicine ID {$medId}.");
                        }

                        // Query pharmacy inventory with row-lock to avoid race conditions
                        $pivot = DB::table('pharmacy_medicine')
                            ->where('pharmacy_id', $pharmacy->id)
                            ->where('medicine_id', $medId)
                            ->lockForUpdate()
                            ->first();

                        if (!$pivot) {
                            $medicine = Medicine::find($medId);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$medId}";
                            throw new \InvalidArgumentException("Medicine '{$medName}' is not available at {$pharmacy->name}.");
                        }

                        if (!$pivot->is_available || (int)$pivot->quantity_on_hand <= 0) {
                            $medicine = Medicine::find($medId);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$medId}";
                            throw new \InvalidArgumentException("Medicine '{$medName}' is currently out of stock.");
                        }

                        if ($qty > (int)$pivot->quantity_on_hand) {
                            $medicine = Medicine::find($medId);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$medId}";
                            throw new \InvalidArgumentException("Insufficient stock for '{$medName}'. Only {$pivot->quantity_on_hand} available.");
                        }

                        // Server-side authoritative price: client price is completely ignored!
                        $serverPrice = (float)$pivot->selling_price;
                        if ($serverPrice <= 0) {
                            $medicine = Medicine::find($medId);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$medId}";
                            throw new \InvalidArgumentException("Unable to determine a valid selling price for '{$medName}'.");
                        }

                        $lineSubtotal = round($serverPrice * $qty, 2);
                        $subtotal += $lineSubtotal;

                        $orderItemsData[] = [
                            'medicine_id' => $medId,
                            'quantity' => $qty,
                            'price' => $serverPrice,
                        ];
                    }
                } else {
                    $cartItems = CartItem::where('user_id', $userId)->get();
                    if ($cartItems->isEmpty()) {
                        throw new \InvalidArgumentException("Your cart is empty.");
                    }

                    foreach ($cartItems as $item) {
                        $qty = (int)$item->quantity;
                        if ($qty < 1 || $qty > 1000) {
                            throw new \InvalidArgumentException("Invalid quantity ({$qty}) in cart.");
                        }

                        $pivot = DB::table('pharmacy_medicine')
                            ->where('pharmacy_id', $pharmacy->id)
                            ->where('medicine_id', $item->medicine_id)
                            ->lockForUpdate()
                            ->first();

                        if (!$pivot) {
                            $medicine = Medicine::find($item->medicine_id);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$item->medicine_id}";
                            throw new \InvalidArgumentException("Medicine '{$medName}' is not available at {$pharmacy->name}.");
                        }

                        if (!$pivot->is_available || (int)$pivot->quantity_on_hand <= 0) {
                            $medicine = Medicine::find($item->medicine_id);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$item->medicine_id}";
                            throw new \InvalidArgumentException("Medicine '{$medName}' is currently out of stock.");
                        }

                        if ($qty > (int)$pivot->quantity_on_hand) {
                            $medicine = Medicine::find($item->medicine_id);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$item->medicine_id}";
                            throw new \InvalidArgumentException("Insufficient stock for '{$medName}'. Only {$pivot->quantity_on_hand} available.");
                        }

                        // Server-side authoritative price: client price is completely ignored!
                        $serverPrice = (float)$pivot->selling_price;
                        if ($serverPrice <= 0) {
                            $medicine = Medicine::find($item->medicine_id);
                            $medName = $medicine ? ($medicine->brand_name ?? $medicine->generic_name) : "ID {$item->medicine_id}";
                            throw new \InvalidArgumentException("Unable to determine a valid selling price for '{$medName}'.");
                        }

                        $lineSubtotal = round($serverPrice * $qty, 2);
                        $subtotal += $lineSubtotal;

                        $orderItemsData[] = [
                            'medicine_id' => $item->medicine_id,
                            'quantity' => $qty,
                            'price' => $serverPrice,
                        ];
                    }
                }

                // Authoritative server-side delivery fee and total amount (client inputs are strictly ignored)
                $deliveryFee = \App\Services\DeliveryService::calculateEstimatedDeliveryFee(
                    isset($pharmacy->latitude) ? (float)$pharmacy->latitude : null,
                    isset($pharmacy->longitude) ? (float)$pharmacy->longitude : null,
                    $request->filled('latitude') ? (float)$request->latitude : null,
                    $request->filled('longitude') ? (float)$request->longitude : null
                );
                $totalAmount = round($subtotal + $deliveryFee, 2);

                $createdOrder = Order::create([
                    'user_id' => $userId,
                    'pharmacy_id' => $pharmacy->id,
                    'status' => Order::STATUS_PENDING_CONFIRMATION,
                    'total_amount' => $totalAmount,
                    'delivery_fee' => $deliveryFee,
                    'payment_method' => 'cod',
                    'delivery_address' => $request->delivery_address,
                    'latitude' => $request->latitude ?? 10.6402,
                    'longitude' => $request->longitude ?? 122.9460,
                ]);

                foreach ($orderItemsData as $itemData) {
                    $createdOrder->items()->create($itemData);
                }

                // Clear web cart if exists
                if (empty($itemsPayload) && $userId) {
                    CartItem::where('user_id', $userId)->delete();
                }

                return $createdOrder;
            });
        } catch (\InvalidArgumentException $e) {
            if ($isApi) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Order placement exception: ' . $e->getMessage(), [
                'user_id' => $userId,
                'pharmacy_id' => $pharmacy->id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            $safeMsg = 'Unable to complete the order. No changes were saved. Please try again.';
            if ($isApi) {
                return response()->json([
                    'status' => 'error',
                    'message' => $safeMsg,
                ], 500);
            }
            return redirect()->route('cart.index')->with('error', $safeMsg);
        }

        // Notify pharmacy
        $pharmacyUser = \App\Models\User::where('pharmacy_id', $pharmacy->id)->first();
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

        $user = $request->user() ?? Auth::user();
        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login')->with('error', 'Please log in to submit a review.');
        }

        $userId = $user->id;

        $order = Order::where('id', $id)
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('status', Order::STATUS_DELIVERED)
                      ->orWhere('customer_confirmed_delivery', true);
            })
            ->firstOrFail();

        if ($order->review) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => 'You have already reviewed this order.'], 422);
            }
            return back()->with('error', 'You have already reviewed this order.');
        }

        $review = \App\Models\Review::create([
            'order_id' => $order->id,
            'user_id' => $userId,
            'pharmacy_id' => $order->pharmacy_id,
            'driver_id' => $order->delivery?->driver_id ?? $order->delivery_partner_id,
            'delivery_provider' => $order->delivery_provider ?? ($order->delivery ? 'georx_legacy' : null),
            'delivery_booking_id' => $order->delivery_booking_id ?? $order->maxim_booking_id ?? null,
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

        if ($request->expectsJson() || $request->is('api/*')) {
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

        $formattedReviews = collect($reviews->items())->map(function ($rev) {
            $name = $rev->user?->name;
            if (!empty($name)) {
                $parts = explode(' ', trim($name));
                $displayName = count($parts) > 1 
                    ? $parts[0] . ' ' . strtoupper(substr(end($parts), 0, 1)) . '.' 
                    : $parts[0];
            } else {
                $displayName = 'Verified Patient';
            }

            return [
                'id' => $rev->id,
                'user_name' => $displayName,
                'rating' => (int) ($rev->pharmacy_overall ?? $rev->rating ?? 5),
                'comment' => $rev->comment,
                'date' => $rev->created_at ? $rev->created_at->format('M d, Y') : null,
                'created_at' => $rev->created_at?->toISOString(),
                'medicine_availability' => $rev->medicine_availability,
                'price_rating' => $rev->price_rating,
                'customer_service' => $rev->customer_service,
                'accuracy' => $rev->accuracy,
                'pharmacy_overall' => $rev->pharmacy_overall,
                'is_verified_purchase' => !empty($rev->order_id),
            ];
        })->values();

        $avgScore = round($avgPharmacy ?: ($pharmacy->average_rating ?: 0), 1);

        return response()->json([
            'status' => 'success',
            'success' => true,
            'pharmacy_id' => $pharmacy->id,
            // Top-level fields for direct mobile consumption:
            'rating' => $avgScore,
            'count' => $totalReviews,
            'reviews' => $formattedReviews,
            // Backward-compatible summary:
            'summary' => [
                'average_delivery_rating' => round($avgDelivery, 1),
                'average_pharmacy_rating' => $avgScore,
                'total_reviews' => $totalReviews,
            ],
            // Backward-compatible paginator info:
            'paginated' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Submit a direct patient feedback/review for a pharmacy.
     * Route: POST /api/pharmacies/{pharmacy}/reviews
     */
    public function submitPharmacyReview(Request $request, \App\Models\Pharmacy $pharmacy)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'medicine_availability' => 'nullable|integer|min:1|max:5',
            'price_rating' => 'nullable|integer|min:1|max:5',
            'customer_service' => 'nullable|integer|min:1|max:5',
            'accuracy' => 'nullable|integer|min:1|max:5',
            'pharmacy_overall' => 'nullable|integer|min:1|max:5',
        ]);

        // Duplicate prevention: 1 direct storefront feedback per user per pharmacy
        $existing = \App\Models\Review::where('pharmacy_id', $pharmacy->id)
            ->where('user_id', $user->id)
            ->whereNull('order_id')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'You have already submitted feedback for this pharmacy.'
            ], 422);
        }

        $rating = (int) $validated['rating'];

        $review = \App\Models\Review::create([
            'order_id' => null,
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacy->id,
            'driver_id' => null,
            'rating' => $rating,
            'comment' => $validated['comment'] ?? null,
            'pharmacy_overall' => $validated['pharmacy_overall'] ?? $rating,
            'medicine_availability' => $validated['medicine_availability'] ?? $rating,
            'price_rating' => $validated['price_rating'] ?? $rating,
            'customer_service' => $validated['customer_service'] ?? $rating,
            'accuracy' => $validated['accuracy'] ?? $rating,
            'delivery_speed' => null,
            'driver_professionalism' => null,
            'medicine_condition' => null,
            'delivery_overall' => null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thank you for your feedback about ' . $pharmacy->name . '!',
            'review' => $review,
        ], 201);
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

        // Check for External Provider (Grab Express / Maxim)
        if ($order->delivery_provider) {
            $isGrab = $order->delivery_provider === 'grab';
            $providerLabel = $isGrab ? 'Grab Express' : 'Maxim';
            $pickupLat = (float)($order->pharmacy->latitude ?? 10.644508);
            $pickupLng = (float)($order->pharmacy->longitude ?? 122.940537);
            $deliveryLat = (float)($order->latitude ?? 10.6385);
            $deliveryLng = (float)($order->longitude ?? 122.9520);

            return response()->json([
                'success'              => true,
                'is_external_provider' => true,
                'has_active_delivery'  => in_array($order->status, [
                    \App\Models\Order::STATUS_ACCEPTED,
                    \App\Models\Order::STATUS_AT_PHARMACY,
                    \App\Models\Order::STATUS_PICKED_UP,
                    'out_for_delivery',
                ]),
                'delivery_id'          => null,
                'delivery_provider'    => $order->delivery_provider,
                'delivery_provider_label' => $providerLabel,
                'delivery_booking_id'  => $order->delivery_booking_id,
                'delivery_tracking_url'=> $order->delivery_tracking_url,
                'status'               => $order->delivery_status ?? $order->status,
                'status_label'         => ucwords(str_replace('_', ' ', $order->delivery_status ?? $order->status)),
                'driver'               => [
                    'name'         => $order->driver_name ?? "{$providerLabel} Courier Rider",
                    'phone'        => $order->driver_phone ?? 'Available in Provider App',
                    'vehicle'      => $order->driver_vehicle ?? 'Motorcycle',
                    'plate_number' => 'External Courier',
                    'rating'       => '5.0',
                    'avatar_url'   => 'https://ui-avatars.com/api/?name=' . urlencode($providerLabel) . '&background=0D9488&color=FFF&size=200',
                    'is_external'  => true,
                ],
                'locations'            => [
                    'driver'   => null,
                    'pickup'   => [
                        'pharmacy_name' => $order->pharmacy->name ?? 'Pharmacy',
                        'latitude'      => $pickupLat,
                        'longitude'     => $pickupLng,
                    ],
                    'delivery' => [
                        'address'   => $order->delivery_address,
                        'latitude'  => $deliveryLat,
                        'longitude' => $deliveryLng,
                    ],
                ],
                'last_location_at'     => null,
                'timestamps'           => [
                    'accepted_at' => $order->accepted_at ? $order->accepted_at->format('M d, Y g:i A') : null,
                    'picked_up_at' => $order->picked_up_at ? $order->picked_up_at->format('M d, Y g:i A') : null,
                    'delivered_at' => $order->delivered_at ? $order->delivered_at->format('M d, Y g:i A') : null,
                ],
                'is_stale'             => false,
            ]);
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
