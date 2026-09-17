<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard — GEORX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        rider: { 950: '#0D0F1A', 900: '#111827', 800: '#1C2333', 600: '#3B5BDB', 500: '#4C6EF5', 400: '#748FFC', 300: '#A5B4FC' }
                    }
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
                <div class="w-10 h-10 bg-gradient-to-br from-rider-600 to-violet-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-motorcycle text-lg text-white"></i>
                </div>
                <div>
                    <h1 class="text-base font-black leading-tight">GEORX Rider Portal</h1>
                    <p class="text-[10px] text-rider-300 font-bold uppercase tracking-widest">Driver Dashboard</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                {{-- Online/Offline Toggle --}}
                <form action="{{ route('driver.availability') }}" method="POST" class="inline">
                    @csrf
                    @php $isOnline = $profile?->is_online; @endphp
                    <button type="submit" id="availability-toggle"
                        class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black border transition-all
                               {{ $isOnline ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-400 hover:bg-rose-500/10 hover:border-rose-500/40 hover:text-rose-400' : 'bg-slate-700/50 border-slate-600 text-slate-400 hover:bg-emerald-500/10 hover:border-emerald-500/40 hover:text-emerald-400' }}">
                        <span class="w-2 h-2 rounded-full {{ $isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-slate-500' }}"></span>
                        {{ $isOnline ? 'ONLINE' : 'OFFLINE' }}
                    </button>
                </form>

                <div class="hidden md:flex items-center gap-2 bg-white/5 px-3 py-2 rounded-xl border border-white/10 text-sm font-bold">
                    <i class="fas fa-user-circle text-rider-300 text-base"></i>
                    {{ $user->name }}
                </div>

                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-white/5 hover:bg-white/15 border border-white/10 px-3 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-[1400px] mx-auto p-6 md:p-8 space-y-8">

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

        {{-- KPI Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Status</p>
                <span class="text-sm font-black px-3 py-1 rounded-full border {{ $hasActiveDelivery ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200' }}">
                    {{ $hasActiveDelivery ? 'On Active Delivery' : 'Available for Booking' }}
                </span>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Available Feed</p>
                <p class="text-3xl font-black text-rider-600">{{ $availableDeliveries->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Completed Today</p>
                <p class="text-3xl font-black text-emerald-600">
                    {{ $completedDeliveries->where('delivered_at', '>=', now()->startOfDay())->count() }}
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Today's Earnings</p>
                <p class="text-3xl font-black text-violet-600">
                    ₱{{ number_format($completedDeliveries->where('delivered_at', '>=', now()->startOfDay())->sum(fn($d) => $d->order->delivery_fee ?? 50), 2) }}
                </p>
            </div>
        </div>

        {{-- MY ACTIVE DELIVERY SECTION --}}
        @if($activeDelivery)
            <div class="bg-white rounded-3xl border-2 border-rider-500 shadow-xl overflow-hidden">
                <div class="bg-gradient-to-r from-rider-950 to-rider-800 text-white px-6 py-4 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 bg-amber-400 rounded-full animate-ping"></span>
                        <h2 class="font-black text-base uppercase tracking-wider">MY ACTIVE DELIVERY #{{ $activeDelivery->id }}</h2>
                    </div>
                    <span class="text-xs font-black uppercase tracking-widest bg-amber-400 text-amber-950 px-3 py-1 rounded-full">
                        {{ str_replace('_', ' ', $activeDelivery->status) }}
                    </span>
                </div>

                <div class="p-6 md:p-8 space-y-6">
                    {{-- Progress Stepper --}}
                    <div class="grid grid-cols-4 gap-2 text-center text-xs font-black border-b border-slate-100 pb-6">
                        <div class="p-2 rounded-xl border {{ in_array($activeDelivery->status, ['driver_assigned', 'driver_at_pharmacy', 'picked_up', 'out_for_delivery', 'delivered']) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                            1. Assigned
                        </div>
                        <div class="p-2 rounded-xl border {{ in_array($activeDelivery->status, ['driver_at_pharmacy', 'picked_up', 'out_for_delivery', 'delivered']) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                            2. At Pharmacy
                        </div>
                        <div class="p-2 rounded-xl border {{ in_array($activeDelivery->status, ['picked_up', 'out_for_delivery', 'delivered']) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                            3. Picked Up
                        </div>
                        <div class="p-2 rounded-xl border {{ $activeDelivery->status === 'delivered' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                            4. Delivered
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Pickup Location --}}
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-2">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-store-alt text-rider-500"></i> PICKUP PHARMACY
                            </p>
                            <p class="font-black text-slate-900 text-lg">{{ $activeDelivery->pickupPharmacy->name }}</p>
                            <p class="text-xs text-slate-600"><i class="fas fa-map-marker-alt text-rose-500 mr-1"></i>{{ $activeDelivery->pickupPharmacy->address }}</p>
                            <p class="text-xs text-slate-500"><i class="fas fa-phone mr-1"></i>{{ $activeDelivery->pickupPharmacy->phone ?? 'N/A' }}</p>
                            @if($activeDelivery->verification_code)
                                <div class="mt-3 p-3 bg-slate-900 text-white rounded-xl border border-slate-700 flex justify-between items-center shadow-inner">
                                    <div>
                                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Pharmacy Pickup PIN</p>
                                        <p class="text-[10px] text-slate-300">Show to pharmacy staff</p>
                                    </div>
                                    <span class="text-base font-black text-emerald-400 tracking-widest font-mono bg-slate-800 px-3 py-1 rounded-lg border border-slate-700">{{ $activeDelivery->verification_code }}</span>
                                </div>
                            @endif
                            @if($activeDelivery->pickup_latitude && $activeDelivery->pickup_longitude)
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $activeDelivery->pickup_latitude }},{{ $activeDelivery->pickup_longitude }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-900 text-white rounded-xl text-xs font-bold hover:bg-slate-800 transition-colors mt-2">
                                    <i class="fas fa-directions text-emerald-400"></i> GPS Navigation to Pharmacy
                                </a>
                            @endif
                        </div>

                        {{-- Delivery Customer Location --}}
                        <div class="bg-blue-50/60 p-5 rounded-2xl border border-blue-200 space-y-2">
                            <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest flex items-center gap-1.5">
                                <i class="fas fa-user-circle text-blue-500"></i> CUSTOMER & DELIVERY ADDRESS
                            </p>
                            <p class="font-black text-slate-900 text-lg">{{ $activeDelivery->customer->name ?? 'Customer' }}</p>
                            <p class="text-xs text-slate-700 font-bold"><i class="fas fa-phone mr-1 text-blue-500"></i>{{ $activeDelivery->customer->phone ?? 'Contact on delivery' }}</p>
                            <p class="text-xs text-slate-700 leading-relaxed font-semibold"><i class="fas fa-map-pin mr-1 text-rose-500"></i>{{ $activeDelivery->delivery_address }}</p>
                            
                            {{-- Rider Contact Patient Actions --}}
                            <div class="flex items-center gap-2 pt-1">
                                <a href="tel:{{ $activeDelivery->customer->phone ?? '' }}"
                                   class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 text-white rounded-xl text-xs font-extrabold hover:bg-blue-700 transition shadow-sm">
                                    <i class="fas fa-phone-alt"></i> Call Patient
                                </a>
                                <a href="sms:{{ $activeDelivery->customer->phone ?? '' }}?body=Hello! I am your GeoRx delivery driver en route with your prescription."
                                   class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 text-white rounded-xl text-xs font-extrabold hover:bg-emerald-700 transition shadow-sm">
                                    <i class="fas fa-comment-alt"></i> SMS Patient
                                </a>
                            </div>

                            @if($activeDelivery->delivery_latitude && $activeDelivery->delivery_longitude)
                                <a href="https://www.google.com/maps/dir/?api=1&destination={{ $activeDelivery->delivery_latitude }},{{ $activeDelivery->delivery_longitude }}"
                                   target="_blank" rel="noopener"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold hover:bg-blue-700 transition-colors mt-2">
                                    <i class="fas fa-location-arrow text-emerald-300"></i> GPS Navigation to Customer
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Medicine Summary --}}
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Medicine Items to Pick Up</p>
                        <div class="space-y-2">
                            @if($activeDelivery->order && $activeDelivery->order->items)
                                @foreach($activeDelivery->order->items as $item)
                                    <div class="flex justify-between items-center text-sm">
                                        <span class="font-bold text-slate-800">{{ $item->quantity }}x {{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</span>
                                        <span class="font-black text-slate-700">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- Next Step Actions --}}
                    @php
                        $nextStep = match($activeDelivery->status) {
                            'driver_assigned'   => ['driver_at_pharmacy', 'I Have Arrived at the Pharmacy', 'fas fa-store-alt', 'bg-rider-600 hover:bg-rider-500'],
                            'driver_at_pharmacy' => ['picked_up', 'Confirm Medicine Picked Up & Out for Delivery', 'fas fa-box-open', 'bg-teal-600 hover:bg-teal-500'],
                            'picked_up', 'out_for_delivery' => ['delivered', 'Mark Order as Delivered', 'fas fa-check-circle', 'bg-emerald-600 hover:bg-emerald-500'],
                            default => null,
                        };
                    @endphp

                    @if($nextStep)
                        <form action="{{ route('driver.bookings.status', $activeDelivery->id) }}" method="POST">
                            @csrf
                            <input type="hidden" name="status" value="{{ $nextStep[0] }}">
                            <button type="submit"
                                class="w-full py-4 {{ $nextStep[3] }} text-white font-black rounded-2xl shadow-xl transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2 text-base">
                                <i class="{{ $nextStep[2] }}"></i> {{ $nextStep[1] }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- AVAILABLE BOOKINGS SECTION --}}
        <div>
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                    <i class="fas fa-inbox text-rider-500"></i> Available Bookings Feed
                    <span class="px-2.5 py-0.5 bg-rider-100 text-rider-700 text-xs font-black rounded-full">{{ $availableDeliveries->count() }}</span>
                </h2>
                <a href="{{ route('driver.bookings') }}" class="text-xs font-bold text-rider-600 hover:underline">View All Feed →</a>
            </div>

            @if($availableDeliveries->isEmpty())
                <div class="py-12 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                    <i class="fas fa-coffee text-3xl text-slate-300 mb-2"></i>
                    <p class="font-bold text-slate-700">No available bookings at this moment.</p>
                    <p class="text-xs text-slate-400 mt-1">Confirmed pharmacy orders in Barangay Alijis will appear here automatically.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($availableDeliveries->take(3) as $delivery)
                        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden flex flex-col justify-between border-t-4 border-t-rider-500">
                            <div class="p-6 space-y-4">
                                <div class="flex justify-between items-center">
                                    <span class="font-black text-slate-800 text-base">DELIVERY #{{ $delivery->id }}</span>
                                    <span class="text-[10px] font-bold text-slate-400">{{ $delivery->created_at->diffForHumans() }}</span>
                                </div>

                                {{-- Pickup --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">PICKUP</p>
                                        @if($delivery->pickup_distance !== null)
                                            <span class="text-xs font-black text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                                                {{ $delivery->pickup_distance }} km
                                            </span>
                                        @endif
                                    </div>
                                    <p class="font-black text-slate-800 text-sm truncate">{{ $delivery->pickupPharmacy->name }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $delivery->pickupPharmacy->address }}</p>
                                </div>

                                {{-- Dropoff --}}
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">DELIVERY</p>
                                        @if($delivery->delivery_distance !== null)
                                            <span class="text-xs font-black text-blue-700 bg-blue-50 border border-blue-200 px-2 py-0.5 rounded-full">
                                                {{ $delivery->delivery_distance }} km
                                            </span>
                                        @endif
                                    </div>
                                    <p class="font-bold text-slate-800 text-xs truncate">{{ $delivery->delivery_address }}</p>
                                </div>
                            </div>

                            <div class="p-5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">FEE</p>
                                    <p class="text-xl font-black text-emerald-600">₱{{ number_format($delivery->order->delivery_fee ?? 50, 2) }}</p>
                                </div>
                                @if($hasActiveDelivery)
                                    <button disabled class="px-4 py-2.5 bg-slate-200 text-slate-400 font-bold rounded-xl text-xs cursor-not-allowed">
                                        <i class="fas fa-lock mr-1"></i> Active
                                    </button>
                                @else
                                    <form action="{{ route('driver.bookings.accept', $delivery->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-4 py-2.5 bg-rider-600 hover:bg-rider-500 text-white font-black rounded-xl text-xs shadow transition-all hover:-translate-y-0.5">
                                            ACCEPT
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- COMPLETED DELIVERIES SECTION --}}
        @if($completedDeliveries->isNotEmpty())
            <div>
                <h2 class="text-lg font-black text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-history text-emerald-500"></i> My Completed Deliveries
                </h2>
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden divide-y divide-slate-100">
                    @foreach($completedDeliveries->take(10) as $done)
                        <div class="p-5 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-emerald-50 rounded-full flex items-center justify-center shrink-0 border border-emerald-100">
                                    <i class="fas fa-check text-emerald-500"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800 text-sm">Delivery #{{ $done->id }} — {{ $done->pickupPharmacy->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $done->delivered_at?->format('M d, Y h:i A') ?? $done->updated_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                            <span class="font-black text-emerald-600 text-lg">₱{{ number_format($done->order->delivery_fee ?? 50, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isOnline = {{ $profile?->is_online ? 'true' : 'false' }};
            const hasActiveDelivery = {{ $hasActiveDelivery ? 'true' : 'false' }};
            const csrfToken = "{{ csrf_token() }}";

            // Only track if driver is online OR has an active delivery
            if (!isOnline && !hasActiveDelivery) {
                console.log('Location tracking paused: Driver is offline with no active delivery.');
                return;
            }

            if (!navigator.geolocation) {
                console.warn('Geolocation is not supported by this browser.');
                return;
            }

            function sendLocationUpdate(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                fetch("{{ route('driver.location.update') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ latitude: lat, longitude: lng })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        console.log('Driver location synchronized:', data.last_location_at);
                    }
                })
                .catch(err => console.error('GPS update failed:', err));
            }

            function handleGpsError(err) {
                if (err.code === err.PERMISSION_DENIED) {
                    console.warn('GPS Permission Denied by user.');
                } else if (err.code === err.POSITION_UNAVAILABLE) {
                    console.warn('GPS Position Unavailable.');
                }
            }

            // Initial position request & watch position
            navigator.geolocation.getCurrentPosition(sendLocationUpdate, handleGpsError, { enableHighAccuracy: true });
            navigator.geolocation.watchPosition(sendLocationUpdate, handleGpsError, { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 });
        });
    </script>
</body>
</html>
