@extends('pharmacy.layouts.app')

@section('title', 'Incoming Orders')

@section('content')
<!-- Header Toolbar -->
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Pharmacy Fulfillment</p>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Incoming Orders</h2>
        <p class="text-slate-500 mt-1 text-sm font-normal">Review incoming customer prescriptions and dispatch packages via Grab Express or Maxim.</p>
    </div>

    <!-- Top Right Controls: Expand All, Search & Notifications -->
    <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap">
        <!-- Expand / Collapse All Toggle -->
        <button type="button" id="toggleAllBtn" onclick="toggleAllOrderDetails()" 
                class="px-3.5 py-2 bg-white border border-slate-200/90 hover:bg-slate-50 text-slate-700 font-bold text-xs rounded-xl shadow-2xs transition flex items-center gap-1.5 shrink-0"
                title="Expand or collapse all order details at once">
            <i class="fas fa-angles-down text-slate-400 text-xs" id="toggleAllIcon"></i>
            <span id="toggleAllText">Expand All</span>
        </button>

        <!-- Search Bar -->
        <div class="relative w-full sm:w-64">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                <i class="fas fa-search text-xs"></i>
            </span>
            <input type="text" id="orderSearchInput" placeholder="Search orders..." 
                   class="w-full pl-9 pr-3.5 py-2 bg-white border border-slate-200/90 rounded-xl text-xs font-semibold text-slate-700 placeholder:text-slate-400 placeholder:font-normal focus:outline-none focus:ring-2 focus:ring-[#0D7A5F] focus:border-transparent transition shadow-2xs">
        </div>

        <!-- Notification Bell -->
        <button type="button" class="w-9 h-9 rounded-xl border border-slate-200/90 bg-white flex items-center justify-center text-slate-600 hover:bg-slate-50 shadow-2xs transition shrink-0" title="Notifications">
            <i class="far fa-bell text-sm"></i>
        </button>
    </div>
</div>

@if($errors->any())
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs text-rose-800 shadow-2xs">
        <div class="font-bold flex items-center gap-2 mb-1">
            <i class="fas fa-exclamation-triangle text-rose-600"></i>
            <span>Unable to process order update</span>
        </div>
        <ul class="list-disc list-inside space-y-0.5 font-medium">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@once
<script>
window.checkCourierProviderMismatch = function(el) {
    const form = el ? el.closest('form') : null;
    if (!form) return;
    const providerSelect = form.querySelector('select[name="delivery_provider"]');
    const refInput = form.querySelector('input[name="delivery_booking_id"]');
    const urlInput = form.querySelector('input[name="delivery_tracking_url"]');
    const hint = form.querySelector('[data-provider-hint]');
    if (!providerSelect || !refInput || !hint) return;

    const provider = (providerSelect.value || '').toLowerCase();
    const ref = (refInput.value || '').trim().toLowerCase();
    const url = (urlInput ? urlInput.value : '').trim().toLowerCase();

    let warning = '';
    if (provider === 'grab' && (ref.includes('maxim') || url.includes('taximaxim') || url.includes('maxim'))) {
        warning = 'Selected provider is Grab Express, but booking reference/URL appears to belong to Maxim.';
    } else if (provider === 'maxim' && (ref.includes('grab') || url.includes('grab.com'))) {
        warning = 'Selected provider is Maxim Delivery, but booking reference/URL appears to belong to Grab Express.';
    }

    if (warning) {
        hint.textContent = warning;
        hint.classList.remove('hidden');
    } else {
        hint.textContent = '';
        hint.classList.add('hidden');
    }
};
</script>
@endonce

