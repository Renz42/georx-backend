@extends('admin.layouts.app')
@section('title', 'Platform Settings')

@section('content')
    <!-- Page Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
        <div class="flex items-center gap-4">
            <div class="bg-[#E9F7F2] text-[#2F7E6A] w-11 h-11 rounded-xl flex items-center justify-center shrink-0">
                <i class="fas fa-sliders-h text-lg"></i>
            </div>
            <div>
                <p class="text-[11px] font-extrabold text-[#2F7E6A] uppercase tracking-widest mb-0.5">System Configuration</p>
                <h2 class="text-2xl font-black text-[#0F172A] tracking-tight">Platform Settings</h2>
                <p class="text-[#64748B] text-sm font-medium mt-0.5">Configure Barangay Alijis delivery fee estimation parameters and partner registration controls.</p>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-xl mb-6 text-sm font-semibold">
            <div class="font-bold mb-1"><i class="fas fa-exclamation-circle text-rose-500 mr-2"></i>Please fix the following validation errors:</div>
            <ul class="list-disc list-inside space-y-1 ml-4 text-xs">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Delivery Scope Section -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6">
                <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
                    <div class="bg-[#E9F7F2] text-[#2F7E6A] w-9 h-9 rounded-xl flex items-center justify-center">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-[#0F172A] text-sm">Geofence Service Boundary</h3>
                        <p class="text-[#64748B] text-xs font-medium">Scoped to Barangay Alijis, Bacolod City.</p>
                    </div>
                </div>

                <div class="bg-[#E9F7F2]/60 border border-[#63C6A7]/40 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-shield-alt text-[#2F7E6A] mt-0.5 shrink-0"></i>
                        <div>
                            <p class="text-[#0F172A] text-sm font-bold">Barangay Alijis Geofence Enforcement</p>
                            <p class="text-[#64748B] text-xs font-medium mt-1">Partner pharmacy registration, approval, restoration, and patient delivery orders are strictly verified against the Barangay Alijis, Bacolod City boundary.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order & Delivery Pricing Section -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6">
                <div class="flex items-center gap-3 mb-5 pb-4 border-b border-slate-100">
                    <div class="bg-[#E9F7F2] text-[#2F7E6A] w-9 h-9 rounded-xl flex items-center justify-center">
                        <i class="fas fa-peso-sign"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-[#0F172A] text-sm">Delivery Estimation Parameters</h3>
                        <p class="text-[#64748B] text-xs font-medium">Controls the estimated courier fee displayed at patient checkout.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-black text-[#0F172A] uppercase tracking-wider mb-1.5">
                            Base Delivery Fee (₱) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#64748B] font-bold text-sm">₱</span>
                            <input type="number" name="base_delivery_fee" step="0.01" min="0" max="1000" required
                                value="{{ old('base_delivery_fee', $settings->base_delivery_fee) }}"
                                class="w-full border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-[#0F172A] font-semibold text-sm focus:outline-none focus:border-[#2F7E6A] bg-slate-50 focus:bg-white">
                        </div>
                        <p class="text-[#64748B] text-[11px] mt-1">Default base courier fee within Barangay Alijis (0 – 1,000 PHP).</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-[#0F172A] uppercase tracking-wider mb-1.5">
                                Additional Fee per km (₱)
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#64748B] font-bold text-sm">₱</span>
                                <input type="number" name="delivery_per_km_fee" step="0.01" min="0" max="100"
                                    value="{{ old('delivery_per_km_fee', $settings->delivery_per_km_fee ?? 0.0) }}"
                                    class="w-full border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-[#0F172A] font-semibold text-sm focus:outline-none focus:border-[#2F7E6A] bg-slate-50 focus:bg-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-[#0F172A] uppercase tracking-wider mb-1.5">
                                Minimum Fee (₱)
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#64748B] font-bold text-sm">₱</span>
                                <input type="number" name="delivery_minimum_fee" step="0.01" min="0" max="1000"
                                    value="{{ old('delivery_minimum_fee', $settings->delivery_minimum_fee ?? 50.0) }}"
                                    class="w-full border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-[#0F172A] font-semibold text-sm focus:outline-none focus:border-[#2F7E6A] bg-slate-50 focus:bg-white">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-[#0F172A] uppercase tracking-wider mb-1.5">
                            Maximum Estimated Fee Cap (₱)
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#64748B] font-bold text-sm">₱</span>
                            <input type="number" name="delivery_maximum_estimate" step="0.01" min="0" max="1000"
                                value="{{ old('delivery_maximum_estimate', $settings->delivery_maximum_estimate ?? 150.0) }}"
                                class="w-full border border-slate-200 rounded-xl pl-9 pr-4 py-2.5 text-[#0F172A] font-semibold text-sm focus:outline-none focus:border-[#2F7E6A] bg-slate-50 focus:bg-white">
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Controls Section -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6 lg:col-span-2 space-y-4">
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                    <div class="bg-[#E9F7F2] text-[#2F7E6A] w-9 h-9 rounded-xl flex items-center justify-center">
                        <i class="fas fa-toggle-on"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-[#0F172A] text-sm">Operational Feature Toggles</h3>
                        <p class="text-[#64748B] text-xs font-medium">Control platform-wide estimation and partner onboarding.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="flex items-start gap-4 cursor-pointer group p-3.5 rounded-xl border border-slate-200/80 hover:border-[#2F7E6A]/40 transition">
                        <div class="relative mt-0.5 shrink-0">
                            <input type="checkbox" name="delivery_estimate_enabled" class="sr-only peer"
                                {{ $settings->delivery_estimate_enabled ? 'checked' : '' }}>
                            <div class="w-12 h-6 bg-slate-200 rounded-full peer peer-checked:bg-[#2F7E6A] transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:w-5 after:h-5 after:rounded-full after:shadow after:transition-all peer-checked:after:translate-x-6"></div>
                        </div>
                        <div>
                            <p class="font-bold text-[#0F172A] text-sm">Distance-Based Delivery Estimation</p>
                            <p class="text-[#64748B] text-xs font-medium mt-0.5">When enabled, estimates courier fee using road/coordinate distance between pharmacy and patient.</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-4 cursor-pointer group p-3.5 rounded-xl border border-slate-200/80 hover:border-[#2F7E6A]/40 transition">
                        <div class="relative mt-0.5 shrink-0">
                            <input type="checkbox" name="enable_new_registrations" id="enable_registrations" class="sr-only peer"
                                {{ $settings->enable_new_registrations ? 'checked' : '' }}>
                            <div class="w-12 h-6 bg-slate-200 rounded-full peer peer-checked:bg-[#2F7E6A] transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:w-5 after:h-5 after:rounded-full after:shadow after:transition-all peer-checked:after:translate-x-6"></div>
                        </div>
                        <div>
                            <p class="font-bold text-[#0F172A] text-sm">Allow New Pharmacy Registrations</p>
                            <p class="text-[#64748B] text-xs font-medium mt-0.5">When disabled, new pharmacy partner applications cannot be submitted.</p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit"
                class="bg-[#2F7E6A] hover:bg-[#256655] text-white font-bold px-6 py-3 rounded-xl text-sm transition-colors shadow-xs flex items-center gap-2">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </form>
@endsection
