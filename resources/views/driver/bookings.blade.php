<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Delivery Bookings — GEORX Driver</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit','sans-serif'] },
                    colors: { rider: { 950:'#0D0F1A', 900:'#111827', 800:'#1C2333', 600:'#3B5BDB', 500:'#4C6EF5', 400:'#748FFC' } }
                }
            }
        }
    </script>
    <style>body { font-family: 'Outfit', sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

    {{-- Navbar --}}
    <nav class="bg-gradient-to-r from-rider-950 to-rider-800 text-white h-[72px] flex items-center px-6 shadow-xl sticky top-0 z-50 border-b border-rider-600/20">
        <div class="w-full flex justify-between items-center max-w-[1400px] mx-auto">
            <div class="flex items-center gap-3">
                <a href="{{ route('driver.dashboard') }}" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div class="w-10 h-10 bg-gradient-to-br from-rider-600 to-violet-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-inbox text-lg text-white"></i>
                </div>
                <div>
                    <h1 class="text-base font-black leading-tight">Available Delivery Bookings</h1>
                    <p class="text-[10px] text-rider-300 font-bold uppercase tracking-widest">{{ $availableDeliveries->count() }} delivery(ies) waiting</p>
                </div>
            </div>
            <a href="{{ route('driver.dashboard') }}" class="text-xs font-bold text-rider-300 hover:text-white transition-colors flex items-center gap-1.5 bg-white/5 px-3 py-2 rounded-xl border border-white/10">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto p-6 md:p-8 space-y-6">

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

        @if($hasActiveDelivery)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-5 py-4 rounded-2xl flex items-start gap-3 shadow-sm">
                <i class="fas fa-exclamation-triangle text-amber-500 text-lg mt-0.5 shrink-0"></i>
                <div>
                    <p class="font-black text-sm">Active Delivery in Progress</p>
                    <p class="text-xs text-amber-700 mt-0.5">You currently have an active delivery in progress. Complete your active delivery before accepting new bookings.</p>
                </div>
            </div>
        @endif

        @if($availableDeliveries->isEmpty())
            <div class="py-24 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                <div class="w-20 h-20 bg-amber-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-amber-100">
                    <i class="fas fa-inbox text-3xl text-amber-400"></i>
                </div>
                <h3 class="text-xl font-black text-slate-800 mb-1">No Bookings Available Right Now</h3>
                <p class="text-slate-500 text-sm max-w-md mx-auto">New delivery requests from pharmacy-confirmed orders in Barangay Alijis will appear here automatically.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($availableDeliveries as $delivery)
                    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm hover:shadow-lg transition-all duration-200 overflow-hidden flex flex-col justify-between border-t-4 border-t-rider-500">
                        <div>
                            {{-- Header --}}
                            <div class="bg-slate-50 px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                                <div>
                                    <span class="font-black text-slate-800 text-base">DELIVERY #{{ $delivery->id }}</span>
                                    <p class="text-[11px] font-bold text-slate-400">Order #{{ $delivery->order_id }}</p>
                                </div>
                                <span class="text-xs font-bold text-slate-500 bg-white px-3 py-1 rounded-full border border-slate-200 shadow-xs">
                                    <i class="far fa-clock mr-1 text-slate-400"></i>{{ $delivery->created_at->diffForHumans() }}
                                </span>
                            </div>

                            <div class="p-6 space-y-5">
                                {{-- PICKUP SECTION --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <i class="fas fa-store-alt text-rider-500"></i> PICKUP PHARMACY
                                        </p>
                                        @if($delivery->pickup_distance !== null)
                                            <span class="text-xs font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                                                <i class="fas fa-location-arrow text-[10px] mr-1"></i>{{ $delivery->pickup_distance }} km away
                                            </span>
                                        @else
                                            <span class="text-xs font-semibold text-slate-400">Barangay Alijis</span>
                                        @endif
                                    </div>
                                    <p class="font-black text-slate-900 text-base">{{ $delivery->pickupPharmacy->name }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5"><i class="fas fa-map-marker-alt text-rose-500 mr-1"></i>{{ $delivery->pickupPharmacy->address }}</p>
                                </div>

                                {{-- ORDER ITEMS SUMMARY SECTION --}}
                                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 space-y-2">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                        <i class="fas fa-pills text-teal-600"></i> ORDER SUMMARY
                                    </p>
                                    @if($delivery->order && $delivery->order->items)
                                        @foreach($delivery->order->items as $item)
                                            <div class="flex justify-between items-center text-xs">
                                                <span class="font-bold text-slate-800">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</span>
                                                <span class="font-black text-slate-600">Qty: {{ $item->quantity }}</span>
                                            </div>
                                        @endforeach
                                    @endif
                                    <div class="border-t border-slate-200/60 pt-2 flex justify-between items-center text-xs font-bold text-slate-700">
                                        <span>Order Amount:</span>
                                        <span class="font-black text-slate-900">₱{{ number_format($delivery->order->total_amount ?? 0, 2) }}</span>
                                    </div>
                                </div>

                                {{-- DELIVERY LOCATION SECTION --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                            <i class="fas fa-map-marker-alt text-rose-500"></i> DELIVERY LOCATION
                                        </p>
                                        @if($delivery->delivery_distance !== null)
                                            <span class="text-xs font-black text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-0.5 rounded-full">
                                                <i class="fas fa-route text-[10px] mr-1"></i>{{ $delivery->delivery_distance }} km from pharmacy
                                            </span>
                                        @endif
                                    </div>
                                    <p class="font-bold text-slate-800 text-sm leading-snug">{{ $delivery->delivery_address }}</p>
                                    <p class="text-[11px] text-slate-400 mt-1"><i class="fas fa-shield-alt mr-1"></i>Customer contact info protected until accepted</p>
                                </div>
                            </div>
                        </div>

                        {{-- Footer Action --}}
                        <div class="p-6 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">DELIVERY FEE</p>
                                <p class="text-2xl font-black text-emerald-600">₱{{ number_format($delivery->order->delivery_fee ?? 50, 2) }}</p>
                            </div>

                            @if($hasActiveDelivery)
                                <button disabled
                                    class="px-5 py-3.5 bg-slate-200 text-slate-400 font-black rounded-2xl text-xs flex items-center gap-2 cursor-not-allowed opacity-75">
                                    <i class="fas fa-lock"></i> Active Delivery
                                </button>
                            @else
                                <form action="{{ route('driver.bookings.accept', $delivery->id) }}" method="POST">
                                    @csrf
                                    <button type="submit"
                                        class="px-6 py-3.5 bg-gradient-to-r from-rider-600 to-violet-600 hover:from-rider-500 hover:to-violet-500 text-white font-black rounded-2xl shadow-lg shadow-rider-600/30 transition-all hover:-translate-y-0.5 text-xs flex items-center gap-2 active:scale-[0.97]">
                                        <i class="fas fa-hand-pointer"></i> ACCEPT BOOKING
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </main>
</body>
</html>
