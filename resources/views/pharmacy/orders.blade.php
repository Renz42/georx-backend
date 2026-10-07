@extends('pharmacy.layouts.app')

@section('title', 'Incoming Orders')

@section('content')
<div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
    <div>
        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Pharmacy Fulfillment</p>
        <h2 class="text-3xl font-black text-slate-800 tracking-tight">Incoming Orders</h2>
        <p class="text-slate-500 mt-1 text-sm">Review incoming customer prescriptions and dispatch packages via Grab Express or Maxim.</p>
    </div>
</div>

<!-- Status Filters -->
<div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 mb-6">
    @php
        $filters = [
            'all' => ['All Orders', 'fas fa-layer-group'],
            'pending_confirmation' => ['Pending Review', 'fas fa-clipboard-check'],
            'pending' => ['Ready for Dispatch', 'fas fa-box'],
            'accepted' => ['Courier Booked', 'fas fa-motorcycle'],
            'picked_up' => ['In Transit', 'fas fa-shipping-fast'],
            'delivered' => ['Delivered', 'fas fa-check-double']
        ];
    @endphp
    
    @foreach($filters as $key => $data)
        <a href="{{ route('pharmacy.orders', ['status' => $key]) }}" 
           class="px-5 py-2.5 rounded-full font-bold text-sm whitespace-nowrap transition-all border flex items-center gap-2
           {{ $filter === $key ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-200' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 hover:border-slate-300' }}">
            <i class="{{ $data[1] }} {{ $filter === $key ? 'text-white/80' : 'text-slate-400' }}"></i>
            {{ $data[0] }}
            <span class="ml-1 text-[10px] px-2 py-0.5 rounded-full {{ $filter === $key ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">
                {{ $counts[$key] ?? 0 }}
            </span>
        </a>
    @endforeach
</div>

@if($orders->isEmpty())
    <div class="py-20 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
            <i class="fas fa-box-open text-3xl text-slate-300"></i>
        </div>
        <h3 class="text-xl font-black text-slate-700 mb-1">No orders found</h3>
        <p class="text-slate-400 text-sm">There are no orders matching this filter.</p>
    </div>
