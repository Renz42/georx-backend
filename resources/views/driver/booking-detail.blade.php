<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery #{{ $delivery->id }} — GEORX Driver</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit','sans-serif'] },
                    colors: { rider: { 950:'#0D0F1A', 800:'#1C2333', 600:'#3B5BDB', 500:'#4C6EF5', 400:'#748FFC' } }
                }
            }
        }
    </script>
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <nav class="bg-gradient-to-r from-rider-950 to-rider-800 text-white h-[72px] flex items-center px-6 shadow-xl sticky top-0 z-50 border-b border-rider-600/20">
        <div class="w-full flex justify-between items-center max-w-4xl mx-auto">
            <div class="flex items-center gap-3">
                <a href="{{ route('driver.bookings') }}" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div>
                    <h1 class="text-base font-black">DELIVERY #{{ $delivery->id }}</h1>
                    <p class="text-[10px] text-rider-300 font-bold uppercase tracking-widest">Order #{{ $delivery->order_id }}</p>
                </div>
            </div>
            <a href="{{ route('driver.dashboard') }}" class="text-xs font-bold text-rider-300 hover:text-white transition-colors bg-white/5 px-3 py-2 rounded-xl border border-white/10">
                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
            </a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto p-6 md:p-8 space-y-6">

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-2xl flex items-center gap-3 shadow-sm">
                <i class="fas fa-check-circle text-emerald-500 text-lg"></i>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-2xl flex items-center gap-3 shadow-sm">
                <i class="fas fa-exclamation-circle text-rose-500 text-lg"></i>
                <span class="font-bold">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Status Banner --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex justify-between items-center">
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Delivery Booking Status</p>
                <p class="text-2xl font-black text-slate-800 capitalize mt-1">{{ str_replace('_', ' ', $delivery->status) }}</p>
            </div>
            <div class="text-right">
                <span class="text-xs font-bold text-slate-500 block">
                    Created {{ $delivery->created_at->diffForHumans() }}
                </span>
                <span class="text-xl font-black text-emerald-600 mt-1 block">
                    Fee: ₱{{ number_format($delivery->order->delivery_fee ?? 50, 2) }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            {{-- Pickup Pharmacy --}}
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3">
                <div class="flex justify-between items-center">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                        <i class="fas fa-store-alt text-rider-500"></i> OFFICIAL PICKUP PHARMACY
                    </p>
                    @if($delivery->pickup_distance !== null)
                        <span class="text-xs font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                            {{ $delivery->pickup_distance }} km away
                        </span>
                    @endif
                </div>
                <p class="font-black text-slate-900 text-xl">{{ $delivery->pickupPharmacy->name }}</p>
                <p class="text-slate-600 text-xs leading-relaxed"><i class="fas fa-map-marker-alt text-rose-500 mr-1"></i>{{ $delivery->pickupPharmacy->address }}</p>
                <p class="text-slate-500 text-xs"><i class="fas fa-phone mr-1"></i>{{ $delivery->pickupPharmacy->phone ?? 'N/A' }}</p>

                @if($delivery->pickup_latitude && $delivery->pickup_longitude)
                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->pickup_latitude }},{{ $delivery->pickup_longitude }}"
                       target="_blank" rel="noopener"
                       class="mt-3 inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-colors">
                        <i class="fas fa-directions text-emerald-400"></i> Navigate to Pharmacy
                    </a>
                @endif
            </div>

            {{-- Customer Delivery Address --}}
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-3">
                <div class="flex justify-between items-center">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                        <i class="fas fa-user-circle text-blue-500"></i> DELIVERY LOCATION
                    </p>
                    @if($delivery->delivery_distance !== null)
                        <span class="text-xs font-black text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-full">
                            {{ $delivery->delivery_distance }} km from pharmacy
                        </span>
                    @endif
                </div>

                @if((int)$delivery->driver_id === (int)Auth::guard('driver')->id())
                    {{-- Full details visible only to assigned driver --}}
                    <p class="font-black text-slate-900 text-xl">{{ $delivery->customer->name ?? 'Customer' }}</p>
                    <p class="text-xs text-slate-700 font-bold"><i class="fas fa-phone text-blue-500 mr-1"></i>{{ $delivery->customer->phone ?? 'Phone on delivery tag' }}</p>
                    <p class="text-slate-700 text-xs font-semibold leading-relaxed"><i class="fas fa-map-pin text-rose-500 mr-1"></i>{{ $delivery->delivery_address }}</p>

                    @if($delivery->delivery_latitude && $delivery->delivery_longitude)
                        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->delivery_latitude }},{{ $delivery->delivery_longitude }}"
                           target="_blank" rel="noopener"
                           class="mt-3 inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition-colors shadow">
                            <i class="fas fa-location-arrow text-emerald-300"></i> Navigate to Customer
                        </a>
                    @endif
                @else
                    {{-- Available feed privacy protection --}}
                    <p class="font-bold text-slate-800 text-sm leading-snug">{{ $delivery->delivery_address }}</p>
                    <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-amber-800 text-xs">
                        <i class="fas fa-lock mr-1 text-amber-500"></i> Customer personal contact details are protected until you accept this booking.
                    </div>
                @endif
            </div>
        </div>

        {{-- Order Items Summary --}}
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                <p class="font-black text-slate-800 text-sm flex items-center gap-2">
                    <i class="fas fa-pills text-teal-600"></i> Medicine Items to Pickup & Deliver
                </p>
                <span class="text-xs font-bold text-slate-500">
                    Order Total: ₱{{ number_format($delivery->order->total_amount ?? 0, 2) }}
                </span>
            </div>
            <div class="divide-y divide-slate-100">
                @if($delivery->order && $delivery->order->items)
                    @foreach($delivery->order->items as $item)
                        <div class="px-6 py-4 flex justify-between items-center text-sm">
                            <div>
                                <p class="font-bold text-slate-800">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</p>
                                <p class="text-xs text-slate-400">{{ $item->medicine->generic_name }}</p>
                            </div>
                            <div class="text-right">
                                <span class="font-black text-slate-900 text-base">Qty: {{ $item->quantity }}</span>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Action Buttons --}}
        @if($delivery->status === 'waiting_for_driver' && is_null($delivery->driver_id))
            <form action="{{ route('driver.bookings.accept', $delivery->id) }}" method="POST">
                @csrf
                <button type="submit" id="accept-booking-btn"
                    class="w-full py-4 bg-gradient-to-r from-rider-600 to-violet-600 hover:from-rider-500 hover:to-violet-500 text-white font-black rounded-2xl shadow-xl shadow-rider-600/30 transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2 text-base active:scale-[0.98]">
                    <i class="fas fa-hand-pointer"></i> ACCEPT BOOKING NOW
                </button>
            </form>
        @elseif((int)$delivery->driver_id === (int)Auth::guard('driver')->id())
            @php
                $nextStep = match($delivery->status) {
                    'driver_assigned'   => ['driver_at_pharmacy', 'I Have Arrived at the Pharmacy', 'fas fa-store-alt', 'bg-rider-600 hover:bg-rider-500'],
                    'driver_at_pharmacy' => ['picked_up', 'Confirm Medicine Picked Up & Out for Delivery', 'fas fa-box-open', 'bg-teal-600 hover:bg-teal-500'],
                    'picked_up', 'out_for_delivery' => ['delivered', 'Mark Order as Delivered to Customer', 'fas fa-check-circle', 'bg-emerald-600 hover:bg-emerald-500'],
                    default => null,
                };
            @endphp

            @if($nextStep)
                <form action="{{ route('driver.bookings.status', $delivery->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="{{ $nextStep[0] }}">
                    <button type="submit"
                        class="w-full py-4 {{ $nextStep[3] }} text-white font-black rounded-2xl shadow-xl transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2 text-base active:scale-[0.98]">
                        <i class="{{ $nextStep[2] }}"></i> {{ $nextStep[1] }}
                    </button>
                </form>
            @else
                <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-6 text-center">
                    <i class="fas fa-check-circle text-4xl text-emerald-500 mb-2"></i>
                    <p class="font-black text-emerald-800 text-lg">Delivery Completed!</p>
                    <p class="text-emerald-600 text-xs mt-1">Delivered on {{ $delivery->delivered_at?->format('M d, Y h:i A') ?? 'N/A' }}</p>
                </div>
            @endif
        @endif

    </main>
</body>
</html>
