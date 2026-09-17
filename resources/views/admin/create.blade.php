<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Pharmacy - Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body { font-family: 'Outfit', sans-serif; }
        #locationMap { height: 450px; border-radius: 16px; }
        
        /* Main marker with pulsing effect */
        .marker-container {
            position: relative;
            width: 50px;
            height: 50px;
        }
        .marker-pin {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 24px;
            height: 24px;
            background: #3b82f6;
            border: 3px solid white;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.6);
            z-index: 2;
        }
        
        /* Pulsing ring effect */
        .pulse-ring {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 60px;
            height: 60px;
            border: 3px solid rgba(59, 130, 246, 0.6);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: pulse-animation 2s ease-out infinite;
            z-index: 1;
        }
        .pulse-ring-2 { animation-delay: 0.5s; }
        .pulse-ring-3 { animation-delay: 1s; }
        
        @keyframes pulse-animation {
            0% {
                width: 30px;
                height: 30px;
                opacity: 1;
                border-color: rgba(59, 130, 246, 0.8);
            }
            100% {
                width: 120px;
                height: 120px;
                opacity: 0;
                border-color: rgba(59, 130, 246, 0);
            }
        }
        
        /* Loading overlay */
        .map-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: 16px;
            backdrop-filter: blur(4px);
        }
        .map-loading.hidden { display: none; }
        
        /* Location info tooltip */
        .location-tooltip {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(15, 23, 42, 0.95);
            color: white;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        .location-tooltip.hidden { display: none; }
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
                    <i class="fas fa-plus-circle text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Add New Pharmacy</h1>
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

        <form action="{{ route('admin.pharmacies.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Map Location Picker -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-blue-600"></i>
                    Pick Location on Map
                </h3>
                <p class="text-sm text-slate-500 mb-4">
                    Search for an address or click on the map to set the pharmacy location.
                </p>

                <!-- Address Search -->
                <div class="flex gap-2 mb-4">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="addressSearch" placeholder="Search address in Bacolod City..."
                               class="w-full px-4 py-3 pl-11 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>
                    <button type="button" id="searchAddressBtn" 
                            class="px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl font-semibold transition-all shadow-sm">
                        <i class="fas fa-location-crosshairs mr-2"></i>Find
                    </button>
                </div>

                <!-- Map Container -->
                <div class="relative">
                    <div id="locationMap" class="mb-4 border border-slate-200"></div>
                    
                    <!-- Loading Overlay -->
                    <div id="mapLoading" class="map-loading hidden">
                        <div class="text-center text-white">
                            <i class="fas fa-spinner fa-spin text-4xl mb-2"></i>
                            <p class="font-medium">Searching location...</p>
                        </div>
                    </div>
                    
                    <!-- Location Info Tooltip -->
                    <div id="locationTooltip" class="location-tooltip hidden">
                        <div class="flex items-center gap-3">
                            <div class="w-3 h-3 bg-blue-400 rounded-full animate-pulse"></div>
                            <div>
                                <div class="font-semibold" id="tooltipTitle">Location Selected</div>
                                <div class="text-sm text-slate-300" id="tooltipAddress">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Latitude</label>
                        <input type="text" name="latitude" id="latitude" readonly
                               value="{{ old('latitude', '10.6765') }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Longitude</label>
                        <input type="text" name="longitude" id="longitude" readonly
                               value="{{ old('longitude', '122.9509') }}"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl bg-slate-50 font-mono text-sm">
                    </div>
                </div>
            </div>

            <!-- Pharmacy Details -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <i class="fas fa-store text-blue-600"></i>
                    Pharmacy Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Pharmacy Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                               placeholder="e.g., TGP Pharmacy"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Full Address *</label>
                        <input type="text" name="address" id="address" value="{{ old('address') }}" required
                               placeholder="e.g., SSI Bldg, Alijis Rd, Bacolod City"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Owner Name</label>
                        <input type="text" name="owner_name" value="{{ old('owner_name') }}"
                               placeholder="e.g., Ma. Jovita G. Segura"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Contact Number *</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" required
                               placeholder="e.g., 09940062886"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-600 mb-2">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}"
                               placeholder="e.g., pharmacy@gmail.com"
                               class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-sm font-semibold text-slate-600 mb-2">Opens At</label>
                            <input type="text" name="operating_hours_open" value="{{ old('operating_hours_open', '8:00 AM') }}"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-600 mb-2">Closes At</label>
                            <input type="text" name="operating_hours_close" value="{{ old('operating_hours_close', '9:00 PM') }}"
                                   class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex gap-4">
                <button type="submit" 
                        class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-4 rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-sm hover:shadow">
                    <i class="fas fa-save"></i>Save Pharmacy
                </button>
                <a href="{{ route('admin.pharmacies') }}" 
                   class="px-6 py-4 bg-slate-100 text-slate-700 rounded-xl font-semibold hover:bg-slate-200 transition-all">
                    Cancel
                </a>
            </div>
        </form>
    </main>

    <script>
        // Initialize map centered on Bacolod City
        const bacolodCenter = [10.6765, 122.9509];
        const map = L.map('locationMap').setView(bacolodCenter, 14);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Custom pulsing marker icon
        const createPulsingMarker = () => {
            return L.divIcon({
                html: `
                    <div class="marker-container">
                        <div class="pulse-ring"></div>
                        <div class="pulse-ring pulse-ring-2"></div>
                        <div class="pulse-ring pulse-ring-3"></div>
                        <div class="marker-pin"></div>
                    </div>
                `,
                className: '',
                iconSize: [50, 50],
                iconAnchor: [25, 25]
            });
        };

        // Create draggable marker with pulsing effect
        let marker = L.marker(bacolodCenter, {
            icon: createPulsingMarker(),
            draggable: true
        }).addTo(map);

        // Tooltip elements
        const tooltip = document.getElementById('locationTooltip');
        const tooltipTitle = document.getElementById('tooltipTitle');
        const tooltipAddress = document.getElementById('tooltipAddress');
        const mapLoading = document.getElementById('mapLoading');

        // Show location tooltip
        function showTooltip(title, address) {
            tooltipTitle.textContent = title;
            tooltipAddress.textContent = address;
            tooltip.classList.remove('hidden');
        }

        // Reverse geocode to get address from coordinates
        async function reverseGeocode(lat, lng) {
            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`
                );
                const data = await response.json();
                if (data.display_name) {
                    const shortAddress = data.display_name.split(',').slice(0, 3).join(', ');
                    showTooltip('📍 Location Selected', shortAddress);
                    return data.display_name;
                }
            } catch (error) {
                console.error('Reverse geocode error:', error);
            }
            showTooltip('📍 Location Selected', `${lat.toFixed(6)}, ${lng.toFixed(6)}`);
            return null;
        }

        // Update coordinates when marker is dragged
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('latitude').value = pos.lat.toFixed(6);
            document.getElementById('longitude').value = pos.lng.toFixed(6);
            reverseGeocode(pos.lat, pos.lng);
        });

        // Click on map to move marker
        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('latitude').value = e.latlng.lat.toFixed(6);
            document.getElementById('longitude').value = e.latlng.lng.toFixed(6);
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });

        // Search address using Nominatim
        document.getElementById('searchAddressBtn').addEventListener('click', searchAddress);
        document.getElementById('addressSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchAddress();
            }
        });

        async function searchAddress() {
            const query = document.getElementById('addressSearch').value.trim();
            if (!query) return;

            mapLoading.classList.remove('hidden');
            tooltip.classList.add('hidden');

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Bacolod City, Negros Occidental, Philippines')}&limit=5`
                );
                const data = await response.json();

                mapLoading.classList.add('hidden');

                if (data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);

                    map.flyTo([lat, lng], 18, {
                        duration: 1.5,
                        easeLinearity: 0.5
                    });

                    setTimeout(() => {
                        marker.setLatLng([lat, lng]);
                        document.getElementById('latitude').value = lat.toFixed(6);
                        document.getElementById('longitude').value = lng.toFixed(6);
                        
                        const shortAddress = data[0].display_name.split(',').slice(0, 4).join(',');
                        document.getElementById('address').value = shortAddress;
                        
                        showTooltip('✅ Location Found', shortAddress);
                    }, 800);
                } else {
                    alert('Address not found. Try:\n• "Lacson Street"\n• "SM City Bacolod"\n• "Robinson\'s Bacolod"\n• Or click directly on the map');
                }
            } catch (error) {
                mapLoading.classList.add('hidden');
                console.error('Search error:', error);
                alert('Failed to search address. Please click on the map to set location.');
            }
        }

        // Show initial tooltip
        showTooltip('📍 Bacolod City Center', 'Drag marker or search to set location');
    </script>
</body>
</html>
