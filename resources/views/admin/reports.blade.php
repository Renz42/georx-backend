@extends('admin.layouts.app')

@section('content')
    {{-- Page Header --}}
    <div class="rounded-3xl p-8 sm:p-10 mb-8 shadow-lg text-white relative overflow-hidden" style="background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #059669 100%);">
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-48 h-48 bg-white rounded-full translate-y-1/2 -translate-x-1/4"></div>
        </div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <p class="text-emerald-300 text-sm font-bold uppercase tracking-wider mb-1">GEORX Admin</p>
                <h2 class="text-3xl sm:text-4xl font-black tracking-tight">System Reports</h2>
                <p class="text-emerald-200 mt-2 font-medium">Lightweight overview of platform health and activity.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <div class="bg-white/15 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-white text-sm font-bold shadow-sm flex items-center">
                    <i class="far fa-calendar-alt mr-2 opacity-80"></i> {{ now()->format('F d, Y') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        @php
            $summaryCards = [
                ['label' => 'Total Users',       'value' => number_format($totalUsers),       'icon' => 'fas fa-users',           'color' => 'indigo'],
                ['label' => 'Active Pharmacies',  'value' => number_format($totalPharmacies),  'icon' => 'fas fa-clinic-medical',  'color' => 'teal'],
                ['label' => 'Total Medicines',    'value' => number_format($totalMedicines),   'icon' => 'fas fa-pills',           'color' => 'violet'],
                ['label' => 'Pending Orders',     'value' => number_format($pendingOrders),    'icon' => 'fas fa-clock',           'color' => 'amber'],
                ['label' => 'Completed Orders',   'value' => number_format($completedOrders),  'icon' => 'fas fa-check-double',    'color' => 'emerald'],
            ];
        @endphp
        @foreach($summaryCards as $card)
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest leading-tight">{{ $card['label'] }}</p>
                <div class="bg-{{ $card['color'] }}-50 text-{{ $card['color'] }}-600 p-2.5 rounded-xl">
                    <i class="{{ $card['icon'] }} text-base"></i>
                </div>
            </div>
            <p class="text-3xl font-black text-slate-800 tracking-tight">{{ $card['value'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

        {{-- Most Ordered Medicines --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-slate-800 text-lg">Most Ordered Medicines</h3>
                    <p class="text-slate-400 text-xs mt-0.5">Top 15 by total quantity ordered</p>
                </div>
                <div class="bg-violet-50 text-violet-600 p-3 rounded-xl">
                    <i class="fas fa-fire text-lg"></i>
                </div>
            </div>
            <div class="overflow-x-auto">
                @if($mostOrdered->isEmpty())
                    <div class="px-6 py-10 text-center text-slate-400">
                        <i class="fas fa-box-open text-2xl mb-2 block"></i>
                        <p class="text-sm font-medium">No orders recorded yet.</p>
                    </div>
                @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">#</th>
                            <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Medicine</th>
                            <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Qty Sold</th>
                            <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Orders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($mostOrdered as $index => $item)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5 font-black text-slate-400 text-xs">{{ $index + 1 }}</td>
                            <td class="px-5 py-3.5">
                                <p class="font-bold text-slate-800 leading-tight">{{ $item->medicine->brand_name ?? '—' }}</p>
                                <p class="text-xs text-slate-400 font-medium">{{ $item->medicine->generic_name ?? '' }}</p>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="font-black text-violet-700 bg-violet-50 px-2.5 py-1 rounded-lg text-xs">{{ number_format($item->total_qty) }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <span class="text-xs font-bold text-slate-500">{{ number_format($item->order_count) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        {{-- Low Stock Medicines --}}
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-slate-800 text-lg">Low Stock Medicines</h3>
                    <p class="text-slate-400 text-xs mt-0.5">
                        Quantity ≤ 10 across all pharmacies
                        @if($outOfStockCount > 0)
                            <span class="ml-2 bg-rose-100 text-rose-700 font-bold px-2 py-0.5 rounded-full">{{ $outOfStockCount }} out of stock</span>
                        @endif
                    </p>
                </div>
                <div class="bg-amber-50 text-amber-600 p-3 rounded-xl">
                    <i class="fas fa-exclamation-triangle text-lg"></i>
                </div>
            </div>
            <div class="overflow-x-auto">
                @if($lowStockMedicines->isEmpty())
                    <div class="px-6 py-10 text-center text-slate-400">
                        <i class="fas fa-check-circle text-2xl mb-2 block text-emerald-400"></i>
                        <p class="text-sm font-medium">All medicines are well-stocked!</p>
                    </div>
                @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Medicine</th>
                            <th class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-widest text-slate-400">Pharmacy</th>
                            <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Stock</th>
                            <th class="px-5 py-3 text-right text-[10px] font-black uppercase tracking-widest text-slate-400">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($lowStockMedicines as $item)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="font-bold text-slate-800 leading-tight">{{ $item->brand_name ?: $item->generic_name }}</p>
                                @if($item->brand_name)
                                    <p class="text-xs text-slate-400 font-medium">{{ $item->generic_name }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 text-slate-600 font-medium text-xs">{{ $item->pharmacy_name }}</td>
                            <td class="px-5 py-3.5 text-right">
                                @php $qty = $item->quantity_on_hand; @endphp
                                <span class="font-black text-xs px-2.5 py-1 rounded-lg {{ $qty <= 3 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $qty }} left
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right text-xs font-bold text-slate-600">
                                ₱{{ number_format($item->selling_price, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

    </div>

    {{-- Quick Links --}}
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
        <h3 class="font-black text-slate-800 mb-4">Quick Actions</h3>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-sm rounded-xl transition-colors">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="{{ route('admin.pharmacies') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-teal-50 hover:bg-teal-100 text-teal-700 font-bold text-sm rounded-xl transition-colors">
                <i class="fas fa-clinic-medical"></i> Pharmacies
            </a>
            <a href="{{ route('admin.medicines') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-violet-50 hover:bg-violet-100 text-violet-700 font-bold text-sm rounded-xl transition-colors">
                <i class="fas fa-pills"></i> Medicines
            </a>
            <a href="{{ route('admin.users') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-sm rounded-xl transition-colors">
                <i class="fas fa-users"></i> Users
            </a>
        </div>
    </div>
@endsection
