@extends('admin.layouts.app')
@section('title', 'Inventory Audit — ' . $pharmacy->name)

@section('content')
    <!-- Breadcrumb & Governance Header -->
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.pharmacies') }}" class="inline-flex items-center gap-2 text-xs font-bold text-[#2F7E6A] hover:underline">
            <i class="fas fa-arrow-left"></i> Back to Partner Pharmacies
        </a>
        <span class="bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest">
            <i class="fas fa-lock mr-1"></i> Read-Only Super Admin Audit
        </span>
    </div>

    <!-- Pharmacy Overview Banner -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2.5 mb-1.5">
                    <h2 class="text-2xl sm:text-3xl font-black text-[#0F172A] tracking-tight">{{ $pharmacy->name }}</h2>
                    @if($pharmacy->status === 'approved' && $pharmacy->is_active)
                        <span class="bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                            <i class="fas fa-check-circle mr-1"></i> Approved &amp; Active
                        </span>
                    @elseif($pharmacy->status === 'approved' && !$pharmacy->is_active)
                        <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                            <i class="fas fa-moon mr-1"></i> Approved (Store Offline)
                        </span>
                    @elseif($pharmacy->status === 'suspended')
                        <span class="bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                            <i class="fas fa-pause-circle mr-1"></i> Suspended Partner
                        </span>
                    @elseif($pharmacy->status === 'pending')
                        <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                            <i class="fas fa-hourglass-half mr-1"></i> Pending Review
                        </span>
                    @else
                        <span class="bg-slate-100 text-slate-700 border border-slate-300 text-[10px] font-black px-2.5 py-1 rounded-full uppercase tracking-wider">
                            {{ ucfirst($pharmacy->status ?? 'Unknown') }}
                        </span>
                    @endif
                </div>
                <p class="text-sm text-[#64748B] font-medium flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-[#2F7E6A]"></i> {{ $pharmacy->address }}
                </p>
                <div class="flex flex-wrap items-center gap-4 mt-3 text-xs text-[#64748B]">
                    <span><strong class="text-[#0F172A]">Owner:</strong> {{ $pharmacy->owner_name ?: ($ownerAccount->name ?? 'Not Listed') }}</span>
                    <span><strong class="text-[#0F172A]">Contact:</strong> {{ $pharmacy->phone ?: 'N/A' }}</span>
                    <span><strong class="text-[#0F172A]">License / LTO:</strong> {{ $pharmacy->license_number ?: ($pharmacy->lto_number ?: 'N/A') }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                <a href="{{ route('admin.pharmacies.review', $pharmacy) }}" class="bg-slate-100 hover:bg-slate-200 text-[#0F172A] px-4 py-2.5 rounded-xl text-xs font-bold transition-colors border border-slate-200">
                    <i class="fas fa-file-shield mr-1.5 text-[#2F7E6A]"></i> Governance Details
                </a>
                <a href="{{ route('admin.pharmacies.edit', $pharmacy) }}" class="bg-[#2F7E6A] hover:bg-[#256655] text-white px-4 py-2.5 rounded-xl text-xs font-bold transition-colors">
                    <i class="fas fa-edit mr-1.5"></i> Edit Pharmacy Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Inventory Summary KPIs -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Catalog Items Listed</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">{{ $inventorySummary['total_items'] ?? $inventory->total() }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">{{ number_format($inventorySummary['total_units'] ?? 0) }} total units on hand</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Healthy Stock</p>
            <p class="text-2xl font-black text-[#2F7E6A] mt-1">{{ $inventorySummary['in_stock_count'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Above reorder threshold</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-amber-200/80 shadow-xs">
            <p class="text-[10px] font-black text-amber-700 uppercase tracking-widest">Low Stock (&le; Reorder Level)</p>
            <p class="text-2xl font-black text-amber-700 mt-1">{{ $inventorySummary['low_stock_count'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Requires partner restock</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-rose-200/80 shadow-xs">
            <p class="text-[10px] font-black text-rose-700 uppercase tracking-widest">Out of Stock</p>
            <p class="text-2xl font-black text-rose-700 mt-1">{{ $inventorySummary['out_of_stock_count'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">0 units available</p>
        </div>
    </div>

    <!-- Search, Stock Filter & Sort Bar -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-5 mb-6">
        <form method="GET" action="{{ route('admin.pharmacies.inventory', $pharmacy) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
            <div class="lg:col-span-5">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Search Pharmacy Inventory</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B] text-xs"></i>
                    <input type="text" name="search" value="{{ $search ?? request('search') }}"
                           placeholder="Search medicine brand, generic name, category, batch..."
                           class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                </div>
            </div>

            <div class="lg:col-span-3">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Stock Status Filter</label>
                <select name="stock_status" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="all" {{ ($stockStatus ?? 'all') === 'all' ? 'selected' : '' }}>All Inventory Items</option>
                    <option value="in_stock" {{ ($stockStatus ?? '') === 'in_stock' ? 'selected' : '' }}>Healthy Stock (&gt; Reorder Level)</option>
                    <option value="low_stock" {{ ($stockStatus ?? '') === 'low_stock' ? 'selected' : '' }}>Low Stock (&le; Reorder Level)</option>
                    <option value="out_of_stock" {{ ($stockStatus ?? '') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock (0 Units)</option>
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Sort By</label>
                <select name="sort" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="name_asc" {{ ($sort ?? 'name_asc') === 'name_asc' ? 'selected' : '' }}>Generic Name (A–Z)</option>
                    <option value="name_desc" {{ ($sort ?? '') === 'name_desc' ? 'selected' : '' }}>Generic Name (Z–A)</option>
                    <option value="stock_asc" {{ ($sort ?? '') === 'stock_asc' ? 'selected' : '' }}>Lowest Stock First</option>
                    <option value="stock_desc" {{ ($sort ?? '') === 'stock_desc' ? 'selected' : '' }}>Highest Stock First</option>
                    <option value="price_asc" {{ ($sort ?? '') === 'price_asc' ? 'selected' : '' }}>Lowest Price First</option>
                    <option value="price_desc" {{ ($sort ?? '') === 'price_desc' ? 'selected' : '' }}>Highest Price First</option>
                </select>
            </div>

            <div class="lg:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 bg-[#2F7E6A] hover:bg-[#256655] text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">
                    Apply
                </button>
                @if(!empty($search) || ($stockStatus ?? 'all') !== 'all' || ($sort ?? 'name_asc') !== 'name_asc')
                    <a href="{{ route('admin.pharmacies.inventory', $pharmacy) }}" class="bg-slate-100 hover:bg-slate-200 text-[#64748B] font-bold py-2.5 px-3 rounded-xl text-sm transition-colors flex items-center justify-center" title="Reset Filters">
                        <i class="fas fa-undo text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Read-Only Inventory Audit Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-[#0F172A] text-sm uppercase tracking-tight">
                <i class="fas fa-clipboard-check text-[#2F7E6A] mr-2"></i> Audited Branch Inventory
            </h3>
            <span class="text-xs font-bold text-[#64748B]">
                Showing {{ $inventory->firstItem() ?? 0 }}–{{ $inventory->lastItem() ?? 0 }} of {{ $inventory->total() }} items
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-black text-[#64748B] uppercase tracking-widest">
                        <th class="px-6 py-3.5">Medicine</th>
                        <th class="px-6 py-3.5">Category</th>
                        <th class="px-6 py-3.5 text-right">Selling Price</th>
                        <th class="px-6 py-3.5 text-right">Qty on Hand</th>
                        <th class="px-6 py-3.5 text-right">Reorder Level</th>
                        <th class="px-6 py-3.5">Stock Status</th>
                        <th class="px-6 py-3.5">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($inventory as $item)
                        @php
                            $qty = (int) ($item->pivot->quantity_on_hand ?? 0);
                            $reorder = (int) ($item->pivot->reorder_level ?? 10);
                            $sellingPrice = (float) ($item->pivot->selling_price ?? 0);
                            $updatedAt = $item->pivot->updated_at ?? $item->pivot->last_restocked_at;
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-center p-1 shrink-0 overflow-hidden">
                                        @if($item->image_url)
                                            <img src="{{ $item->image_url }}"
                                                 data-original-src="{{ $item->image_url }}"
                                                 @if($item->fallback_image_url) data-fallback-src="{{ $item->fallback_image_url }}" @endif
                                                 data-retry-step="0"
                                                 alt="{{ $item->generic_name }}"
                                                 class="w-full h-full object-contain"
                                                 onerror="this.onerror=null;this.classList.add('hidden');if(this.nextElementSibling){this.nextElementSibling.classList.remove('hidden');}">
                                        @endif
                                        <i class="{{ $item->image_url ? 'hidden' : '' }} fas fa-pills text-slate-300 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-black text-[#0F172A] leading-tight">{{ $item->brand_name ?: $item->generic_name }}</p>
                                        <p class="text-xs text-[#64748B] font-medium mt-0.5">
                                            {{ $item->generic_name }}
                                            @if($item->strength || $item->dosage_form)
                                                &bull; {{ $item->strength }} {{ $item->dosage_form }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-[#0F172A]">
                                    {{ $item->drug_category ?: ($item->therapeutic_class ?: 'General') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-black text-[#0F172A]">
                                ₱{{ number_format($sellingPrice, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right font-black {{ $qty <= 0 ? 'text-rose-600' : ($qty <= $reorder ? 'text-amber-700' : 'text-[#0F172A]') }}">
                                {{ number_format($qty) }} <span class="text-xs font-normal text-[#64748B]">{{ $item->pivot->unit ?? 'units' }}</span>
                            </td>
                            <td class="px-6 py-4 text-right text-xs font-bold text-[#64748B]">
                                {{ number_format($reorder) }}
                            </td>
                            <td class="px-6 py-4">
                                @if($qty <= 0)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                                        <i class="fas fa-times-circle"></i> Out of Stock
                                    </span>
                                @elseif($qty <= $reorder)
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                                        <i class="fas fa-exclamation-triangle"></i> Low Stock
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40">
                                        <i class="fas fa-check-circle"></i> In Stock
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-[#64748B] font-medium">
                                @if($updatedAt)
                                    {{ \Carbon\Carbon::parse($updatedAt)->format('M d, Y') }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-14 text-center">
                                <i class="fas fa-box-open text-3xl text-slate-300 mb-2 block"></i>
                                <p class="font-bold text-[#0F172A] text-base">No inventory records found for this filter</p>
                                <p class="text-xs text-[#64748B] mt-1">This partner pharmacy has no matching items in stock.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inventory->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $inventory->links() }}
            </div>
        @endif
    </div>
@endsection
