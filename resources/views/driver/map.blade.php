<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Delivery Map #{{ $delivery->id }} — GEORX Driver</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        rider: { 950: '#0D0F1A', 900: '#111827', 800: '#1C2333', 600: '#3B5BDB', 500: '#4C6EF5', 400: '#748FFC' }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        #delivery-map { height: calc(100vh - 72px); width: 100%; }
        .custom-pin-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4);
        }
    </style>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen overflow-hidden flex flex-col">

    {{-- Top Navigation Bar --}}
    <nav class="bg-rider-950 text-white h-[72px] flex items-center px-6 shadow-xl sticky top-0 z-50 border-b border-rider-600/20 shrink-0">
        <div class="w-full flex justify-between items-center max-w-[1400px] mx-auto">
            <div class="flex items-center gap-3">
                <a href="{{ route('driver.dashboard') }}" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-xl flex items-center justify-center transition-colors">
                    <i class="fas fa-arrow-left text-sm"></i>
                </a>
                <div class="w-10 h-10 bg-gradient-to-br from-rider-600 to-violet-600 rounded-xl flex items-center justify-center shadow-lg">
                    <i class="fas fa-route text-lg text-white"></i>
                </div>
                <div>
                    <h1 class="text-base font-black leading-tight flex items-center gap-2">
                        Delivery Map #{{ $delivery->id }}
                        <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-0.5 rounded-full bg-amber-400/20 text-amber-300 border border-amber-400/30">
                            {{ str_replace('_', ' ', $delivery->status) }}
                        </span>
                    </h1>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Barangay Alijis Service Area</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('driver.bookings.show', $delivery->id) }}" class="text-xs font-bold text-rider-300 hover:text-white transition-colors bg-white/5 px-3 py-2 rounded-xl border border-white/10 flex items-center gap-1.5">
                    <i class="fas fa-info-circle"></i> Details
                </a>
            </div>
        </div>
    </nav>

    {{-- Map Container & Floating Overlay Cards --}}
    <div class="relative flex-1 w-full overflow-hidden">

        {{-- Leaflet Map Element --}}
        <div id="delivery-map" class="z-0"></div>

        {{-- GPS Graceful Alert Banner --}}
        <div id="gps-alert-banner" class="hidden absolute top-4 left-1/2 -translate-x-1/2 z-20 max-w-md w-full px-4">
            <div class="bg-slate-900/90 backdrop-blur-md border border-amber-500/30 text-amber-300 px-4 py-3 rounded-2xl text-xs font-bold flex items-center justify-between shadow-2xl">
                <div class="flex items-center gap-2">
                    <i class="fas fa-location-crosshairs text-amber-400"></i>
                    <span id="gps-alert-text">Using fixed pickup and delivery coordinates.</span>
                </div>
                <button onclick="document.getElementById('gps-alert-banner').classList.add('hidden')" class="text-slate-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- Floating Bottom Action Bar --}}
        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 max-w-2xl w-[92%] bg-rider-950/90 backdrop-blur-xl border border-rider-600/30 rounded-3xl p-5 shadow-2xl space-y-4">

            {{-- Step Indicator Header --}}
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <div class="flex items-center gap-2.5">
                    @if(in_array($delivery->status, ['driver_assigned', 'driver_at_pharmacy']))
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-xs border border-emerald-500/30">
                            1
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">STEP 1 OF 2</p>
                            <p class="font-black text-sm text-white">Go to Pickup Pharmacy</p>
                        </div>
                    @else
                        <div class="w-8 h-8 rounded-lg bg-blue-500/20 text-blue-400 flex items-center justify-center font-black text-xs border border-blue-500/30">
                            2
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">STEP 2 OF 2</p>
                            <p class="font-black text-sm text-white">Deliver Medicine to Customer</p>
                        </div>
                    @endif
                </div>

                {{-- Distance Badges --}}
                <div class="flex items-center gap-2">
                    @if($delivery->pickup_distance !== null)
                        <span class="text-xs font-black px-2.5 py-1 rounded-xl bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <i class="fas fa-location-arrow text-[10px] mr-1"></i>{{ $delivery->pickup_distance }} km to pickup
                        </span>
                    @endif
                    @if($delivery->delivery_distance !== null)
                        <span class="text-xs font-black px-2.5 py-1 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20">
                            <i class="fas fa-route text-[10px] mr-1"></i>{{ $delivery->delivery_distance }} km dropoff
                        </span>
                    @endif
                </div>
            </div>

            {{-- Target Information Card --}}
            @if(in_array($delivery->status, ['driver_assigned', 'driver_at_pharmacy']))
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-white/5 p-4 rounded-2xl border border-white/10">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-emerald-400 uppercase tracking-widest">🏥 PICKUP PHARMACY</p>
                        <p class="font-black text-white text-base truncate">{{ $delivery->pickupPharmacy->name }}</p>
                        <p class="text-xs text-slate-300 truncate">{{ $delivery->pickupPharmacy->address }}</p>
                    </div>

                    {{-- Navigation Actions --}}
                    @if($delivery->pickup_latitude && $delivery->pickup_longitude)
                        <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto">
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->pickup_latitude }},{{ $delivery->pickup_longitude }}"
                               target="_blank" rel="noopener"
                               class="flex-1 sm:flex-none px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 shadow-lg shadow-emerald-600/20">
                                <i class="fas fa-map-marked-alt"></i> Google Maps
                            </a>
                            <a href="https://waze.com/ul?ll={{ $delivery->pickup_latitude }},{{ $delivery->pickup_longitude }}&navigate=yes"
                               target="_blank" rel="noopener"
                               class="flex-1 sm:flex-none px-3.5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 shadow-lg shadow-blue-600/20">
                                <i class="fas fa-location-arrow"></i> Waze
                            </a>
                        </div>
                    @endif
                </div>
            @else
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 bg-white/5 p-4 rounded-2xl border border-white/10">
                    <div class="min-w-0">
                        <p class="text-[10px] font-black text-blue-400 uppercase tracking-widest">CUSTOMER DELIVERY LOCATION</p>
                        <p class="font-black text-white text-base truncate">{{ $delivery->customer->name ?? 'Customer' }} ({{ $delivery->customer->phone ?? 'N/A' }})</p>
                        <p class="text-xs text-slate-300 leading-snug">{{ $delivery->delivery_address }}</p>
                    </div>

                    {{-- Navigation Actions --}}
                    @if($delivery->delivery_latitude && $delivery->delivery_longitude)
                        <div class="flex items-center gap-2 shrink-0 w-full sm:w-auto">
                            <a href="https://www.google.com/maps/dir/?api=1&destination={{ $delivery->delivery_latitude }},{{ $delivery->delivery_longitude }}"
                               target="_blank" rel="noopener"
                               class="flex-1 sm:flex-none px-3.5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 shadow-lg shadow-blue-600/20">
                                <i class="fas fa-map-marked-alt"></i> Google Maps
                            </a>
                            <a href="https://waze.com/ul?ll={{ $delivery->delivery_latitude }},{{ $delivery->delivery_longitude }}&navigate=yes"
                               target="_blank" rel="noopener"
                               class="flex-1 sm:flex-none px-3.5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 shadow-lg shadow-indigo-600/20">
                                <i class="fas fa-location-arrow"></i> Waze
                            </a>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Progression Action Button --}}
            @php
                $actionButton = match($delivery->status) {
                    'driver_assigned'   => ['driver_at_pharmacy', 'ARRIVED AT PHARMACY', 'fas fa-store', 'bg-emerald-600 hover:bg-emerald-500'],
                    'driver_at_pharmacy' => ['picked_up', 'CONFIRM MEDICINE PICKED UP', 'fas fa-box', 'bg-teal-600 hover:bg-teal-500'],
                    'picked_up'         => ['out_for_delivery', 'START DELIVERY TO CUSTOMER', 'fas fa-shipping-fast', 'bg-blue-600 hover:bg-blue-500'],
                    'out_for_delivery'   => ['delivered', 'MARK ORDER AS DELIVERED', 'fas fa-check-circle', 'bg-emerald-600 hover:bg-emerald-500'],
                    default => null,
                };
            @endphp

            @if($actionButton)
                <form action="{{ route('driver.bookings.status', $delivery->id) }}" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="{{ $actionButton[0] }}">
                    <button type="submit"
                        class="w-full py-4 {{ $actionButton[3] }} text-white font-black rounded-2xl shadow-xl transition-all hover:-translate-y-0.5 flex items-center justify-center gap-2 text-sm uppercase tracking-wider">
                        <i class="{{ $actionButton[2] }}"></i> {{ $actionButton[1] }}
                    </button>
                </form>
            @else
                <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl text-center">
                    <p class="font-black text-emerald-400 text-sm">Delivery Completed!</p>
                </div>
            @endif

        </div>

    </div>

    {{-- Leaflet Map Script --}}
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Coords
            const pharmacyLat = {{ $delivery->pickup_latitude ?? 10.6385 }};
            const pharmacyLng = {{ $delivery->pickup_longitude ?? 122.9520 }};

            const deliveryLat = {{ $delivery->delivery_latitude ?? 10.6390 }};
            const deliveryLng = {{ $delivery->delivery_longitude ?? 122.9530 }};

            const driverLat = {{ $profile?->current_latitude ?? 10.6350 }};
            const driverLng = {{ $profile?->current_longitude ?? 122.9500 }};

            // Initialize Map
            const map = L.map('delivery-map', { zoomControl: false }).setView([pharmacyLat, pharmacyLng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap GEORX',
                maxZoom: 19
            }).addTo(map);

            L.control.zoom({ position: 'topright' }).addTo(map);

            // Icon Creator Helper
            function createCustomIcon(bgColor, iconClass, size = 38) {
                return L.divIcon({
                    className: 'custom-leaflet-pin',
                    html: `<div style="background-color:${bgColor}; width:${size}px; height:${size}px;" class="custom-pin-icon"><i class="${iconClass} text-white text-sm"></i></div>`,
                    iconSize: [size, size],
                    iconAnchor: [size / 2, size / 2],
                    popupAnchor: [0, -size / 2]
                });
            }

            // 1. Pickup Pharmacy Marker
            const pharmacyMarker = L.marker([pharmacyLat, pharmacyLng], {
                icon: createCustomIcon('#059669', 'fas fa-hospital')
            }).addTo(map).bindPopup('<strong>🏥 Pickup Pharmacy</strong><br>{{ e($delivery->pickupPharmacy->name) }}');

            // 2. Customer Delivery Marker
            const deliveryMarker = L.marker([deliveryLat, deliveryLng], {
                icon: createCustomIcon('#dc2626', 'fas fa-map-pin')
            }).addTo(map).bindPopup('<strong>Customer Location</strong><br>{{ e(Str::limit($delivery->delivery_address, 40)) }}');

            // 3. Driver Live Marker
            let driverMarker = L.marker([driverLat, driverLng], {
                icon: createCustomIcon('#2563eb', 'fas fa-motorcycle', 44)
            }).addTo(map).bindPopup('<strong>Driver Location</strong>');

            // Polyline Connector (Driver -> Pharmacy -> Customer)
            const routePoints = [
                [driverLat, driverLng],
                [pharmacyLat, pharmacyLng],
                [deliveryLat, deliveryLng]
            ];

            const polyline = L.polyline(routePoints, {
                color: '#3b82f6',
                weight: 4,
                opacity: 0.8,
                dashArray: '8, 8'
            }).addTo(map);

            // Fit Bounds to cover all pins
            const group = L.featureGroup([pharmacyMarker, deliveryMarker, driverMarker]);
            map.fitBounds(group.getBounds().pad(0.2));

            // Geolocation Live Permission Check
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const newLat = position.coords.latitude;
                        const newLng = position.coords.longitude;
                        driverMarker.setLatLng([newLat, newLng]);
                        polyline.setLatLngs([[newLat, newLng], [pharmacyLat, pharmacyLng], [deliveryLat, deliveryLng]]);
                    },
                    function(error) {
                        const alertBanner = document.getElementById('gps-alert-banner');
                        const alertText = document.getElementById('gps-alert-text');
                        if (alertBanner && alertText) {
                            alertText.innerText = "GPS permission denied. Displaying fixed route coordinates.";
                            alertBanner.classList.remove('hidden');
                        }
                    },
                    { enableHighAccuracy: true, timeout: 5000 }
                );
            }
        });
    </script>
</body>
</html>
