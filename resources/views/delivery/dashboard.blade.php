<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Dashboard - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <style>
        body { font-family: 'Outfit', sans-serif; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
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
<body class="bg-slate-50 text-slate-800 min-h-screen">

    <!-- Top Navbar -->
    <nav class="bg-gradient-to-r from-violet-600 to-indigo-700 text-white h-[72px] flex items-center px-6 shadow-lg sticky top-0 z-50">
        <div class="w-full flex justify-between items-center max-w-[1600px] mx-auto">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center border border-white/10">
                    <i class="fas fa-motorcycle text-xl"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black tracking-tight leading-none">GEORX Rider</h1>
                    <p class="text-white/70 text-[10px] font-bold uppercase tracking-widest mt-0.5">Delivery Partner Dashboard</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="hidden md:flex items-center gap-2 bg-white/10 backdrop-blur px-4 py-2.5 rounded-xl border border-white/10">
                    <i class="fas fa-user-circle text-white/80"></i>
                    <span class="text-sm font-bold">{{ Auth::user()->name }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-white/10 hover:bg-white/25 px-4 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 border border-white/10">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="max-w-[1600px] mx-auto p-6 md:p-8 lg:p-12 space-y-8">

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-2xl flex items-center shadow-sm animate-pulse">
                <i class="fas fa-check-circle mr-3 text-emerald-500 text-lg"></i>
                <span class="font-bold">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-2xl flex items-center shadow-sm">
                <i class="fas fa-exclamation-circle mr-3 text-rose-500 text-lg"></i>
                <span class="font-bold">{{ session('error') }}</span>
            </div>
        @endif

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Available Orders</span>
                    <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center"><i class="fas fa-inbox text-amber-500"></i></div>
                </div>
                <p class="text-3xl font-black text-slate-900">{{ $availableOrders->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Active Deliveries</span>
                    <div class="w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center"><i class="fas fa-shipping-fast text-blue-500"></i></div>
                </div>
                <p class="text-3xl font-black text-slate-900">{{ $activeDeliveries->count() }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Completed Today</span>
                    <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center"><i class="fas fa-check-double text-emerald-500"></i></div>
                </div>
                <p class="text-3xl font-black text-slate-900">{{ $completedDeliveries->where('updated_at', '>=', now()->startOfDay())->count() }}</p>
            </div>
        </div>

        <!-- Active Deliveries Section -->
        @if($activeDeliveries->isNotEmpty())
        <div>
            <h2 class="text-xl font-black text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-bolt text-blue-500"></i> My Active Deliveries
            </h2>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($activeDeliveries as $delivery)
                    @php
                        $nextStatus = match($delivery->status) {
                            'accepted' => ['at_pharmacy', 'Arrived at Pharmacy', 'fas fa-store', 'indigo'],
                            'at_pharmacy' => ['picked_up', 'Order Picked Up', 'fas fa-box', 'violet'],
                            'picked_up' => ['delivered', 'Mark as Delivered', 'fas fa-check-double', 'emerald'],
                            default => null,
                        };
                        $statusColor = match($delivery->status) {
                            'accepted' => 'blue',
                            'at_pharmacy' => 'indigo',
                            'picked_up' => 'violet',
                            default => 'slate',
                        };
                    @endphp
                    <div class="bg-white rounded-2xl border-2 border-slate-200 shadow-sm overflow-hidden">
                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-100 flex justify-between items-center">
                            <span class="font-black text-slate-700 text-sm">Order #{{ $delivery->id }}</span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-600 bg-slate-100 px-3 py-1 rounded-full">
                                {{ str_replace('_', ' ', $delivery->status) }}
                            </span>
                        </div>
                        <div class="p-6 space-y-4">
                            <!-- Pharmacy Pickup -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-slate-50 rounded-lg flex items-center justify-center border border-slate-100 shrink-0">
                                        <i class="fas fa-store-alt text-slate-400"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Pick Up From</p>
                                        <p class="font-black text-slate-800">{{ $delivery->pharmacy->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $delivery->pharmacy->address }}</p>
                                    </div>
                                </div>
                                @if($delivery->pharmacy->latitude && $delivery->pharmacy->longitude)
                                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->pharmacy->latitude }},{{ $delivery->pharmacy->longitude }}" target="_blank" class="px-3 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-xs font-bold flex items-center gap-1.5 border border-slate-200">
                                        <i class="fas fa-directions text-slate-500"></i> Pickup GPS
                                    </a>
                                @endif
                            </div>
                            <!-- Customer Dropoff -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-slate-50 rounded-lg flex items-center justify-center border border-slate-100 shrink-0">
                                        <i class="fas fa-map-pin text-blue-400"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Deliver To</p>
                                        <p class="font-bold text-slate-800">{{ $delivery->user->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $delivery->delivery_address }}</p>
                                    </div>
                                </div>
                                @if($delivery->latitude && $delivery->longitude)
                                    <a href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->latitude }},{{ $delivery->longitude }}" target="_blank" class="px-3 py-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-xl text-xs font-bold flex items-center gap-1.5 border border-blue-200">
                                        <i class="fas fa-location-arrow text-blue-500"></i> Dropoff GPS
                                    </a>
                                @endif
                            </div>
                            <!-- Items -->
                            <div class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Items ({{ $delivery->items->count() }})</p>
                                @foreach($delivery->items as $item)
                                    <p class="text-sm text-slate-700 font-medium">{{ $item->quantity }}x {{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</p>
                                @endforeach
                                <p class="text-sm font-black text-slate-800 mt-2 pt-2 border-t border-slate-200">Total: ₱{{ number_format($delivery->total_amount, 2) }}</p>
                            </div>
                            <!-- Action -->
                            @if($nextStatus)
                                <form action="{{ route('delivery.updateStatus', $delivery->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $nextStatus[0] }}">
                                    <button type="submit" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-xl shadow-lg transition hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                        <i class="{{ $nextStatus[2] }}"></i> {{ $nextStatus[1] }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Available Orders Section -->
        <div>
            <h2 class="text-xl font-black text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-inbox text-amber-500"></i> Available Orders
            </h2>

            @if($availableOrders->isEmpty())
                <div class="py-16 text-center bg-white rounded-2xl border border-slate-200 shadow-sm">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-coffee text-3xl text-slate-300"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-700 mb-2">No orders right now</h3>
                    <p class="text-slate-500">New delivery requests will appear here automatically.</p>
                </div>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($availableOrders as $order)
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                            <div class="bg-amber-50 px-6 py-3 border-b border-amber-100 flex justify-between items-center">
                                <span class="font-black text-amber-700 text-sm">Order #{{ $order->id }}</span>
                                <span class="text-xs font-bold text-slate-500">{{ $order->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-slate-50 rounded-lg flex items-center justify-center border border-slate-100 shrink-0">
                                        <i class="fas fa-store-alt text-slate-400"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">From</p>
                                        <p class="font-black text-slate-800">{{ $order->pharmacy->name }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-slate-50 rounded-lg flex items-center justify-center border border-slate-100 shrink-0">
                                        <i class="fas fa-map-pin text-blue-400"></i>
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Deliver To</p>
                                        <p class="text-sm font-medium text-slate-700 line-clamp-2">{{ $order->delivery_address }}</p>
                                    </div>
                                </div>
                                <div class="flex justify-between items-end pt-2 border-t border-slate-100">
                                    <div>
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Earnings</p>
                                        <p class="text-2xl font-black text-emerald-600">₱{{ number_format($order->delivery_fee, 2) }}</p>
                                    </div>
                                    <form action="{{ route('delivery.accept', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-xl shadow-lg shadow-blue-200 transition hover:-translate-y-0.5 flex items-center gap-2">
                                            <i class="fas fa-hand-pointer"></i> Accept
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Completed Deliveries Section -->
        @if($completedDeliveries->isNotEmpty())
        <div>
            <h2 class="text-xl font-black text-slate-800 mb-4 flex items-center gap-2">
                <i class="fas fa-history text-emerald-500"></i> Recent Completions
            </h2>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="divide-y divide-slate-100">
                    @foreach($completedDeliveries as $completed)
                        <div class="p-5 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-emerald-50 rounded-full flex items-center justify-center shrink-0">
                                    <i class="fas fa-check text-emerald-500"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-slate-800">Order #{{ $completed->id }} — {{ $completed->pharmacy->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $completed->updated_at->format('M d, Y h:i A') }}</p>
                                </div>
                            </div>
                            <span class="font-black text-emerald-600">₱{{ number_format($completed->delivery_fee, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </main>
</body>
</html>
