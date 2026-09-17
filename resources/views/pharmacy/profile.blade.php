@extends('pharmacy.layouts.app')
@section('title', 'Pharmacy Profile')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
    <!-- Page Header -->
    <div class="mb-10">
        <h2 class="text-3xl font-black text-slate-800 tracking-tight">Pharmacy Information</h2>
        <p class="text-slate-500 mt-1 font-medium">Verify your registered business credentials</p>
    </div>

    <!-- Professional Read-Only Notice -->
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-6 mb-8 flex items-start gap-5 shadow-sm">
        <div class="bg-blue-600 p-3 rounded-xl text-white shadow-md shadow-blue-200">
            <i class="fas fa-shield-alt text-xl"></i>
        </div>
        <div>
            <h3 class="text-blue-900 font-black text-sm uppercase tracking-tight">Verified Account Information</h3>
            <p class="text-blue-700 text-sm mt-1 leading-relaxed font-medium">
                To maintain the integrity of our medical network, core business details (Name, Address, and GPS Location) are verified and locked. 
                If you need to update this information, please <span class="font-bold underline cursor-pointer">contact system support</span>.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-12">
        
        <!-- Left: Business Details -->
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="font-black text-slate-800 flex items-center gap-2 text-sm uppercase tracking-tight">
                        <i class="fas fa-store text-blue-600"></i> Business Credentials
                    </h3>
                    <div class="flex items-center gap-2">
                        @if($pharmacy->is_approved)
                            <span class="bg-emerald-100 text-emerald-700 text-[10px] px-3 py-1 rounded-lg font-black uppercase tracking-widest border border-emerald-200">
                                <i class="fas fa-check-circle mr-1"></i> Verified Partner
                            </span>
                        @endif
                    </div>
                </div>

                <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-10">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pharmacy Name</label>
                        <p class="text-slate-800 font-bold text-lg leading-tight">{{ $pharmacy->name }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Owner / Manager</label>
                        <p class="text-slate-700 font-bold">{{ $pharmacy->owner_name ?? 'Not Set' }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Business Permit</label>
                        <p class="text-slate-700 font-bold">{{ $pharmacy->business_permit ?? 'Not Provided' }}</p>
                    </div>

                    <div class="space-y-1 md:col-span-2">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Registered Street Address</label>
                        <p class="text-slate-700 font-semibold leading-relaxed">{{ $pharmacy->address }}</p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Contact Number</label>
                        <div class="flex items-center gap-2 text-slate-700 font-bold">
                            <i class="fas fa-phone-alt text-slate-300 text-xs"></i>
                            {{ $pharmacy->phone ?? '---' }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Public Email</label>
                        <div class="flex items-center gap-2 text-slate-700 font-bold">
                            <i class="fas fa-envelope text-slate-300 text-xs"></i>
                            {{ $pharmacy->email ?? 'Not set' }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Operating Hours</label>
                        <p class="text-slate-700 font-bold flex items-center gap-2">
                            @if(is_array($pharmacy->operating_hours) && isset($pharmacy->operating_hours['open']) && isset($pharmacy->operating_hours['close']))
                                <span class="bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg border border-blue-100 font-bold text-sm">
                                    <i class="far fa-clock mr-1.5 text-blue-400"></i>
                                    {{ \Carbon\Carbon::parse($pharmacy->operating_hours['open'])->format('h:i A') }}
                                    <span class="text-blue-300 mx-1">—</span>
                                    {{ \Carbon\Carbon::parse($pharmacy->operating_hours['close'])->format('h:i A') }}
                                </span>
                            @else
                                <span class="text-slate-400 italic bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 text-sm">
                                    <i class="fas fa-exclamation-circle mr-1"></i> Not configured
                                </span>
                            @endif
                        </p>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Current Status</label>
                        <div class="pt-1">
                            @if($pharmacy->is_active)
                                <span class="bg-emerald-50 text-emerald-600 text-[10px] px-3 py-1 rounded-full font-black uppercase tracking-widest border border-emerald-100">
                                    <i class="fas fa-signal mr-1"></i> Currently Online
                                </span>
                            @else
                                <span class="bg-red-50 text-red-600 text-[10px] px-3 py-1 rounded-full font-black uppercase tracking-widest border border-red-100">
                                    <i class="fas fa-power-off mr-1"></i> Maintenance Mode
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Map Location -->
        <div class="space-y-6">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-6">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                    <h3 class="font-black text-slate-800 flex items-center gap-2 text-sm uppercase tracking-tight">
                        <i class="fas fa-map-marker-alt text-red-500"></i> GPS Location
                    </h3>
                </div>
                
                <div id="map" class="h-64 z-0"></div>

                <div class="p-6 bg-slate-50 border-t border-slate-100">
                    <div class="flex flex-col gap-3">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Latitude</span>
                            <span class="font-mono text-xs font-bold text-slate-600">{{ $pharmacy->latitude }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Longitude</span>
                            <span class="font-mono text-xs font-bold text-slate-600">{{ $pharmacy->longitude }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const lat = {{ $pharmacy->latitude }};
        const lng = {{ $pharmacy->longitude }};
        
        const map = L.map('map', {
            zoomControl: false,
            dragging: false,
            touchZoom: false,
            scrollWheelZoom: false,
            doubleClickZoom: false
        }).setView([lat, lng], 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        const customIcon = L.divIcon({
            html: `<div class="bg-blue-600 w-8 h-8 rounded-full border-4 border-white shadow-lg flex items-center justify-center text-white">
                    <i class="fas fa-clinic-medical text-xs"></i>
                   </div>`,
            className: '',
            iconSize: [32, 32],
            iconAnchor: [16, 16]
        });

        L.marker([lat, lng], { icon: customIcon }).addTo(map)
            .bindPopup('<strong>{{ addslashes($pharmacy->name) }}</strong>')
            .openPopup();
            
        // Adding a slight delay to ensure Leaflet renders correctly in the container
        setTimeout(() => { map.invalidateSize(); }, 100);
    </script>
@endpush
