@extends('pharmacy.layouts.app')
@section('title', 'My Inventory')

@push('styles')
<style>
    /* Defense-Ready: Tooltip System */
    .field-tooltip { position: relative; display: inline-flex; align-items: center; }
    .field-tooltip .tooltip-content {
        visibility: hidden; opacity: 0;
        position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%);
        background: #1e293b; color: #f1f5f9;
        padding: 8px 14px; border-radius: 10px; font-size: 11px; font-weight: 600;
        white-space: nowrap; z-index: 50; pointer-events: none;
        transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.2s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .field-tooltip .tooltip-content::after {
        content: ''; position: absolute; top: 100%; left: 50%; transform: translateX(-50%);
        border: 5px solid transparent; border-top-color: #1e293b;
    }
    .field-tooltip:hover .tooltip-content,
    .field-tooltip:focus-within .tooltip-content {
        visibility: visible; opacity: 1; transform: translateX(-50%) translateY(-2px);
    }
    /* Defense-Ready: Margin Widget */
    .margin-widget { transition: all 0.3s ease; }
    .margin-widget:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    /* Pulse for expiring-soon indicator */
    @keyframes gentle-pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
    .pulse-dot { animation: gentle-pulse 2s ease-in-out infinite; }
</style>
@endpush

@section('content')
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

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
        <div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight">My Inventory</h2>
            <p class="text-slate-500 mt-1">Manage your medicine stock, prices, and batches.</p>
        </div>
        @if(!Auth::user()->isPharmacyStaff())
        <button onclick="openAddModal()"
           class="text-white px-6 py-3 rounded-xl font-bold flex items-center gap-2 transition hover:brightness-110 shadow-lg hover:-translate-y-0.5"
           style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}; shadow-color: {{ $pharmacy->theme_color ?? '#2563eb' }}66;">
            <i class="fas fa-plus"></i>Add Stock
        </button>
        @endif
    </div>

    <!-- ✨ KPI SUMMARY CARDS ✨ -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-[2rem] p-6 border border-slate-200 shadow-sm flex items-center gap-5 hover:shadow-md transition-shadow">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0" style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}15; color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                <i class="fas fa-boxes text-2xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-bold uppercase tracking-widest mb-1">Total Stock</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight">{{ $expiryCounts['all'] }} <span class="text-lg text-slate-400 font-medium tracking-normal">items</span></h3>
            </div>
        </div>
        <div class="bg-white rounded-[2rem] p-6 border border-slate-200 shadow-sm flex items-center gap-5 hover:shadow-md transition-shadow">
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i class="fas fa-check-circle text-2xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-bold uppercase tracking-widest mb-1">Valid</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight">{{ $expiryCounts['valid'] }} <span class="text-lg text-slate-400 font-medium tracking-normal">items</span></h3>
            </div>
        </div>
        <div class="bg-white rounded-[2rem] p-6 border border-slate-200 shadow-sm flex items-center gap-5 hover:shadow-md transition-shadow">
            <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <i class="fas fa-exclamation-triangle text-2xl"></i>
            </div>
            <div>
                <p class="text-slate-500 text-sm font-bold uppercase tracking-widest mb-1">Action Needed</p>
                <h3 class="text-3xl font-black text-slate-800 tracking-tight">{{ $expiryCounts['expiring'] + $expiryCounts['expired'] }} <span class="text-lg text-slate-400 font-medium tracking-normal">items</span></h3>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">

        <!-- ✨ EXPIRY TABS + SEARCH BAR ROW ✨ -->
        <div class="bg-slate-50/80 border-b border-slate-200 px-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Expiry Filter Tabs (Left) -->
                <nav class="flex gap-2 overflow-x-auto" aria-label="Inventory expiry filter tabs">
                    @php
                        $tabs = [
                            'all' => ['label' => 'All Stock', 'icon' => 'fas fa-boxes', 'color' => 'blue', 'count' => $expiryCounts['all']],
                            'valid' => ['label' => 'Valid', 'icon' => 'fas fa-check-circle', 'color' => 'emerald', 'count' => $expiryCounts['valid']],
                            'expiring' => ['label' => 'Expiring Soon', 'icon' => 'fas fa-clock', 'color' => 'amber', 'count' => $expiryCounts['expiring']],
                            'expired' => ['label' => 'Expired', 'icon' => 'fas fa-skull-crossbones', 'color' => 'rose', 'count' => $expiryCounts['expired']],
                            'low_stock' => ['label' => 'Low Stock', 'icon' => 'fas fa-arrow-down', 'color' => 'orange', 'count' => $stockCounts['low_stock']],
                            'out_of_stock' => ['label' => 'Out of Stock', 'icon' => 'fas fa-times-circle', 'color' => 'red', 'count' => $stockCounts['out_of_stock']],
                        ];
                    @endphp
                    @foreach($tabs as $key => $tab)
                        <a href="{{ route('pharmacy.inventory', array_merge(request()->only(['search']), ['filter' => $key])) }}"
                           class="py-5 px-4 border-b-4 text-xs font-black uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap
                                  {{ $filter === $key 
                                      ? 'border-' . $tab['color'] . '-600 text-' . $tab['color'] . '-600' 
                                      : 'border-transparent text-slate-400 hover:text-slate-600 hover:border-slate-300' }}">
                            <i class="{{ $tab['icon'] }} text-[10px]"></i>
                            {{ $tab['label'] }}
                            <span class="ml-1 px-2 py-0.5 rounded-full text-[9px] font-black
                                {{ $filter === $key
                                    ? 'bg-' . $tab['color'] . '-100 text-' . $tab['color'] . '-700'
                                    : 'bg-slate-100 text-slate-500' }}">
                                {{ $tab['count'] }}
                                @if($key === 'expired' && $tab['count'] > 0)
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-rose-500 ml-1 pulse-dot"></span>
                                @endif
                            </span>
                        </a>
                    @endforeach
                </nav>

                <!-- ✨ SEARCH FORM (Right) ✨ -->
                <form method="GET" action="{{ route('pharmacy.inventory') }}" class="flex items-center gap-3 py-4 lg:py-0" id="inventory-filter-form">
                    <input type="hidden" name="filter" value="{{ $filter }}">

                    <div class="relative">
                        <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ $search }}" 
                            placeholder="Search medicine or batch..." 
                            class="pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 w-56 transition shadow-sm"
                            id="inventory-search-input"
                        >
                    </div>

                    <button type="submit" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black uppercase tracking-widest rounded-xl transition shadow-lg shadow-blue-200 hover:-translate-y-0.5">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>

                    @if(!empty($search))
                        <a href="{{ route('pharmacy.inventory', ['filter' => $filter]) }}" class="px-4 py-2.5 bg-white hover:bg-slate-50 text-slate-500 border border-slate-200 text-xs font-black uppercase tracking-widest rounded-xl transition shadow-sm hover:-translate-y-0.5">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    @endif
                </form>
            </div>
        </div>

        <!-- Expired Alert Banner (shown only on the expired tab with items) -->
        @if($filter === 'expired' && $expiryCounts['expired'] > 0)
            <div class="mx-6 mt-6 p-4 bg-rose-50 border border-rose-200 rounded-2xl flex items-start gap-3">
                <div class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-exclamation-circle text-rose-600"></i>
                </div>
                <div>
                    <p class="font-black text-rose-700 text-sm">{{ $expiryCounts['expired'] }} Expired Item(s) Detected</p>
                    <p class="text-xs text-rose-500 mt-0.5 font-medium">These medicines have passed their expiration date and should be removed from sale immediately per FDA guidelines.</p>
                </div>
            </div>
        @endif
        @if($filter === 'expiring' && $expiryCounts['expiring'] > 0)
            <div class="mx-6 mt-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-start gap-3">
                <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-clock text-amber-600"></i>
                </div>
                <div>
                    <p class="font-black text-amber-700 text-sm">{{ $expiryCounts['expiring'] }} Item(s) Expiring Within 90 Days</p>
                    <p class="text-xs text-amber-500 mt-0.5 font-medium">Consider running promotions or returning these items to your supplier before they expire.</p>
                </div>
            </div>
        @endif

        @if($filter === 'low_stock' && $stockCounts['low_stock'] > 0)
            <div class="mx-6 mt-6 p-4 bg-orange-50 border border-orange-200 rounded-2xl flex items-start gap-3">
                <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-exclamation-triangle text-orange-600"></i>
                </div>
                <div>
                    <p class="font-black text-orange-700 text-sm">{{ $stockCounts['low_stock'] }} Item(s) Low on Stock</p>
                    <p class="text-xs text-orange-500 mt-0.5 font-medium">These medicines have hit their reorder threshold. Restock soon to avoid running out.</p>
                </div>
            </div>
        @endif

        @if($filter === 'out_of_stock' && $stockCounts['out_of_stock'] > 0)
            <div class="mx-6 mt-6 p-4 bg-red-50 border border-red-200 rounded-2xl flex items-start gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-times-circle text-red-600"></i>
                </div>
                <div>
                    <p class="font-black text-red-700 text-sm">{{ $stockCounts['out_of_stock'] }} Item(s) Out of Stock</p>
                    <p class="text-xs text-red-500 mt-0.5 font-medium">These medicines are currently unavailable for purchase.</p>
                </div>
            </div>
        @endif

        <div class="overflow-x-auto p-2">
            <table class="w-full text-left border-collapse" role="table" aria-label="Pharmacy inventory list">
                <thead class="bg-slate-50/80 rounded-t-xl">
                    <tr>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Medicine Info</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Pricing</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Inventory</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">Batch & Expiry</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center border-b border-slate-200">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($inventory as $item)
                        @php
                            $stock = $item->pivot->quantity_on_hand ?? 0;
                            $reorder = $item->pivot->reorder_level ?? 10;
                            $expDate = $item->pivot->expiration_date;
                            $isExpired = $expDate && \Carbon\Carbon::parse($expDate)->isPast();
                            $daysToExpiry = $expDate ? (int) \Carbon\Carbon::now()->diffInDays(\Carbon\Carbon::parse($expDate), false) : null;
                            $isExpiringSoon = !$isExpired && $daysToExpiry !== null && $daysToExpiry >= 0 && $daysToExpiry <= 90;
                            $storageCondition = $item->pivot->storage_condition ?? null;
                            $costPrice = $item->pivot->purchase_price ?? 0;
                            $sellingPrice = $item->pivot->selling_price;
                            $margin = ($sellingPrice > 0 && $costPrice > 0) ? round((($sellingPrice - $costPrice) / $sellingPrice) * 100, 1) : null;
                            $watchers = \App\Models\StockAlert::where('pharmacy_id', $pharmacy->id)
                                          ->where('medicine_id', $item->id)
                                          ->where('is_active', true)
                                          ->count();
                        @endphp
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-5">
                                <div class="font-bold text-slate-800">{{ $item->brand_name ? $item->brand_name . ' (' . $item->generic_name . ')' : $item->generic_name }}</div>
                                <div class="text-xs text-slate-500 font-medium mt-0.5">Generic: {{ $item->generic_name }}{{ $item->brand_name ? ' · Brand: '.$item->brand_name : '' }} • {{ $item->dosage_form }}{{ $item->strength ? ' · '.$item->strength : '' }}</div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    @if($item->drug_category)
                                        <span class="text-[9px] px-2 py-0.5 rounded-md inline-block font-black uppercase tracking-wider"
                                             style="color: {{ $pharmacy->theme_color ?? '#2563eb' }}; background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}1A; border: 1px solid {{ $pharmacy->theme_color ?? '#2563eb' }}33;">
                                            {{ $item->drug_category }}
                                        </span>
                                    @endif
                                    @if($item->prescription_required)
                                        <span class="text-[9px] bg-violet-50 text-violet-600 border border-violet-200 px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider" title="Prescription Required">Rx</span>
                                    @else
                                        <span class="text-[9px] bg-teal-50 text-teal-600 border border-teal-200 px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider" title="Over-the-Counter">OTC</span>
                                    @endif
                                    @if($item->controlled_substance_flag)
                                        <span class="text-[9px] bg-rose-50 text-rose-600 border border-rose-200 px-1.5 py-0.5 rounded-md font-black uppercase tracking-wider" title="Controlled Substance"><i class="fas fa-shield-alt text-[7px]"></i> Controlled</span>
                                    @endif
                                </div>
                                @if($item->fda_registration_no)
                                    <div class="text-[9px] text-slate-400 mt-1 flex items-center gap-1" title="FDA Registration Number">
                                        <i class="fas fa-certificate text-[8px]"></i>
                                        FDA: {{ $item->fda_registration_no }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <div class="font-black text-lg" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};">₱{{ number_format($sellingPrice, 2) }}</div>
                                <div class="text-[10px] font-bold text-slate-400 mt-0.5">Cost: ₱{{ number_format($costPrice, 2) }}</div>
                                @if($margin !== null)
                                    <div class="mt-1.5">
                                        <span class="text-[9px] px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1
                                            {{ $margin >= 30 ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : ($margin >= 15 ? 'bg-amber-50 text-amber-600 border border-amber-200' : 'bg-rose-50 text-rose-600 border border-rose-200') }}">
                                            <i class="fas fa-chart-line text-[8px]"></i>
                                            {{ $margin }}% margin
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-xl font-black {{ $stock <= 0 ? 'text-red-600' : ($stock <= $reorder ? 'text-orange-600' : 'text-slate-800') }}">{{ $stock }}</span>
                                    <span class="text-[10px] text-slate-400 uppercase font-black tracking-widest">{{ $item->pivot->unit ?? 'Piece / Tablet' }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if($stock <= 0)
                                        <span class="text-[9px] bg-red-50 text-red-600 border border-red-200 px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1">
                                            <i class="fas fa-times-circle text-[7px]"></i> Out of Stock
                                        </span>
                                    @elseif($stock <= $reorder)
                                        <span class="text-[9px] bg-orange-50 text-orange-600 border border-orange-200 px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1">
                                            <i class="fas fa-arrow-down text-[7px]"></i> Low Stock
                                        </span>
                                    @else
                                        <span class="text-[9px] bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1">
                                            <i class="fas fa-check text-[7px]"></i> Available
                                        </span>
                                    @endif
                                </div>
                                @if($stock <= 0 && $watchers > 0)
                                    <div class="mt-2 flex items-center gap-1.5 text-rose-600 bg-rose-50 px-2 py-1 rounded-lg border border-rose-100 inline-flex w-auto">
                                        <i class="fas fa-eye text-[10px] animate-pulse"></i>
                                        <span class="text-[10px] font-black uppercase tracking-widest">{{ $watchers }} {{ $watchers === 1 ? 'Customer' : 'Customers' }} Waiting</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <div class="text-xs font-mono font-bold text-slate-600 mb-1">#{{ $item->pivot->batch_number ?? 'NO-BATCH' }}</div>
                                <div class="text-[10px] font-bold uppercase tracking-wider {{ $isExpired ? 'text-rose-600' : ($isExpiringSoon ? 'text-amber-600' : 'text-slate-400') }}">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    {{ $expDate ? \Carbon\Carbon::parse($expDate)->format('M d, Y') : 'No Expiry' }}
                                </div>
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @if($isExpired)
                                        <span class="text-[9px] bg-rose-50 text-rose-600 border border-rose-200 px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 shrink-0"></span> Expired
                                        </span>
                                    @elseif($isExpiringSoon)
                                        <span class="text-[9px] bg-amber-50 text-amber-600 border border-amber-200 px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0 pulse-dot"></span> {{ $daysToExpiry }}d left
                                        </span>
                                    @elseif($expDate)
                                        <span class="text-[9px] bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-0.5 rounded-full font-black uppercase tracking-wider inline-flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span> Valid
                                        </span>
                                    @endif
                                </div>
                                @if($storageCondition)
                                    <div class="text-[9px] text-slate-400 mt-1.5 flex items-center gap-1" title="Storage Requirement">
                                        <i class="fas fa-temperature-low text-[8px]"></i>
                                        {{ $storageCondition }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-5 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick="openEditModal({{ json_encode([
                                        'id' => $item->id,
                                        'name' => $item->generic_name,
                                        'selling_price' => $item->pivot->selling_price,
                                        'purchase_price' => $item->pivot->purchase_price,
                                        'quantity' => $stock,
                                        'unit' => $item->pivot->unit,
                                        'reorder' => $reorder,
                                        'batch' => $item->pivot->batch_number,
                                        'expiration' => $expDate,
                                        'storage' => $item->pivot->storage_condition,
                                        'supplier' => $item->pivot->supplier,
                                        'last_restocked_at' => $item->pivot->last_restocked_at
                                    ]) }})" class="p-2 hover:bg-slate-100 rounded-lg transition-colors" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};" aria-label="Edit Item"><i class="fas fa-edit"></i></button>
                                    
                                    @if(!Auth::user()->isPharmacyStaff())
                                    <form action="{{ route('pharmacy.inventory.remove', $item->id) }}" method="POST" onsubmit="return confirm('Remove this from inventory?');">
                                        @csrf @method('DELETE')
                                        <button aria-label="Delete Item" class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"><i class="fas fa-trash"></i></button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center">
                                <div class="bg-slate-50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100 shadow-inner">
                                    @if(!empty($search))
                                        <i class="fas fa-search text-3xl text-slate-300"></i>
                                    @elseif($filter === 'expired')
                                        <i class="fas fa-check-circle text-3xl text-emerald-400"></i>
                                    @elseif($filter === 'expiring')
                                        <i class="fas fa-shield-alt text-3xl text-blue-400"></i>
                                    @elseif($filter === 'low_stock')
                                        <i class="fas fa-arrow-up text-3xl text-emerald-400"></i>
                                    @elseif($filter === 'out_of_stock')
                                        <i class="fas fa-boxes text-3xl text-emerald-400"></i>
                                    @else
                                        <i class="fas fa-box-open text-3xl text-slate-300"></i>
                                    @endif
                                </div>
                                @if(!empty($search))
                                    <p class="font-black text-lg text-slate-600 tracking-tight">No results for "{{ $search }}"</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">Try a different medicine name, brand, or batch number.</p>
                                @elseif($filter === 'expired')
                                    <p class="font-black text-lg text-emerald-600 tracking-tight">No expired items</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">All your inventory items have valid expiration dates.</p>
                                @elseif($filter === 'expiring')
                                    <p class="font-black text-lg text-blue-600 tracking-tight">No items expiring soon</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">None of your medicines are expiring within the next 90 days.</p>
                                @elseif($filter === 'low_stock')
                                    <p class="font-black text-lg text-emerald-600 tracking-tight">Stock levels are healthy</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">None of your medicines are running low.</p>
                                @elseif($filter === 'out_of_stock')
                                    <p class="font-black text-lg text-emerald-600 tracking-tight">No out of stock items</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">All your inventory is currently in stock.</p>
                                @elseif($filter === 'valid')
                                    <p class="font-black text-lg text-slate-600 tracking-tight">No valid items found</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">Check your expiring or expired tabs for items that need attention.</p>
                                @else
                                    <p class="font-black text-lg text-slate-600 tracking-tight">Your inventory is empty</p>
                                    <p class="text-sm text-slate-400 mt-1 font-medium">Click "Add Stock" to start listing medicines.</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- ✨ PAGINATION ✨ -->
        @if($inventory->hasPages())
            <div class="px-8 py-6 bg-slate-50/60 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <!-- Results Summary -->
                    <p class="text-sm font-semibold text-slate-500">
                        Showing <span class="font-black text-slate-700">{{ $inventory->firstItem() }}</span> 
                        to <span class="font-black text-slate-700">{{ $inventory->lastItem() }}</span> 
                        of <span class="font-black text-slate-700">{{ $inventory->total() }}</span> items
                    </p>

                    <!-- Pagination Links -->
                    <nav class="flex items-center gap-1.5">
                        {{-- Previous --}}
                        @if($inventory->onFirstPage())
                            <span class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 text-slate-300 cursor-not-allowed">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </span>
                        @else
                            <a href="{{ $inventory->previousPageUrl() }}" 
                               class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 transition shadow-sm">
                                <i class="fas fa-chevron-left text-xs"></i>
                            </a>
                        @endif

                        {{-- Page Numbers --}}
                        @foreach($inventory->getUrlRange(1, $inventory->lastPage()) as $page => $url)
                            @if($page == $inventory->currentPage())
                                <span class="w-10 h-10 flex items-center justify-center rounded-xl bg-blue-600 text-white text-sm font-black shadow-lg shadow-blue-200">
                                    {{ $page }}
                                </span>
                            @else
                                <a href="{{ $url }}" 
                                   class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 text-sm font-bold transition shadow-sm">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach

                        {{-- Next --}}
                        @if($inventory->hasMorePages())
                            <a href="{{ $inventory->nextPageUrl() }}" 
                               class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 text-slate-600 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-600 transition shadow-sm">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </a>
                        @else
                            <span class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-100 text-slate-300 cursor-not-allowed">
                                <i class="fas fa-chevron-right text-xs"></i>
                            </span>
                        @endif
                    </nav>
                </div>
            </div>
        @endif
    </div>

    <div id="addInventoryModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4" role="dialog" aria-modal="true" aria-labelledby="addModalTitle">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 shrink-0">
                <h3 id="addModalTitle" class="text-xl font-black text-slate-800 tracking-tight">Add to Inventory</h3>
                <button aria-label="Close" onclick="document.getElementById('addInventoryModal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors flex items-center justify-center shadow-sm">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="flex border-b border-slate-200 px-8 pt-4 shrink-0 bg-slate-50/50">
                <button onclick="switchAddTab('existing')" id="btn-tab-existing" class="px-6 py-3 font-bold text-sm border-b-4 transition-colors"
                        style="color: {{ $pharmacy->theme_color ?? '#2563eb' }}; border-bottom-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                    <i class="fas fa-search mr-1.5"></i> Select Existing
                </button>
                <button onclick="switchAddTab('new')" id="btn-tab-new" class="px-6 py-3 font-bold text-sm text-slate-400 border-b-4 border-transparent hover:text-slate-600 transition-colors">
                    <i class="fas fa-plus-circle mr-1.5"></i> Create New Medicine
                </button>
            </div>

            <div class="overflow-y-auto p-6 sm:p-8 custom-scrollbar overscroll-contain">
                
                <form id="form-existing" method="POST" action="{{ route('pharmacy.inventory.add') }}" class="block">
                    @csrf
                    
                    <div class="bg-blue-50 border border-blue-100 text-blue-700 text-sm font-medium px-5 py-4 rounded-2xl mb-8 flex items-start gap-3 shadow-sm">
                        <i class="fas fa-info-circle mt-0.5 text-blue-500 text-lg"></i>
                        <p>Select a medicine from the global database, then set <strong>your pharmacy's specific price and stock levels</strong> below.</p>
                    </div>

                    <div class="space-y-6">
                        <div class="bg-slate-50 p-5 rounded-2xl border border-slate-100">
                            <label for="existing_medicine_id" class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">1. Select Medicine *</label>
                            <select name="medicine_id" id="existing_medicine_id" required aria-required="true" class="w-full px-4 py-3.5 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-bold text-slate-700 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                <option value="">-- Search Master List --</option>
                                @foreach($medicines as $medicine)
                                    <option value="{{ $medicine->id }}">{{ $medicine->generic_name }} ({{ $medicine->brand_name ?? 'Generic' }})</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div class="sm:col-span-2">
                                <p class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">2. Your Pricing</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="relative">
                                        <label for="existing_selling" class="block text-xs font-bold text-slate-600 mb-1.5">Selling Price *</label>
                                        <div class="absolute left-4 top-[30px] text-slate-400 font-bold">₱</div>
                                        <input type="number" step="0.01" inputmode="decimal" name="selling_price" id="existing_selling" required aria-required="true" placeholder="0.00" class="w-full pl-8 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-black text-slate-800 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                        <p class="text-[9px] text-slate-400 mt-1 font-medium">Your store's retail price</p>
                                    </div>
                                    <div class="relative">
                                        <label for="existing_cost" class="block text-xs font-bold text-slate-600 mb-1.5">Cost Price</label>
                                        <div class="absolute left-4 top-[30px] text-slate-400 font-bold">₱</div>
                                        <input type="number" step="0.01" inputmode="decimal" name="purchase_price" id="existing_cost" placeholder="0.00" class="w-full pl-8 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-600 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                        <p class="text-[9px] text-slate-400 mt-1 font-medium">For your profit reports</p>
                                    </div>
                                </div>
                                {{-- X-Factor: Live Profit Margin Preview --}}
                                <div class="mt-3 p-3.5 rounded-xl bg-gradient-to-r from-slate-50 to-blue-50/30 border border-slate-100 flex items-center justify-between margin-widget">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center shadow-sm">
                                            <i class="fas fa-percentage text-xs text-slate-400"></i>
                                        </div>
                                        <div>
                                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Profit Margin</p>
                                            <p class="text-[9px] text-slate-400">Auto-calculated</p>
                                        </div>
                                    </div>
                                    <span id="margin_preview_existing" class="text-xl font-black text-slate-300" aria-live="polite">—</span>
                                </div>
                            </div>

                            <div class="sm:col-span-2 mt-2">
                                <p class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">3. Stock & Tracking</p>
                                
                                {{-- Box to Piece Auto-Converter (Edit) --}}
                                <div class="bg-blue-50/50 border border-blue-100 rounded-2xl p-4 mb-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <input type="checkbox" id="edit_convert" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 cursor-pointer" onchange="toggleConverter('edit')">
                                        <label for="edit_convert" class="text-sm font-bold text-slate-700 cursor-pointer select-none">Stock by Box, Sell by Piece (Auto-Convert)</label>
                                    </div>
                                    <div id="edit_converter_box" class="hidden grid grid-cols-2 gap-4 pt-3 mt-1 border-t border-blue-100/50">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Number of Boxes</label>
                                            <input type="number" id="edit_boxes" placeholder="e.g. 30" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" oninput="runConverter('edit')">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Pieces per Box</label>
                                            <input type="number" id="edit_pieces" placeholder="e.g. 60" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" oninput="runConverter('edit')">
                                        </div>
                                    </div>
                                </div>
                                
                                {{-- Box to Piece Auto-Converter --}}
                                <div class="bg-blue-50/50 border border-blue-100 rounded-2xl p-4 mb-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <input type="checkbox" id="existing_convert" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500 cursor-pointer" onchange="toggleConverter('existing')">
                                        <label for="existing_convert" class="text-sm font-bold text-slate-700 cursor-pointer select-none">Stock by Box, Sell by Piece (Auto-Convert)</label>
                                    </div>
                                    <div id="existing_converter_box" class="hidden grid grid-cols-2 gap-4 pt-3 mt-1 border-t border-blue-100/50">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Number of Boxes</label>
                                            <input type="number" id="existing_boxes" placeholder="e.g. 30" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" oninput="runConverter('existing')">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Pieces per Box</label>
                                            <input type="number" id="existing_pieces" placeholder="e.g. 60" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" oninput="runConverter('existing')">
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div>
                                        <label for="existing_qty" class="block text-xs font-bold text-slate-600 mb-1.5">Qty on Hand *</label>
                                        <input type="number" name="quantity_on_hand" id="existing_qty" required aria-required="true" placeholder="0" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-black text-slate-800 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    </div>
                                    <div class="col-span-1 sm:col-span-1">
                                        <label for="existing_unit" class="block text-xs font-bold text-slate-600 mb-1.5">Unit Type *</label>
                                        <select name="unit" id="existing_unit" required aria-required="true" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                            <option value="Piece / Tablet">Piece / Tablet</option>
                                            <option value="Box">Box</option>
                                            <option value="Bottle">Bottle</option>
                                            <option value="Sachet">Sachet</option>
                                            <option value="Vial / Ampule">Vial / Ampule</option>
                                            <option value="Tube">Tube</option>
                                        </select>
                                    </div>
                                    <div>
                                        <div class="field-tooltip">
                                            <label for="existing_reorder" class="block text-xs font-bold text-slate-600 mb-1.5 flex items-center gap-1">
                                                Low Alert At
                                                <i class="fas fa-question-circle text-slate-300 text-[10px] cursor-help" tabindex="0"></i>
                                            </label>
                                            <span class="tooltip-content" id="reorder-tooltip-existing" role="tooltip">System alerts when stock drops to this level</span>
                                        </div>
                                        <input type="number" name="reorder_level" id="existing_reorder" value="10" aria-describedby="reorder-tooltip-existing" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-600 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label for="existing_batch" class="block text-xs font-bold text-slate-600 mb-1.5">Batch Number</label>
                                        <input type="text" name="batch_number" id="existing_batch" class="auto-batch w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-mono text-sm shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    </div>
                                    <div>
                                        <label for="existing_exp" class="block text-xs font-bold text-slate-600 mb-1.5">Expiration Date</label>
                                        <input type="date" name="expiration_date" id="existing_exp" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    </div>
                                </div>
                                <div>
                                    <label for="existing_storage" class="block text-xs font-bold text-slate-600 mb-1.5"><i class="fas fa-temperature-low text-slate-400 mr-1 text-[10px]"></i>Storage Condition</label>
                                    <select name="storage_condition" id="existing_storage" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                        <option value="">— Not Specified —</option>
                                        <option value="Room Temperature (15-25°C)">Room Temperature (15-25°C)</option>
                                        <option value="Refrigerated (2-8°C)">Refrigerated (2-8°C)</option>
                                        <option value="Frozen (-20°C)">Frozen (-20°C)</option>
                                        <option value="Cool & Dry Place">Cool & Dry Place</option>
                                        <option value="Protect from Light">Protect from Light</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label for="existing_supplier" class="block text-xs font-bold text-slate-600 mb-1.5">Supplier</label>
                                        <input type="text" name="supplier" id="existing_supplier" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" placeholder="Supplier Name">
                                    </div>
                                    <div>
                                        <label for="existing_last_restocked" class="block text-xs font-bold text-slate-600 mb-1.5">Last Restocked Date</label>
                                        <input type="date" name="last_restocked_at" id="existing_last_restocked" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <button type="submit" class="w-full py-4 text-white rounded-2xl font-black shadow-lg shadow-blue-200 hover:-translate-y-0.5 transition-all text-lg"
                                style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            <i class="fas fa-check-circle mr-2"></i> Add to My Stock
                        </button>
                    </div>
                </form>

                <form id="form-new" method="POST" action="{{ route('pharmacy.inventory.storeNew') }}" enctype="multipart/form-data" class="hidden">
                    @csrf
                    <div class="bg-amber-50 border border-amber-100 text-amber-700 text-xs font-bold px-5 py-4 rounded-2xl mb-8 flex items-start gap-3 shadow-sm">
                        <i class="fas fa-exclamation-triangle mt-0.5 text-amber-500 text-lg shrink-0"></i>
                        <p class="leading-relaxed">Only create a new medicine if it is NOT in the master list. This will permanently add it to the global database for all pharmacies to see.</p>
                    </div>

                    <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100 mb-8">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2"><i class="fas fa-globe text-slate-300"></i> 1. Global Medicine Data</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="new_generic" class="block text-xs font-bold text-slate-600 mb-1.5">Generic Name *</label>
                                <input type="text" name="generic_name" id="new_generic" required aria-required="true" placeholder="e.g. Paracetamol" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-bold shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                            <div>
                                <label for="new_brand" class="block text-xs font-bold text-slate-600 mb-1.5">Brand Name</label>
                                <input type="text" name="brand_name" id="new_brand" placeholder="e.g. Biogesic" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                            <div>
                                <label for="new_category" class="block text-xs font-bold text-slate-600 mb-1.5">Classification</label>
                                <input type="text" name="drug_category" id="new_category" placeholder="e.g. Analgesic" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                            <div>
                                <p class="block text-xs font-bold text-slate-600 mb-1.5">Dosage Form & Strength</p>
                                <div class="flex gap-2">
                                    <input type="text" aria-label="Dosage Form" name="dosage_form" placeholder="Tablet" class="w-1/2 px-3 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    <input type="text" aria-label="Strength" name="strength" placeholder="500mg" class="w-1/2 px-3 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="new_rx" class="block text-xs font-bold text-slate-600 mb-1.5">Prescription Required?</label>
                                <select name="prescription_required" id="new_rx" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-bold text-slate-700 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    <option value="0">No, Over-the-counter (OTC)</option>
                                    <option value="1">Yes, Prescription needed (Rx)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="px-2">
                        <p class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">2. Your Pricing & Stock</p>
                        
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div class="relative">
                                <label for="new_selling" class="block text-xs font-bold text-slate-600 mb-1.5">Selling Price *</label>
                                <div class="absolute left-4 top-[30px] text-slate-400 font-bold">₱</div>
                                <input type="number" step="0.01" inputmode="decimal" name="selling_price" id="new_selling" required aria-required="true" placeholder="0.00" class="w-full pl-8 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-black text-slate-800 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                            <div class="relative">
                                <label for="new_cost" class="block text-xs font-bold text-slate-600 mb-1.5">Cost Price</label>
                                <div class="absolute left-4 top-[30px] text-slate-400 font-bold">₱</div>
                                <input type="number" step="0.01" inputmode="decimal" name="purchase_price" id="new_cost" placeholder="0.00" class="w-full pl-8 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-600 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                        </div>
                        {{-- X-Factor: Live Profit Margin Preview --}}
                        <div class="mb-4 p-3.5 rounded-xl bg-gradient-to-r from-slate-50 to-blue-50/30 border border-slate-100 flex items-center justify-between margin-widget">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center shadow-sm">
                                    <i class="fas fa-percentage text-xs text-slate-400"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Profit Margin</p>
                                    <p class="text-[9px] text-slate-400">Auto-calculated</p>
                                </div>
                            </div>
                            <span id="margin_preview_new" class="text-xl font-black text-slate-300" aria-live="polite">—</span>
                        </div>

                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="new_qty" class="block text-xs font-bold text-slate-600 mb-1.5">Qty on Hand *</label>
                                <input type="number" name="quantity_on_hand" id="new_qty" required aria-required="true" placeholder="0" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-black text-slate-800 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                            <div class="col-span-1 sm:col-span-1">
                                <label for="new_unit" class="block text-xs font-bold text-slate-600 mb-1.5">Unit Type *</label>
                                <select name="unit" id="new_unit" required aria-required="true" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                    <option value="Piece / Tablet">Piece / Tablet</option>
                                    <option value="Box">Box</option>
                                    <option value="Bottle">Bottle</option>
                                    <option value="Sachet">Sachet</option>
                                    <option value="Vial / Ampule">Vial / Ampule</option>
                                    <option value="Tube">Tube</option>
                                </select>
                            </div>
                            <div>
                                <div class="field-tooltip">
                                    <label for="new_reorder" class="block text-xs font-bold text-slate-600 mb-1.5 flex items-center gap-1">
                                        Low Alert At
                                        <i class="fas fa-question-circle text-slate-300 text-[10px] cursor-help" tabindex="0"></i>
                                    </label>
                                    <span class="tooltip-content" id="reorder-tooltip-new" role="tooltip">System alerts when stock drops to this level</span>
                                </div>
                                <input type="number" name="reorder_level" id="new_reorder" value="10" aria-describedby="reorder-tooltip-new" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-600 shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="new_batch" class="block text-xs font-bold text-slate-600 mb-1.5">Batch Number</label>
                                <input type="text" name="batch_number" id="new_batch" class="auto-batch w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none font-mono text-sm shadow-sm transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                            <div>
                                <label for="new_exp" class="block text-xs font-bold text-slate-600 mb-1.5">Expiration Date</label>
                                <input type="date" name="expiration_date" id="new_exp" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                        </div>
                        <div>
                            <label for="new_storage" class="block text-xs font-bold text-slate-600 mb-1.5"><i class="fas fa-temperature-low text-slate-400 mr-1 text-[10px]"></i>Storage Condition</label>
                            <select name="storage_condition" id="new_storage" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                <option value="">— Not Specified —</option>
                                <option value="Room Temperature (15-25°C)">Room Temperature (15-25°C)</option>
                                <option value="Refrigerated (2-8°C)">Refrigerated (2-8°C)</option>
                                <option value="Frozen (-20°C)">Frozen (-20°C)</option>
                                <option value="Cool & Dry Place">Cool & Dry Place</option>
                                <option value="Protect from Light">Protect from Light</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <label for="new_supplier" class="block text-xs font-bold text-slate-600 mb-1.5">Supplier</label>
                                <input type="text" name="supplier" id="new_supplier" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" placeholder="Supplier Name">
                            </div>
                            <div>
                                <label for="new_last_restocked" class="block text-xs font-bold text-slate-600 mb-1.5">Last Restocked Date</label>
                                <input type="date" name="last_restocked_at" id="new_last_restocked" class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium shadow-sm text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <button type="submit" class="w-full py-4 text-white rounded-2xl font-black shadow-lg shadow-blue-200 hover:-translate-y-0.5 transition-all text-lg flex items-center justify-center gap-2"
                                style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            <i class="fas fa-globe"></i> Create Global & Add to Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="editInventoryModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-[100] p-4" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-xl overflow-hidden flex flex-col max-h-[90vh]">
            
            <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-slate-50/50 shrink-0">
                <h3 id="editModalTitle" class="text-xl font-black text-slate-800 tracking-tight">Update: <span id="edit_display_name" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};"></span></h3>
                <button type="button" aria-label="Close" onclick="document.getElementById('editInventoryModal').classList.add('hidden')" class="w-8 h-8 rounded-full bg-white border border-slate-200 text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-colors flex items-center justify-center shadow-sm">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="overflow-y-auto custom-scrollbar overscroll-contain">
                <form method="POST" id="editForm" class="p-8">
                    @csrf @method('PUT')
                    
                    <p class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">1. Pricing Update</p>
                    <div class="grid grid-cols-2 gap-5 mb-4">
                        <div class="relative">
                            <label for="edit_selling" class="block text-xs font-bold text-slate-600 mb-1.5">Selling Price ₱ *</label>
                            <div class="absolute left-4 top-[30px] text-slate-400 font-bold">₱</div>
                            <input type="number" step="0.01" inputmode="decimal" name="selling_price" id="edit_selling" required aria-required="true" class="w-full pl-8 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none font-black text-slate-800 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                        <div class="relative">
                            <label for="edit_purchase" class="block text-xs font-bold text-slate-600 mb-1.5">Cost Price</label>
                            <div class="absolute left-4 top-[30px] text-slate-400 font-bold">₱</div>
                            <input type="number" step="0.01" inputmode="decimal" name="purchase_price" id="edit_purchase" class="w-full pl-8 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                    </div>
                    {{-- X-Factor: Live Profit Margin Preview --}}
                    <div class="mb-6 p-3.5 rounded-xl bg-gradient-to-r from-slate-50 to-blue-50/30 border border-slate-100 flex items-center justify-between margin-widget">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center shadow-sm">
                                <i class="fas fa-percentage text-xs text-slate-400"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Profit Margin</p>
                                <p class="text-[9px] text-slate-400">Auto-calculated</p>
                            </div>
                        </div>
                        <span id="margin_preview_edit" class="text-xl font-black text-slate-300" aria-live="polite">—</span>
                    </div>

                    <p class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">2. Stock Update</p>
                    <div class="grid grid-cols-3 gap-5 mb-6">
                        <div>
                            <label for="edit_qty" class="block text-xs font-bold text-slate-600 mb-1.5">Current Qty *</label>
                            <input type="number" name="quantity_on_hand" id="edit_qty" required aria-required="true" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none font-black text-slate-800 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                        <div class="col-span-1">
                            <label for="edit_unit" class="block text-xs font-bold text-slate-600 mb-1.5">Unit Type *</label>
                            <select name="unit" id="edit_unit" required aria-required="true" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-700 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                <option value="Piece / Tablet">Piece / Tablet</option>
                                <option value="Box">Box</option>
                                <option value="Bottle">Bottle</option>
                                <option value="Sachet">Sachet</option>
                                <option value="Vial / Ampule">Vial / Ampule</option>
                                <option value="Tube">Tube</option>
                            </select>
                        </div>
                        <div>
                            <div class="field-tooltip">
                                <label for="edit_reorder" class="block text-xs font-bold text-slate-600 mb-1.5 flex items-center gap-1">
                                    Low Alert At
                                    <i class="fas fa-question-circle text-slate-300 text-[10px] cursor-help" tabindex="0"></i>
                                </label>
                                <span class="tooltip-content" id="reorder-tooltip-edit" role="tooltip">System alerts when stock drops to this level</span>
                            </div>
                            <input type="number" name="reorder_level" id="edit_reorder" aria-describedby="reorder-tooltip-edit" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none font-bold text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-5 mb-4">
                        <div>
                            <label for="edit_batch" class="block text-xs font-bold text-slate-600 mb-1.5">Batch Number</label>
                            <input type="text" name="batch_number" id="edit_batch" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none font-mono text-sm text-slate-700 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                        <div>
                            <label for="edit_expiration" class="block text-xs font-bold text-slate-600 mb-1.5">Expiration Date</label>
                            <input type="date" name="expiration_date" id="edit_expiration" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                    </div>
                    <div>
                        <label for="edit_storage" class="block text-xs font-bold text-slate-600 mb-1.5"><i class="fas fa-temperature-low text-slate-400 mr-1 text-[10px]"></i>Storage Condition</label>
                        <select name="storage_condition" id="edit_storage" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            <option value="">— Not Specified —</option>
                            <option value="Room Temperature (15-25°C)">Room Temperature (15-25°C)</option>
                            <option value="Refrigerated (2-8°C)">Refrigerated (2-8°C)</option>
                            <option value="Frozen (-20°C)">Frozen (-20°C)</option>
                            <option value="Cool & Dry Place">Cool & Dry Place</option>
                            <option value="Protect from Light">Protect from Light</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-5 mb-4 mt-4">
                        <div>
                            <label for="edit_supplier" class="block text-xs font-bold text-slate-600 mb-1.5">Supplier</label>
                            <input type="text" name="supplier" id="edit_supplier" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};" placeholder="Supplier Name">
                        </div>
                        <div>
                            <label for="edit_last_restocked" class="block text-xs font-bold text-slate-600 mb-1.5">Last Restocked Date</label>
                            <input type="date" name="last_restocked_at" id="edit_last_restocked" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 outline-none text-sm font-medium text-slate-600 transition-shadow" style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <button type="submit" class="w-full py-4 text-white rounded-xl font-black shadow-lg hover:brightness-110 transition"
                                style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}; shadow-color: {{ $pharmacy->theme_color ?? '#2563eb' }}66;">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function generateAutoBatch() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        let randomStr = '';
        for (let i = 0; i < 5; i++) {
            randomStr += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return 'BCH-' + randomStr;
    }

    function openAddModal() {
        const batchInputs = document.querySelectorAll('.auto-batch');
        const newBatch = generateAutoBatch();
        
        batchInputs.forEach(input => {
            input.value = newBatch;
        });

        document.getElementById('addInventoryModal').classList.remove('hidden');
    }

    function switchAddTab(tab) {
        const themeColor = '{{ $pharmacy->theme_color ?? "#2563eb" }}';
        const existingBtn = document.getElementById('btn-tab-existing');
        const newBtn = document.getElementById('btn-tab-new');
        
        if(tab === 'existing') {
            document.getElementById('form-existing').classList.remove('hidden');
            document.getElementById('form-new').classList.add('hidden');
            
            existingBtn.style.color = themeColor;
            existingBtn.style.borderBottomColor = themeColor;
            existingBtn.classList.replace('text-slate-400', 'font-black');
            
            newBtn.style.color = '#94a3b8'; 
            newBtn.style.borderBottomColor = 'transparent';
            newBtn.classList.replace('font-black', 'text-slate-400');
        } else {
            document.getElementById('form-new').classList.remove('hidden');
            document.getElementById('form-existing').classList.add('hidden');
            
            newBtn.style.color = themeColor;
            newBtn.style.borderBottomColor = themeColor;
            newBtn.classList.replace('text-slate-400', 'font-black');
            
            existingBtn.style.color = '#94a3b8'; 
            existingBtn.style.borderBottomColor = 'transparent';
            existingBtn.classList.replace('font-black', 'text-slate-400');
        }
    }

    function openEditModal(data) {
        const modal = document.getElementById('editInventoryModal');
        const form = document.getElementById('editForm');
        
        form.action = `/portal/inventory/${data.id}`;
        document.getElementById('edit_display_name').innerText = data.name;
        document.getElementById('edit_qty').value = data.quantity;
        document.getElementById('edit_reorder').value = data.reorder;
        document.getElementById('edit_selling').value = data.selling_price;
        document.getElementById('edit_batch').value = data.batch || generateAutoBatch();
        
        // Populating all fields safely
        document.getElementById('edit_purchase').value = data.purchase_price || '';
        document.getElementById('edit_unit').value = data.unit || 'Piece / Tablet';
        document.getElementById('edit_storage').value = data.storage || '';
        
        // Format the date properly for the HTML5 date input (YYYY-MM-DD)
        if (data.expiration) {
            document.getElementById('edit_expiration').value = data.expiration.split('T')[0];
        } else {
            document.getElementById('edit_expiration').value = '';
        }
        
        document.getElementById('edit_supplier').value = data.supplier || '';
        if (data.last_restocked_at) {
            document.getElementById('edit_last_restocked').value = data.last_restocked_at.split('T')[0];
        } else {
            document.getElementById('edit_last_restocked').value = '';
        }
        
        // Trigger margin calculation with existing values
        calculateMargin('edit_selling', 'edit_purchase', 'margin_preview_edit');
        
        modal.classList.remove('hidden');
    }

    // =========================================================
    // X-FACTOR: Live Profit Margin Calculator
    // Formula: ((Selling - Cost) / Selling) × 100
    // Color: Green ≥30% | Amber 15-29% | Red <15%
    // =========================================================
    function calculateMargin(sellingId, costId, targetId) {
        const selling = parseFloat(document.getElementById(sellingId).value) || 0;
        const cost = parseFloat(document.getElementById(costId).value) || 0;
        const target = document.getElementById(targetId);

        if (selling > 0 && cost > 0) {
            const margin = ((selling - cost) / selling) * 100;
            target.textContent = margin.toFixed(1) + '%';

            // Color-coded feedback: enterprise pharma standard
            if (margin >= 30) {
                target.className = 'text-xl font-black text-emerald-600';
            } else if (margin >= 15) {
                target.className = 'text-xl font-black text-amber-600';
            } else {
                target.className = 'text-xl font-black text-rose-600';
            }
        } else {
            target.textContent = '—';
            target.className = 'text-xl font-black text-slate-300';
        }
    }

    function setupMarginListeners(sellingId, costId, targetId) {
        const sellingInput = document.getElementById(sellingId);
        const costInput = document.getElementById(costId);
        if (sellingInput && costInput) {
            sellingInput.addEventListener('input', () => calculateMargin(sellingId, costId, targetId));
            costInput.addEventListener('input', () => calculateMargin(sellingId, costId, targetId));
        }
    }

    // Initialize all margin listeners on page load
    document.addEventListener('DOMContentLoaded', function() {
        setupMarginListeners('existing_selling', 'existing_cost', 'margin_preview_existing');
        setupMarginListeners('new_selling', 'new_cost', 'margin_preview_new');
        setupMarginListeners('edit_selling', 'edit_purchase', 'margin_preview_edit');
    });

    // =========================================================
    // Box-to-Piece Auto Converter Logic
    // =========================================================
    function toggleConverter(prefix) {
        const isChecked = document.getElementById(prefix + '_convert').checked;
        const box = document.getElementById(prefix + '_converter_box');
        
        if (isChecked) {
            box.classList.remove('hidden');
        } else {
            box.classList.add('hidden');
            document.getElementById(prefix + '_boxes').value = '';
            document.getElementById(prefix + '_pieces').value = '';
        }
    }

    function runConverter(prefix) {
        const boxes = parseFloat(document.getElementById(prefix + '_boxes').value) || 0;
        const pieces = parseFloat(document.getElementById(prefix + '_pieces').value) || 0;
        
        if (boxes > 0 && pieces > 0) {
            const total = boxes * pieces;
            
            // Set the target quantity input and fire the input event just in case
            const qtyInput = document.getElementById(prefix + '_qty');
            qtyInput.value = total;
            
            // Highlight it briefly so the user sees it change
            qtyInput.classList.add('ring-2', 'ring-emerald-500', 'bg-emerald-50');
            setTimeout(() => {
                qtyInput.classList.remove('ring-2', 'ring-emerald-500', 'bg-emerald-50');
            }, 800);

            // Auto-force the unit type to Piece / Tablet
            const unitSelect = document.getElementById(prefix + '_unit');
            unitSelect.value = 'Piece / Tablet';
        }
    }
</script>
@endpush
