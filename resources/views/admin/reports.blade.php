@extends('admin.layouts.app')
@section('title', 'System Reports')

@section('content')
    <!-- Page Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-extrabold text-[#2F7E6A] uppercase tracking-widest mb-1">Reconciled Platform Analytics</p>
                <h2 class="text-2xl sm:text-3xl font-black text-[#0F172A] tracking-tight">System Reports</h2>
                <p class="text-[#64748B] text-sm mt-1 font-medium">Verified completed medicine sales, normalized search demand, and active partner stock alerts.</p>
            </div>
            <div class="bg-[#E9F7F2] px-4 py-2.5 rounded-xl border border-[#63C6A7]/40 text-[#2F7E6A] text-xs font-bold shrink-0 flex items-center gap-2">
                <i class="far fa-calendar-alt"></i> {{ now()->format('F d, Y') }}
            </div>
        </div>
    </div>

    <!-- Reconciled KPI Summary Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Operational Users</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">{{ number_format($totalUsers) }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Excludes archived drivers</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Active Pharmacies</p>
            <p class="text-2xl font-black text-[#2F7E6A] mt-1">{{ number_format($totalPharmacies) }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Approved &amp; online on map</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Completed Orders</p>
            <p class="text-2xl font-black text-[#2F7E6A] mt-1">{{ number_format($completedOrders) }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">{{ number_format($pendingOrders) }} active &bull; {{ number_format($rejectedOrCancelledOrders ?? 0) }} rejected</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Medicine Sales (GMV)</p>
            <p class="text-2xl font-black text-[#2F7E6A] mt-1">₱{{ number_format($medicineSalesGmv ?? 0, 2) }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Delivered merchandise</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Est. Courier Fees</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">₱{{ number_format($estimatedDeliveryFees ?? 0, 2) }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">External courier dispatch</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Total Order Value</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">₱{{ number_format($completedOrderValue ?? 0, 2) }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">GMV + Courier Fees</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Completed Medicine Sales (Delivered Orders Only) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-[#0F172A] text-sm uppercase tracking-tight">Top Completed Medicine Sales</h3>
                    <p class="text-xs text-[#64748B] mt-0.5">Top 15 medicines from verified delivered orders only</p>
                </div>
                <div class="bg-[#E9F7F2] text-[#2F7E6A] w-9 h-9 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-double text-sm"></i>
                </div>
            </div>
            <div class="overflow-x-auto flex-1">
                @if($mostOrdered->isEmpty())
                    <div class="px-6 py-12 text-center text-[#64748B]">
                        <i class="fas fa-box-open text-2xl mb-2 block text-slate-300"></i>
                        <p class="text-sm font-medium">No delivered medicine sales recorded yet.</p>
                    </div>
                @else
                    <table class="w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-[#64748B]">
                                <th class="px-5 py-3">#</th>
                                <th class="px-5 py-3">Medicine</th>
                                <th class="px-5 py-3 text-right">Units Sold</th>
                                <th class="px-5 py-3 text-right">Delivered Orders</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($mostOrdered as $index => $item)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-5 py-3 font-black text-[#64748B] text-xs">{{ $index + 1 }}</td>
                                    <td class="px-5 py-3">
                                        <p class="font-bold text-[#0F172A] leading-tight">{{ $item->medicine->brand_name ?: ($item->medicine->generic_name ?? 'N/A') }}</p>
                                        <p class="text-xs text-[#64748B] font-medium">{{ $item->medicine->generic_name ?? '' }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="font-black text-[#2F7E6A] bg-[#E9F7F2] px-2.5 py-1 rounded-lg text-xs">{{ number_format($item->total_qty) }}</span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <span class="text-xs font-bold text-[#0F172A]">{{ number_format($item->order_count) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <!-- Low Stock Medicines (Unified <= Reorder Level on Active Approved Pharmacies) -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-[#0F172A] text-sm uppercase tracking-tight">Low Stock Alerts ({{ $lowStockCount ?? $lowStockMedicines->count() }})</h3>
                    <p class="text-xs text-[#64748B] mt-0.5">
                        Quantity &le; Reorder Level across active approved pharmacies
                        <span class="ml-2 bg-rose-50 text-rose-700 border border-rose-200 font-bold px-2 py-0.5 rounded-full text-[11px]">{{ $outOfStockCount }} out of stock</span>
                    </p>
                </div>
                <div class="bg-amber-50 text-amber-700 w-9 h-9 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-sm"></i>
                </div>
            </div>
            <div class="overflow-x-auto flex-1">
                @if($lowStockMedicines->isEmpty())
                    <div class="px-6 py-12 text-center text-[#64748B]">
                        <i class="fas fa-check-circle text-2xl mb-2 block text-[#2F7E6A]"></i>
                        <p class="text-sm font-medium">All active partner medicines are stocked above their reorder threshold.</p>
                    </div>
                @else
                    <table class="w-full text-sm text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 text-[10px] font-black uppercase tracking-widest text-[#64748B]">
                                <th class="px-5 py-3">Medicine</th>
                                <th class="px-5 py-3">Partner Pharmacy</th>
                                <th class="px-5 py-3 text-right">Qty / Reorder</th>
                                <th class="px-5 py-3 text-right">Selling Price</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($lowStockMedicines as $item)
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-5 py-3">
                                        <p class="font-bold text-[#0F172A] leading-tight">{{ $item->brand_name ?: $item->generic_name }}</p>
                                        @if($item->brand_name)
                                            <p class="text-xs text-[#64748B] font-medium">{{ $item->generic_name }}</p>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-xs font-bold text-[#2F7E6A]">
                                        @if(!empty($item->pharmacy_id))
                                            <a href="{{ route('admin.pharmacies.inventory', $item->pharmacy_id) }}" class="hover:underline">{{ $item->pharmacy_name }}</a>
                                        @else
                                            {{ $item->pharmacy_name }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @php $qty = (int) $item->quantity_on_hand; $reorder = (int) ($item->reorder_level ?? 10); @endphp
                                        <span class="font-black text-xs px-2.5 py-1 rounded-lg {{ $qty <= 3 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                                            {{ $qty }} / {{ $reorder }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right text-xs font-bold text-[#0F172A]">
                                        ₱{{ number_format((float) $item->selling_price, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <!-- Normalized Search Demand Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden mb-6">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-black text-[#0F172A] text-sm uppercase tracking-tight">Normalized Patient Search Demand</h3>
                <p class="text-xs text-[#64748B] mt-0.5">Case-insensitive aggregated search terms (minimum 3 characters)</p>
            </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-slate-100">
            @forelse($mostSearched ?? [] as $idx => $search)
                <div class="p-4 flex items-center justify-between">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="w-6 h-6 rounded-full bg-[#E9F7F2] text-[#2F7E6A] text-[10px] font-black flex items-center justify-center shrink-0">{{ $idx + 1 }}</span>
                        <span class="text-xs font-bold text-[#0F172A] truncate">{{ $search->term }}</span>
                    </div>
                    <span class="text-xs font-black text-[#2F7E6A] shrink-0">{{ $search->search_count }}&times;</span>
                </div>
            @empty
                <div class="col-span-full p-6 text-center text-xs text-[#64748B]">No search logs available.</div>
            @endforelse
        </div>
    </div>
@endsection
