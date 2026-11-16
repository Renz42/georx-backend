@extends('admin.layouts.app')
@section('title', 'Medicine Master Catalog')

@section('content')
    <!-- Page Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[11px] font-extrabold text-[#2F7E6A] uppercase tracking-widest">Platform Reference Registry</span>
                    <span class="bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">Read-Only Governance View</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-[#0F172A] tracking-tight">Medicine Master Catalog</h2>
                <p class="text-[#64748B] text-sm mt-1 font-medium">Audit all registered medicines across the GEORX network. Operational stock and pricing are managed by each partner pharmacy.</p>
            </div>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.pharmacies') }}" class="bg-slate-100 hover:bg-slate-200 text-[#0F172A] px-4 py-2.5 rounded-xl text-xs font-bold transition-colors flex items-center gap-2 border border-slate-200">
                    <i class="fas fa-store text-[#2F7E6A]"></i> Partner Inventories
                </a>
            </div>
        </div>
    </div>

    <!-- Summary KPI Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Total Catalog Items</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">{{ $catalogStats['total_medicines'] ?? $medicines->total() }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Master medicine records</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">In Stock Across Network</p>
            <p class="text-2xl font-black text-[#2F7E6A] mt-1">{{ $catalogStats['stocked_medicines'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Available in active pharmacies</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Prescription Required</p>
            <p class="text-2xl font-black text-amber-600 mt-1">{{ $catalogStats['rx_medicines'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Regulated Rx medicines</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Drug Categories</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">{{ $catalogStats['categories_count'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Therapeutic classifications</p>
        </div>
    </div>

    <!-- Search, Filter & Sort Controls -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-5 mb-6">
        <form method="GET" action="{{ route('admin.medicines') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
            <div class="lg:col-span-4">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Search Catalog</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B] text-xs"></i>
                    <input type="text" name="search" value="{{ $search ?? request('search') }}"
                           placeholder="Brand, generic name, category, FDA code..."
                           class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#2F7E6A]/20 focus:border-[#2F7E6A]">
                </div>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Drug Category</label>
                <select name="category" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="">All Categories</option>
                    @foreach($categories ?? [] as $cat)
                        <option value="{{ $cat }}" {{ ($category ?? request('category')) === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Dosage Form</label>
                <select name="dosage_form" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="">All Forms</option>
                    @foreach($dosageForms ?? [] as $form)
                        <option value="{{ $form }}" {{ ($dosageForm ?? request('dosage_form')) === $form ? 'selected' : '' }}>{{ $form }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Availability</label>
                <select name="availability" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="all" {{ ($availability ?? 'all') === 'all' ? 'selected' : '' }}>All Items</option>
                    <option value="in_stock" {{ ($availability ?? '') === 'in_stock' ? 'selected' : '' }}>In Stock (Active Stores)</option>
                    <option value="out_of_stock" {{ ($availability ?? '') === 'out_of_stock' ? 'selected' : '' }}>Unstocked / Out of Stock</option>
                    <option value="prescription_required" {{ ($availability ?? '') === 'prescription_required' ? 'selected' : '' }}>Prescription Required (Rx)</option>
                </select>
            </div>

            <div class="lg:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 bg-[#2F7E6A] hover:bg-[#256655] text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors flex items-center justify-center gap-1.5">
                    <i class="fas fa-filter text-xs"></i> Apply
                </button>
                @if(!empty($search) || !empty($category) || !empty($dosageForm) || ($availability ?? 'all') !== 'all' || ($sort ?? 'generic_asc') !== 'generic_asc')
                    <a href="{{ route('admin.medicines') }}" class="bg-slate-100 hover:bg-slate-200 text-[#64748B] font-bold py-2.5 px-3 rounded-xl text-sm transition-colors flex items-center justify-center" title="Reset Filters">
                        <i class="fas fa-undo text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Master Medicine Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <p class="text-xs font-bold text-[#64748B]">
                Showing <span class="text-[#0F172A] font-black">{{ $medicines->firstItem() ?? 0 }}–{{ $medicines->lastItem() ?? 0 }}</span> of <span class="text-[#0F172A] font-black">{{ $medicines->total() }}</span> catalog medicines
            </p>
            <div class="flex items-center gap-2 text-xs">
                <span class="text-[#64748B] font-semibold">Sort by:</span>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'generic_asc']) }}" class="px-2.5 py-1 rounded-lg font-bold {{ ($sort ?? 'generic_asc') === 'generic_asc' ? 'bg-[#E9F7F2] text-[#2F7E6A]' : 'text-[#64748B] hover:bg-slate-100' }}">Generic A–Z</a>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'brand_asc']) }}" class="px-2.5 py-1 rounded-lg font-bold {{ ($sort ?? '') === 'brand_asc' ? 'bg-[#E9F7F2] text-[#2F7E6A]' : 'text-[#64748B] hover:bg-slate-100' }}">Brand A–Z</a>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'newest']) }}" class="px-2.5 py-1 rounded-lg font-bold {{ ($sort ?? '') === 'newest' ? 'bg-[#E9F7F2] text-[#2F7E6A]' : 'text-[#64748B] hover:bg-slate-100' }}">Newest</a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-black text-[#64748B] uppercase tracking-widest">
                        <th class="px-6 py-3.5">Medicine</th>
                        <th class="px-6 py-3.5">Form &amp; Strength</th>
                        <th class="px-6 py-3.5">Category</th>
                        <th class="px-6 py-3.5">Active Network Availability</th>
                        <th class="px-6 py-3.5">Partner Price Range</th>
                        <th class="px-6 py-3.5 text-right">Audit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @forelse($medicines as $medicine)
                        @php
                            $activePharmaciesForMed = $medicine->pharmacies ?? collect();
                            $inStockPharmacies = $activePharmaciesForMed->filter(fn($p) => (int) ($p->pivot->quantity_on_hand ?? 0) > 0);
                            $totalNetworkUnits = (int) $inStockPharmacies->sum(fn($p) => (int) ($p->pivot->quantity_on_hand ?? 0));
                            $prices = $activePharmaciesForMed->pluck('pivot.selling_price')->filter(fn($v) => $v !== null && (float) $v > 0)->map(fn($v) => (float) $v);
                            $minPrice = $prices->min();
                            $maxPrice = $prices->max();

                            $modalPayload = [
                                'id' => $medicine->id,
                                'brand_name' => $medicine->brand_name ?: 'Generic Only',
                                'generic_name' => $medicine->generic_name ?: 'N/A',
                                'strength' => $medicine->strength ?: 'N/A',
                                'dosage_form' => $medicine->dosage_form ?: 'N/A',
                                'drug_category' => $medicine->drug_category ?: ($medicine->therapeutic_class ?: 'General'),
                                'fda_registration_no' => $medicine->fda_registration_no ?: 'Not Specified',
                                'medicine_code' => $medicine->medicine_code ?: '—',
                                'prescription_required' => (bool) $medicine->prescription_required,
                                'description' => $medicine->description ?: ($medicine->primary_use ?: 'No clinical description provided yet.'),
                                'image_url' => $medicine->image_url,
                                'stocking_pharmacies' => $activePharmaciesForMed->map(fn($p) => [
                                    'id' => $p->id,
                                    'name' => $p->name,
                                    'quantity_on_hand' => (int) ($p->pivot->quantity_on_hand ?? 0),
                                    'selling_price' => number_format((float) ($p->pivot->selling_price ?? 0), 2),
                                    'inventory_url' => route('admin.pharmacies.inventory', $p->id),
                                ])->values()->all(),
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3.5">
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-200/80 flex items-center justify-center p-1.5 shrink-0 overflow-hidden">
                                        @if($medicine->image_url)
                                            <img src="{{ $medicine->image_url }}"
                                                 data-original-src="{{ $medicine->image_url }}"
                                                 @if($medicine->fallback_image_url) data-fallback-src="{{ $medicine->fallback_image_url }}" @endif
                                                 data-retry-step="0"
                                                 alt="{{ $medicine->generic_name }}"
                                                 class="w-full h-full object-contain object-center"
                                                 onerror="handleAdminCatalogImageError(this)">
                                        @endif
                                        <i data-image-placeholder class="{{ $medicine->image_url ? 'hidden' : '' }} fas fa-pills text-slate-300 text-lg"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-black text-[#0F172A] leading-tight">{{ $medicine->brand_name ?: $medicine->generic_name }}</p>
                                            @if($medicine->prescription_required)
                                                <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[9px] font-black px-2 py-0.5 rounded-md uppercase">Rx</span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-[#64748B] font-medium mt-0.5">{{ $medicine->generic_name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="font-bold text-[#0F172A] text-xs">{{ $medicine->strength ?: '—' }}</p>
                                <p class="text-xs text-[#64748B] uppercase tracking-wider font-semibold mt-0.5">{{ $medicine->dosage_form ?: 'Standard' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-[#E9F7F2] text-[#2F7E6A]">
                                    {{ $medicine->drug_category ?: ($medicine->therapeutic_class ?: 'General') }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($inStockPharmacies->count() > 0)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[#2F7E6A] bg-[#E9F7F2] px-2.5 py-1 rounded-lg border border-[#63C6A7]/40">
                                        <i class="fas fa-check-circle"></i> {{ $inStockPharmacies->count() }} {{ Str::plural('pharmacy', $inStockPharmacies->count()) }} ({{ number_format($totalNetworkUnits) }} units)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-[#64748B] bg-slate-100 px-2.5 py-1 rounded-lg">
                                        <i class="fas fa-minus-circle"></i> Not currently stocked
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs font-bold text-[#0F172A]">
                                @if($minPrice !== null)
                                    @if(abs($minPrice - $maxPrice) < 0.01)
                                        ₱{{ number_format($minPrice, 2) }}
                                    @else
                                        ₱{{ number_format($minPrice, 2) }} – ₱{{ number_format($maxPrice, 2) }}
                                    @endif
                                @else
                                    <span class="text-[#64748B] font-medium">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button"
                                        onclick='openMedicineDetailModal(@json($modalPayload))'
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-[#E9F7F2] text-[#0F172A] hover:text-[#2F7E6A] text-xs font-bold transition-colors border border-slate-200/80">
                                    <i class="fas fa-eye"></i> Inspect
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center">
                                <i class="fas fa-pills text-3xl text-slate-300 mb-2 block"></i>
                                <p class="font-bold text-[#0F172A] text-base">No medicines matched your filter criteria</p>
                                <p class="text-xs text-[#64748B] mt-1">Try clearing your search query or selecting a different category.</p>
                                <a href="{{ route('admin.medicines') }}" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#2F7E6A] text-white text-xs font-bold">
                                    Reset Filters
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($medicines->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $medicines->links() }}
            </div>
        @endif
    </div>

    <!-- Read-Only Medicine Detail Modal -->
    <div id="medicineDetailModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-2xl overflow-hidden max-h-[90vh] flex flex-col">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <span class="text-[10px] font-black text-[#2F7E6A] uppercase tracking-widest">Catalog Record Inspection</span>
                    <h3 id="medModalTitle" class="text-lg font-black text-[#0F172A]">Medicine Details</h3>
                </div>
                <button type="button" onclick="closeMedicineDetailModal()" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-[#64748B] hover:text-[#0F172A] flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-5">
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Brand Name</p>
                        <p id="medModalBrand" class="font-bold text-[#0F172A] mt-0.5">—</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Generic Name</p>
                        <p id="medModalGeneric" class="font-bold text-[#0F172A] mt-0.5">—</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Category</p>
                        <p id="medModalCategory" class="font-bold text-[#2F7E6A] mt-0.5">—</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Strength &amp; Form</p>
                        <p id="medModalFormStrength" class="font-bold text-[#0F172A] mt-0.5">—</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                        <p class="text-[10px] font-black text-[#64748B] uppercase">FDA Registration</p>
                        <p id="medModalFda" class="font-bold text-[#0F172A] mt-0.5">—</p>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200/60">
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Prescription</p>
                        <p id="medModalRx" class="font-bold text-[#0F172A] mt-0.5">—</p>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200/60">
                    <p class="text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1">Clinical Description &amp; Indications</p>
                    <p id="medModalDescription" class="text-xs text-[#0F172A] leading-relaxed">—</p>
                </div>

                <div>
                    <p class="text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-2">Active Partner Pharmacies Carrying This Item</p>
                    <div id="medModalPharmacies" class="divide-y divide-slate-100 border border-slate-200/80 rounded-xl overflow-hidden text-xs"></div>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" onclick="closeMedicineDetailModal()" class="px-5 py-2 bg-[#0F172A] text-white text-xs font-bold rounded-xl">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function handleAdminCatalogImageError(img) {
            if (!img) return;
            const step = parseInt(img.dataset.retryStep || '0', 10);
            const originalSrc = img.dataset.originalSrc || '';
            const fallbackSrc = img.dataset.fallbackSrc || '';

            if (step === 0 && originalSrc) {
                img.dataset.retryStep = '1';
                let retrySrc;
                try {
                    const parsedUrl = new URL(originalSrc, window.location.origin);
                    parsedUrl.searchParams.set('retry', '1');
                    retrySrc = parsedUrl.toString();
                } catch (e) {
                    const hashIdx = originalSrc.indexOf('#');
                    const base = hashIdx === -1 ? originalSrc : originalSrc.slice(0, hashIdx);
                    const hash = hashIdx === -1 ? '' : originalSrc.slice(hashIdx);
                    retrySrc = base + (base.indexOf('?') === -1 ? '?' : '&') + 'retry=1' + hash;
                }
                setTimeout(function () { img.src = retrySrc; }, 250);
                return;
            }
            if (step <= 1 && fallbackSrc && img.getAttribute('src') !== fallbackSrc) {
                img.dataset.retryStep = '2';
                img.src = fallbackSrc;
                return;
            }
            img.dataset.retryStep = '3';
            img.onerror = null;
            img.classList.add('hidden');
            const placeholder = img.parentElement ? img.parentElement.querySelector('[data-image-placeholder]') : null;
            if (placeholder) placeholder.classList.remove('hidden');
        }

        function openMedicineDetailModal(med) {
            document.getElementById('medModalTitle').textContent = (med.brand_name && med.brand_name !== 'Generic Only')
                ? med.brand_name + ' (' + med.generic_name + ')'
                : med.generic_name;
            document.getElementById('medModalBrand').textContent = med.brand_name;
            document.getElementById('medModalGeneric').textContent = med.generic_name;
            document.getElementById('medModalCategory').textContent = med.drug_category;
            document.getElementById('medModalFormStrength').textContent = med.strength + ' • ' + med.dosage_form;
            document.getElementById('medModalFda').textContent = med.fda_registration_no;
            document.getElementById('medModalRx').textContent = med.prescription_required ? 'Prescription Required (Rx)' : 'Over-the-Counter (OTC)';
            document.getElementById('medModalDescription').textContent = med.description;

            const container = document.getElementById('medModalPharmacies');
            container.innerHTML = '';

            if (!med.stocking_pharmacies || med.stocking_pharmacies.length === 0) {
                container.innerHTML = '<div class="p-4 text-center text-[#64748B]">No active partner pharmacies currently list this medicine in inventory.</div>';
            } else {
                med.stocking_pharmacies.forEach(function (p) {
                    const row = document.createElement('div');
                    row.className = 'px-4 py-3 flex items-center justify-between bg-white hover:bg-slate-50';
                    const left = document.createElement('div');
                    const nameEl = document.createElement('p');
                    nameEl.className = 'font-bold text-[#0F172A]';
                    nameEl.textContent = p.name;
                    const priceEl = document.createElement('p');
                    priceEl.className = 'text-[11px] text-[#64748B]';
                    priceEl.textContent = 'Selling Price: ₱' + p.selling_price + ' • Stock: ' + p.quantity_on_hand + ' units';
                    left.appendChild(nameEl);
                    left.appendChild(priceEl);

                    const link = document.createElement('a');
                    link.href = p.inventory_url;
                    link.className = 'text-xs font-bold text-[#2F7E6A] hover:underline';
                    link.textContent = 'Audit Store →';

                    row.appendChild(left);
                    row.appendChild(link);
                    container.appendChild(row);
                });
            }

            document.getElementById('medicineDetailModal').classList.remove('hidden');
        }

        function closeMedicineDetailModal() {
            document.getElementById('medicineDetailModal').classList.add('hidden');
        }
    </script>
@endsection
