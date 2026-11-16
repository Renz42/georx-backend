@extends('admin.layouts.app')
@section('title', 'Review Pharmacy — ' . $pharmacy->name)

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.pharmacies') }}" class="inline-flex items-center gap-2 text-xs font-bold text-[#2F7E6A] hover:underline">
            <i class="fas fa-arrow-left"></i> Back to Partner Pharmacies
        </a>
        <a href="{{ route('admin.pharmacies.inventory', $pharmacy) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0F172A] bg-white px-3.5 py-2 rounded-xl border border-slate-200 shadow-2xs hover:border-[#2F7E6A]">
            <i class="fas fa-boxes-stacked text-[#2F7E6A]"></i> Audit Branch Inventory
        </a>
    </div>

    @php
        $inAlijis = $isInsideAlijis ?? \App\Services\GeofenceService::isInsideAlijis((float) $pharmacy->latitude, (float) $pharmacy->longitude);
    @endphp

    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden mb-6">
        <!-- Status Banner -->
        <div class="px-6 py-5 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4 {{ $pharmacy->status === 'approved' ? 'bg-[#E9F7F2]/60' : ($pharmacy->status === 'suspended' ? 'bg-rose-50/70' : 'bg-amber-50/70') }}">
            <div>
                <p class="text-[10px] font-black uppercase tracking-widest {{ $pharmacy->status === 'approved' ? 'text-[#2F7E6A]' : ($pharmacy->status === 'suspended' ? 'text-rose-700' : 'text-amber-800') }}">
                    Partner Governance Record #{{ $pharmacy->id }}
                </p>
                <h2 class="text-2xl font-black text-[#0F172A] mt-0.5">{{ $pharmacy->name }}</h2>
            </div>

            <div class="flex items-center gap-2">
                @if($pharmacy->status === 'approved' && $pharmacy->is_active)
                    <span class="bg-[#2F7E6A] text-white text-xs font-black uppercase tracking-wider px-3.5 py-1.5 rounded-xl">
                        <i class="fas fa-check-circle mr-1"></i> Approved &amp; Live
                    </span>
                @elseif($pharmacy->status === 'approved' && !$pharmacy->is_active)
                    <span class="bg-amber-600 text-white text-xs font-black uppercase tracking-wider px-3.5 py-1.5 rounded-xl">
                        <i class="fas fa-moon mr-1"></i> Approved (Offline)
                    </span>
                @elseif($pharmacy->status === 'suspended')
                    <span class="bg-rose-600 text-white text-xs font-black uppercase tracking-wider px-3.5 py-1.5 rounded-xl">
                        <i class="fas fa-pause-circle mr-1"></i> Suspended
                    </span>
                @elseif($pharmacy->status === 'pending')
                    <span class="bg-amber-500 text-white text-xs font-black uppercase tracking-wider px-3.5 py-1.5 rounded-xl">
                        <i class="fas fa-hourglass-half mr-1"></i> Pending Approval
                    </span>
                @else
                    <span class="bg-slate-700 text-white text-xs font-black uppercase tracking-wider px-3.5 py-1.5 rounded-xl">
                        <i class="fas fa-ban mr-1"></i> Rejected
                    </span>
                @endif
            </div>
        </div>

        <!-- Details Grid -->
        <div class="p-6 sm:p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-4">
                <h3 class="text-xs font-black text-[#0F172A] uppercase tracking-widest border-b border-slate-100 pb-2">
                    <i class="fas fa-store text-[#2F7E6A] mr-1.5"></i> Storefront &amp; Location Details
                </h3>

                <div>
                    <p class="text-[10px] font-black text-[#64748B] uppercase">Pharmacy Name</p>
                    <p class="text-sm font-bold text-[#0F172A] mt-0.5">{{ $pharmacy->name }}</p>
                </div>

                <div>
                    <p class="text-[10px] font-black text-[#64748B] uppercase">Physical Address</p>
                    <p class="text-sm font-medium text-[#0F172A] mt-0.5">{{ $pharmacy->address }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Coordinates</p>
                        <p class="text-xs font-mono font-bold text-[#0F172A] mt-0.5">{{ $pharmacy->latitude }}, {{ $pharmacy->longitude }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Barangay Alijis Geofence</p>
                        @if($inAlijis)
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-[#2F7E6A] mt-0.5">
                                <i class="fas fa-check-circle"></i> Inside Boundary
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-rose-600 mt-0.5">
                                <i class="fas fa-times-circle"></i> Outside Boundary (Ineligible)
                            </span>
                        @endif
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-[#64748B] uppercase">Operating Hours</p>
                    <p class="text-xs font-bold text-[#0F172A] mt-1">
                        @if(is_array($pharmacy->operating_hours) && isset($pharmacy->operating_hours['open'], $pharmacy->operating_hours['close']))
                            <span class="bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 inline-block">
                                {{ $pharmacy->operating_hours['open'] }} — {{ $pharmacy->operating_hours['close'] }}
                            </span>
                        @else
                            <span class="text-[#64748B]">Default schedule / Not specified</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <h3 class="text-xs font-black text-[#0F172A] uppercase tracking-widest border-b border-slate-100 pb-2">
                    <i class="fas fa-id-card text-[#2F7E6A] mr-1.5"></i> Legal, Licensing &amp; Ownership
                </h3>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Registered Owner</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-0.5">{{ $pharmacy->owner_name ?: ($ownerAccount->name ?? 'Not Provided') }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Contact Phone</p>
                        <p class="text-sm font-bold text-[#0F172A] mt-0.5">{{ $pharmacy->phone ?: 'Not Provided' }}</p>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-[#64748B] uppercase">Registered Email</p>
                    <p class="text-sm font-bold text-[#0F172A] mt-0.5">{{ $pharmacy->email ?: ($ownerAccount->email ?? 'Not Provided') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-[10px] font-black text-[#64748B] uppercase">FDA LTO / License Number</p>
                        <p class="font-mono text-xs font-bold bg-slate-50 px-3 py-2 rounded-lg border border-slate-200 text-[#0F172A] mt-1">
                            {{ $pharmacy->license_number ?? $pharmacy->lto_number ?? 'Not Provided' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-[#64748B] uppercase">Business Permit</p>
                        <p class="font-mono text-xs font-bold bg-slate-50 px-3 py-2 rounded-lg border border-slate-200 text-[#0F172A] mt-1">
                            {{ $pharmacy->business_permit ?: 'Verified on File' }}
                        </p>
                    </div>
                </div>

                <div>
                    <p class="text-[10px] font-black text-[#64748B] uppercase">Application / Registration Date</p>
                    <p class="text-xs font-bold text-[#0F172A] mt-0.5">{{ $pharmacy->created_at ? $pharmacy->created_at->format('F d, Y h:i A') : 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Governance Action Bar -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200/80 flex flex-wrap items-center justify-between gap-4">
            <a href="{{ route('admin.pharmacies.edit', $pharmacy) }}" class="px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-[#0F172A] text-xs font-bold hover:bg-slate-100">
                <i class="fas fa-edit mr-1.5 text-[#2F7E6A]"></i> Edit Pharmacy Details
            </a>

            <div class="flex flex-wrap items-center gap-3">
                @if($pharmacy->status === 'pending')
                    <form action="{{ route('admin.pharmacies.reject', $pharmacy->id) }}" method="POST" onsubmit="return confirm('Mark this application as rejected? The record will be preserved in audit history.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-white border border-rose-200 text-rose-700 hover:bg-rose-50 text-xs font-bold">
                            <i class="fas fa-times mr-1"></i> Reject Application
                        </button>
                    </form>

                    <form action="{{ route('admin.pharmacies.approve', $pharmacy->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" {{ !$inAlijis ? 'disabled' : '' }}
                                class="px-5 py-2.5 rounded-xl bg-[#2F7E6A] hover:bg-[#256655] disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold">
                            <i class="fas fa-check mr-1"></i> Approve Partner Pharmacy
                        </button>
                    </form>
                @elseif($pharmacy->status === 'suspended' && $pharmacy->is_approved && $inAlijis)
                    <form action="{{ route('admin.pharmacies.restore', $pharmacy->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="reason" value="Restored from Pharmacy Review page by Super Admin.">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#2F7E6A] hover:bg-[#256655] text-white text-xs font-bold">
                            <i class="fas fa-undo mr-1.5"></i> Restore Suspended Account
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Governance Audit History -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100">
            <h3 class="font-black text-[#0F172A] text-sm uppercase tracking-tight">
                <i class="fas fa-history text-[#2F7E6A] mr-2"></i> Pharmacy Audit &amp; Governance Log
            </h3>
        </div>
        <div class="divide-y divide-slate-100 text-xs">
            @forelse($auditHistory ?? [] as $log)
                <div class="px-6 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <span class="font-black text-[#0F172A]">{{ $log->action }}</span>
                        <span class="text-[#64748B] ml-2">{{ $log->details }}</span>
                    </div>
                    <div class="text-[#64748B] font-medium shrink-0">
                        {{ $log->user->name ?? 'System / Admin' }} &bull; {{ $log->created_at ? $log->created_at->format('M d, Y h:i A') : '' }}
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-[#64748B]">
                    No audit log entries recorded for this pharmacy yet.
                </div>
            @endforelse
        </div>
    </div>
@endsection