<!-- Status Filters (Image 2: Deep Pine Active Filter #0D7A5F with Translucent Counter) -->
<div class="flex gap-2.5 overflow-x-auto custom-scrollbar pb-2 mb-6">
    @php
        $filters = [
            'all' => ['All Orders', 'fas fa-layer-group'],
            'pending_confirmation' => ['Pending Review', 'far fa-clipboard-check'],
            'pending' => ['Ready for Dispatch', 'fas fa-box'],
            'accepted' => ['Courier Booked', 'fas fa-motorcycle'],
            'picked_up' => ['In Transit', 'fas fa-shipping-fast'],
            'delivered' => ['Delivered', 'far fa-check-circle']
        ];
    @endphp

    @foreach($filters as $key => $data)
        <a href="{{ route('pharmacy.orders', ['status' => $key]) }}" 
           class="px-4 py-2 rounded-xl font-bold text-xs whitespace-nowrap transition-all border flex items-center gap-2 shadow-2xs
           {{ $filter === $key ? 'bg-[#0D7A5F] text-white border-[#0D7A5F] shadow-xs' : 'bg-white text-slate-600 border-slate-200/90 hover:bg-slate-50 hover:text-slate-900 hover:border-slate-300' }}">
            <i class="{{ $data[1] }} {{ $filter === $key ? 'text-white' : 'text-slate-400' }}"></i>
            <span>{{ $data[0] }}</span>
            <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full font-bold {{ $filter === $key ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">
                {{ $counts[$key] ?? 0 }}
            </span>
        </a>
    @endforeach
</div>

@if($orders->isEmpty())
    <div class="py-20 text-center bg-white rounded-2xl border border-slate-200/90 shadow-sm">
        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100">
            <i class="fas fa-box-open text-2xl text-slate-300"></i>
        </div>
        <h3 class="text-lg font-black text-slate-800 mb-1">No orders found</h3>
        <p class="text-slate-400 text-xs font-medium">There are no orders matching this filter.</p>
    </div>
@else
    <!-- Real-time Search Empty State Container -->
    <div id="noSearchResults" class="hidden py-16 text-center bg-white rounded-2xl border border-slate-200/90 shadow-sm mb-6">
        <div class="w-14 h-14 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100">
            <i class="fas fa-search text-xl text-slate-300"></i>
        </div>
        <h3 class="text-base font-black text-slate-800 mb-0.5">No matching orders found</h3>
        <p class="text-slate-400 text-xs">Try adjusting your search query.</p>
    </div>

    <!-- Orders Stack (Compact Summaries with Expandable Details) -->
    <div class="space-y-4" id="ordersContainer">
        @foreach($orders as $order)
            @php
                $statusConfig = [
                    'pending_confirmation' => ['PENDING REVIEW', 'bg-slate-100 text-slate-700 border-slate-200', 'far fa-clock'],
                    'pending' => ['READY FOR DISPATCH', 'bg-amber-50 text-amber-800 border-amber-200', 'fas fa-box'],
                    'accepted' => ['COURIER BOOKED', 'bg-blue-50 text-blue-800 border-blue-200', 'fas fa-motorcycle'],
                    'at_pharmacy' => ['RIDER ARRIVED', 'bg-indigo-50 text-indigo-800 border-indigo-200', 'fas fa-user-clock'],
                    'picked_up' => ['IN TRANSIT', 'bg-violet-50 text-violet-800 border-violet-200', 'fas fa-shipping-fast'],
                    'delivered' => ['DELIVERED', 'bg-[#dcfce7] text-[#065f46] border-[#a7f3d0]', 'fas fa-check'],
                    'cancelled' => ['CANCELLED', 'bg-rose-50 text-rose-700 border-rose-200', 'fas fa-ban'],
                    'rejected' => ['REJECTED', 'bg-rose-50 text-rose-700 border-rose-200', 'fas fa-times-circle'],
                ];
                $cfg = $statusConfig[$order->status] ?? ['PROCESSING', 'bg-slate-50 text-slate-700 border-slate-200', 'fas fa-clock'];
                $pharmacyObj = $order->pharmacy ?? $pharmacy;
                $pricing = $order->pricingSummary();
                $isSubmittedBookingOrder = (string) old('_booking_order_id') === (string) $order->id;
                $isSubmittedFeeOrder = (string) old('_fee_order_id') === (string) $order->id;
                $hasOrderValidationBounce = $errors->any() && ($isSubmittedBookingOrder || $isSubmittedFeeOrder);

                // Build searchable text index for instant client-side search
                $searchTerms = [
                    $order->id,
                    '#' . $order->id,
                    $order->user->name,
                    $order->delivery_address,
                    $order->user->phone ?? '',
                    $order->delivery_booking_id ?? '',
                    $order->driver_name ?? '',
                    $order->driver_phone ?? ''
                ];
                foreach ($order->items as $itm) {
                    if ($itm->medicine) {
                        $searchTerms[] = $itm->medicine->brand_name ?? '';
                        $searchTerms[] = $itm->medicine->generic_name ?? '';
                        $searchTerms[] = $itm->medicine->medicine_code ?? '';
                    }
                }
                $searchData = strtolower(implode(' ', array_filter($searchTerms)));
            @endphp

            <div class="order-card bg-white rounded-2xl border border-slate-200/90 shadow-sm overflow-hidden hover:shadow-md transition-all"
                 data-search="{{ $searchData }}">

                <!-- COMPACT ORDER SUMMARY (Click to Expand / Collapse) -->
                <div role="button" tabindex="0" 
                     onclick="toggleOrderDetails('order-details-{{ $order->id }}', this)" 
                     onkeydown="if(event.key === 'Enter' || event.key === ' ') { event.preventDefault(); toggleOrderDetails('order-details-{{ $order->id }}', this); }"
                     class="order-summary-trigger p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 cursor-pointer select-none hover:bg-slate-50/80 transition-colors"
                     aria-expanded="{{ $hasOrderValidationBounce ? 'true' : 'false' }}"
                     aria-controls="order-details-{{ $order->id }}"
                     title="Click to view full order details">
                    
                    <!-- Left: Order Number, Customer, Date -->
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-10 h-10 bg-slate-100 rounded-xl border border-slate-200/80 flex items-center justify-center shrink-0 text-slate-600 shadow-2xs font-mono font-black text-xs">
                            #{{ $order->id }}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-black text-slate-900 text-base sm:text-lg tracking-tight truncate">
                                    Order #{{ $order->id }}
                                </h3>
                                <span class="text-xs text-slate-400 font-medium">•</span>
                                <span class="font-bold text-slate-700 text-xs sm:text-sm truncate">
                                    {{ $order->user->name }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 font-semibold mt-0.5 flex items-center gap-2">
                                <span><i class="far fa-clock text-[11px] mr-1 text-slate-400"></i>{{ $order->created_at->format('M d, Y h:i A') }}</span>
                                <span>•</span>
                                <span>{{ $order->items->count() }} item(s)</span>
                            </p>
                        </div>
                    </div>

                    <!-- Right: Total, Status Badge, Expand Indicator -->
                    <div class="flex items-center justify-between sm:justify-end gap-3.5 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                        <div class="text-left sm:text-right">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">
                                CUSTOMER PAYABLE (COD)
                            </span>
                            <span class="text-sm sm:text-base font-black text-slate-900 leading-none block mt-0.5">
                                ₱{{ number_format($pricing['customer_payable_amount'], 2) }}
                            </span>
                            <span class="text-[9px] font-bold {{ $pricing['has_actual_courier_fee'] ? 'text-slate-600' : 'text-amber-600' }} block mt-0.5">
                                {{ $pricing['has_actual_courier_fee'] ? 'Courier Exp. (Info): ₱' . number_format($pricing['actual_courier_fee'], 2) : 'Courier Exp. (Info): Pending' }}
                            </span>
                        </div>

                        <!-- Status Badge -->
                        <span class="px-3 py-1 rounded-lg text-xs font-black tracking-wider border {{ $cfg[1] }} flex items-center gap-1.5 shrink-0">
                            <i class="{{ $cfg[2] }} text-[11px]"></i> {{ $cfg[0] }}
                        </span>

                        <!-- Expand Chevron -->
                        <div class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-transform duration-200 chevron-icon shrink-0">
                            <i class="fas fa-chevron-down text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- FULL ORDER DETAILS (Expandable Panel) -->
                <div id="order-details-{{ $order->id }}" class="order-details-panel {{ $hasOrderValidationBounce ? '' : 'hidden' }} border-t border-slate-100 p-5 sm:p-6 bg-slate-50/30 space-y-5">

                    <!-- Customer & Drop-off Details -->
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-4 border-b border-slate-100">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-11 h-11 bg-white rounded-full border border-slate-200/80 flex items-center justify-center shrink-0 text-slate-500 shadow-2xs">
                                <i class="far fa-user text-base"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">PATIENT / CUSTOMER</p>
                                <h4 class="font-bold text-slate-900 text-base mt-0.5 truncate">{{ $order->user->name }}</h4>
                                <p class="text-xs text-slate-600 mt-1 flex items-start gap-1.5 leading-snug break-words">
                                    <i class="fas fa-map-marker-alt text-rose-500 text-xs mt-0.5 shrink-0"></i>
                                    <span>{{ $order->delivery_address }}</span>
                                </p>
                                @if($order->user->phone)
                                    <p class="text-xs text-slate-600 mt-1 flex items-center gap-1.5 font-medium">
                                        <i class="fas fa-phone-alt text-slate-400 text-xs shrink-0"></i>
                                        <a href="tel:{{ $order->user->phone }}" class="hover:text-[#0D7A5F] transition font-bold">{{ $order->user->phone }}</a>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <!-- Dedicated Order Delivery Fee Box -->
                        <div class="border border-slate-200/90 rounded-xl px-4 py-2.5 text-left sm:text-right bg-white shrink-0 shadow-2xs">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">DELIVERY FEE (COD)</span>
                            <span class="text-lg font-black text-slate-900 mt-0.5 block leading-none">
                                ₱{{ number_format($pricing['estimated_delivery_fee'], 2) }}
                            </span>
                            <span class="text-[10px] font-semibold text-slate-500 mt-1 block">
                                @if($pricing['has_actual_courier_fee'])
                                    Internal Courier Exp.: <strong class="text-slate-900">₱{{ number_format($pricing['actual_courier_fee'], 2) }}</strong>
                                @else
                                    Internal Courier Exp.: <span class="text-amber-600 font-bold">Pending / Not yet recorded</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Assisted External Delivery Logistics (Grab Express / Maxim) -->
                    @if($order->delivery_provider)
                        @php
                            $isGrab = $order->delivery_provider === 'grab';
                            $providerLabel = $isGrab ? 'GRAB EXPRESS' : 'MAXIM DELIVERY';
                            $providerCardClass = $isGrab ? 'bg-[#ecfdf5] border-[#a7f3d0]' : 'bg-[#fef9c3]/50 border-[#fde68a]';
                            $providerTextClass = $isGrab ? 'text-[#065f46]' : 'text-[#78350f]';
                            $providerIconBadge = $isGrab ? 'bg-[#a7f3d0]/70 border-[#6ee7b7] text-[#065f46]' : 'bg-[#fef08a] border-[#fde047] text-[#854d0e]';
                            $providerIcon = $isGrab ? 'fas fa-motorcycle' : 'fas fa-car-side';
                            $deliveryStatusLabel = match($order->delivery_status) {
                                'in_transit' => 'IN TRANSIT TO PATIENT',
                                'delivered' => 'DELIVERED TO PATIENT',
                                default => 'BOOKED / DISPATCH PENDING',
                            };
                        @endphp
                        <div class="p-4 rounded-xl border {{ $providerCardClass }} space-y-3 shadow-2xs">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-9 h-9 rounded-lg border {{ $providerIconBadge }} flex items-center justify-center shrink-0">
                                        <i class="{{ $providerIcon }} text-sm"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-black uppercase tracking-wide {{ $providerTextClass }}">
                                                {{ $providerLabel }} • {{ $deliveryStatusLabel }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <p class="text-xs font-mono font-medium text-slate-700">Ref: <span class="text-slate-900 font-bold">{{ $order->delivery_booking_id ?? 'N/A' }}</span></p>
                                            @if($order->delivery_tracking_url)
                                                <a href="{{ $order->delivery_tracking_url }}" target="_blank" rel="noopener noreferrer" 
                                                   class="text-[11px] font-bold text-[#0D7A5F] hover:text-[#0B6E53] underline flex items-center gap-1 ml-2">
                                                    <span>Open Tracking</span>
                                                    <i class="fas fa-external-link-alt text-[9px]"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if($order->delivery_booked_at)
                                    <div class="text-right text-xs shrink-0">
                                        <span class="text-[9px] uppercase tracking-wider block text-slate-400 font-bold">BOOKED •</span>
                                        <span class="font-semibold text-slate-700">{{ $order->delivery_booked_at->format('M d, Y h:i A') }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Financial Distinction: Charged Delivery Fee (COD) vs Internal Courier Expense -->
                            <div class="pt-2 border-t border-slate-200/60 space-y-2 text-xs text-slate-600">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">Charged Delivery Fee (COD):</span>
                                    <strong class="text-slate-900 font-bold">₱{{ number_format($pricing['estimated_delivery_fee'], 2) }}</strong>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">Actual Courier Expense (Info):</span>
                                    @if($pricing['has_actual_courier_fee'])
                                        <strong class="text-slate-900 font-black">₱{{ number_format($pricing['actual_courier_fee'], 2) }}</strong>
                                    @else
                                        <span class="text-amber-600 font-bold">Pending / Not yet recorded</span>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">Customer Payable (COD):</span>
                                    <strong class="text-slate-900 font-black">₱{{ number_format($pricing['customer_payable_amount'], 2) }}</strong>
                                </div>

                                @if(($order->delivery_type ?? 'delivery') !== 'pickup' && !empty($order->delivery_provider) && !empty($order->delivery_booking_id) && !in_array($order->status, ['cancelled', 'rejected'], true))
                                    <form action="{{ route('pharmacy.orders.courier-fee', $order->id) }}" method="POST" class="pt-2 border-t border-slate-200/60 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="_fee_order_id" value="{{ $order->id }}">
                                        <label for="actual_courier_fee_{{ $order->id }}" class="text-[10px] font-black text-slate-500 uppercase tracking-wider">
                                            Record / Update Actual Courier Fare (₱)
                                        </label>
                                        <input id="actual_courier_fee_{{ $order->id }}" type="number" step="0.01" min="0" max="10000"
                                               name="actual_courier_fee"
                                               value="{{ $isSubmittedFeeOrder ? old('actual_courier_fee') : ($pricing['has_actual_courier_fee'] ? number_format($pricing['actual_courier_fee'], 2, '.', '') : '') }}"
                                               placeholder="e.g. 58.00"
                                               required
                                               class="w-28 bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                        <button type="submit" class="px-3 py-1 rounded-lg bg-[#0D7A5F] text-white text-[11px] font-bold hover:bg-[#0B664F] transition">
                                            {{ $pricing['has_actual_courier_fee'] ? 'Update Fare' : 'Save Actual Fare' }}
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <!-- Rider Information if recorded -->
                            @if($order->driver_name || $order->driver_phone)
                                <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs text-slate-700">
                                    <span class="font-medium flex items-center gap-1.5">
                                        <i class="far fa-id-badge text-slate-400"></i>
                                        Courier Rider: <strong class="text-slate-900">{{ $order->driver_name ?? 'Assigned Rider' }}</strong>
                                    </span>
                                    @if($order->driver_phone)
                                        <a href="tel:{{ $order->driver_phone }}" class="font-bold text-[#0D7A5F] hover:text-[#0B6E53] hover:underline flex items-center gap-1">
                                            <i class="fas fa-phone-alt text-[10px]"></i> {{ $order->driver_phone }}
                                        </a>
                                    @endif
                                </div>
                            @endif

                            <!-- Handover and Delivery Progress Actions for Pharmacy -->
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

                                    @if($order->delivery_status === 'in_transit' || $order->status === 'picked_up')
                                        <form action="{{ route('pharmacy.orders.delivery-status', $order->id) }}" method="POST" onsubmit="return confirm('Confirm that this package has been successfully delivered to the customer?');">
                                            @csrf
                                            <input type="hidden" name="status" value="delivered">
                                            <button type="submit" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-md transition flex items-center gap-1.5">
                                                <i class="fas fa-check-double"></i> Mark Delivered
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @elseif($order->is_prepared && !$order->delivery_provider && !$order->delivery)
                        <!-- Assisted External Delivery Booking Interface (Grab Express / Maxim) -->
                        <div class="p-5 bg-gradient-to-br from-slate-50 to-[#E6F4EA]/30 rounded-xl border border-emerald-200/80 space-y-4 shadow-2xs">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-[10px] font-black text-[#0D7A5F] uppercase tracking-widest flex items-center gap-1.5">
                                        <i class="fas fa-box text-[#0D7A5F]"></i> Ready for Delivery
                                    </p>
                                    <h4 class="font-black text-slate-800 text-sm mt-0.5">Assisted External Courier Booking</h4>
                                    <p class="text-xs text-slate-500 mt-0.5">Launch Grab Express or Maxim, book the rider, then record the reference below.</p>
                                </div>
                                <button type="button" 
                                        onclick="copyDeliveryDispatchDetails('{{ addslashes($pharmacyObj->name ?? 'Pharmacy') }}', '{{ addslashes($pharmacyObj->address ?? '') }}', '{{ addslashes($pharmacyObj->phone ?? '') }}', '{{ addslashes($order->user->name) }}', '{{ addslashes($order->delivery_address) }}', '{{ addslashes($order->user->phone ?? '') }}', '{{ $order->id }}')"
                                        class="px-3 py-1.5 bg-white border border-slate-200 hover:border-slate-300 text-slate-700 rounded-lg text-xs font-bold shadow-2xs transition flex items-center gap-1.5 shrink-0"
                                        title="Copy pickup, dropoff, and medicine info to clipboard">
                                    <i class="far fa-copy text-[#0D7A5F]"></i> Copy Details
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
                                <input type="hidden" name="_booking_order_id" value="{{ $order->id }}">
                                @php
                                    $selectedBookingProvider = $isSubmittedBookingOrder
                                        ? old('delivery_provider', $order->delivery_provider)
                                        : $order->delivery_provider;
                                @endphp
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Provider *</label>
                                        <select name="delivery_provider" required onchange="window.checkCourierProviderMismatch(this)" class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                            <option value="grab" {{ $selectedBookingProvider === 'grab' ? 'selected' : '' }}>Grab Express</option>
                                            <option value="maxim" {{ $selectedBookingProvider === 'maxim' ? 'selected' : '' }}>Maxim Delivery</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Booking Reference ID *</label>
                                        <input type="text" name="delivery_booking_id" required
                                               value="{{ $isSubmittedBookingOrder ? old('delivery_booking_id') : '' }}"
                                               oninput="window.checkCourierProviderMismatch(this)"
                                               placeholder="e.g. GRAB-94821 or MAXIM-1024"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                    </div>
                                </div>
                                <p data-provider-hint class="hidden rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-semibold text-amber-800"></p>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="sm:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Live Tracking URL (Optional)</label>
                                        <input type="url" name="delivery_tracking_url"
                                               value="{{ $isSubmittedBookingOrder ? old('delivery_tracking_url') : '' }}"
                                               oninput="window.checkCourierProviderMismatch(this)"
                                               placeholder="https://express.grab.com/track/..."
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Actual Courier Fare ₱ (Optional)</label>
                                        <input type="number" step="0.01" min="0" max="10000" name="actual_courier_fee"
                                               value="{{ $isSubmittedBookingOrder ? old('actual_courier_fee') : '' }}"
                                               placeholder="e.g. 52.00"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Rider Phone (Optional)</label>
                                        <input type="text" name="driver_phone"
                                               value="{{ $isSubmittedBookingOrder ? old('driver_phone') : '' }}"
                                               placeholder="e.g. 0917 123 4567"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-wider mb-1">Rider Name (Optional)</label>
                                        <input type="text" name="driver_name"
                                               value="{{ $isSubmittedBookingOrder ? old('driver_name') : '' }}"
                                               placeholder="e.g. Juan Rider"
                                               class="w-full bg-white border border-slate-200 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F]">
                                    </div>
                                </div>

                                <button type="submit" class="w-full py-2.5 bg-[#0D7A5F] hover:bg-[#0B6E53] text-white font-bold rounded-xl text-xs shadow-md transition flex items-center justify-center gap-2">
                                    <i class="fas fa-check"></i> Confirm Dispatch & Notify Patient
                                </button>
                            </form>
                        </div>
                    @elseif($order->delivery || $order->deliveryPartner)
                        <!-- Legacy Driver Archive Record -->
                        @php
                            $assignedRider = $order->delivery?->driver ?? $order->deliveryPartner;
                        @endphp
                        <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Internal Rider Archive</p>
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-slate-200 rounded-full flex items-center justify-center shrink-0 text-slate-600 font-bold">
                                    <i class="fas fa-motorcycle text-xs"></i>
                                </div>
                                <div>
                                    <h5 class="font-bold text-slate-800 text-xs">{{ $assignedRider?->name ?? 'GEORX Driver' }}</h5>
                                    <p class="text-[11px] text-slate-500">Historical delivery record</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Customer Delivery Approval Status Banner -->
                    @if($order->customer_confirmed_delivery)
                        <div class="p-3.5 bg-[#dcfce7] border border-[#a7f3d0] rounded-xl flex items-center gap-3 shadow-2xs">
                            <div class="w-7 h-7 bg-white rounded-full border border-[#86efac] flex items-center justify-center text-[#059669] shrink-0">
                                <i class="fas fa-check text-xs font-bold"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-[#065f46] uppercase tracking-wider">CUSTOMER RECEIPT APPROVED •</p>
                                <p class="text-xs font-semibold text-[#047857]">
                                    Confirmed by {{ $order->user->name }} on {{ $order->customer_confirmed_at ? $order->customer_confirmed_at->format('M d, Y g:i A') : 'Recently' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Rejection / Cancellation Notice if applicable -->
                    @if($order->status === 'rejected' || $order->status === 'cancelled')
                        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-start gap-2.5 text-xs text-rose-800">
                            <i class="fas fa-exclamation-triangle text-rose-500 mt-0.5 shrink-0"></i>
                            <div>
                                <span class="font-bold uppercase text-[10px] tracking-wider block">Order {{ ucfirst($order->status) }}</span>
                                <p class="mt-0.5 font-medium">{{ $order->rejection_reason ?? $order->cancellation_reason ?? 'No detailed explanation recorded.' }}</p>
                            </div>
                        </div>
                    @endif

                    <!-- Medicine Fulfillment Information -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">PRESCRIBED MEDICINES & FULFILLMENT SPECS</p>
                            <span class="text-[10px] text-slate-400 font-semibold">{{ $order->items->count() }} item(s)</span>
                        </div>

                        <!-- Responsive 2-Column Medicine Items Grid -->
                        <div class="grid grid-cols-1 {{ $order->items->count() > 1 ? 'md:grid-cols-2' : '' }} gap-3">
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
                                <div class="p-3.5 bg-slate-100/70 border border-slate-200/80 rounded-xl flex flex-col justify-between">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-start gap-2.5 min-w-0">
                                            <span class="w-7 h-7 rounded-lg bg-slate-200/90 text-slate-700 font-black text-xs flex items-center justify-center shrink-0 mt-0.5">
                                                {{ $item->quantity }}x
                                            </span>
                                            <div class="min-w-0">
                                                <h5 class="font-black text-slate-900 text-xs sm:text-sm leading-snug truncate" title="{{ $brand ? "{$brand} ({$generic})" : $generic }}">
                                                    {{ $brand ? "{$brand} ({$generic})" : $generic }}
                                                </h5>
                                                <p class="text-[11px] text-slate-500 font-medium mt-0.5">
                                                    {{ $dosage }} {{ $strength ? "• {$strength}" : '' }} • SKU: <span class="font-mono font-semibold text-slate-700">{{ $sku }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        <div class="text-right shrink-0">
                                            <span class="text-xs sm:text-sm font-black text-slate-900 block">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                                            <span class="text-[10px] text-slate-400 block font-medium">₱{{ number_format($item->price, 2) }}/{{ $unit }}</span>
                                        </div>
                                    </div>

                                    <!-- FIFO Batch Traceability -->
                                    <div class="mt-3 pt-2 border-t border-slate-200/60 flex flex-col gap-1">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">FULFILLMENT BATCH:</span>
                                        @if(!empty($deductions) && is_array($deductions))
                                            <div class="flex flex-wrap gap-1">
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

                    <!-- Financial Summary & Workflow Controls -->
                    <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
                            <span>Medicine Subtotal: <strong class="text-slate-900">₱{{ number_format($pricing['medicine_subtotal'], 2) }}</strong></span>
                            <span class="text-slate-300">•</span>
                            <span>Delivery Fee (COD): <strong class="text-slate-900">₱{{ number_format($pricing['estimated_delivery_fee'], 2) }}</strong></span>
                            <span class="text-slate-300">•</span>
                            <span>Courier Expense (Info):
                                @if($pricing['has_actual_courier_fee'])
                                    <strong class="text-slate-900">₱{{ number_format($pricing['actual_courier_fee'], 2) }}</strong>
                                @else
                                    <strong class="text-amber-600">Pending</strong>
                                @endif
                            </span>
                            <span class="text-slate-300">•</span>
                            <span class="text-slate-900 font-black">Customer Payable (COD): ₱{{ number_format($pricing['customer_payable_amount'], 2) }}</span>
                        </div>

                        <!-- Workflow Actions -->
                        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                            @if($order->status === 'pending_confirmation')
                                <form action="{{ route('pharmacy.orders.reject', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to reject this order?');">
                                    @csrf
                                    <input type="hidden" name="reason" value="Out of stock or unavailable">
                                    <button type="submit" class="px-3.5 py-2 bg-white border border-rose-200 hover:bg-rose-50 text-rose-600 font-bold rounded-xl text-xs shadow-2xs transition flex items-center gap-1.5">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                                <form action="{{ route('pharmacy.orders.confirm', $order->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition flex items-center gap-1.5">
                                        <i class="fas fa-check"></i> Accept & Confirm (FIFO)
                                    </button>
                                </form>
                            @elseif(in_array($order->status, ['pending', 'accepted', 'at_pharmacy']))
                                @if(!$order->is_prepared && !$order->delivery_provider)
                                    <form action="{{ route('pharmacy.orders.prepare', $order->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-4 py-2 bg-[#0D7A5F] hover:bg-[#0B6E53] text-white font-bold rounded-xl text-xs shadow-md transition flex items-center gap-1.5 active:scale-95">
                                            <i class="fas fa-box"></i> Mark Ready for Delivery
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>

                </div>

            </div>
        @endforeach
    </div>
@endif

<script>
// Expand / Collapse Single Order Details
function toggleOrderDetails(panelId, triggerEl) {
    const panel = document.getElementById(panelId);
    if (!panel) return;

    const isHidden = panel.classList.contains('hidden');
    const chevron = triggerEl.querySelector('.chevron-icon');

    if (isHidden) {
        panel.classList.remove('hidden');
        triggerEl.setAttribute('aria-expanded', 'true');
        if (chevron) chevron.classList.add('rotate-180');
    } else {
        panel.classList.add('hidden');
        triggerEl.setAttribute('aria-expanded', 'false');
        if (chevron) chevron.classList.remove('rotate-180');
    }
}

// Expand / Collapse All Orders Simultaneously
let allExpanded = false;
function toggleAllOrderDetails() {
    allExpanded = !allExpanded;
    const panels = document.querySelectorAll('.order-details-panel');
    const triggers = document.querySelectorAll('.order-summary-trigger');
    const toggleText = document.getElementById('toggleAllText');
    const toggleIcon = document.getElementById('toggleAllIcon');

    panels.forEach(panel => {
        if (allExpanded) {
            panel.classList.remove('hidden');
        } else {
            panel.classList.add('hidden');
        }
    });

    triggers.forEach(trigger => {
        trigger.setAttribute('aria-expanded', allExpanded ? 'true' : 'false');
        const chevron = trigger.querySelector('.chevron-icon');
        if (chevron) {
            if (allExpanded) {
                chevron.classList.add('rotate-180');
            } else {
                chevron.classList.remove('rotate-180');
            }
        }
    });

    if (toggleText) {
        toggleText.textContent = allExpanded ? 'Collapse All' : 'Expand All';
    }
    if (toggleIcon) {
        toggleIcon.className = allExpanded ? 'fas fa-angles-up text-slate-400 text-xs' : 'fas fa-angles-down text-slate-400 text-xs';
    }
}

// Clipboard Helper Function for Grab Express / Maxim Dispatch
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

// Real-time Client-side Search Filtering
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('orderSearchInput');
    const orderCards = document.querySelectorAll('.order-card');
    const noSearchResults = document.getElementById('noSearchResults');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            orderCards.forEach(function (card) {
                const searchData = card.getAttribute('data-search') || '';
                if (!query || searchData.includes(query)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (noSearchResults) {
                if (visibleCount === 0 && orderCards.length > 0 && query !== '') {
                    noSearchResults.classList.remove('hidden');
                } else {
                    noSearchResults.classList.add('hidden');
                }
            }
        });
    }
});
</script>
@endsection
