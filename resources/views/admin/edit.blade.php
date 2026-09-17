<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pharmacy - Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .marker-pin {
            width: 30px;
            height: 30px;
            background: #3b82f6;
            border: 3px solid white;
            border-radius: 50% 50% 50% 0;
            transform: rotate(-45deg);
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script></head>
<body class="bg-slate-50 min-h-screen">
    <nav class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <i class="fas fa-edit text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Edit Pharmacy</h1>
                    <p class="text-blue-200 text-xs font-medium">Super Admin Panel</p>  
                </div>
            </div>
            <a href="{{ route('admin.pharmacies') }}" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i>Back to List
            </a>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 shadow-sm">
                <div class="flex items-center gap-2 mb-2 font-bold">
                    <i class="fas fa-exclamation-triangle text-red-500"></i> Please fix the following errors:
                </div>
                <ul class="list-disc list-inside text-sm space-y-1 ml-2">
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
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-blue-600"></i>
                    Location & Address
                </h3>
                <p class="text-sm text-slate-500 mb-4">
                    Type the address below to move the map, or use the map button to precisely pin the location.
                </p>

                <div class="mb-4">
                    <label class="block text-sm font-semibold text-slate-600 mb-2">Full Address *</label>
                    <div class="relative">
                        <i class="fas fa-map-pin absolute left-4 top-1/2 transform -translate-y-1/2 text-blue-500"></i>
                        <input type="text" name="address" id="address" value="{{ old('address', $pharmacy->address) }}" required
                               placeholder="e.g., Lacson St, Bacolod City"
                               class="w-full px-4 py-3 pl-11 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-700">
                    </div>
                </div>

                <!-- Open Map Modal Button -->
                <div class="mb-4 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                    <button type="button" onclick="openMapModal()" class="w-full py-3 bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold border border-blue-300 rounded-xl transition duration-200 flex items-center justify-center gap-2">
                        <i class="fas fa-map-marked-alt text-lg"></i>
                        Open Map to Update Pin Location
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Latitude</label>
                        <input type="text" name="latitude" id="latitude" readonly
                               value="{{ old('latitude', $pharmacy->latitude) }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 font-mono text-sm text-slate-500">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Longitude</label>
                        <input type="text" name="longitude" id="longitude" readonly
                               value="{{ old('longitude', $pharmacy->longitude) }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 font-mono text-sm text-slate-500">
                    </div>
                </div>
            </div>

            <!-- Pharmacy Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-store text-blue-600"></i>
                    Pharmacy Details
                </h3>

                @php
                    $hours = is_array($pharmacy->operating_hours) ? $pharmacy->operating_hours : ['open' => '8:00 AM', 'close' => '9:00 PM'];
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Pharmacy Name *</label>
                        <input type="text" name="name" value="{{ old('name', $pharmacy->name) }}" required
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Owner Name</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name', $pharmacy->owner_name) }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">LTO / License Number</label>
                        <input type="text" name="license_number" value="{{ old('license_number', $pharmacy->license_number ?? $pharmacy->lto_number) }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Contact Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $pharmacy->phone) }}" required
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $pharmacy->email) }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div class="grid grid-cols-2 gap-2 md:col-span-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-600 mb-2">Opens At</label>
                            <input type="text" name="operating_hours_open" value="{{ old('operating_hours_open', $hours['open']) }}"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-600 mb-2">Closes At</label>
                            <input type="text" name="operating_hours_close" value="{{ old('operating_hours_close', $hours['close']) }}"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>

                    <div class="md:col-span-2 mt-2 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                        <label class="flex items-center gap-3 cursor-pointer group">
                            <input type="checkbox" name="is_active" value="1" 
                                   {{ old('is_active', $pharmacy->is_active) ? 'checked' : '' }}
                                   class="w-5 h-5 text-blue-600 rounded-lg border-slate-300 focus:ring-blue-500 cursor-pointer">
                            <div>
                                <span class="text-sm font-bold text-slate-800 block">Pharmacy is Active</span>
                                <span class="text-xs text-slate-500">Uncheck this to temporarily hide the pharmacy from the public map.</span>
                            </div>
                        </label>
                    </div>

                    <div class="md:col-span-2 mt-4">
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Storefront Image / Cover Photo (Optional)</label>
                        @if($pharmacy->cover_photo)
                            <div class="mb-3">
                                <img src="/storage/{{ $pharmacy->cover_photo }}" alt="Current Image" class="h-32 rounded-lg border border-slate-200">
                            </div>
                        @endif
                        <input type="file" name="cover_photo" accept="image/*"
                               class="w-full px-4 py-2 border border-slate-200 rounded-xl bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-slate-500 mt-1">Upload a photo of the pharmacy's storefront. Maximum size: 2MB.</p>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex gap-4 pb-12">
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-4 rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-md hover:shadow-lg">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <a href="{{ route('admin.pharmacies') }}" 
                   class="px-8 py-4 bg-white border border-slate-200 text-slate-700 rounded-xl font-bold hover:bg-slate-50 transition-all shadow-sm">
                    Cancel
                </a>
            </div>
        </form>
    </main>

    <!-- MAP MODAL OVERLAY -->
    <div id="mapModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col h-[80vh]">
            
            <!-- Modal Header -->
            <div class="bg-blue-600 p-4 text-white flex justify-between items-center shrink-0">
                <h3 class="font-bold text-lg"><i class="fas fa-map-marker-alt mr-2"></i> Update Exact Location</h3>
                <button type="button" onclick="closeMapModal()" class="text-blue-200 hover:text-white transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <!-- Modal Body (The Map) -->
            <div class="p-2 flex-1 relative">
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 z-[400] bg-white/90 backdrop-blur px-4 py-2 rounded-full shadow-md text-sm font-bold text-slate-700 border border-slate-200 pointer-events-none">
                    Drag the pin to update the pharmacy's exact door
                </div>
                <div id="locationMap" class="w-full h-full rounded-xl border border-slate-200 z-0"></div>
            </div>
            
            <!-- Modal Footer -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeMapModal()" class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-md">
                    Confirm Location
                </button>
            </div>
            
        </div>
    </div>

    <script>
        // Existing pharmacy data
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

        // 1. Initialize Map Function
        function initMap() {
            if (map !== null) return; // Prevent double initialization

            const startLat = parseFloat(latInput.value) || pharmacyLat || 10.6765;
            const startLng = parseFloat(lngInput.value) || pharmacyLng || 122.9509;

            map = L.map('locationMap').setView([startLat, startLng], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            marker = L.marker([startLat, startLng], {
                icon: pharmacyIcon,
                draggable: true
            }).addTo(map);

            // Event: Marker Drag
            marker.on('dragend', function(e) {
                const pos = marker.getLatLng();
                updateCoordinates(pos.lat, pos.lng);
                fetchAddress(pos.lat, pos.lng);
            });

            // Event: Map Click
            map.on('click', function(e) {
                updateCoordinates(e.latlng.lat, e.latlng.lng);
                fetchAddress(e.latlng.lat, e.latlng.lng);
            });
        }

        // 2. Modal Controls
        function openMapModal() {
            document.getElementById('mapModal').classList.remove('hidden');
            
            // Initialize map if it doesn't exist yet
            if (!map) {
                initMap();
            } else {
                // If it exists, Leaflet needs to recalculate its size because it was hidden
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
        }

        // 3. Update coordinates function
        function updateCoordinates(lat, lng) {
            if (marker) marker.setLatLng([lat, lng]);
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
        }

        // 4. Fetch address from coordinates (Reverse Geocoding)
        function fetchAddress(lat, lng) {
            addressInput.value = "Locating address...";
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.display_name) {
                        addressInput.value = data.display_name;
                    } else {
                        addressInput.value = "Address not found";
                    }
                })
                .catch(err => {
                    console.error("Geocoding error:", err);
                    addressInput.value = "";
                });
        }

        // 5. Event: User Types Address (Smart Geocoding)
        addressInput.addEventListener('change', function(e) {
            const query = e.target.value;
            // Ignore if it's loading text or empty
            if (!query || query === "Locating address..." || query === "Address not found") return;

            // Automatically append Philippines for better accuracy
            const searchQuery = query + ", Philippines";

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        
                        updateCoordinates(lat, lng);
                        
                        // If map is initialized, move it to new location
                        if (map) {
                            map.flyTo([lat, lng], 16);
                        }
                    } else {
                        alert("⚠️ We couldn't automatically find that exact address on the map.\n\nTry clicking 'Open Map to Update Pin Location' and placing the pin manually.");
                    }
                })
                .catch(err => console.error("Search error:", err));
        });
    </script>
</body>
</html>