@else
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @foreach($orders as $order)
            @php
                $statusConfig = [
                    'pending_confirmation' => ['Pending Review', 'bg-purple-50 text-purple-700 border-purple-200', 'fas fa-clipboard-check'],
                    'pending' => ['Ready for Delivery', 'bg-amber-50 text-amber-700 border-amber-200', 'fas fa-box'],
                    'accepted' => ['Courier Booked', 'bg-blue-50 text-blue-700 border-blue-200', 'fas fa-motorcycle'],
                    'at_pharmacy' => ['Rider Arrived', 'bg-indigo-50 text-indigo-700 border-indigo-200', 'fas fa-user-clock'],
                    'picked_up' => ['In Transit', 'bg-violet-50 text-violet-700 border-violet-200', 'fas fa-shipping-fast'],
                    'delivered' => ['Delivered', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fas fa-check-double'],
                    'cancelled' => ['Cancelled', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-ban'],
                    'rejected' => ['Rejected', 'bg-rose-50 text-rose-700 border-rose-200', 'fas fa-times-circle'],
                ];
                $cfg = $statusConfig[$order->status] ?? ['Processing', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-clock'];
                $pharmacyObj = $order->pharmacy ?? $pharmacy;
            @endphp
            
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <!-- Order Header -->
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <div>
                        <span class="font-black text-slate-900 text-lg">Order #{{ $order->id }}</span>
                        <p class="text-xs text-slate-500 font-medium mt-0.5">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    <span class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest border {{ $cfg[1] }} flex items-center gap-1.5">
                        <i class="{{ $cfg[2] }}"></i> {{ $cfg[0] }}
                    </span>
                </div>
                
                <div class="p-6 flex-1 space-y-6">
                    <!-- Customer & Drop-off Details -->
                    <div class="p-4 bg-slate-50/70 rounded-2xl border border-slate-100 flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 bg-white rounded-xl border border-slate-200 flex items-center justify-center shrink-0 shadow-2xs">
                                <i class="fas fa-user text-slate-500 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient / Customer</p>
                                <h3 class="font-black text-slate-900 text-sm mt-0.5">{{ $order->user->name }}</h3>
                                <p class="text-xs text-slate-600 mt-1 flex items-start gap-1">
                                    <i class="fas fa-map-marker-alt text-rose-500 text-[11px] mt-0.5 shrink-0"></i>
                                    <span>{{ $order->delivery_address }}</span>
                                </p>
                                @if($order->user->phone)
                                    <p class="text-xs text-slate-500 mt-0.5 flex items-center gap-1">
                                        <i class="fas fa-phone text-slate-400 text-[10px]"></i>
                                        <span>{{ $order->user->phone }}</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest block">Estimated Delivery Fee</span>
                            <span class="text-xs font-black text-slate-700 bg-white border border-slate-200 px-2 py-0.5 rounded-md mt-1 inline-block">
                                ₱{{ number_format($order->delivery_fee, 2) }}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Assisted External Delivery Logistics (Grab Express / Maxim) -->
                    @if($order->delivery_provider)
                        @php
                            $isGrab = $order->delivery_provider === 'grab';
                            $providerLabel = $isGrab ? 'Grab Express' : 'Maxim Delivery';
                            $providerBg = $isGrab ? 'bg-emerald-50/60 border-emerald-200' : 'bg-amber-50/60 border-amber-200';
                            $providerText = $isGrab ? 'text-emerald-800' : 'text-amber-900';
                            $providerIcon = $isGrab ? 'fas fa-motorcycle text-emerald-600' : 'fas fa-car-side text-amber-600';
                            $deliveryStatusLabel = match($order->delivery_status) {
                                'in_transit' => 'In Transit to Patient',
                                'delivered' => 'Delivered to Patient',
                                default => 'Booked / Dispatch Pending',
                            };
                        @endphp
                        <div class="p-4.5 rounded-2xl border {{ $providerBg }} space-y-3.5 shadow-2xs">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shrink-0 shadow-2xs border border-slate-200/60">
                                        <i class="{{ $providerIcon }} text-lg"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black uppercase tracking-wider {{ $providerText }}">{{ $providerLabel }}</span>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-widest bg-white border border-slate-200 text-slate-700">
                                                {{ $deliveryStatusLabel }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <p class="text-xs font-mono font-bold text-slate-700">Ref: <span class="text-slate-900 font-black">{{ $order->delivery_booking_id ?? 'N/A' }}</span></p>
                                            @if($order->delivery_tracking_url)
                                                <a href="{{ $order->delivery_tracking_url }}" target="_blank" rel="noopener noreferrer" 
                                                   class="text-[11px] font-bold text-blue-600 hover:text-blue-800 underline flex items-center gap-1 ml-2">
                                                    <span>Open Tracking</span>
                                                    <i class="fas fa-external-link-alt text-[9px]"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($order->delivery_booked_at)
                                    <div class="text-right text-[11px] text-slate-500 font-medium">
                                        <span class="text-[9px] uppercase tracking-wider block text-slate-400 font-bold">Booked</span>
                                        {{ $order->delivery_booked_at->format('M d, Y h:i A') }}
                                    </div>
                                @endif
                            </div>

                            <!-- Financial Distinction: Courier Fare vs Delivery Estimate -->
                            @if($order->actual_courier_fee)
                            <div class="px-3 py-1.5 bg-white/80 rounded-xl border border-slate-200/60 flex items-center justify-between text-xs text-slate-600">
                                <span class="font-medium">Actual Courier Fare Paid:</span>
                                <strong class="text-slate-800 font-black">₱{{ number_format($order->actual_courier_fee, 2) }}</strong>
                            </div>
                            @endif

                            <!-- Rider Information if recorded -->
                            @if($order->driver_name || $order->driver_phone)
                            <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 text-xs text-slate-700">
                                <span class="font-medium flex items-center gap-1.5">
                                    <i class="fas fa-id-badge text-slate-400"></i>
                                    Courier Rider: <strong class="text-slate-900">{{ $order->driver_name ?? 'Assigned Rider' }}</strong>
                                </span>
                                @if($order->driver_phone)
                                    <a href="tel:{{ $order->driver_phone }}" class="font-bold text-blue-600 hover:underline flex items-center gap-1">
                                        <i class="fas fa-phone text-xs"></i> {{ $order->driver_phone }}
                                    </a>
                                @endif
                            </div>
                            @endif

                            <!-- Handover and Delivery Progress Actions -->
                            @if($order->status !== 'delivered' && $order->delivery_status !== 'delivered')
                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200/60">
                                @if($order->delivery_status === 'booked' || $order->status === 'accepted')
                                <form action="{{ route('pharmacy.orders.delivery-status', $order->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="in_transit">
                                    <button type="submit" class="px-3.5 py-1.5 bg-white hover:bg-slate-50 text-indigo-700 border border-indigo-200 rounded-xl font-bold text-xs shadow-2xs transition flex items-center gap-1.5">
                                        <i class="fas fa-motorcycle"></i> Mark Handed Over (In Transit)
                                    </button>
                                </form>
                                @endif

                                <form action="{{ route('pharmacy.orders.delivery-status', $order->id) }}" method="POST" onsubmit="return confirm('Confirm that this package has been successfully delivered to the customer?');">
                                    @csrf
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition flex items-center gap-1.5">
                                        <i class="fas fa-check-double"></i> Mark Delivered
                                    </button>
                                </form>
                            </div>
                            @endif
                        </div>
                    @elseif($order->is_prepared && !$order->delivery_provider && !$order->delivery)
                        <!-- Assisted External Delivery Booking Interface (Grab Express / Maxim) -->
                        <div class="p-5 bg-gradient-to-br from-slate-50 to-blue-50/40 rounded-2xl border border-blue-100/80 space-y-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest flex items-center gap-1.5">
                                        <i class="fas fa-box text-blue-500"></i> Ready for Delivery
                                    </p>
                                    <h4 class="font-black text-slate-800 text-sm mt-0.5">Assisted External Courier Booking</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Launch Grab Express or Maxim, book the rider, then record the reference below.</p>
                                </div>
                                <button type="button" 
                                        onclick="copyDeliveryDispatchDetails('{{ addslashes($pharmacyObj->name ?? 'Pharmacy') }}', '{{ addslashes($pharmacyObj->address ?? '') }}', '{{ addslashes($pharmacyObj->phone ?? '') }}', '{{ addslashes($order->user->name) }}', '{{ addslashes($order->delivery_address) }}', '{{ addslashes($order->user->phone ?? '') }}', '{{ $order->id }}')"
                                        class="px-3 py-1.5 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-lg text-xs font-bold shadow-2xs transition flex items-center gap-1.5 shrink-0"
                                        title="Copy pickup, dropoff, and medicine info to clipboard">
                                    <i class="fas fa-copy text-blue-500"></i> Copy Details
                                </button>
                            </div>

                            <!-- Provider Portals Buttons -->
                            <div class="grid grid-cols-2 gap-2.5 pt-1">
                                <a href="https://express.grab.com" target="_blank" rel="noopener noreferrer" 
                                   class="flex items-center justify-center gap-2 p-2.5 bg-white hover:bg-emerald-50/50 border border-emerald-200 rounded-xl text-emerald-800 font-bold text-xs shadow-2xs transition">
                                    <i class="fas fa-motorcycle text-emerald-600"></i>
                                    <span>Book via Grab Express</span>
                                    <i class="fas fa-external-link-alt text-[9px] text-emerald-500 ml-0.5"></i>
                                </a>
                                <a href="https://taximaxim.com/ph/en-US/" target="_blank" rel="noopener noreferrer" 
                                   class="flex items-center justify-center gap-2 p-2.5 bg-white hover:bg-amber-50/50 border border-amber-200 rounded-xl text-amber-900 font-bold text-xs shadow-2xs transition">
                                    <i class="fas fa-car-side text-amber-600"></i>
                                    <span>Book via Maxim</span>
                                    <i class="fas fa-external-link-alt text-[9px] text-amber-500 ml-0.5"></i>
                                </a>
                            </div>

                            <!-- Dispatch Booking Form -->
                            <form action="{{ route('pharmacy.orders.book-delivery', $order->id) }}" method="POST" class="pt-2 border-t border-slate-200/80 space-y-3">
                                @csrf
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Provider *</label>
                                        <select name="delivery_provider" required class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            <option value="grab">Grab Express</option>
                                            <option value="maxim">Maxim Delivery</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Booking Reference ID *</label>
                                        <input type="text" name="delivery_booking_id" required placeholder="e.g. GRAB-94821 or MAXIM-1024"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Live Tracking URL (Optional)</label>
                                        <input type="url" name="delivery_tracking_url" placeholder="https://express.grab.com/track/..."
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Actual Courier Fare ₱ (Optional)</label>
                                        <input type="number" step="0.01" min="0" name="actual_courier_fee" placeholder="e.g. 52.00"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Rider Phone (Optional)</label>
                                        <input type="text" name="driver_phone" placeholder="e.g. 0917 123 4567"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Rider Name (Optional)</label>
                                        <input type="text" name="driver_name" placeholder="e.g. Juan Rider"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>

                                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2">
                                    <i class="fas fa-check"></i> Confirm Dispatch & Notify Patient
                                </button>
                            </form>
                        </div>
                    @elseif($order->delivery || $order->deliveryPartner)
                        <!-- Legacy Driver Archive Record -->
                        @php
                            $assignedRider = $order->delivery?->driver ?? $order->deliveryPartner;
                        @endphp
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Internal Rider Archive</p>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center shrink-0 text-slate-600 font-bold">
                                    <i class="fas fa-motorcycle text-xs"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 text-xs">{{ $assignedRider?->name ?? 'GEORX Driver' }}</h3>
                                    <p class="text-[11px] text-slate-500">Historical delivery record</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Customer Delivery Approval Status Banner -->
                    @if($order->customer_confirmed_delivery)
                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 flex items-center gap-3">
                        <div class="w-7 h-7 bg-emerald-500 text-white rounded-full flex items-center justify-center shrink-0">
                            <i class="fas fa-check text-xs"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-emerald-700 uppercase tracking-widest">Customer Receipt Approved</p>
                            <p class="text-xs font-bold text-emerald-900">
                                Confirmed by {{ $order->user->name }} on {{ $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : 'Recently' }}
                            </p>
                        </div>
                    </div>
                    @endif

                    <!-- Medicine Fulfillment Information (Section 9 Specification) -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Prescribed Medicines & Fulfillment Specs</p>
                            <span class="text-[10px] text-slate-400 font-semibold">{{ $order->items->count() }} item(s)</span>
                        </div>

                        <div class="space-y-3">
                            @foreach($order->items as $item)
                                @php
                                    $med = $item->medicine;
                                    $sku = $med->medicine_code ?? ('MED-' . str_pad($item->medicine_id, 5, '0', STR_PAD_LEFT));
                                    $brand = $med->brand_name ?? '';
                                    $generic = $med->generic_name ?? 'Medicine';
                                    $dosage = $med->dosage_form ?? 'Tablet';
                                    $strength = $med->strength ?? '';
                                    $unit = $med->unit_of_measure ?? 'piece';
                                    $deductions = $order->fifo_deductions[$item->medicine_id] ?? null;
                                @endphp
                                <div class="p-3.5 bg-slate-50/60 rounded-xl border border-slate-100 space-y-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-black text-blue-700 bg-blue-50 border border-blue-100 px-2 py-0.5 rounded text-xs">
                                                    {{ $item->quantity }}x
                                                </span>
                                                <h4 class="font-black text-slate-900 text-sm">
                                                    {{ $brand ? "{$brand} ({$generic})" : $generic }}
                                                </h4>
                                            </div>
                                            <p class="text-xs text-slate-500 mt-0.5">
                                                {{ $dosage }} {{ $strength ? "• {$strength}" : '' }} • SKU: <span class="font-mono font-semibold text-slate-700">{{ $sku }}</span>
                                            </p>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="text-xs font-black text-slate-900 block">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                                            <span class="text-[10px] text-slate-400">₱{{ number_format($item->price, 2) }}/{{ $unit }}</span>
                                        </div>
                                    </div>

                                    <!-- FIFO Batch Traceability -->
                                    <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
                                        <span class="font-bold text-slate-500 uppercase tracking-wider text-[9px]">Fulfillment Batch:</span>
                                        @if(!empty($deductions) && is_array($deductions))
                                            <div class="flex flex-wrap gap-1 justify-end">
                                                @foreach($deductions as $d)
                                                    <span class="px-2 py-0.5 rounded bg-white border border-slate-200 text-slate-700 font-mono font-bold text-[10px]">
                                                        {{ $d['batch_number'] ?? 'Batch' }} (Exp: {{ $d['expiration_date'] ?? 'N/A' }})
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-slate-400 italic text-[10px]">
                                                {{ $order->status === 'pending_confirmation' ? 'Will allocate via FIFO on confirmation' : 'Active Inventory' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                
                <!-- Financial Summary & Workflow Controls -->
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-end gap-4">
                    <div class="space-y-0.5">
                        <div class="flex items-center gap-3 text-xs text-slate-500">
                            <span>Subtotal: <strong>₱{{ number_format($order->total_amount - $order->delivery_fee, 2) }}</strong></span>
                            <span>•</span>
                            <span>Est. Delivery: <strong>₱{{ number_format($order->delivery_fee, 2) }}</strong></span>
                        </div>
                        <p class="text-2xl font-black text-slate-900 tracking-tight">
                            Total: ₱{{ number_format($order->total_amount, 2) }}
                        </p>
                    </div>

                    <div class="flex gap-2 w-full sm:w-auto justify-end">
                        @if($order->status === 'pending_confirmation')
                            <form action="{{ route('pharmacy.orders.reject', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to reject this order?');">
                                @csrf
                                <input type="hidden" name="reason" value="Out of stock or unavailable">
                                <button type="submit" class="px-4 py-2 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 font-bold rounded-xl shadow-2xs transition text-xs flex items-center gap-1.5">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                            <form action="{{ route('pharmacy.orders.confirm', $order->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl shadow-md transition text-xs flex items-center gap-1.5">
                                    <i class="fas fa-check"></i> Accept & Confirm (FIFO)
                                </button>
                            </form>
                        @elseif(in_array($order->status, ['pending', 'accepted', 'at_pharmacy']))
                            @if($order->is_prepared)
                                <div class="px-3.5 py-2 bg-emerald-50 text-emerald-700 font-bold rounded-xl text-xs flex items-center gap-1.5 border border-emerald-200">
                                    <i class="fas fa-check-circle"></i> Ready for Delivery
                                </div>
                            @else
                                <form action="{{ route('pharmacy.orders.prepare', $order->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md transition text-xs flex items-center gap-1.5">
                                        <i class="fas fa-box"></i> Mark Ready for Delivery
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

<script>
function copyDeliveryDispatchDetails(pharmacyName, pharmacyAddress, pharmacyPhone, customerName, customerAddress, customerPhone, orderId) {
    const text = `--- GEORX MEDICINE DELIVERY (ORDER #${orderId}) ---
PICKUP PHARMACY:
Name: ${pharmacyName}
Address: ${pharmacyAddress}
Phone: ${pharmacyPhone || 'N/A'}

DROP-OFF PATIENT:
Name: ${customerName}
Address: ${customerAddress}
Phone: ${customerPhone || 'N/A'}

INSTRUCTIONS:
Fragile medicinal package from GEORX Hub. Collect package at pharmacy counter and deliver to patient.`;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            alert(`Order #${orderId} dispatch details copied to clipboard!\nYou can now paste them directly into Grab Express or Maxim.`);
        }).catch(() => {
            promptFallback(text);
        });
    } else {
        promptFallback(text);
    }
}

function promptFallback(text) {
    const textarea = document.createElement("textarea");
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert("Delivery details copied to clipboard!");
    } catch (err) {
        alert("Copy failed. Please manually copy the address.");
    }
    document.body.removeChild(textarea);
}
</script>
@endsection
