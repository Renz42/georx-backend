@extends('pharmacy.layouts.app')

@section('title', 'Incoming Orders')

@section('content')
<div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Pharmacy Fulfillment</p>
        <h2 class="text-3xl font-black text-slate-800 tracking-tight">Incoming Orders</h2>
        <p class="text-slate-500 mt-1">Manage orders placed by customers and assigned to delivery partners.</p>
    </div>
</div>

<!-- Status Filters -->
<div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 mb-6">
    @php
        $filters = [
            'all' => ['All Orders', 'fas fa-layer-group'],
            'pending_confirmation' => ['Pending Review', 'fas fa-clipboard-check'],
            'pending' => ['Waiting for Rider', 'fas fa-clock'],
            'accepted' => ['Rider Assigned', 'fas fa-motorcycle'],
            'picked_up' => ['In Transit', 'fas fa-shipping-fast'],
            'delivered' => ['Completed', 'fas fa-check-double']
        ];
    @endphp
    
    @foreach($filters as $key => $data)
        <a href="{{ route('pharmacy.orders', ['status' => $key]) }}" 
           class="px-5 py-2.5 rounded-full font-bold text-sm whitespace-nowrap transition-all border flex items-center gap-2
           {{ $filter === $key ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:border-slate-300' }}">
            <i class="{{ $data[1] }} {{ $filter === $key ? 'text-white/80' : 'text-slate-400' }}"></i>
            {{ $data[0] }}
            <span class="ml-1 text-[10px] px-2 py-0.5 rounded-full {{ $filter === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">
                {{ $counts[$key] }}
            </span>
        </a>
    @endforeach
</div>

@if($orders->isEmpty())
    <div class="py-20 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
        <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-box-open text-4xl text-slate-300"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-700 mb-2">No orders found</h3>
        <p class="text-slate-500">There are no orders matching this filter.</p>
    </div>
@else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($orders as $order)
            @php
                $statusConfig = [
                    'pending_confirmation' => ['Pending Review', 'bg-purple-50 text-purple-700 border-purple-200', 'fas fa-clipboard-check'],
                    'pending' => ['Waiting for Rider', 'bg-amber-50 text-amber-700 border-amber-200', 'fas fa-search'],
                    'accepted' => ['Rider En Route', 'bg-blue-50 text-blue-700 border-blue-200', 'fas fa-motorcycle'],
                    'at_pharmacy' => ['Rider Waiting', 'bg-indigo-50 text-indigo-700 border-indigo-200', 'fas fa-user-clock'],
                    'picked_up' => ['In Transit', 'bg-violet-50 text-violet-700 border-violet-200', 'fas fa-shipping-fast'],
                    'delivered' => ['Delivered', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fas fa-check-double'],
                    'cancelled' => ['Cancelled', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-ban'],
                    'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 border-rose-200', 'fas fa-times-circle'],
                ];
                $cfg = $statusConfig[$order->status] ?? ['Unknown', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-question'];
            @endphp
            
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <div>
                        <span class="font-black text-slate-800 text-lg">Order #{{ $order->id }}</span>
                        <p class="text-xs text-slate-500 font-medium">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $cfg[1] }} flex items-center gap-1.5">
                        <i class="{{ $cfg[2] }}"></i> {{ $cfg[0] }}
                    </span>
                </div>
                
                <div class="p-6 flex-1 space-y-6">
                    <!-- Customer Info -->
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center shrink-0">
                            <i class="fas fa-user text-slate-400"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Customer</p>
                            <h3 class="font-black text-slate-800">{{ $order->user->name }}</h3>
                            <p class="text-xs text-slate-500 line-clamp-1"><i class="fas fa-map-pin text-slate-400 mr-1"></i> {{ $order->delivery_address }}</p>
                        </div>
                    </div>
                    
                    <!-- Rider Details & Verification Code Info -->
                    @if($order->delivery || $order->deliveryPartner)
                    @php
                        $assignedRider = $order->delivery?->driver ?? $order->deliveryPartner;
                        $driverProfile = $assignedRider?->driverProfile;
                    @endphp
                    <div class="p-4 bg-blue-50/60 rounded-2xl border border-blue-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center shrink-0 text-blue-600 font-bold">
                                    <i class="fas fa-helmet-safety"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Assigned Delivery Rider</p>
                                    <h3 class="font-bold text-slate-800 text-sm">{{ $assignedRider?->name ?? 'Awaiting Rider Acceptance' }}</h3>
                                    @if($assignedRider?->phone)
                                        <p class="text-xs text-blue-600 font-semibold"><i class="fas fa-phone mr-1"></i>{{ $assignedRider->phone }}</p>
                                    @endif
                                </div>
                            </div>
                            @if($order->delivery?->verification_code)
                            <div class="text-right bg-white px-3 py-1.5 rounded-xl border border-blue-200 shadow-sm">
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Pickup Code</p>
                                <p class="font-black text-blue-600 text-sm tracking-wider font-mono">{{ $order->delivery->verification_code }}</p>
                            </div>
                            @endif
                        </div>

                        <!-- Vehicle & Plate Number Details -->
                        @if($driverProfile)
                        <div class="flex items-center justify-between pt-2 border-t border-blue-100/80 text-xs">
                            <span class="text-slate-600 font-medium">
                                <i class="fas fa-motorcycle text-slate-400 mr-1"></i>
                                {{ ucfirst($driverProfile->vehicle_type ?? 'Motorcycle') }} 
                                @if($driverProfile->vehicle_model) ({{ $driverProfile->vehicle_model }}) @endif
                            </span>
                            <span class="font-black text-slate-800 bg-white px-2.5 py-1 rounded-lg border border-slate-200 uppercase tracking-wider">
                                🚘 Plate: {{ $driverProfile->plate_number ?? 'N/A' }}
                            </span>
                        </div>
                        @endif
                    </div>
                    @endif

                    <!-- Customer Delivery Approval Status Banner -->
                    @if($order->customer_confirmed_delivery)
                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 flex items-center gap-3">
                        <div class="w-8 h-8 bg-emerald-500 text-white rounded-full flex items-center justify-center shrink-0">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Customer Delivery Receipt Approved</p>
                            <p class="text-xs font-bold text-emerald-900">
                                Confirmed by {{ $order->user->name }} on {{ $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : 'Recently' }}
                            </p>
                        </div>
                    </div>
                    @endif

                    <!-- Items List -->
                    <div class="space-y-3">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-2">Order Items</p>
                        @foreach($order->items as $item)
                            <div class="flex justify-between items-start text-sm">
                                <div class="flex gap-2">
                                    <span class="font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">{{ $item->quantity }}x</span>
                                    <span class="font-bold text-slate-700">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</span>
                                </div>
                                <span class="font-bold text-slate-800">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-between items-end">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Total Value</p>
                        <p class="text-2xl font-black text-slate-900">₱{{ number_format($order->total_amount - $order->delivery_fee, 2) }}</p>
                    </div>
                    <div class="flex gap-2">
                        @if($order->status === 'pending_confirmation')
                            <form action="{{ route('pharmacy.orders.reject', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to reject this order?');">
                                @csrf
                                <input type="hidden" name="reason" value="Out of stock or unavailable">
                                <button type="submit" class="px-5 py-2.5 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 font-bold rounded-xl shadow-sm transition text-sm flex items-center gap-2">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                            <form action="{{ route('pharmacy.orders.confirm', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-lg transition hover:-translate-y-0.5 text-sm flex items-center gap-2">
                                    <i class="fas fa-check"></i> Confirm Order
                                </button>
                            </form>
                        @elseif(in_array($order->status, ['pending', 'accepted', 'at_pharmacy']))
                            @if($order->is_prepared)
                                <div class="px-4 py-2 bg-emerald-50 text-emerald-600 font-bold rounded-xl text-sm flex items-center gap-2 border border-emerald-200">
                                    <i class="fas fa-check-circle"></i> Prepared
                                </div>
                            @else
                                <form action="{{ route('pharmacy.orders.prepare', $order->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-lg transition hover:-translate-y-0.5 text-sm flex items-center gap-2">
                                        <i class="fas fa-box"></i> Mark Prepared
                                    </button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

@endsection
