@extends('layouts.app') <!-- Assuming there's a main layout, wait, let's just make it a full HTML for now if we don't know the layout -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <x-navbar />

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-2xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle mr-3 text-emerald-500 text-lg"></i>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-2xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-exclamation-circle mr-3 text-rose-500 text-lg"></i>
                <span class="font-bold">{{ session('error') }}</span>
            </div>
        @endif

        @if($cartItems->isEmpty())
            <div class="py-20 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-shopping-basket text-4xl text-slate-300"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-700 mb-2">Your cart is empty</h3>
                <p class="text-slate-500 mb-6">Looks like you haven't added any medicines yet.</p>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-bold rounded-xl text-white bg-blue-600 hover:bg-blue-700 transition shadow-lg shadow-blue-200 hover:-translate-y-0.5">
                    Start Shopping
                </a>
            </div>
        @else
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-8">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="font-black text-slate-800 text-lg">Items from {{ $cartItems->first()->pharmacy->name }}</h2>
                    <form action="{{ route('cart.clear') }}" method="POST" onsubmit="return confirm('Clear your entire cart?');">
                        @csrf
                        <button type="submit" class="text-xs font-bold text-rose-500 hover:text-rose-700 transition"><i class="fas fa-trash mr-1"></i> Clear All</button>
                    </form>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach($cartItems as $item)
                        <div class="p-6 flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 bg-slate-50 rounded-xl flex items-center justify-center shrink-0 border border-slate-100 p-2">
                                    @if($item->medicine->image)
                                        <img src="/storage/{{ $item->medicine->image }}" class="w-full h-full object-contain mix-blend-multiply">
                                    @else
                                        <i class="fas fa-pills text-2xl text-slate-300"></i>
                                    @endif
                                </div>
                                <div>
                                    <h3 class="font-black text-slate-800">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</h3>
                                    <p class="text-sm text-slate-500 font-medium">{{ $item->medicine->strength }} {{ $item->medicine->dosage_form }}</p>
                                    <p class="text-sm font-bold text-blue-600 mt-1">₱{{ number_format($item->price, 2) }} <span class="text-xs text-slate-400 font-medium">each</span></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-6">
                                <div class="text-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Quantity</p>
                                    <span class="font-black text-lg">{{ $item->quantity }}</span>
                                </div>
                                <div class="text-center w-24">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Subtotal</p>
                                    <span class="font-black text-lg text-slate-800">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                                </div>
                                <form action="{{ route('cart.remove', $item->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-8 h-8 rounded-full bg-rose-50 text-rose-500 hover:bg-rose-100 transition flex items-center justify-center">
                                        <i class="fas fa-times text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-6 py-6 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div>
                        <p class="text-slate-500 font-medium text-sm">Total items: <span class="font-bold text-slate-800">{{ $cartItems->sum('quantity') }}</span></p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="text-right">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cart Total</p>
                            <p class="text-3xl font-black text-slate-900">₱{{ number_format($total, 2) }}</p>
                        </div>
                        <a href="{{ route('orders.checkout') }}" class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-xl shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 text-lg">
                            Checkout <i class="fas fa-arrow-right ml-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        @endif

    </main>
</body>
</html>
