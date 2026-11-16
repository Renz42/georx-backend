@extends('admin.layouts.app')
@section('title', 'Edit Pharmacy — ' . $pharmacy->name)

@push('styles')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <style>
        .marker-pin {
            width: 30px;
            height: 30px;
            background: #2F7E6A;
            border: 3px solid white;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            box-shadow: 0 4px 8px rgba(47, 126, 106, 0.4);
        }
    </style>
@endpush

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.pharmacies') }}" class="inline-flex items-center gap-2 text-xs font-bold text-[#2F7E6A] hover:underline">
            <i class="fas fa-arrow-left"></i> Back to Partner Pharmacies
        </a>
        <a href="{{ route('admin.pharmacies.review', $pharmacy) }}" class="text-xs font-bold text-[#64748B] hover:text-[#0F172A]">
            View Governance Record &rarr;
        </a>
    </div>

    <div class="max-w-4xl">
        <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
            <p class="text-[11px] font-extrabold text-[#2F7E6A] uppercase tracking-widest mb-1">Partner Profile Configuration</p>
            <h2 class="text-2xl font-black text-[#0F172A] tracking-tight">Edit {{ $pharmacy->name }}</h2>
            <p class="text-[#64748B] text-sm mt-1">All partner coordinates must remain within the Barangay Alijis, Bacolod City service boundary.</p>
        </div>

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-xl mb-6 text-sm font-semibold">
                <div class="flex items-center gap-2 mb-1.5 font-bold">
                    <i class="fas fa-exclamation-triangle text-rose-500"></i> Please fix the following errors:
                </div>
                <ul class="list-disc list-inside text-xs space-y-1 ml-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.pharmacies.update', $pharmacy) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Map Location Picker -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6">
                <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-wider mb-1 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-[#2F7E6A]"></i> Location &amp; Barangay Alijis Pin
                </h3>
                <p class="text-xs text-[#64748B] mb-4">
                    Enter the street address or open the interactive map to pin the exact storefront location inside Barangay Alijis.
                </p>

                <div class="mb-4">
                    <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Full Address *</label>
                    <div class="relative">
                        <i class="fas fa-map-pin absolute left-4 top-1/2 transform -translate-y-1/2 text-[#2F7E6A]"></i>
                        <input type="text" name="address" id="address" value="{{ old('address', $pharmacy->address) }}" required
                               placeholder="e.g., Alijis Road, Barangay Alijis, Bacolod City"
                               class="w-full px-4 py-2.5 pl-11 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                    </div>
                </div>

                <div class="mb-4 p-3.5 bg-[#E9F7F2]/60 border border-[#63C6A7]/40 rounded-xl">
                    <button type="button" onclick="openMapModal()" class="w-full py-2.5 bg-[#2F7E6A] hover:bg-[#256655] text-white text-xs font-bold rounded-xl transition flex items-center justify-center gap-2">
                        <i class="fas fa-map-marked-alt"></i> Open Map to Update Pin Location
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-[#64748B] uppercase tracking-wider mb-1.5">Latitude</label>
                        <input type="text" name="latitude" id="latitude" readonly
                               value="{{ old('latitude', $pharmacy->latitude) }}"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 font-mono text-xs text-[#64748B]">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-[#64748B] uppercase tracking-wider mb-1.5">Longitude</label>
                        <input type="text" name="longitude" id="longitude" readonly
                               value="{{ old('longitude', $pharmacy->longitude) }}"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 font-mono text-xs text-[#64748B]">
                    </div>
                </div>
            </div>

            <!-- Pharmacy Details -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6">
                <h3 class="text-sm font-black text-[#0F172A] uppercase tracking-wider mb-4 flex items-center gap-2">
                    <i class="fas fa-store text-[#2F7E6A]"></i> Pharmacy Details
                </h3>

                @php
                    $hours = is_array($pharmacy->operating_hours) ? $pharmacy->operating_hours : ['open' => '8:00 AM', 'close' => '9:00 PM'];
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Pharmacy Name *</label>
                        <input type="text" name="name" value="{{ old('name', $pharmacy->name) }}" required
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Owner Name</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name', $pharmacy->owner_name) }}"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">LTO / License Number</label>
                        <input type="text" name="license_number" value="{{ old('license_number', $pharmacy->license_number ?? $pharmacy->lto_number) }}"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Contact Number *</label>
                        <input type="text" name="phone" value="{{ old('phone', $pharmacy->phone) }}" required
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $pharmacy->email) }}"
                               class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                    </div>

                    <div class="grid grid-cols-2 gap-3 md:col-span-2">
                        <div>
                            <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Opens At</label>
                            <input type="text" name="operating_hours_open" value="{{ old('operating_hours_open', $hours['open'] ?? '8:00 AM') }}"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-1.5">Closes At</label>
                            <input type="text" name="operating_hours_close" value="{{ old('operating_hours_close', $hours['close'] ?? '9:00 PM') }}"
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl focus:outline-none focus:border-[#2F7E6A] text-sm font-medium text-[#0F172A]">
                        </div>
                    </div>

                    <div class="md:col-span-2 mt-2 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1"
                                   {{ old('is_active', $pharmacy->is_active) ? 'checked' : '' }}
                                   class="w-5 h-5 text-[#2F7E6A] rounded-lg border-slate-300 focus:ring-[#2F7E6A] cursor-pointer">
                            <div>
                                <span class="text-sm font-bold text-[#0F172A] block">Pharmacy is Active on Public Map</span>
                                <span class="text-xs text-[#64748B]">Uncheck to temporarily hide this pharmacy from the public map.</span>
                            </div>
                        </label>
                    </div>

                    <div class="md:col-span-2 mt-2">
                        <label class="block text-xs font-bold text-[#0F172A] uppercase tracking-wider mb-2">Storefront Image / Cover Photo (Optional)</label>
                        @if($pharmacy->cover_photo_url)
                            <div class="mb-3">
                                <img src="{{ $pharmacy->cover_photo_url }}" alt="Current Image" class="h-32 max-w-full object-contain bg-slate-50 p-1 rounded-lg border border-slate-200" onerror="this.onerror=null;this.parentElement.classList.add('hidden');">
                            </div>
                        @endif
                        <input type="file" name="cover_photo" accept="image/*"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl bg-white text-xs text-[#64748B] file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-[#E9F7F2] file:text-[#2F7E6A]">
                        <p class="text-[11px] text-[#64748B] mt-1">Upload a storefront cover photo (JPEG, PNG, WEBP, Max 2MB).</p>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pb-8">
                <button type="submit" class="bg-[#2F7E6A] hover:bg-[#256655] text-white px-6 py-3 rounded-xl font-bold text-sm flex items-center gap-2 transition-colors">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="{{ route('admin.pharmacies') }}" class="px-6 py-3 bg-white border border-slate-200 text-[#0F172A] rounded-xl font-bold text-sm hover:bg-slate-50 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- MAP MODAL OVERLAY -->
    <div id="mapModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col h-[80vh]">
            <div class="bg-[#0F172A] p-4 text-white flex justify-between items-center shrink-0">
                <h3 class="font-bold text-sm uppercase tracking-wider"><i class="fas fa-map-marker-alt text-[#63C6A7] mr-2"></i> Update Exact Location</h3>
                <button type="button" onclick="closeMapModal()" class="text-slate-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="p-2 flex-1 relative">
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 z-[400] bg-white/95 px-4 py-2 rounded-full shadow-md text-xs font-bold text-[#0F172A] border border-slate-200 pointer-events-none">
                    Drag the pin to update the pharmacy's exact storefront coordinates
                </div>
                <div id="locationMap" class="w-full h-full rounded-xl border border-slate-200 z-0"></div>
            </div>
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeMapModal()" class="px-6 py-2.5 bg-[#2F7E6A] hover:bg-[#256655] text-white text-xs font-bold rounded-xl transition-all">
                    Confirm Location
                </button>
            </div>
        </div>
    </div>

    <script>
        const pharmacyLat = {{ $pharmacy->latitude }};
        const pharmacyLng = {{ $pharmacy->longitude }};
        let map = null;
        let marker = null;
        const addressInput = document.getElementById('address');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');

        const pharmacyIcon = L.divIcon({
            html: '<div class="marker-pin"></div>',
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 30]
        });

        function initMap() {
            if (map !== null) return;
            const startLat = parseFloat(latInput.value) || pharmacyLat || 10.6407;
            const startLng = parseFloat(lngInput.value) || pharmacyLng || 122.9463;
            map = L.map('locationMap').setView([startLat, startLng], 16);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            marker = L.marker([startLat, startLng], { icon: pharmacyIcon, draggable: true }).addTo(map);
            marker.on('dragend', function() {
                const pos = marker.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
            });
            map.on('click', function(e) {
                updateCoordinates(e.latlng.lat, e.latlng.lng);
            });
        }

        function openMapModal() {
            document.getElementById('mapModal').classList.remove('hidden');
            if (!map) {
                initMap();
            } else {
                setTimeout(() => map.invalidateSize(), 100);
            }
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
        }

        function updateCoordinates(lat, lng) {
            if (marker) marker.setLatLng([lat, lng]);
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
        }
    </script>
@endsection
