<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
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
    </script>
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <x-navbar />

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 flex flex-col md:flex-row gap-8">
        
        <!-- Delivery Form -->
        <div class="flex-1">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden mb-6">
                <div class="px-6 py-5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <h2 class="font-black text-slate-800 text-lg flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-emerald-600"></i> Delivery Details
                    </h2>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-full">
                        <i class="fas fa-shield-alt mr-1"></i> Barangay Alijis Service Area
                    </span>
                </div>
                <div class="p-6">
                    @if(session('error'))
                        <div class="mb-5 p-4 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm font-semibold flex items-center gap-2">
                            <i class="fas fa-exclamation-circle text-rose-500 text-base"></i>
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('orders.place') }}" method="POST" id="checkoutForm">
                        @csrf
                        <div class="mb-5">
                            <label class="block text-sm font-bold text-slate-700 mb-2">
                                Complete Delivery Address <span class="text-rose-500">*</span>
                            </label>
                            <textarea name="delivery_address" rows="3" required
                                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition text-slate-800 placeholder-slate-400"
                                      placeholder="House/Unit No., Street Name, Landmark (e.g., Near Alijis Elementary School, Bacolod City)"></textarea>
                            <p class="text-xs text-slate-500 mt-1">Please provide clear landmarks to assist the driver during delivery.</p>
                        </div>

                        <!-- GPS Location Capture Section -->
                        <div class="mb-5">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-sm font-bold text-slate-700">
                                    GPS Location <span class="text-xs font-normal text-slate-500">(Optional but recommended)</span>
                                </label>
                                <button type="button" id="detectGpsBtn" onclick="detectUserGps()"
                                        class="px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-200 transition flex items-center gap-1.5">
                                    <i class="fas fa-location-crosshairs text-emerald-600"></i> Detect My Location
                                </button>
                            </div>

                            <!-- GPS Status Container -->
                            <div id="gpsStatusContainer" class="p-4 bg-slate-50 rounded-xl border border-slate-200 flex items-start gap-3">
                                <div id="gpsIcon" class="w-8 h-8 rounded-lg bg-slate-200 text-slate-500 flex items-center justify-center shrink-0 mt-0.5">
                                    <i class="fas fa-compass"></i>
                                </div>
                                <div class="flex-1 text-xs">
                                    <p id="gpsTitle" class="font-bold text-slate-700">GPS Coords: Not Set</p>
                                    <p id="gpsMessage" class="text-slate-500 mt-0.5">Click "Detect My Location" to pin your exact location, or proceed with your complete text address above.</p>
                                </div>
                            </div>

                            <input type="hidden" name="latitude" id="latitudeInput" value="{{ old('latitude') }}">
                            <input type="hidden" name="longitude" id="longitudeInput" value="{{ old('longitude') }}">
                        </div>

                        <div class="mt-6 border-t border-slate-100 pt-6">
                            <h3 class="font-bold text-slate-800 mb-4 text-sm uppercase tracking-widest">Payment Method</h3>
                            <div class="flex items-center gap-3 p-4 bg-emerald-50 border border-emerald-200 rounded-xl cursor-pointer">
                                <input type="radio" checked class="w-5 h-5 text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <p class="font-black text-emerald-900">Cash on Delivery (COD)</p>
                                    <p class="text-xs text-emerald-700">Pay the driver directly when your medicine arrives.</p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="w-full md:w-96 shrink-0">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-24">
                <div class="px-6 py-5 bg-slate-50 border-b border-slate-200">
                    <h2 class="font-black text-slate-800 text-lg">Order Summary</h2>
                </div>
                <div class="p-6">
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Official Pickup Pharmacy</p>
                    <div class="flex items-center gap-3 mb-6 p-3 bg-slate-50 rounded-xl border border-slate-100">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                            <i class="fas fa-clinic-medical"></i>
                        </div>
                        <div class="overflow-hidden">
                            <p class="font-black text-slate-800 text-sm truncate">{{ $pharmacy->name }}</p>
                            <p class="text-xs text-slate-500 truncate"><i class="fas fa-map-marker-alt text-rose-500 mr-1"></i>{{ $pharmacy->address }}</p>
                        </div>
                    </div>

                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Items</p>
                    <div class="space-y-3 mb-6 max-h-48 overflow-y-auto custom-scrollbar pr-2">
                        @foreach($cartItems as $item)
                            <div class="flex justify-between items-start text-sm">
                                <div>
                                    <p class="font-bold text-slate-700">{{ $item->quantity }}x {{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</p>
                                </div>
                                <p class="font-bold text-slate-800 shrink-0">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-2 mb-6">
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Subtotal</span>
                            <span class="font-bold text-slate-800">₱{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500 font-medium">Delivery Fee</span>
                            <span class="font-bold text-slate-800">₱{{ number_format($deliveryFee, 2) }}</span>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 pt-4 flex justify-between items-end mb-6">
                        <span class="font-bold text-slate-600">Total</span>
                        <span class="text-3xl font-black text-slate-900">₱{{ number_format($total, 2) }}</span>
                    </div>

                    <button type="submit" form="checkoutForm" class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-lg shadow-emerald-200 transition hover:-translate-y-0.5 text-lg flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i> Place Order
                    </button>
                    <p class="text-center text-xs text-slate-400 mt-3">Only Barangay Alijis addresses are supported for delivery.</p>
                </div>
            </div>
        </div>

    </main>

    <script>
        function detectUserGps() {
            const btn = document.getElementById('detectGpsBtn');
            const statusContainer = document.getElementById('gpsStatusContainer');
            const icon = document.getElementById('gpsIcon');
            const title = document.getElementById('gpsTitle');
            const message = document.getElementById('gpsMessage');
            const latInput = document.getElementById('latitudeInput');
            const lngInput = document.getElementById('longitudeInput');

            if (!navigator.geolocation) {
                statusContainer.className = "p-4 bg-amber-50 rounded-xl border border-amber-200 flex items-start gap-3";
                icon.className = "w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0 mt-0.5";
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                title.className = "font-bold text-amber-800";
                title.innerText = "GPS Not Supported";
                message.className = "text-amber-700 mt-0.5";
                message.innerText = "Your browser does not support GPS geolocation. You can place your order safely using your complete delivery address above.";
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Locating...';

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude.toFixed(6);
                    const lng = position.coords.longitude.toFixed(6);

                    latInput.value = lat;
                    lngInput.value = lng;

                    statusContainer.className = "p-4 bg-emerald-50 rounded-xl border border-emerald-200 flex items-start gap-3";
                    icon.className = "w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5";
                    icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                    title.className = "font-bold text-emerald-800";
                    title.innerText = "GPS Location Captured";
                    message.className = "text-emerald-700 mt-0.5";
                    message.innerText = `Latitude: ${lat}, Longitude: ${lng} (Pinned for delivery rider)`;

                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-check"></i> Location Captured';
                },
                function(error) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-location-crosshairs text-emerald-600"></i> Retry GPS';

                    statusContainer.className = "p-4 bg-slate-100 rounded-xl border border-slate-300 flex items-start gap-3";
                    icon.className = "w-8 h-8 rounded-lg bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 mt-0.5";
                    icon.innerHTML = '<i class="fas fa-info-circle"></i>';
                    title.className = "font-bold text-slate-800";
                    title.innerText = "GPS Location Optional";
                    message.className = "text-slate-600 mt-0.5";

                    if (error.code === error.PERMISSION_DENIED) {
                        message.innerText = "GPS permission was denied. Your order will be delivered safely using your complete text address above.";
                    } else {
                        message.innerText = "Could not retrieve exact GPS coords. Your order will be delivered safely using your text address above.";
                    }
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }
    </script>
</body>
</html>
