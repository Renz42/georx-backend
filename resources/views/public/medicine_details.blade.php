<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $medicine->generic_name }} at {{ $pharmacy->name }}</title>
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
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #BFE8D6; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #63C6A7; }
    </style>
</head>
<body class="bg-geo-50 text-geo-900 pb-0">

    <nav class="bg-white shadow-sm border-b border-geo-400 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2 text-geo-600 hover:text-geo-900 transition-colors font-bold text-sm bg-geo-100 px-4 py-2 rounded-full">
                <i class="fas fa-arrow-left"></i> Back to Map
            </a>
            
            <div class="flex items-center gap-2 font-extrabold text-xl tracking-tight text-geo-900">
                <i class="fas fa-clinic-medical text-geo-500"></i> MedLocator
            </div>
            
            <div class="flex items-center gap-4">
                <a href="{{ url('/cart') }}" class="relative p-2 text-geo-600 hover:bg-geo-100 rounded-full transition-colors">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <span id="cart-count" class="absolute top-0 right-0 w-4 h-4 bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center border-2 border-white">0</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 mb-16">
        
        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-2xl mb-8 font-bold shadow-sm flex items-center gap-3">
                <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i> {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-3xl shadow-[0_4px_20px_rgba(191,232,214,0.3)] border border-geo-400 overflow-hidden mb-8 flex flex-col md:flex-row items-center p-2 gap-4">
            <div class="w-full md:w-48 h-32 relative bg-geo-100 rounded-2xl overflow-hidden shrink-0 border border-geo-400/50">
                @if($pharmacy->cover_photo)
                    <img src="/storage/{{ $pharmacy->cover_photo }}" alt="Storefront" class="w-full h-full object-cover opacity-90">
                @else
                    <iframe width="100%" height="100%" frameborder="0" style="border:0; pointer-events: none;" src="https://maps.google.com/maps?q={{ $pharmacy->latitude }},{{ $pharmacy->longitude }}&t=k&z=19&ie=UTF8&iwloc=&output=embed"></iframe>
                @endif
            </div>
            <div class="flex-1 py-4 px-2 md:px-4">
                <div class="flex flex-wrap items-center gap-3 mb-1">
                    <h1 class="text-2xl font-extrabold text-geo-900 tracking-tight">{{ $pharmacy->name }}</h1>
                    <span class="bg-geo-100 text-geo-600 border border-geo-400 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> Verified Partner
                    </span>
                </div>
                <p class="text-geo-900/60 font-medium text-sm flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-geo-500"></i> {{ $pharmacy->address }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 flex flex-col gap-8">
                
                <div class="flex flex-col sm:flex-row gap-8 items-start">
                    <div class="w-full sm:w-64 h-64 bg-white rounded-3xl border border-geo-400 shadow-sm flex flex-col items-center justify-center shrink-0 p-6 relative overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-br from-geo-100/50 to-transparent"></div>
                        @if($medicine->image)
                            <img src="/storage/{{ $medicine->image }}" alt="Medicine" class="w-full h-full object-contain mix-blend-multiply relative z-10 hover:scale-110 transition-transform duration-500">
                        @else
                            <i class="fas fa-pills text-6xl mb-3 text-geo-500/30 relative z-10"></i>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-geo-600/50 relative z-10">No Photo</span>
                        @endif
                    </div>
                    
                    <div class="flex-1 pt-2">
                        <h2 class="text-4xl font-black text-geo-900 leading-tight mb-2 tracking-tight">
                            {{ $medicine->brand_name ? $medicine->brand_name . ' / ' : '' }}{{ $medicine->generic_name }}
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 mb-6">
                            <span class="bg-geo-500/20 text-geo-600 font-extrabold uppercase tracking-widest text-xs px-3 py-1.5 rounded-lg border border-geo-500/30">
                                {{ $medicine->strength ?? 'N/A' }}
                            </span>
                            <span class="bg-slate-100 text-slate-600 font-extrabold uppercase tracking-widest text-xs px-3 py-1.5 rounded-lg border border-slate-200">
                                {{ $medicine->dosage_form ?? 'N/A' }}
                            </span>
                        </div>
                        
                        <div class="bg-white p-5 rounded-2xl border border-geo-400 shadow-sm">
                            <p class="text-[10px] font-black text-geo-900/50 uppercase tracking-widest mb-1.5">
                                <i class="fas fa-bullseye text-geo-500 mr-1"></i> Primary Indication
                            </p>
                            <p class="text-geo-900 font-semibold text-sm leading-relaxed">{{ $medicine->primary_use ?? 'Not specified' }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-8 border border-geo-400 shadow-sm">
                    <h3 class="text-lg font-extrabold text-geo-900 border-b border-geo-100 pb-4 mb-5 flex items-center gap-2">
                        <i class="fas fa-file-medical text-geo-500 text-xl"></i> Clinical Details & Warnings
                    </h3>
                    <p class="text-geo-900/70 font-medium leading-relaxed text-sm">
                        {{ $medicine->description ?? 'No detailed description available for this medicine. Please consult the pharmacist upon pickup.' }}
                    </p>
                </div>
            </div>

            <!-- Right Column Sidebar -->
            <div class="w-full shrink-0">
                <div class="bg-white rounded-3xl border-2 border-geo-400 shadow-[0_10px_40px_rgba(191,232,214,0.5)] overflow-hidden sticky top-24">
                    
                    <div class="p-8 border-b border-geo-100 bg-gradient-to-b from-geo-100/30 to-white">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-[11px] font-black text-geo-900/50 uppercase tracking-widest mb-1">Unit Price</p>
                                <p class="font-black text-5xl tracking-tight text-geo-600" id="base_price_display">
                                    <span class="text-2xl text-geo-500 mr-1">₱</span>{{ number_format($medicine->pivot->selling_price, 2) }}
                                </p>
                            </div>
                            <div class="text-right">
                                @if($medicine->pivot->quantity_on_hand > 0)
                                    <span class="bg-geo-100 text-geo-600 font-black px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 uppercase tracking-wider shadow-sm border border-geo-400">
                                        <i class="fas fa-check-circle text-geo-500"></i> In Stock ({{ $medicine->pivot->quantity_on_hand }} {{ $medicine->pivot->unit ?? '' }})
                                    </span>
                                @else
                                    <span class="bg-rose-50 text-rose-600 font-black px-3 py-1.5 rounded-xl text-xs flex items-center gap-1.5 uppercase tracking-wider shadow-sm border border-rose-200">
                                        <i class="fas fa-times-circle text-rose-500"></i> Out of Stock
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="p-8">
                        @if($medicine->prescription_required)
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-xl mb-6 text-xs font-bold flex items-start gap-3 shadow-sm">
                                <i class="fas fa-prescription mt-0.5 text-red-500 text-base"></i>
                                <span>Prescription Required. Present a valid physical prescription upon pickup.</span>
                            </div>
                        @endif

                        @if($medicine->pivot->quantity_on_hand > 0)
                            <form action="/reserve" method="POST" id="reserveForm">
                                @csrf
                                <input type="hidden" name="pharmacy_id" value="{{ $pharmacy->id }}">
                                <input type="hidden" name="medicine_id" value="{{ $medicine->id }}">
                                
                                <div class="mb-5 relative group">
                                    <label class="block text-xs font-black text-geo-900/60 uppercase tracking-widest mb-2">Quantity</label>
                                    <div class="flex items-center relative">
                                        <input type="number" id="res_quantity" name="quantity" min="1" max="{{ $medicine->pivot->quantity_on_hand }}" value="1" required 
                                            class="w-full px-5 py-4 bg-geo-100/50 border border-geo-400 rounded-xl focus:ring-2 focus:ring-geo-500 focus:border-geo-500 outline-none font-black text-xl text-geo-900 transition-all">
                                    </div>
                                </div>

                                <div class="mb-8 relative group">
                                    <label class="block text-xs font-black text-geo-900/60 uppercase tracking-widest mb-2">Pickup Schedule</label>
                                    <input type="datetime-local" id="res_pickup_time" name="scheduled_pickup_at" required 
                                           class="w-full px-5 py-4 bg-geo-100/50 border border-geo-400 rounded-xl focus:ring-2 focus:ring-geo-500 focus:border-geo-500 outline-none font-bold text-sm text-geo-900 transition-all">
                                    
                                    @php
                                        $opHours = $pharmacy->operating_hours;
                                        if (is_string($opHours)) {
                                            $opHours = json_decode($opHours, true);
                                        }
                                    @endphp
                                    @if(is_array($opHours) && isset($opHours['open']) && isset($opHours['close']))
                                        <div class="mt-2 flex items-center gap-2 text-[11px] font-bold text-geo-600 bg-geo-100 px-3 py-2 rounded-lg border border-geo-400">
                                            <i class="fas fa-clock text-geo-500"></i>
                                            <span>Operating Hours: <strong>{{ $opHours['open'] }} – {{ $opHours['close'] }}</strong></span>
                                        </div>
                                    @else
                                        <p class="mt-2 text-[11px] font-semibold text-geo-900/40"><i class="fas fa-info-circle"></i> Operating hours not configured by this pharmacy.</p>
                                    @endif
                                    <p class="mt-1.5 text-[11px] font-bold text-geo-600/70"><i class="fas fa-hourglass-start mr-1"></i> Must be scheduled at least 1 hour from now.</p>
                                    <p id="pickup_time_error" class="mt-1.5 text-[11px] font-bold text-red-500 hidden"><i class="fas fa-exclamation-triangle mr-1"></i> <span id="pickup_error_text">Selected time is outside operating hours.</span></p>
                                </div>

                                <div class="flex flex-col gap-3">
                                    <button type="button" onclick="addToCart({{ $medicine->id }}, {{ $pharmacy->id }})" class="w-full bg-white border-2 border-geo-500 hover:bg-geo-100 text-geo-600 font-black py-4 rounded-xl transition-all active:scale-[0.98] flex justify-center items-center gap-2">
                                        <i class="fas fa-cart-plus text-lg"></i> Add to Cart
                                    </button>

                                    <button type="submit" class="w-full bg-geo-600 hover:bg-geo-900 text-geo-100 font-black py-4 rounded-xl shadow-lg shadow-geo-600/20 transition-all active:scale-[0.98] flex justify-between items-center px-6">
                                        <span>Reserve Now</span>
                                        <span id="total_price_display" class="bg-white/20 border border-white/20 px-3 py-1 rounded-lg">₱{{ number_format($medicine->pivot->selling_price, 2) }}</span>
                                    </button>
                                </div>
                            </form>
                        @else
                            @php
                                $isSubscribed = false;
                                if (auth()->check()) {
                                    $isSubscribed = \App\Models\StockAlert::where('user_id', auth()->id())
                                        ->where('pharmacy_id', $pharmacy->id)
                                        ->where('medicine_id', $medicine->id)
                                        ->where('is_active', true)
                                        ->exists();
                                }
                            @endphp

                            <div class="text-center py-4 space-y-4">
                                <div class="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-2xl text-xs font-semibold flex items-center gap-3">
                                    <i class="fas fa-box-open text-amber-500 text-xl shrink-0"></i>
                                    <span>This medicine is currently out of stock at <strong>{{ $pharmacy->name }}</strong>.</span>
                                </div>

                                @auth
                                    <button id="restockBtn" onclick="toggleRestockAlert({{ $pharmacy->id }}, {{ $medicine->id }})" 
                                            class="w-full font-black py-4 px-6 rounded-2xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2 {{ $isSubscribed ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-200' : 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-200' }}">
                                        <i class="fas {{ $isSubscribed ? 'fa-check-circle' : 'fa-bell' }} text-lg" id="restockIcon"></i>
                                        <span id="restockText">{{ $isSubscribed ? 'Alert Active (Click to Remove)' : 'Notify Me When Restocked' }}</span>
                                    </button>
                                @else
                                    <a href="{{ route('login') }}?redirect_to={{ urlencode(request()->fullUrl()) }}" 
                                       class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 px-6 rounded-2xl shadow-lg shadow-indigo-200 transition-all flex items-center justify-center gap-2 text-sm">
                                        <i class="fas fa-sign-in-alt text-lg"></i> Login to Set Restock Alert
                                    </a>
                                @endauth
                                
                                <p id="restockFeedback" class="text-xs font-bold text-emerald-600 hidden"></p>
                            </div>

                            <script>
                                function toggleRestockAlert(pharmacyId, medicineId) {
                                    const btn = document.getElementById('restockBtn');
                                    const icon = document.getElementById('restockIcon');
                                    const text = document.getElementById('restockText');
                                    const feedback = document.getElementById('restockFeedback');

                                    const isCurrentlySet = text.innerText.includes('Alert Set');
                                    const method = isCurrentlySet ? 'DELETE' : 'POST';

                                    btn.disabled = true;
                                    text.innerText = 'Processing...';

                                    fetch('/stock-alerts', {
                                        method: method,
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({
                                            pharmacy_id: pharmacyId,
                                            medicine_id: medicineId
                                        })
                                    })
                                    .then(res => res.json())
                                    .then(data => {
                                        btn.disabled = false;
                                        if (data.success) {
                                            if (method === 'POST') {
                                                btn.className = 'w-full font-black py-4 px-6 rounded-2xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-200';
                                                icon.className = 'fas fa-check-circle text-lg';
                                                text.innerText = 'Alert Active (Click to Remove)';
                                                feedback.innerText = "You'll be notified when {{ $medicine->generic_name }} is available again at {{ $pharmacy->name }}.";
                                                feedback.classList.remove('hidden');
                                            } else {
                                                btn.className = 'w-full font-black py-4 px-6 rounded-2xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white shadow-indigo-200';
                                                icon.className = 'fas fa-bell text-lg';
                                                text.innerText = 'Notify Me When Restocked';
                                                feedback.innerText = "Restock alert removed.";
                                                feedback.classList.remove('hidden');
                                            }
                                        } else {
                                            alert(data.message || 'Error updating restock alert.');
                                        }
                                    })
                                    .catch(err => {
                                        console.error(err);
                                        btn.disabled = false;
                                        text.innerText = isCurrentlySet ? 'Alert Active (Click to Remove)' : 'Notify Me When Restocked';
                                        alert('An error occurred. Please try again.');
                                    });
                                }
                            </script>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </main>

    <section class="bg-geo-100 border-t border-geo-400 py-16 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            
            <div class="flex flex-col md:flex-row justify-between items-end gap-6 mb-10">
                <div>
                    <h3 class="text-3xl font-extrabold text-geo-900 tracking-tight">More from this Pharmacy</h3>
                    <p class="text-geo-600 text-sm font-semibold mt-2">Browse {{ $pharmacy->name }}'s complete catalog.</p>
                </div>
                
                <div class="w-full md:w-96 relative">
                    <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-geo-500"></i>
                    <input type="text" id="catalogSearch" placeholder="Search other medicines..." 
                           class="w-full pl-11 pr-4 py-3.5 bg-white border border-geo-400 rounded-xl focus:ring-2 focus:ring-geo-500 outline-none transition-all shadow-sm font-medium text-geo-900">
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 md:gap-6" id="catalogGrid">
                @forelse($pharmacy->medicines as $item)
                    @if($item->id != $medicine->id)
                    <div class="catalog-item bg-white rounded-2xl p-4 border border-geo-400 shadow-sm hover:shadow-xl hover:border-geo-500 transition-all duration-300 flex flex-col group relative overflow-hidden" 
                         data-search-text="{{ strtolower($item->brand_name . ' ' . $item->generic_name . ' ' . $item->drug_category) }}">
                        
                        @if($item->prescription_required)
                            <div class="absolute top-2 left-2 bg-red-100 text-red-700 text-[8px] font-black px-2 py-1 rounded-md flex items-center gap-1 uppercase tracking-wider z-10 shadow-sm border border-red-200">
                                <i class="fas fa-prescription"></i> Rx
                            </div>
                        @endif

                        <div class="h-36 bg-geo-50 rounded-xl mb-4 flex items-center justify-center p-3 border border-geo-100 relative">
                            @if($item->image)
                                <img src="/storage/{{ $item->image }}" alt="{{ $item->generic_name }}" class="h-full object-contain mix-blend-multiply group-hover:scale-110 transition-transform duration-500">
                            @else
                                <i class="fas fa-pills text-3xl text-geo-500/30"></i>
                            @endif
                        </div>

                        <div class="flex-1 flex flex-col">
                            <span class="text-[9px] font-black uppercase tracking-widest text-geo-500 mb-1 block line-clamp-1">{{ $item->drug_category ?? 'Medicine' }}</span>
                            <h4 class="font-extrabold text-geo-900 text-sm leading-tight mb-1 line-clamp-2 group-hover:text-geo-600 transition-colors">{{ $item->brand_name ? $item->brand_name . ' / ' : '' }}{{ $item->generic_name }}</h4>
                            <p class="text-[10px] font-bold text-geo-900/50 uppercase">{{ $item->strength }} {{ $item->dosage_form }}</p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-geo-100 flex flex-col gap-3">
                            <div class="flex justify-between items-end">
                                <p class="font-black text-lg leading-none text-geo-600">₱{{ number_format($item->pivot->selling_price, 2) }}</p>
                                <p class="text-[10px] font-bold text-geo-500 tracking-wide">{{ $item->pivot->quantity_on_hand }} {{ $item->pivot->unit ?? 'Stock' }}</p>
                            </div>
                            
                            <div class="flex gap-2">
                                <a href="/pharmacy/{{ $pharmacy->id }}/medicine/{{ $item->id }}" class="flex-1 text-center text-[11px] font-bold text-geo-600 bg-geo-100 hover:bg-geo-600 hover:text-white py-2.5 rounded-lg transition-all border border-geo-400 hover:border-transparent">
                                    View
                                </a>
                                <button type="button" onclick="addToCart({{ $item->id }}, {{ $pharmacy->id }})" class="w-10 flex items-center justify-center bg-white border border-geo-500 text-geo-500 hover:bg-geo-500 hover:text-white rounded-lg transition-all" title="Add to Cart">
                                    <i class="fas fa-cart-plus text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                @empty
                    <div class="col-span-full py-10 text-center text-geo-900/40 font-medium">
                        <i class="fas fa-box-open text-4xl mb-3 text-geo-400"></i>
                        <p>No other medicines found in this pharmacy's catalog.</p>
                    </div>
                @endforelse
            </div>
            
            <div id="noResultsMsg" class="hidden py-16 text-center text-geo-900/40 font-medium">
                <i class="fas fa-search text-4xl mb-3 text-geo-400"></i>
                <p>No medicines matched your search.</p>
            </div>
        </div>
    </section>

    @if($medicine->pivot->quantity_on_hand > 0)
    <script>
        // Live Price Calculator
        const basePrice = {{ $medicine->pivot->selling_price }};
        const qtyInput = document.getElementById('res_quantity');
        const totalDisplay = document.getElementById('total_price_display');

        if (qtyInput && totalDisplay) {
            qtyInput.addEventListener('input', function() {
                let qty = parseInt(this.value) || 1;
                totalDisplay.innerText = '₱' + (basePrice * qty).toFixed(2);
            });
        }
    </script>
    @endif
</body>
</html>
