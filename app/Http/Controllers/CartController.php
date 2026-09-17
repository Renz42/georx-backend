<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = CartItem::where('user_id', Auth::id())->with('medicine', 'pharmacy')->get();
        
        $total = 0;
        foreach ($cartItems as $item) {
            // Need to get the price from the pharmacy_medicine pivot
            $pivot = $item->pharmacy->medicines()->where('medicine_id', $item->medicine_id)->first()->pivot;
            $total += ($pivot->selling_price * $item->quantity);
            $item->price = $pivot->selling_price;
        }

        return view('user.cart', compact('cartItems', 'total'));
    }

    public function add(Request $request)
    {
        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'medicine_id' => 'required|exists:medicines,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = Auth::id();

        // Enforce 1 pharmacy per checkout rule
        $existingCart = CartItem::where('user_id', $userId)->first();
        if ($existingCart && $existingCart->pharmacy_id != $request->pharmacy_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You can only order from one pharmacy at a time. Please clear your cart first.'
            ], 400);
        }

        $cartItem = CartItem::where('user_id', $userId)
            ->where('pharmacy_id', $request->pharmacy_id)
            ->where('medicine_id', $request->medicine_id)
            ->first();

        $requestedTotalQuantity = $request->quantity;
        if ($cartItem) {
            $requestedTotalQuantity += $cartItem->quantity;
        }

        // Check if pharmacy has enough stock
        $pharmacy = Pharmacy::find($request->pharmacy_id);
        if ($pharmacy) {
            $pivot = $pharmacy->medicines()->where('medicine_id', $request->medicine_id)->first();
            if ($pivot) {
                $availableStock = $pivot->pivot->aggregate_stock;
                if ($requestedTotalQuantity > $availableStock) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Cannot add to cart. Only ' . $availableStock . ' items are available in stock.'
                    ], 400);
                }
            } else {
                return response()->json(['status' => 'error', 'message' => 'Medicine not available in this pharmacy.'], 400);
            }
        }

        if ($cartItem) {
            $cartItem->quantity += $request->quantity;
            $cartItem->save();
        } else {
            CartItem::create([
                'user_id' => $userId,
                'pharmacy_id' => $request->pharmacy_id,
                'medicine_id' => $request->medicine_id,
                'quantity' => $request->quantity,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Added to cart successfully',
            'cart_count' => CartItem::where('user_id', $userId)->sum('quantity')
        ]);
    }

    public function remove($id)
    {
        CartItem::where('id', $id)->where('user_id', Auth::id())->delete();
        return redirect()->route('cart.index')->with('success', 'Item removed from cart.');
    }

    public function clear()
    {
        CartItem::where('user_id', Auth::id())->delete();
        return redirect()->route('cart.index')->with('success', 'Cart cleared.');
    }
}
