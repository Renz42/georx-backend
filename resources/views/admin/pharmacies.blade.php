@extends('admin.layouts.app')

@section('content')
    <style>
        .dropdown-content { display: none; }
        .dropdown-content.show { display: block; }
    </style>

    <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end gap-4 mb-8">
        <div>
            <h2 class="text-3xl font-black text-[#122056] tracking-tight">Partner Pharmacies</h2>
            <p class="text-[#122056]/60 mt-1">Review, audit, and manage your network.</p>
        </div>
        
        <div class="flex gap-3 w-full lg:w-auto">
            <div class="relative flex-1 lg:w-80">
                <i class="fas fa-search absolute left-4 top-3.5 text-[#122056]/40"></i>
                <input type="text" placeholder="Search pharmacy name..." class="w-full pl-10 pr-4 py-3 rounded-xl border border-[#EEEFFD] focus:border-[#5B65DC] focus:ring-2 focus:ring-[#EEEFFD] outline-none transition-all text-sm font-medium bg-[#FFFFFF]">
            </div>
            <button class="bg-[#FFFFFF] border border-[#EEEFFD] text-[#122056]/70 px-4 py-3 rounded-xl hover:bg-[#EEEFFD] transition-all font-bold text-sm flex items-center gap-2 shadow-sm">
                <i class="fas fa-filter"></i> Filters
            </button>
        </div>
    </div>

    <div class="bg-[#FFFFFF] p-2 rounded-2xl shadow-sm border border-[#EEEFFD] mb-8 flex gap-2 w-fit">
        <button onclick="switchTab('active')" id="tab-active" class="px-6 py-2.5 text-sm font-bold rounded-xl bg-[#EEEFFD] text-[#5B65DC] transition-all">
            Active Partners ({{ $activePharmacies->count() }})
        </button>
        <button onclick="switchTab('pending')" id="tab-pending" class="px-6 py-2.5 text-sm font-bold rounded-xl text-[#122056]/60 hover:bg-[#EEEFFD] hover:text-[#5B65DC] transition-all flex items-center gap-2">
            Pending Approvals
            @if($pendingPharmacies->count() > 0)
                <span class="bg-red-500 text-white text-[10px] px-2 py-0.5 rounded-full">{{ $pendingPharmacies->count() }}</span>
            @endif
        </button>
        <button onclick="switchTab('history')" id="tab-history" class="px-6 py-2.5 text-sm font-bold rounded-xl text-[#122056]/60 hover:bg-[#EEEFFD] hover:text-[#5B65DC] transition-all">
            History & Suspended
        </button>
    </div>

    <div id="content-active" class="block">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($activePharmacies as $pharmacy)
                <div class="bg-[#FFFFFF] border border-[#EEEFFD] rounded-2xl p-6 shadow-sm hover:shadow-md transition-all relative flex flex-col group">
                    <div class="flex justify-between items-start mb-4">
                        <div class="pr-6">
                            <h3 class="text-xl font-bold text-[#122056] leading-tight mb-1">{{ $pharmacy->name }}</h3>
                            <div class="flex items-start gap-2 text-sm text-[#122056]/60">
                                <i class="fas fa-map-marker-alt text-[#5B65DC] mt-1"></i>
                                <p class="line-clamp-2">{{ $pharmacy->address }}</p>
                            </div>
                        </div>
                        
                        <div class="absolute top-5 right-4">
                            <button onclick="toggleDropdown('dropdown-{{ $pharmacy->id }}')" class="p-2 text-[#122056]/40 hover:text-[#5B65DC] hover:bg-[#EEEFFD] rounded-lg transition-colors focus:outline-none">
                                <i class="fas fa-ellipsis-v px-1"></i>
                            </button>
                            <div id="dropdown-{{ $pharmacy->id }}" class="dropdown-content absolute right-0 top-10 w-48 bg-[#FFFFFF] border border-[#EEEFFD] shadow-xl rounded-xl py-2 z-50 text-left">
                                <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest px-4 py-2">Manage</p>
                                <a href="{{ route('admin.pharmacies.edit', $pharmacy) }}" class="block px-4 py-2 text-sm text-[#122056] hover:bg-[#EEEFFD] hover:text-[#5B65DC] font-medium transition-colors">
                                    <i class="fas fa-edit w-5 opacity-70"></i> Edit Profile
                                </a>
                                <a href="{{ route('admin.pharmacies.inventory', $pharmacy) }}" class="block px-4 py-2 text-sm text-[#122056] hover:bg-[#EEEFFD] hover:text-[#5B65DC] font-medium transition-colors">
                                    <i class="fas fa-box w-5 opacity-70"></i> Audit Inventory
                                </a>
                                <div class="border-t border-[#EEEFFD] my-1"></div>
                                <form action="{{ route('admin.pharmacies.suspend', $pharmacy) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <button type="submit" onclick="return confirm('Suspend this pharmacy? They will be hidden from the public map.');" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-medium transition-colors">
                                        <i class="fas fa-ban w-5 opacity-70"></i> Suspend Account
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <hr class="border-[#EEEFFD] mb-4">

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div class="bg-geo-50 rounded-xl p-3 border border-[#EEEFFD]/50">
                            <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest mb-1"><i class="fas fa-phone mr-1"></i> Contact</p>
                            <p class="text-sm font-semibold text-[#122056] truncate">{{ $pharmacy->phone }}</p>
                        </div>
                        <div class="bg-geo-50 rounded-xl p-3 border border-[#EEEFFD]/50">
                            <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest mb-1"><i class="fas fa-user-md mr-1"></i> Owner</p>
                            <p class="text-sm font-semibold text-[#122056] truncate">{{ $pharmacy->owner_name ?? 'No Owner Listed' }}</p>
                        </div>
                    </div>

                    <div class="mt-auto flex items-center justify-between pt-2">
                        <div>
                            <p class="text-[11px] font-semibold text-[#122056]/60">Joined <span class="text-[#122056]">{{ $pharmacy->created_at->format('M Y') }}</span></p>
                            <p class="text-[10px] font-medium text-emerald-600 mt-0.5"><i class="fas fa-sync-alt mr-1"></i> Sync: {{ $pharmacy->updated_at->diffForHumans() }}</p>
                        </div>
                        
                        <a href="{{ route('admin.pharmacies.inventory', $pharmacy) }}" class="inline-flex items-center justify-center gap-2 bg-[#EEEFFD] hover:bg-[#5B65DC] text-[#5B65DC] hover:text-[#FFFFFF] text-sm font-bold px-4 py-2.5 rounded-xl border border-[#5B65DC]/20 transition-all">
                            <i class="fas fa-pills"></i> {{ $pharmacy->medicines->count() }} Items <i class="fas fa-arrow-right text-[11px]"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-16 text-center bg-[#FFFFFF] rounded-2xl border border-[#EEEFFD]">
                    <i class="fas fa-store-slash text-4xl text-[#122056]/20 mb-3"></i>
                    <p class="text-lg font-bold text-[#122056]">No active pharmacies found.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div id="content-pending" class="hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($pendingPharmacies as $pharmacy)
                <div class="bg-[#FFFFFF] border-2 border-amber-200/50 rounded-2xl p-6 shadow-sm hover:shadow-md transition-all relative flex flex-col overflow-hidden">
                    <div class="absolute top-0 right-0 bg-amber-100 text-amber-700 text-[10px] font-black px-4 py-1 rounded-bl-xl uppercase tracking-widest">
                        <i class="fas fa-clock mr-1"></i> Pending Review
                    </div>

                    <div class="mb-4 pr-24 mt-2">
                        <h3 class="text-xl font-bold text-[#122056] leading-tight mb-1">{{ $pharmacy->name }}</h3>
                        <div class="flex items-start gap-2 text-sm text-[#122056]/60">
                            <i class="fas fa-map-marker-alt text-[#5B65DC] mt-1"></i>
                            <p class="line-clamp-2">{{ $pharmacy->address }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest"><i class="fas fa-phone mr-1"></i> Phone</p>
                            <p class="text-sm font-semibold text-[#122056] mt-0.5">{{ $pharmacy->phone }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest"><i class="fas fa-user-md mr-1"></i> Owner</p>
                            <p class="text-sm font-semibold text-[#122056] mt-0.5">{{ $pharmacy->owner_name ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <div class="mt-auto flex items-center justify-between pt-4 border-t border-[#EEEFFD]">
                        <div>
                            <p class="text-[11px] font-semibold text-[#122056]/50 uppercase tracking-wide">Applied On</p>
                            <p class="text-xs font-bold text-[#122056] mt-0.5">{{ $pharmacy->created_at->format('M d, Y') }}</p>
                        </div>
                        
                        <a href="{{ route('admin.pharmacies.review', $pharmacy) }}" class="inline-flex items-center justify-center gap-2 bg-[#5B65DC] hover:bg-[#122056] text-[#FFFFFF] text-sm font-bold px-5 py-2.5 rounded-xl transition-all shadow-sm">
                            Review Details <i class="fas fa-arrow-right text-[11px]"></i>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-20 text-center bg-[#FFFFFF] rounded-2xl border border-[#EEEFFD]">
                    <div class="bg-geo-50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4 text-emerald-400 border-4 border-emerald-50">
                        <i class="fas fa-check-double text-3xl"></i>
                    </div>
                    <p class="text-xl font-bold text-[#122056]">All caught up!</p>
                    <p class="text-sm text-[#122056]/60 mt-1">There are no pending applications to review.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div id="content-history" class="hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($rejectedPharmacies as $pharmacy)
                <div class="bg-geo-50 border border-[#EEEFFD] rounded-2xl p-6 shadow-sm opacity-80 grayscale-[20%] relative flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <div class="pr-6">
                            <h3 class="text-lg font-bold text-[#122056] line-through decoration-[#122056]/30 leading-tight mb-1">{{ $pharmacy->name }}</h3>
                            <p class="text-xs text-[#122056]/60 line-clamp-1">{{ $pharmacy->address }}</p>
                        </div>
                        
                        <div>
                            @if($pharmacy->status == 'suspended')
                                <span class="bg-[#122056]/10 text-[#122056] text-[10px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest flex items-center gap-1">
                                    <i class="fas fa-pause-circle"></i> Suspended
                                </span>
                            @else
                                <span class="bg-red-100 text-red-700 text-[10px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest flex items-center gap-1">
                                    <i class="fas fa-ban"></i> Rejected
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest">Contact</p>
                            <p class="text-sm font-semibold text-[#122056] mt-0.5">{{ $pharmacy->phone }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest">Date Applied</p>
                            <p class="text-sm font-semibold text-[#122056] mt-0.5">{{ $pharmacy->created_at->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-16 text-center bg-[#FFFFFF] rounded-2xl border border-[#EEEFFD]">
                    <i class="fas fa-history text-4xl text-[#122056]/20 mb-3"></i>
                    <p class="text-lg font-bold text-[#122056]">No history found.</p>
                    <p class="text-sm text-[#122056]/60 mt-1">There are no rejected or suspended applications.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Tab Switcher logic
        function switchTab(tabName) {
            document.getElementById('content-pending').classList.add('hidden');
            document.getElementById('content-active').classList.add('hidden');
            document.getElementById('content-history').classList.add('hidden');
            
            const tabs = ['pending', 'active', 'history'];
            tabs.forEach(t => {
                let el = document.getElementById('tab-' + t);
                el.classList.remove('bg-[#EEEFFD]', 'text-[#5B65DC]');
                el.classList.add('text-[#122056]/60', 'hover:bg-[#EEEFFD]', 'hover:text-[#5B65DC]');
            });

            document.getElementById('content-' + tabName).classList.remove('hidden');
            let activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('text-[#122056]/60', 'hover:bg-[#EEEFFD]', 'hover:text-[#5B65DC]');
            activeTab.classList.add('bg-[#EEEFFD]', 'text-[#5B65DC]');
        }

        // Dropdown Kebab Menu logic
        function toggleDropdown(id) {
            document.querySelectorAll('.dropdown-content').forEach(el => {
                if(el.id !== id) el.classList.remove('show');
            });
            document.getElementById(id).classList.toggle('show');
        }

        // Close dropdown if clicked outside
        window.onclick = function(event) {
            if (!event.target.closest('.fa-ellipsis-v') && !event.target.closest('button')) {
                document.querySelectorAll('.dropdown-content').forEach(el => {
                    el.classList.remove('show');
                });
            }
        }
    </script>
@endpush
