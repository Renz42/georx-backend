<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #{{ 10000 + $order->id }} - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#0D9488', 500: '#14B8A6', 400: '#2DD4BF', 300: '#5EEAD4', 100: '#CCFBF1', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script>
    <style>
        .custom-leaflet-icon {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <x-navbar />

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">

        <!-- Top Breadcrumb & Status Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-1">
                    <a href="{{ route('user.orders') }}" class="hover:text-teal-600 transition-colors">My Orders</a>
                    <span>/</span>
                    <span class="text-slate-800 font-bold">Order #{{ 10000 + $order->id }}</span>
                </div>
                <h1 class="text-2xl font-black text-slate-900 tracking-tight">Live Delivery Tracking</h1>
            </div>
            
            <div class="flex items-center gap-3">
                <a href="{{ route('user.orders') }}" class="px-4 py-2 bg-white hover:bg-slate-100 text-slate-700 font-bold text-xs rounded-xl border border-slate-200 shadow-sm transition-all flex items-center gap-2">
                    <i class="fas fa-arrow-left text-slate-400"></i> Back to Orders
                </a>
                @if($order->status === 'delivered')
                    <a href="{{ route('orders.receipt', $order->id) }}" target="_blank" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white font-bold text-xs rounded-xl shadow-md shadow-teal-200 transition-all flex items-center gap-2">
                        <i class="fas fa-file-pdf"></i> Download Receipt
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-4 rounded-2xl flex items-center shadow-sm mb-6">
                <i class="fas fa-check-circle mr-3 text-emerald-600 text-lg"></i>
                <span class="font-bold text-sm">{{ session('success') }}</span>
            </div>
        @endif

        @php
            $statusConfigs = [
                'pending_confirmation' => ['Finding a driver...', 'Pharmacy is validating your order.', 'fas fa-spinner fa-spin', 'amber', 'WAITING_FOR_DRIVER'],
                'pending' => ['Waiting for Driver', 'Order confirmed! Finding an available rider...', 'fas fa-clock', 'amber', 'WAITING_FOR_DRIVER'],
                'accepted' => ['Driver Assigned', 'Your driver has accepted the delivery.', 'fas fa-user-check', 'blue', 'DRIVER_ASSIGNED'],
                'at_pharmacy' => ['Driver at Pharmacy', 'Your driver is picking up your medicine.', 'fas fa-store', 'indigo', 'DRIVER_AT_PHARMACY'],
                'picked_up' => ['Medicine Picked Up', 'Your medicine has been picked up.', 'fas fa-box-open', 'teal', 'PICKED_UP'],
                'out_for_delivery' => ['Driver on the Way', 'Your driver is on the way to your address.', 'fas fa-motorcycle', 'emerald', 'OUT_FOR_DELIVERY'],
                'delivered' => ['Order Delivered', 'Your medicine has been delivered.', 'fas fa-check-double', 'emerald', 'DELIVERED'],
                'cancelled' => ['Order Cancelled', 'This delivery order was cancelled.', 'fas fa-times-circle', 'rose', 'CANCELLED'],
                'rejected' => ['Order Rejected', 'Order rejected by dispensing pharmacy.', 'fas fa-ban', 'rose', 'DELIVERY_FAILED'],
            ];
            $currentStatus = $order->status;
            $statusInfo = $statusConfigs[$currentStatus] ?? ['Processing', 'Updating live position...', 'fas fa-spinner', 'slate', 'IN_PROGRESS'];
        @endphp

        <!-- Main 2-Column Responsive Layout (Mobile: Map First, Desktop: Left Info / Right Map) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

            <!-- LEFT COLUMN: Delivery Status, Driver Card, Addresses & Items (Desktop Left, Mobile Second) -->
            <div class="lg:col-span-5 space-y-6 order-2 lg:order-1">

                <!-- 1. Live Status Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl bg-{{ $statusInfo[3] }}-100 text-{{ $statusInfo[3] }}-700 shrink-0">
                            <i class="{{ $statusInfo[2] }}"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-{{ $statusInfo[3] }}-700 bg-{{ $statusInfo[3] }}-50 px-2.5 py-0.5 rounded-full border border-{{ $statusInfo[3] }}-200">
                                {{ $statusInfo[4] }}
                            </span>
                            <h2 class="text-xl font-black text-slate-900 mt-1">{{ $statusInfo[0] }}</h2>
                        </div>
                    </div>
                    <p class="text-sm font-medium text-slate-600 leading-relaxed">{{ $statusInfo[1] }}</p>

                    @if($order->status === 'pending_confirmation')
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                            <span class="text-xs text-slate-500 font-medium">Changed your mind?</span>
                            <form action="{{ route('orders.cancel', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                @csrf
                                <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3.5 py-1.5 rounded-lg border border-rose-200 transition-colors">
                                    Cancel Order
                                </button>
                            </form>
                        </div>
                    @endif
                </div>

                <!-- 2. Assigned Driver Profile Card -->
                @if($order->deliveryPartner || $order->delivery?->driver)
                    @php
                        $driverUser = $order->deliveryPartner ?? $order->delivery?->driver;
                        $driverProfile = $driverUser?->driverProfile;
                    @endphp
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3.5">
                                <div class="w-12 h-12 bg-teal-600 text-white font-black text-lg rounded-2xl flex items-center justify-center shadow-md shadow-teal-200">
                                    {{ strtoupper(substr($driverUser->name, 0, 1)) }}
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-base text-slate-900">{{ $driverUser->name }}</h3>
                                    <p class="text-xs font-semibold text-slate-500">{{ $driverProfile->vehicle_description ?? 'Motorcycle Courier' }}</p>
                                    @if($driverProfile->plate_number ?? null)
                                        <p class="text-xs font-bold text-teal-700 mt-0.5"><i class="fas fa-id-card mr-1"></i> Plate: {{ $driverProfile->plate_number }}</p>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs font-black text-amber-600 bg-amber-50 px-2.5 py-1 rounded-lg border border-amber-200">
                                ★ 4.9 Rating
                            </span>
                        </div>

                        @if($driverUser->phone)
                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <a href="tel:{{ $driverUser->phone }}" class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-sm transition-all">
                                    <i class="fas fa-phone"></i> Call Driver
                                </a>
                                <a href="sms:{{ $driverUser->phone }}?body=Hello! Checking on my prescription order #{{ 10000 + $order->id }}" class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs flex items-center justify-center gap-2 border border-slate-200 transition-all">
                                    <i class="fas fa-comment-alt"></i> SMS Driver
                                </a>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 flex items-center gap-3 text-slate-500">
                        <i class="fas fa-user-clock text-slate-400 text-xl"></i>
                        <p class="text-xs font-semibold">Courier assignment in progress by dispensing pharmacy...</p>
                    </div>
                @endif

                <!-- 3. Locations (Pharmacy Pickup & Customer Delivery Destination) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Route Waypoints</h3>
                    
                    <!-- Pharmacy Pickup -->
                    <div class="flex items-start gap-3.5">
                        <div class="w-9 h-9 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center shrink-0 mt-0.5 border border-teal-100">
                            <i class="fas fa-clinic-medical text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">1. Dispensing Pharmacy</p>
                            <h4 class="font-extrabold text-sm text-slate-800">{{ $order->pharmacy->name }}</h4>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $order->pharmacy->address }}</p>
                        </div>
                    </div>

                    <div class="h-4 border-l-2 border-dashed border-slate-200 ml-4"></div>

                    <!-- Customer Delivery -->
                    <div class="flex items-start gap-3.5">
                        <div class="w-9 h-9 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center shrink-0 mt-0.5 border border-rose-100">
                            <i class="fas fa-map-marker-alt text-sm"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">2. Delivery Address</p>
                            <h4 class="font-extrabold text-sm text-slate-800">Your Location</h4>
                            <p class="text-xs text-slate-500 mt-0.5">{{ $order->delivery_address }}</p>
                        </div>
                    </div>
                </div>

                <!-- 4. Prescription Medicine Summary -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-extrabold text-sm text-slate-900">Prescription Medicines</h3>
                        <span class="text-xs font-bold text-slate-500">{{ $order->items->count() }} items</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($order->items as $item)
                            <div class="p-4 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-slate-50 rounded-lg flex items-center justify-center border border-slate-200 text-teal-600 shrink-0">
                                        <i class="fas fa-pills"></i>
                                    </div>
                                    <div>
                                        <p class="font-extrabold text-xs text-slate-800">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</p>
                                        <p class="text-[11px] font-medium text-slate-500">Qty: {{ $item->quantity }}</p>
                                    </div>
                                </div>
                                <span class="font-black text-xs text-slate-900">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex justify-between items-center text-xs">
                        <span class="font-bold text-slate-600">Total Amount (Incl. Delivery):</span>
                        <span class="font-black text-sm text-slate-900">₱{{ number_format($order->total_amount, 2) }}</span>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: Dedicated Interactive OpenStreetMap (Desktop Right, Mobile First) -->
            <div class="lg:col-span-7 order-1 lg:order-2 space-y-4">
                
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <!-- Map Top Control Bar -->
                    <div class="px-6 py-4 bg-slate-900 text-white flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-teal-500"></span>
                            </span>
                            <h2 class="font-black text-xs uppercase tracking-wider">Live OpenStreetMap Navigation</h2>
                        </div>
                        <span id="gps-status-badge" class="text-xs font-bold bg-teal-500/20 text-teal-300 px-3 py-1 rounded-full border border-teal-500/30">
                            <i class="fas fa-satellite-dish mr-1"></i> Signal Active
                        </span>
                    </div>

                    <!-- Stale Signal Warning Banner -->
                    <div id="stale-warning" class="hidden bg-amber-500 text-white px-6 py-2.5 text-xs font-bold flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-amber-100 text-sm"></i>
                        <span>Driver GPS signal was updated over 5 minutes ago. Location display may be delayed.</span>
                    </div>

                    <!-- Leaflet Container -->
                    <div id="customer-live-map" class="w-full h-[460px] bg-slate-100 z-0"></div>

                    <!-- Legend Footer -->
                    <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 text-xs font-semibold text-slate-600">
                        <div class="flex items-center gap-4">
                            <span class="flex items-center gap-1.5"><i class="fas fa-clinic-medical text-teal-600"></i> Pharmacy</span>
                            <span class="flex items-center gap-1.5"><i class="fas fa-motorcycle text-indigo-600"></i> Driver Live</span>
                            <span class="flex items-center gap-1.5"><i class="fas fa-map-marker-alt text-rose-600"></i> Your Location</span>
                        </div>
                        <span id="last-update-time" class="text-slate-400">Polling live position...</span>
                    </div>
                </div>

            </div>

        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const orderId = "{{ $order->id }}";
            const mapEl = document.getElementById('customer-live-map');
            if (!mapEl) return;

            const defaultLat = {{ $order->latitude ?? 10.6401 }};
            const defaultLng = {{ $order->longitude ?? 122.9501 }};

            const map = L.map('customer-live-map', {
                zoomControl: true,
                attributionControl: false
            }).setView([defaultLat, defaultLng], 14);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19
            }).addTo(map);

            let driverMarker = null;
            let pickupMarker = null;
            let deliveryMarker = null;
            let routePolyline = null;

            const createCustomIcon = (faClass, bgColor, borderColor = '#ffffff') => {
                return L.divIcon({
                    className: 'custom-leaflet-icon',
                    html: `<div style="background-color:${bgColor}; width:38px; height:38px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:16px; color:#ffffff; box-shadow:0 4px 10px rgba(0,0,0,0.25); border:2px solid ${borderColor};"><i class="${faClass}"></i></div>`,
                    iconSize: [38, 38],
                    iconAnchor: [19, 19]
                });
            };

            const pharmacyIcon = createCustomIcon('fas fa-hospital', '#0D9488');
            const driverIcon   = createCustomIcon('fas fa-motorcycle', '#2563EB', '#4ADE80');
            const customerIcon = createCustomIcon('fas fa-home', '#EF4444');

            async function updateLiveLocation() {
                try {
                    const response = await fetch(`/order/${orderId}/live-location`);
                    if (!response.ok) return;

                    const data = await response.json();
                    if (!data.success || !data.locations) return;

                    const locs = data.locations;
                    const bounds = [];

                    // 1. Pickup Pharmacy Marker
                    if (locs.pickup && locs.pickup.latitude && locs.pickup.longitude) {
                        const pLat = locs.pickup.latitude;
                        const pLng = locs.pickup.longitude;
                        bounds.push([pLat, pLng]);

                        if (!pickupMarker) {
                            pickupMarker = L.marker([pLat, pLng], { icon: pharmacyIcon })
                                .bindPopup(`<div style="font-family: Outfit, sans-serif; padding: 2px;"><strong style="font-size:13px; color:#0F172A;">${locs.pickup.pharmacy_name || 'Pickup Pharmacy'}</strong><p style="margin:2px 0 0 0; font-size:11px; color:#64748B;">Dispensing Pharmacy</p></div>`)
                                .addTo(map);
                        } else {
                            pickupMarker.setLatLng([pLat, pLng]);
                        }
                    }

                    // 2. Customer Delivery Destination Marker
                    if (locs.delivery && locs.delivery.latitude && locs.delivery.longitude) {
                        const dLat = locs.delivery.latitude;
                        const dLng = locs.delivery.longitude;
                        bounds.push([dLat, dLng]);

                        if (!deliveryMarker) {
                            deliveryMarker = L.marker([dLat, dLng], { icon: customerIcon })
                                .bindPopup(`<div style="font-family: Outfit, sans-serif; padding: 2px;"><strong style="font-size:13px; color:#0F172A;">Delivery Destination</strong><p style="margin:2px 0 0 0; font-size:11px; color:#64748B;">${locs.delivery.address || ''}</p></div>`)
                                .addTo(map);
                        } else {
                            deliveryMarker.setLatLng([dLat, dLng]);
                        }
                    }

                    // 3. Driver Live Location Marker
                    if (locs.driver && locs.driver.latitude && locs.driver.longitude && locs.driver.latitude !== 0) {
                        const rLat = locs.driver.latitude;
                        const rLng = locs.driver.longitude;
                        bounds.push([rLat, rLng]);

                        if (!driverMarker) {
                            driverMarker = L.marker([rLat, rLng], { icon: driverIcon })
                                .bindPopup(`<div style="font-family: Outfit, sans-serif; padding: 2px;"><strong style="font-size:13px; color:#0F172A;">${data.driver?.name || 'Assigned Driver'}</strong><p style="margin:2px 0 0 0; font-size:11px; color:#2563EB; font-weight:bold;">${data.driver?.vehicle_info || 'Motorcycle'}</p></div>`)
                                .addTo(map);
                        } else {
                            driverMarker.setLatLng([rLat, rLng]);
                        }
                    }

                    // Draw connecting polyline
                    if (bounds.length >= 2) {
                        if (routePolyline) {
                            map.removeLayer(routePolyline);
                        }
                        routePolyline = L.polyline(bounds, {
                            color: '#0D9488',
                            weight: 4,
                            opacity: 0.8,
                            dashArray: '8, 8'
                        }).addTo(map);

                        map.fitBounds(bounds, { padding: [50, 50] });
                    }

                    // Stale Warning Badge Update
                    const staleEl = document.getElementById('stale-warning');
                    const statusBadge = document.getElementById('gps-status-badge');
                    const timeEl = document.getElementById('last-update-time');

                    if (data.is_stale) {
                        if (staleEl) staleEl.classList.remove('hidden');
                        if (statusBadge) {
                            statusBadge.className = 'text-xs font-bold bg-amber-500/20 text-amber-300 px-3 py-1 rounded-full border border-amber-500/30';
                            statusBadge.innerHTML = '<i class="fas fa-exclamation-circle mr-1"></i> Signal Weak';
                        }
                    } else {
                        if (staleEl) staleEl.classList.add('hidden');
                        if (statusBadge) {
                            statusBadge.className = 'text-xs font-bold bg-teal-500/20 text-teal-300 px-3 py-1 rounded-full border border-teal-500/30';
                            statusBadge.innerHTML = '<i class="fas fa-satellite-dish mr-1"></i> Signal Active';
                        }
                    }

                    if (timeEl && data.last_location_at) {
                        timeEl.innerText = `Updated: ${data.last_location_at}`;
                    }

                } catch (err) {
                    console.warn('Live location polling notice:', err);
                }
            }

            // Initial fetch & set poll interval (6 seconds for real-time smoothness)
            updateLiveLocation();
            setInterval(updateLiveLocation, 6000);
        });
    </script>
</body>
</html>
