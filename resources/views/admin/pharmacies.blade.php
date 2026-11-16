@extends('admin.layouts.app')
@section('title', 'Partner Pharmacies')

@section('content')
    <!-- Page Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-extrabold text-[#2F7E6A] uppercase tracking-widest mb-1">Network Governance</p>
                <h2 class="text-2xl sm:text-3xl font-black text-[#0F172A] tracking-tight">Partner Pharmacies</h2>
                <p class="text-[#64748B] text-sm mt-1 font-medium">Review applications, enforce Barangay Alijis geofence compliance, suspend or restore accounts, and audit branch inventories.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5 text-xs font-bold">
                <span class="px-3 py-2 rounded-xl bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40">
                    <i class="fas fa-check-circle mr-1"></i> {{ $statusCounts['approved'] ?? 0 }} Active
                </span>
                <span class="px-3 py-2 rounded-xl bg-amber-50 text-amber-800 border border-amber-200">
                    <i class="fas fa-hourglass-half mr-1"></i> {{ $statusCounts['pending'] ?? 0 }} Pending
                </span>
                <span class="px-3 py-2 rounded-xl bg-rose-50 text-rose-700 border border-rose-200">
                    <i class="fas fa-pause-circle mr-1"></i> {{ $statusCounts['suspended'] ?? 0 }} Suspended
                </span>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl mb-6 text-sm font-semibold">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Server-Side Search, Status Filter Tabs & Sort Controls -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-5 mb-6 space-y-4">
        <form method="GET" action="{{ route('admin.pharmacies') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3.5 items-end">
            <input type="hidden" name="status" value="{{ $statusFilter ?? 'all' }}">
            <div class="md:col-span-6">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Search Directory</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B] text-xs"></i>
                    <input type="text" name="search" value="{{ $search ?? request('search') }}"
                           placeholder="Search pharmacy name, owner, phone, email, license, address..."
                           class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                </div>
            </div>

            <div class="md:col-span-3">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Sort Order</label>
                <select name="sort" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="newest" {{ ($sort ?? 'newest') === 'newest' ? 'selected' : '' }}>Newest First</option>
                    <option value="name_asc" {{ ($sort ?? '') === 'name_asc' ? 'selected' : '' }}>Name (A–Z)</option>
                    <option value="name_desc" {{ ($sort ?? '') === 'name_desc' ? 'selected' : '' }}>Name (Z–A)</option>
                    <option value="updated_desc" {{ ($sort ?? '') === 'updated_desc' ? 'selected' : '' }}>Recently Updated</option>
                    <option value="oldest" {{ ($sort ?? '') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
                </select>
            </div>

            <div class="md:col-span-3 flex gap-2">
                <button type="submit" class="flex-1 bg-[#2F7E6A] hover:bg-[#256655] text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors flex items-center justify-center gap-1.5">
                    <i class="fas fa-search text-xs"></i> Search
                </button>
                @if(!empty($search) || ($statusFilter ?? 'all') !== 'all' || ($sort ?? 'newest') !== 'newest')
                    <a href="{{ route('admin.pharmacies') }}" class="bg-slate-100 hover:bg-slate-200 text-[#64748B] font-bold py-2.5 px-3.5 rounded-xl text-sm transition-colors flex items-center justify-center" title="Reset Filters">
                        <i class="fas fa-undo text-xs"></i>
                    </a>
                @endif
            </div>
        </form>

        <!-- Status Filter Pills -->
        @php
            $tabs = [
                'all' => ['label' => 'All Records', 'count' => $statusCounts['all'] ?? 0],
                'approved' => ['label' => 'Approved & Active', 'count' => $statusCounts['approved'] ?? 0],
                'pending' => ['label' => 'Pending Review', 'count' => $statusCounts['pending'] ?? 0],
                'suspended' => ['label' => 'Suspended', 'count' => $statusCounts['suspended'] ?? 0],
                'inactive' => ['label' => 'Approved (Offline)', 'count' => $statusCounts['inactive'] ?? 0],
                'rejected' => ['label' => 'Rejected', 'count' => $statusCounts['rejected'] ?? 0],
            ];
            $currentTab = $statusFilter ?? 'all';
        @endphp
        <div class="flex flex-wrap gap-2 pt-3 border-t border-slate-100">
            @foreach($tabs as $tabKey => $tabMeta)
                <a href="{{ request()->fullUrlWithQuery(['status' => $tabKey, 'page' => null]) }}"
                   class="px-3.5 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 {{ $currentTab === $tabKey ? 'bg-[#2F7E6A] text-white shadow-2xs' : 'bg-slate-50 text-[#64748B] hover:bg-slate-100 hover:text-[#0F172A]' }}">
                    <span>{{ $tabMeta['label'] }}</span>
                    <span class="px-1.5 py-0.5 rounded-md text-[10px] font-black {{ $currentTab === $tabKey ? 'bg-white/20 text-white' : 'bg-white text-[#0F172A] border border-slate-200' }}">{{ $tabMeta['count'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <!-- Pharmacy Directory Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($pharmacies as $pharmacy)
            @php
                $inAlijis = \App\Services\GeofenceService::isInsideAlijis((float) $pharmacy->latitude, (float) $pharmacy->longitude);
                $canRestore = $pharmacy->status === 'suspended' && (bool) $pharmacy->is_approved && $inAlijis;
                $medCount = $pharmacy->medicines_count ?? $pharmacy->medicines()->count();
            @endphp
            <div class="bg-white border border-slate-200/80 rounded-2xl p-6 shadow-xs hover:shadow-sm transition-all flex flex-col justify-between">
                <div>
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-black text-[#0F172A] leading-snug truncate">{{ $pharmacy->name }}</h3>
                            <p class="text-xs text-[#64748B] mt-1 line-clamp-2 flex items-start gap-1.5">
                                <i class="fas fa-map-marker-alt text-[#2F7E6A] mt-0.5 shrink-0"></i>
                                <span>{{ $pharmacy->address }}</span>
                            </p>
                        </div>

                        <!-- Status Badge -->
                        <div class="shrink-0">
                            @if($pharmacy->status === 'approved' && $pharmacy->is_active)
                                <span class="bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider inline-flex items-center gap-1">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            @elseif($pharmacy->status === 'approved' && !$pharmacy->is_active)
                                <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider inline-flex items-center gap-1">
                                    <i class="fas fa-moon"></i> Offline
                                </span>
                            @elseif($pharmacy->status === 'pending')
                                <span class="bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider inline-flex items-center gap-1">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            @elseif($pharmacy->status === 'suspended')
                                <span class="bg-rose-50 text-rose-700 border border-rose-200 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider inline-flex items-center gap-1">
                                    <i class="fas fa-pause-circle"></i> Suspended
                                </span>
                            @else
                                <span class="bg-slate-100 text-slate-700 border border-slate-300 text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider inline-flex items-center gap-1">
                                    <i class="fas fa-ban"></i> Rejected
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 my-4 bg-slate-50 rounded-xl p-3.5 border border-slate-200/60 text-xs">
                        <div>
                            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-wider">Owner</p>
                            <p class="font-bold text-[#0F172A] truncate mt-0.5">{{ $pharmacy->owner_name ?: 'Not Listed' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-wider">Contact</p>
                            <p class="font-bold text-[#0F172A] truncate mt-0.5">{{ $pharmacy->phone ?: 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-wider">License / LTO</p>
                            <p class="font-bold text-[#0F172A] truncate mt-0.5">{{ $pharmacy->license_number ?: ($pharmacy->lto_number ?: 'N/A') }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-wider">Geofence</p>
                            @if($inAlijis)
                                <p class="font-bold text-[#2F7E6A] mt-0.5"><i class="fas fa-check-circle mr-1"></i>Barangay Alijis</p>
                            @else
                                <p class="font-bold text-rose-600 mt-0.5"><i class="fas fa-exclamation-triangle mr-1"></i>Outside Alijis</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="pt-3 border-t border-slate-100 space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-[#64748B] font-medium">Registered {{ $pharmacy->created_at ? $pharmacy->created_at->format('M d, Y') : 'N/A' }}</span>
                        <a href="{{ route('admin.pharmacies.inventory', $pharmacy) }}" class="font-bold text-[#2F7E6A] hover:underline inline-flex items-center gap-1">
                            <i class="fas fa-boxes-stacked"></i> Audit Inventory ({{ $medCount }}) &rarr;
                        </a>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <a href="{{ route('admin.pharmacies.review', $pharmacy) }}"
                           class="flex-1 text-center px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#0F172A] text-xs font-bold transition-colors">
                            <i class="fas fa-file-shield mr-1"></i> Review
                        </a>

                        <a href="{{ route('admin.pharmacies.edit', $pharmacy) }}"
                           class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#0F172A] text-xs font-bold transition-colors" title="Edit Profile">
                            <i class="fas fa-edit"></i>
                        </a>

                        @if($pharmacy->status === 'approved')
                            <button type="button"
                                    data-name="{{ $pharmacy->name }}"
                                    data-action="{{ route('admin.pharmacies.suspend', $pharmacy) }}"
                                    onclick="openSuspendPharmacyModal(this)"
                                    class="px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-bold transition-colors">
                                <i class="fas fa-pause mr-1"></i> Suspend
                            </button>
                        @elseif($pharmacy->status === 'suspended')
                            @if($canRestore)
                                <button type="button"
                                        data-name="{{ $pharmacy->name }}"
                                        data-action="{{ route('admin.pharmacies.restore', $pharmacy) }}"
                                        onclick="openRestorePharmacyModal(this)"
                                        class="px-3 py-2 rounded-xl bg-[#2F7E6A] hover:bg-[#256655] text-white text-xs font-bold transition-colors">
                                    <i class="fas fa-undo mr-1"></i> Restore
                                </button>
                            @else
                                <span class="px-2.5 py-1.5 rounded-xl bg-slate-100 text-[#64748B] text-[11px] font-semibold" title="Cannot restore: pharmacy was never approved or is outside Barangay Alijis geofence">
                                    Ineligible to Restore
                                </span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-white rounded-2xl border border-slate-200/80">
                <i class="fas fa-store-slash text-3xl text-slate-300 mb-2 block"></i>
                <p class="text-base font-bold text-[#0F172A]">No partner pharmacies matched your filter</p>
                <p class="text-xs text-[#64748B] mt-1">Try adjusting your search keywords or status filter.</p>
                <a href="{{ route('admin.pharmacies') }}" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#2F7E6A] text-white text-xs font-bold">
                    Reset Directory Filters
                </a>
            </div>
        @endforelse
    </div>

    @if($pharmacies->hasPages())
        <div class="mt-6">
            {{ $pharmacies->links() }}
        </div>
    @endif

    <!-- Suspend Pharmacy Confirmation Modal (Requires Reason) -->
    <div id="suspendPharmacyModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 bg-rose-50 border-b border-rose-100 flex items-center justify-between">
                <h3 class="text-base font-black text-rose-800 flex items-center gap-2">
                    <i class="fas fa-pause-circle"></i> Suspend Partner Pharmacy
                </h3>
                <button type="button" onclick="closeSuspendPharmacyModal()" class="text-rose-700 hover:text-rose-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="suspendPharmacyForm" method="POST" action="" class="p-6 space-y-4">
                @csrf
                @method('PATCH')
                <p class="text-xs text-[#64748B] leading-relaxed">
                    You are suspending <strong id="suspendPharmacyName" class="text-[#0F172A]"></strong>. This hides the pharmacy from the public map and pauses new patient orders while preserving all historical inventory and order records.
                </p>
                <div>
                    <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-wider mb-1.5">Suspension Reason (Required) *</label>
                    <textarea name="reason" rows="3" required minlength="5" maxlength="500"
                              placeholder="Document the compliance or operational reason for suspending this pharmacy..."
                              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-rose-500"></textarea>
                </div>
                <div class="flex justify-end gap-2.5 pt-2">
                    <button type="button" onclick="closeSuspendPharmacyModal()" class="px-4 py-2 rounded-xl bg-slate-100 text-[#0F172A] text-xs font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold">
                        Confirm Suspension
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restore Pharmacy Confirmation Modal -->
    <div id="restorePharmacyModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 bg-[#E9F7F2] border-b border-[#63C6A7]/40 flex items-center justify-between">
                <h3 class="text-base font-black text-[#2F7E6A] flex items-center gap-2">
                    <i class="fas fa-undo"></i> Restore Suspended Pharmacy
                </h3>
                <button type="button" onclick="closeRestorePharmacyModal()" class="text-[#2F7E6A] hover:text-[#0F172A]">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="restorePharmacyForm" method="POST" action="" class="p-6 space-y-4">
                @csrf
                @method('PATCH')
                <p class="text-xs text-[#64748B] leading-relaxed">
                    Restore <strong id="restorePharmacyName" class="text-[#0F172A]"></strong> to active approved status. The pharmacy will become visible on the Barangay Alijis public map again with its existing inventory intact.
                </p>
                <div>
                    <label class="block text-[10px] font-black text-[#0F172A] uppercase tracking-wider mb-1.5">Restoration Audit Note (Optional)</label>
                    <textarea name="reason" rows="2" maxlength="500"
                              placeholder="e.g., License renewal verified; partner restored to active status."
                              class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]"></textarea>
                </div>
                <div class="flex justify-end gap-2.5 pt-2">
                    <button type="button" onclick="closeRestorePharmacyModal()" class="px-4 py-2 rounded-xl bg-slate-100 text-[#0F172A] text-xs font-bold">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-[#2F7E6A] hover:bg-[#256655] text-white text-xs font-bold">
                        Confirm Restoration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openSuspendPharmacyModal(btn) {
            document.getElementById('suspendPharmacyName').textContent = btn.dataset.name || '';
            document.getElementById('suspendPharmacyForm').action = btn.dataset.action || '';
            document.getElementById('suspendPharmacyModal').classList.remove('hidden');
        }
        function closeSuspendPharmacyModal() {
            document.getElementById('suspendPharmacyModal').classList.add('hidden');
        }
        function openRestorePharmacyModal(btn) {
            document.getElementById('restorePharmacyName').textContent = btn.dataset.name || '';
            document.getElementById('restorePharmacyForm').action = btn.dataset.action || '';
            document.getElementById('restorePharmacyModal').classList.remove('hidden');
        }
        function closeRestorePharmacyModal() {
            document.getElementById('restorePharmacyModal').classList.add('hidden');
        }
    </script>
@endsection
