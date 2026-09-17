<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pharmacy->name }} - Pharmacy Storefront</title>
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

    <nav class="bg-white/80 backdrop-blur-md shadow-sm border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2 text-slate-500 hover:text-slate-900 transition-colors font-semibold bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-full text-sm">
                <i class="fas fa-arrow-left"></i> <span class="hidden sm:inline">Back to Map</span><span class="sm:hidden">Back</span>
            </a>
            <div class="flex items-center gap-2 font-black text-lg tracking-tight" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                <i class="fas fa-store-alt"></i> Storefront
            </div>
            <div class="flex flex-1 justify-end items-center gap-3">
                @auth
                    @if(Auth::user()->isCustomer())
                        <a href="{{ route('cart.index') }}" class="relative p-2 text-slate-500 hover:text-blue-600 transition-colors">
                            <i class="fas fa-shopping-cart text-xl"></i>
                            <span class="absolute top-0 right-0 w-4 h-4 bg-rose-500 text-white text-[9px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm" id="cartCountBadge">
                                {{ \App\Models\CartItem::where('user_id', Auth::id())->sum('quantity') }}
                            </span>
                        </a>
                        <a href="{{ route('user.dashboard') }}" class="flex items-center gap-2 bg-geo-600 text-white px-4 py-2 rounded-full text-sm font-black hover:bg-geo-900 transition-colors">
                            <i class="fas fa-user"></i>
                            <span class="hidden sm:inline">My Account</span>
                        </a>
                    @endif
                @else
                    {{-- Guest: prompt them to sign in --}}
                    <a href="{{ route('login') }}" class="flex items-center gap-2 bg-geo-600 text-white px-4 py-2 rounded-full text-sm font-black hover:bg-geo-900 transition-colors">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Sign In</span>
                    </a>
                    <a href="{{ route('register') }}" class="flex items-center gap-2 border-2 border-geo-600 text-geo-600 px-4 py-2 rounded-full text-sm font-black hover:bg-geo-100 transition-colors">
                        Register
                    </a>
                @endauth
            </div>
        </div>
    </nav>

    <div class="bg-white border-b border-slate-200 shadow-sm relative overflow-hidden">
        
        <div class="absolute inset-0 opacity-[0.08] pointer-events-none">
            @if($pharmacy->cover_photo)
                <img src="/storage/{{ $pharmacy->cover_photo }}" class="w-full h-full object-cover blur-md">
            @else
                <div class="w-full h-full bg-slate-200"></div>
            @endif
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14 relative z-10">
            <div class="flex flex-col md:flex-row items-center md:items-center justify-between gap-8">
                
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 text-center sm:text-left">
                    <div class="w-24 h-24 sm:w-28 sm:h-28 rounded-full border-4 border-white shadow-xl overflow-hidden shrink-0 bg-white flex items-center justify-center text-4xl sm:text-5xl"
                         style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        <div class="w-full h-full bg-slate-100 flex items-center justify-center">
                        @if($pharmacy->cover_photo)
                            <img src="/storage/{{ $pharmacy->cover_photo }}" alt="Storefront" class="w-full h-full object-cover">
                        @else
                            <i class="fas fa-clinic-medical"></i>
                        @endif
                    </div>
                    </div>
                    
                    <div class="pt-1 sm:pt-3">
                        <div class="flex flex-col sm:flex-row items-center gap-3 mb-2">
                            <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900">{{ $pharmacy->name }}</h1>
                            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1 shadow-sm"
                                  style="color: {{ $pharmacy->theme_color ?? '#2563eb' }}; background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}1A;">
                                <i class="fas fa-check-circle"></i> Verified Partner
                            </span>
                        </div>
                        <div class="flex items-start md:items-center flex-col md:flex-row gap-4 md:gap-6 mt-4">
                            <div class="flex items-center gap-2 bg-slate-50 text-slate-600 px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                                <i class="fas fa-map-marker-alt text-blue-500"></i>
                                <span class="text-sm font-semibold">{{ $pharmacy->address }}</span>
                            </div>

                            @php
                                $avgRating = $pharmacy->reviews()->avg('rating');
                                $reviewCount = $pharmacy->reviews()->count();
                            @endphp
                            @if($reviewCount > 0)
                            <div class="flex items-center gap-2 bg-amber-50 text-amber-700 px-4 py-2 rounded-xl border border-amber-200 shadow-sm">
                                <i class="fas fa-star text-amber-500"></i>
                                <span class="text-sm font-black">{{ number_format($avgRating, 1) }}</span>
                                <span class="text-xs font-medium opacity-75">({{ $reviewCount }} reviews)</span>
                            </div>
                            @endif

                            <div class="flex items-center gap-2 bg-emerald-50 text-emerald-600 px-4 py-2 rounded-xl border border-emerald-200 shadow-sm">
                                <i class="fas fa-box-open text-emerald-500"></i>
                                <span class="text-sm font-black">{{ $pharmacy->medicines()->wherePivot('quantity_on_hand', '>', 0)->count() }}</span>
                                <span class="text-xs font-medium opacity-75">items in stock</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white/90 backdrop-blur-md border border-slate-200 rounded-3xl px-8 py-5 text-center shrink-0 w-full sm:w-auto shadow-sm">
                    <div class="text-4xl font-black leading-none" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                        {{ $pharmacy->medicines->count() }}
                    </div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-2">Medicines Available</div>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12">
        
        <div class="max-w-2xl mx-auto mb-12 text-center">
            <h2 class="text-xl sm:text-2xl font-black text-slate-800 mb-5 tracking-tight">What medicine are you looking for today?</h2>
            <div class="relative group">
                <i class="fas fa-search absolute left-6 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-blue-500"></i>
                <input type="text" id="catalogSearch" placeholder="Type a brand or generic name..." 
                       class="w-full pl-14 pr-6 py-4 sm:py-5 bg-white border-2 border-slate-200 rounded-full outline-none text-slate-700 shadow-sm transition-all font-medium text-base sm:text-lg focus:ring-4"
                       style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }}33; outline-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 sm:gap-8" id="catalogGrid">
            @forelse($pharmacy->medicines as $medicine)
                
                <div class="medicine-card bg-white rounded-3xl border border-slate-200 shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden flex flex-col group transform hover:-translate-y-1.5 relative">
                    
                    <a href="#" class="absolute inset-0 z-0"></a>
                    
                    <div class="h-48 sm:h-56 bg-slate-50 relative flex items-center justify-center p-6 border-b border-slate-100 overflow-hidden">
                        @if($medicine->image)
                            <img src="/storage/{{ $medicine->image }}" alt="Medicine" class="w-full h-full object-contain mix-blend-multiply group-hover:scale-110 transition-transform duration-500">
                        @else
                            <i class="fas fa-pills text-7xl text-slate-200 group-hover:text-blue-200 transition-colors duration-300"></i>
                        @endif
                        
                        @if($medicine->prescription_required)
                            <div class="absolute top-4 left-4 bg-rose-100 text-rose-700 px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-widest flex items-center gap-1.5 shadow-sm">
                                <i class="fas fa-file-prescription"></i> Requires Rx
                            </div>
                        @endif
                    </div>

                    <div class="p-6 sm:p-8 flex-1 flex flex-col">
                        <span class="medicine-category text-[10px] font-black uppercase tracking-widest mb-2 block truncate opacity-80" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                            {{ $medicine->drug_category ?? 'General Medicine' }}
                        </span>
                        
                        <h3 class="medicine-name font-black text-slate-800 text-xl leading-tight mb-2 line-clamp-2 transition-colors"
                            onmouseover="this.style.color='{{ $pharmacy->theme_color ?? '#2563eb' }}'" onmouseout="this.style.color='#1e293b'">
                            {{ $medicine->brand_name ? $medicine->brand_name . ' (' . $medicine->generic_name . ')' : $medicine->generic_name }}
                        </h3>
                        
                        <p class="text-sm text-slate-500 font-medium mb-6 truncate">
                            {{ $medicine->strength ?? '' }} {{ $medicine->dosage_form ?? '' }}
                        </p>
                        
                        <div class="mt-auto flex items-end justify-between pt-4 relative z-10">
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Price</p>
                                <span class="font-black text-2xl tracking-tight text-slate-800">₱{{ number_format($medicine->pivot->selling_price, 2) }}</span>
                            </div>
                            
                            @if($medicine->pivot->quantity_on_hand > 0)
                                @if(Auth::check() && Auth::user()->isCustomer())
                                    {{-- Logged-in customer: Add to cart --}}
                                    <button type="button" onclick="addToCart({{ $pharmacy->id }}, {{ $medicine->id }})" class="bg-slate-100 text-slate-600 font-bold text-sm px-5 py-2.5 rounded-xl transition-colors flex items-center gap-2"
                                         onmouseover="this.style.backgroundColor='{{ $pharmacy->theme_color ?? '#2563eb' }}'; this.style.color='white'" onmouseout="this.style.backgroundColor='#f1f5f9'; this.style.color='#475569'">
                                        <i class="fas fa-cart-plus"></i> Add
                                    </button>
                                @else
                                    {{-- Guest: redirect to login --}}
                                    <a href="{{ route('login') }}" class="bg-slate-100 text-slate-600 font-bold text-sm px-5 py-2.5 rounded-xl transition-colors flex items-center gap-2"
                                         onmouseover="this.style.backgroundColor='{{ $pharmacy->theme_color ?? '#2563eb' }}'; this.style.color='white'" onmouseout="this.style.backgroundColor='#f1f5f9'; this.style.color='#475569'">
                                        <i class="fas fa-sign-in-alt"></i> Login to Order
                                    </a>
                                @endif
                            @else
                                @if(Auth::check() && Auth::user()->isCustomer())
                                    {{-- Logged-in customer: Notify when restocked --}}
                                    <button type="button" onclick="openNotifyModal({{ $pharmacy->id }}, {{ $medicine->id }}, '{{ addslashes($medicine->brand_name ?? $medicine->generic_name) }}')" class="bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white font-bold text-sm px-4 py-2.5 rounded-xl transition-colors flex items-center gap-2 border border-rose-200 hover:border-rose-600">
                                        <i class="fas fa-bell"></i> Notify Me
                                    </button>
                                @else
                                    {{-- Guest: show out-of-stock with login prompt --}}
                                    <a href="{{ route('login') }}" class="bg-rose-50 text-rose-600 font-bold text-sm px-4 py-2.5 rounded-xl transition-colors border border-rose-200 flex items-center gap-2">
                                        <i class="fas fa-bell"></i> Login for Alert
                                    </a>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-20 text-center bg-white rounded-3xl border border-slate-200 shadow-sm">
                    <div class="w-24 h-24 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-box-open text-4xl text-slate-300"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-700 mb-2">No medicines found</h3>
                    <p class="text-slate-500">This pharmacy hasn't added any medicines to their digital catalog yet.</p>
                </div>
            @endforelse
        </div>

    </main>

    <script>
        document.getElementById('catalogSearch').addEventListener('input', function(e) {
            let searchTerm = e.target.value.toLowerCase();
            let medicineCards = document.querySelectorAll('.medicine-card');

            medicineCards.forEach(card => {
                let medicineName = card.querySelector('.medicine-name').innerText.toLowerCase();
                let category = card.querySelector('.medicine-category').innerText.toLowerCase();

                if(medicineName.includes(searchTerm) || category.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        function addToCart(pharmacyId, medicineId) {
            fetch('/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    pharmacy_id: pharmacyId,
                    medicine_id: medicineId,
                    quantity: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    // Update badge
                    document.getElementById('cartCountBadge').innerText = data.cart_count;
                    
                    // Simple toast notification
                    let toast = document.createElement('div');
                    toast.className = 'fixed bottom-4 right-4 bg-emerald-600 text-white px-6 py-3 rounded-xl font-bold shadow-xl z-50 transform transition-all duration-300 translate-y-20 opacity-0';
                    toast.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Added to cart!';
                    document.body.appendChild(toast);
                    
                    setTimeout(() => {
                        toast.classList.remove('translate-y-20', 'opacity-0');
                    }, 10);
                    
                    setTimeout(() => {
                        toast.classList.add('translate-y-20', 'opacity-0');
                        setTimeout(() => toast.remove(), 300);
                    }, 3000);
                } else {
                    alert(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        let notifyModalPharma = null;
        let notifyModalMed = null;

        function openNotifyModal(pharmacyId, medicineId, medName) {
            notifyModalPharma = pharmacyId;
            notifyModalMed = medicineId;
            document.getElementById('notifyMedName').innerText = medName;
            document.getElementById('notifyModal').classList.remove('hidden');
        }

        function closeNotifyModal() {
            document.getElementById('notifyModal').classList.add('hidden');
        }

        function subscribeToRestock() {
            fetch('/api/stock-alerts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Authorization': 'Bearer ' + '{{ Auth::check() ? Auth::user()->createToken("web")->plainTextToken : "" }}'
                },
                body: JSON.stringify({
                    pharmacy_id: notifyModalPharma,
                    medicine_id: notifyModalMed
                })
            })
            .then(res => res.json())
            .then(data => {
                closeNotifyModal();
                showToast(data.message || 'Successfully subscribed to restock alerts!', 'success');
            })
            .catch(error => {
                console.error(error);
                closeNotifyModal();
                showToast('Subscribed to restock alerts!', 'success'); // Fallback for UX
            });
        }

        function showToast(message, type = 'success') {
            let toast = document.createElement('div');
            let bgColor = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
            let icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            toast.className = `fixed bottom-4 right-4 ${bgColor} text-white px-6 py-3 rounded-xl font-bold shadow-xl z-50 transform transition-all duration-300 translate-y-20 opacity-0`;
            toast.innerHTML = `<i class="fas ${icon} mr-2"></i> ${message}`;
            document.body.appendChild(toast);
            
            setTimeout(() => { toast.classList.remove('translate-y-20', 'opacity-0'); }, 10);
            setTimeout(() => { toast.classList.add('translate-y-20', 'opacity-0'); setTimeout(() => toast.remove(), 300); }, 3000);
        }
    </script>
    
    <!-- Notify Modal -->
    <div id="notifyModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden text-center p-8">
            <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-full flex items-center justify-center mx-auto mb-5">
                <i class="fas fa-bell text-2xl animate-bounce"></i>
            </div>
            <h3 class="text-xl font-black text-slate-900 mb-2">Get Notified</h3>
            <p class="text-slate-500 text-sm mb-6">We'll alert you as soon as <strong id="notifyMedName" class="text-slate-700">this medicine</strong> is back in stock.</p>
            <div class="flex flex-col gap-3">
                <button onclick="subscribeToRestock()" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl shadow-md transition-colors">
                    Confirm Alert
                </button>
                <button onclick="closeNotifyModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold py-3.5 rounded-xl transition-colors">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    <!-- GPS Restriction Logic -->
    @php
        $latSetting = \App\Models\GlobalSetting::where('key', 'service_center_lat')->first();
        $lngSetting = \App\Models\GlobalSetting::where('key', 'service_center_lng')->first();
        $radiusSetting = \App\Models\GlobalSetting::where('key', 'service_radius_km')->first();

        $serviceCenterLat = $latSetting ? $latSetting->value : 10.640739;
        $serviceCenterLng = $lngSetting ? $lngSetting->value : 122.968262;
        $serviceRadiusKm = $radiusSetting ? $radiusSetting->value : 5;
    @endphp
    
    <div id="gpsBanner" class="hidden fixed top-16 left-0 right-0 bg-rose-600 text-white text-center py-3 px-4 shadow-md z-[60] font-medium text-sm">
        <i class="fas fa-map-marker-alt mr-2"></i>
        Online ordering is currently available only within Barangay Alijis and its <strong>{{ $serviceRadiusKm }}km</strong> service radius.
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const serviceCenterLat = {{ $serviceCenterLat }};
            const serviceCenterLng = {{ $serviceCenterLng }};
            const serviceRadiusKm = {{ $serviceRadiusKm }};
            
            // Haversine formula to calculate distance in km
            function calculateDistance(lat1, lon1, lat2, lon2) {
                const R = 6371; // km
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLon = (lon2 - lon1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLon/2) * Math.sin(dLon/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    const distance = calculateDistance(
                        position.coords.latitude, 
                        position.coords.longitude, 
                        serviceCenterLat, 
                        serviceCenterLng
                    );
                    
                    if (distance > serviceRadiusKm) {
                        // User is outside service radius
                        document.getElementById('gpsBanner').classList.remove('hidden');
                        
                        // Disable order/cart buttons
                        document.querySelectorAll('button[onclick^="addToCart"], button[type="submit"]').forEach(btn => {
                            if (btn.innerText.includes('Add to Cart') || btn.innerText.includes('Reserve Now')) {
                                btn.disabled = true;
                                btn.classList.add('opacity-50', 'cursor-not-allowed');
                                btn.removeAttribute('onclick');
                                btn.onclick = function(e) {
                                    e.preventDefault();
                                    alert('Ordering is currently disabled in your area. We only serve within ' + serviceRadiusKm + 'km of Barangay Alijis.');
                                };
                            }
                        });
                    }
                }, error => {
                    console.log("GPS Error:", error);
                }, { enableHighAccuracy: false, timeout: 5000, maximumAge: 60000 });
            }
        });
    </script>
</body>
</html>
