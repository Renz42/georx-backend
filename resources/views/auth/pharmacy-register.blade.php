<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Registration - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#E9F7F2] min-h-screen py-10 px-4">
    <div class="max-w-4xl mx-auto">
        
        <div class="text-center mb-10">
            <div class="w-16 h-16 bg-[#63C6A7] text-[#1F2E2C] rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-[#63C6A7]/20 rotate-3 transition-transform hover:rotate-0 duration-300">
                <i class="fas fa-store-medical text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-[#1F2E2C] tracking-tight mb-2">Partner Your Pharmacy</h1>
            <p class="text-[#2F7E6A] font-medium text-sm px-4">Join our network and help Bacolod City stay healthy.</p>
        </div>

        <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(191,232,214,0.6)] p-8 md:p-10 border-t-4 border border-[#BFE8D6]/50 border-t-[#63C6A7]">
            
            @if($errors->any())
                <div class="bg-red-50 border border-red-100 text-red-600 px-5 py-4 rounded-xl mb-8 text-sm font-medium">
                    <div class="flex items-center gap-2 mb-2 font-bold text-red-700">
                        <i class="fas fa-exclamation-circle text-red-500 text-lg"></i> Please fix the following errors:
                    </div>
                    <ul class="list-disc list-inside space-y-1 ml-6">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('pharmacy.register') }}">
                @csrf

                <div class="mb-10">
                    <h3 class="text-xl font-extrabold text-[#1F2E2C] mb-6 pb-3 border-b-2 border-[#E9F7F2] flex items-center gap-3">
                        <div class="bg-[#E9F7F2] p-2 rounded-lg"><i class="fas fa-user-shield text-[#63C6A7]"></i></div>
                        Account Setup
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Admin Name</label>
                            <div class="relative group">
                                <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="text" name="admin_name" value="{{ old('admin_name') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="Your full name">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Admin Email</label>
                            <div class="relative group">
                                <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="email" name="admin_email" value="{{ old('admin_email') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="admin@pharmacy.com">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Password</label>
                            <div class="relative group">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="password" name="password" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="••••••••">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Confirm Password</label>
                            <div class="relative group">
                                <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="password" name="password_confirmation" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="••••••••">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-10">
                    <h3 class="text-xl font-extrabold text-[#1F2E2C] mb-6 pb-3 border-b-2 border-[#E9F7F2] flex items-center gap-3">
                        <div class="bg-[#E9F7F2] p-2 rounded-lg"><i class="fas fa-clinic-medical text-[#63C6A7]"></i></div>
                        Pharmacy Profile
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Pharmacy Name</label>
                            <div class="relative group">
                                <i class="fas fa-store absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="text" name="pharmacy_name" value="{{ old('pharmacy_name') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="e.g., Mercury Drug - SM City Bacolod">
                            </div>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Address (Bacolod City)</label>
                            <div class="relative group">
                                <i class="fas fa-map-marker-alt absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="text" name="address" id="addressInput" value="{{ old('address') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="e.g., Lacson Street, Bacolod City">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Phone Number</label>
                            <div class="relative group">
                                <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="text" name="phone" value="{{ old('phone') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="(034) XXX-XXXX">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Email (Public)</label>
                            <div class="relative group">
                                <i class="fas fa-at absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="email" name="email" value="{{ old('email') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="info@pharmacy.com">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Owner Name</label>
                            <div class="relative group">
                                <i class="fas fa-user-tie absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="text" name="owner_name" value="{{ old('owner_name') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="Owner's full name">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">LTO / License Number</label>
                            <div class="relative group">
                                <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                <input type="text" name="lto_number" value="{{ old('lto_number') }}" required
                                    class="w-full pl-11 pr-4 py-3.5 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium placeholder:text-[#1F2E2C]/40"
                                    placeholder="FDA License to Operate">
                            </div>
                        </div>
                        
                        <!-- ✨ FIXED OPERATING HOURS BLOCK ✨ -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-[#1F2E2C] mb-2">Operating Hours</label>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="relative group">
                                    <label class="block text-[10px] font-black text-[#1F2E2C]/60 uppercase tracking-widest mb-1.5 ml-1">Opening Time</label>
                                    <i class="fas fa-sun absolute left-4 top-[38px] text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                    <input type="time" name="open_time" value="{{ old('open_time') }}" required
                                        class="w-full pl-11 pr-4 py-3 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium">
                                </div>
                                <div class="relative group">
                                    <label class="block text-[10px] font-black text-[#1F2E2C]/60 uppercase tracking-widest mb-1.5 ml-1">Closing Time</label>
                                    <i class="fas fa-moon absolute left-4 top-[38px] text-[#63C6A7] group-focus-within:text-[#2F7E6A] transition-colors"></i>
                                    <input type="time" name="close_time" value="{{ old('close_time') }}" required
                                        class="w-full pl-11 pr-4 py-3 bg-[#E9F7F2]/50 border border-[#BFE8D6] rounded-xl focus:ring-2 focus:ring-[#63C6A7]/50 focus:border-[#63C6A7] focus:bg-white outline-none text-[#1F2E2C] transition-all font-medium">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-10 p-6 bg-[#E9F7F2]/40 border border-[#BFE8D6] rounded-2xl">
                    <h3 class="text-xl font-extrabold text-[#1F2E2C] mb-2 flex items-center gap-2">
                        <i class="fas fa-map-pin text-[#63C6A7]"></i> Pin Your Location
                    </h3>
                    <p class="text-sm font-medium text-[#1F2E2C]/60 mb-5">Help patients find your exact storefront on the map.</p>
                    
                    <button type="button" onclick="openMapModal()" class="w-full py-4 bg-white border-2 border-[#63C6A7] hover:bg-[#E9F7F2] text-[#2F7E6A] font-bold rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 mb-6">
                        <i class="fas fa-map-marked-alt text-xl"></i>
                        Open Map to Pin Location
                    </button>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-[#1F2E2C]/50 uppercase tracking-widest mb-1.5">Latitude</label>
                            <input type="text" name="latitude" id="latitude" value="{{ old('latitude', '10.6765') }}" required readonly
                                class="w-full px-4 py-2.5 bg-gray-50/50 border border-[#BFE8D6]/50 rounded-lg text-sm text-[#1F2E2C]/70 font-mono focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-[#1F2E2C]/50 uppercase tracking-widest mb-1.5">Longitude</label>
                            <input type="text" name="longitude" id="longitude" value="{{ old('longitude', '122.9509') }}" required readonly
                                class="w-full px-4 py-2.5 bg-gray-50/50 border border-[#BFE8D6]/50 rounded-lg text-sm text-[#1F2E2C]/70 font-mono focus:outline-none">
                        </div>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-[#2F7E6A] hover:bg-[#1F2E2C] text-[#E9F7F2] font-bold py-4 rounded-xl shadow-lg shadow-[#2F7E6A]/20 transition-all flex justify-center items-center gap-2 transform active:scale-[0.98] text-lg mt-4">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-[#E9F7F2] text-center">
                <p class="text-[#1F2E2C]/60 text-sm mb-3 font-medium">Already registered?</p>
                <a href="{{ route('login') }}" class="text-[#2F7E6A] hover:text-[#63C6A7] font-bold transition-colors">
                    Sign In to Portal
                </a>
            </div>
        </div>

        <div class="text-center mt-8 mb-10">
            <a href="{{ url('/') }}" class="text-[#2F7E6A] hover:text-[#1F2E2C] font-semibold text-sm transition-colors flex items-center justify-center gap-2 group">
                <i class="fas fa-arrow-left transform group-hover:-translate-x-1 transition-transform"></i> Back to Medicine Search
            </a>
        </div>
    </div>

    <!-- Map Modal -->
    <div id="mapModal" class="fixed inset-0 bg-[#1F2E2C]/70 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden flex flex-col h-[80vh] border border-[#BFE8D6]">
            
            <div class="bg-[#2F7E6A] p-5 text-[#E9F7F2] flex justify-between items-center shrink-0 border-b border-[#1F2E2C]/20">
                <h3 class="font-bold text-lg"><i class="fas fa-map-marker-alt text-[#63C6A7] mr-2"></i> Pin Location (Barangay Alijis, Bacolod City)</h3>
                <button type="button" onclick="closeMapModal()" class="text-[#BFE8D6] hover:text-white transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <div class="p-2 flex-1 relative bg-[#E9F7F2]/30">
                <div class="absolute top-4 left-1/2 transform -translate-x-1/2 z-[400] bg-white/95 backdrop-blur px-5 py-2.5 rounded-full shadow-lg text-sm font-bold text-[#1F2E2C] border border-[#BFE8D6] pointer-events-none flex items-center gap-2">
                    <i class="fas fa-mouse-pointer text-[#63C6A7]"></i> Drag the pin to your pharmacy's exact door
                </div>
                <div id="map" class="w-full h-full rounded-xl border border-[#BFE8D6] z-0 shadow-inner"></div>
            </div>
            
            <div class="p-4 bg-[#E9F7F2] border-t border-[#BFE8D6] flex justify-end gap-3 shrink-0">
                <button type="button" onclick="closeMapModal()" class="px-8 py-3.5 bg-[#2F7E6A] hover:bg-[#1F2E2C] text-[#E9F7F2] font-bold rounded-xl transition-all shadow-md active:scale-95">
                    Confirm Location
                </button>
            </div>
            
        </div>
    </div>

    <script>
        let map = null;
        let marker = null;
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const addressInput = document.getElementById('addressInput');

        const alijisBounds = [
            [10.6050, 122.9200],
            [10.6750, 122.9850] 
        ];
        const alijisCenter = [10.6385, 122.9520];

        function initMap() {
            if (map !== null) return; 

            const startLat = parseFloat(latInput.value) || 10.6385;
            const startLng = parseFloat(lngInput.value) || 122.9520;

            map = L.map('map', {
                maxBounds: alijisBounds,
                maxBoundsViscosity: 1.0, 
                minZoom: 14,
                maxZoom: 18
            }).setView([startLat, startLng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap GEORX',
                maxZoom: 18,
                minZoom: 14
            }).addTo(map);

            L.circle(alijisCenter, {
                radius: 1200,
                color: '#2F7E6A',
                weight: 2.5,
                dashArray: '5, 5',
                fillColor: '#63C6A7',
                fillOpacity: 0.15
            }).addTo(map);

            marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

            marker.on('dragend', function(e) {
                const pos = marker.getLatLng();
                if (!L.latLngBounds(alijisBounds).contains(pos)) {
                    alert("Please select a location within Barangay Alijis, Bacolod City.");
                    marker.setLatLng(alijisCenter); 
                    updateCoordinates(alijisCenter[0], alijisCenter[1]);
                    fetchAddress(alijisCenter[0], alijisCenter[1]);
                    return;
                }
                updateCoordinates(pos.lat, pos.lng);
                fetchAddress(pos.lat, pos.lng);
            });

            map.on('click', function(e) {
                if (!L.latLngBounds(alijisBounds).contains(e.latlng)) {
                    alert("Please select a location within Barangay Alijis, Bacolod City.");
                    return;
                }
                updateCoordinates(e.latlng.lat, e.latlng.lng);
                fetchAddress(e.latlng.lat, e.latlng.lng);
            });
        }

        function openMapModal() {
            document.getElementById('mapModal').classList.remove('hidden');
            if (!map) {
                initMap();
            } else {
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
        }

        function updateCoordinates(lat, lng) {
            if(marker) marker.setLatLng([lat, lng]);
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
        }

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

        addressInput.addEventListener('change', function(e) {
            const query = e.target.value;
            if (!query || query === "Locating address..." || query === "Address not found") return;

            const searchQuery = query + ", Barangay Alijis, Bacolod City, Negros Occidental, Philippines";

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        
                        if (!L.latLngBounds(alijisBounds).contains([lat, lng])) {
                            alert("We found that address, but it seems to be outside of Barangay Alijis.\n\nPlease provide a local address within Barangay Alijis or open the map to pin it manually.");
                            return;
                        }

                        updateCoordinates(lat, lng);
                        if (map) {
                            map.flyTo([lat, lng], 16);
                        }
                    } else {
                        alert("We couldn't automatically find that exact address in Barangay Alijis, Bacolod City.\n\nTry clicking 'Open Map to Pin Location' and placing the pin manually.");
                    }
                })
                .catch(err => console.error("Search error:", err));
        });
    </script>
</body>
</html>
