@extends('admin.layouts.app')
@section('title', 'Platform Settings')

@section('content')
    <!-- Page Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-sm border border-slate-200">
        <div class="flex items-center gap-4">
            <div class="bg-slate-100 p-3 rounded-xl">
                <i class="fas fa-sliders-h text-slate-600 text-xl"></i>
            </div>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">Platform Settings</h2>
                <p class="text-slate-500 text-sm font-medium mt-0.5">Manage the operational parameters for the GEORX system.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-xl mb-6 flex items-center gap-3 text-sm font-semibold">
            <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 px-5 py-4 rounded-xl mb-6 text-sm font-semibold">
            <div class="font-bold mb-1"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>Please fix the following:</div>
            <ul class="list-disc list-inside space-y-1 ml-4">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Delivery Scope Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div class="bg-blue-50 p-2.5 rounded-lg">
                        <i class="fas fa-map-marker-alt text-blue-600"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-sm">Delivery Area</h3>
                        <p class="text-slate-400 text-xs font-medium">The system is scoped to Barangay Alijis, Bacolod City only.</p>
                    </div>
                </div>

                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 shrink-0"></i>
                        <div>
                            <p class="text-blue-800 text-sm font-bold">Fixed Service Area</p>
                            <p class="text-blue-600 text-xs font-medium mt-1">Delivery is limited to Barangay Alijis, Bacolod City. This is fixed for the pilot phase and cannot be changed here.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order & Pricing Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div class="bg-emerald-50 p-2.5 rounded-lg">
                        <i class="fas fa-peso-sign text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-sm">Order & Pricing</h3>
                        <p class="text-slate-400 text-xs font-medium">Controls delivery fees applied to all orders.</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-2">
                            Base Delivery Fee (₱)
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 font-bold text-sm">₱</span>
                            <input type="number" name="base_delivery_fee" step="0.01" min="0"
                                value="{{ old('base_delivery_fee', $settings->base_delivery_fee) }}"
                                class="w-full border border-slate-200 rounded-xl pl-9 pr-4 py-3 text-slate-800 font-semibold text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-slate-50 hover:bg-white transition-colors">
                        </div>
                        <p class="text-slate-400 text-xs mt-1.5">This flat fee is added to every order at checkout.</p>
                    </div>
                </div>
            </div>

            <!-- System Controls Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 lg:col-span-2">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-100">
                    <div class="bg-amber-50 p-2.5 rounded-lg">
                        <i class="fas fa-toggle-on text-amber-600"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-800 text-sm">System Controls</h3>
                        <p class="text-slate-400 text-xs font-medium">Toggle system-wide features on or off.</p>
                    </div>
                </div>

                <label class="flex items-start gap-4 cursor-pointer group">
                    <div class="relative mt-0.5 shrink-0">
                        <input type="checkbox" name="enable_new_registrations" id="enable_registrations" class="sr-only peer"
                            {{ $settings->enable_new_registrations ? 'checked' : '' }}>
                        <div class="w-12 h-6 bg-slate-200 rounded-full peer peer-checked:bg-emerald-500 transition-colors duration-200 after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:w-5 after:h-5 after:rounded-full after:shadow after:transition-all peer-checked:after:translate-x-6"></div>
                    </div>
                    <div>
                        <p class="font-bold text-slate-800 text-sm group-hover:text-slate-900 transition-colors">Allow New Pharmacy Registrations</p>
                        <p class="text-slate-400 text-xs font-medium mt-0.5">When off, the Pharmacy Register link will be hidden and new applications will not be accepted.</p>
                    </div>
                </label>
            </div>

        </div>

        <!-- Save Button -->
        <div class="mt-6 flex justify-end">
            <button type="submit"
                class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-8 py-3 rounded-xl text-sm transition-colors shadow-sm flex items-center gap-2 active:scale-95">
                <i class="fas fa-save"></i> Save Settings
            </button>
        </div>
    </form>
@endsection
