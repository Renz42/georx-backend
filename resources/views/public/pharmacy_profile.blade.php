<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $pharmacy->name }} - GEORX: A Medicine Hub Portal</title>
    <meta name="description" content="{{ $pharmacy->name }} pharmacy profile - medicines, ratings, and contact information on GEORX Medicine Hub Portal.">
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
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 pb-20">

    <!-- Nav -->
    <nav class="bg-white/80 backdrop-blur-md shadow-sm border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2 text-slate-500 hover:text-slate-900 transition-colors font-semibold bg-slate-100 hover:bg-slate-200 px-4 py-2 rounded-full text-sm">
                <i class="fas fa-arrow-left"></i> <span class="hidden sm:inline">Back to Map</span><span class="sm:hidden">Back</span>
            </a>
            <div class="flex items-center gap-2 font-black text-lg tracking-tight text-blue-600">
                <i class="fas fa-store"></i> Pharmacy Profile
            </div>
            <div class="w-16 sm:w-24"></div>
        </div>
    </nav>

    <!-- Cover + Logo Header -->
    <div class="relative">
        <div class="h-48 sm:h-64 w-full overflow-hidden" style="background: linear-gradient(135deg, {{ $pharmacy->theme_color ?? '#2563eb' }}dd, {{ $pharmacy->theme_color ?? '#2563eb' }}99);">
            @if($pharmacy->cover_photo)
                <img src="{{ asset('storage/' . $pharmacy->cover_photo) }}" class="w-full h-full object-cover opacity-80" alt="Cover photo">
            @else
                <div class="absolute inset-0 opacity-10">
                    <div class="absolute top-0 right-0 w-80 h-80 bg-white rounded-full -translate-y-1/2 translate-x-1/3"></div>
                    <div class="absolute bottom-0 left-0 w-64 h-64 bg-white rounded-full translate-y-1/3 -translate-x-1/4"></div>
                </div>
            @endif
        </div>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4 -mt-16 sm:-mt-12">
                <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-3xl bg-white shadow-xl border-4 border-white overflow-hidden shrink-0 flex items-center justify-center">
                    @if($pharmacy->logo)
                        <img src="{{ asset('storage/' . $pharmacy->logo) }}" class="w-full h-full object-cover" alt="{{ $pharmacy->name }}">
                    @else
                        <i class="fas fa-clinic-medical text-4xl" style="color: {{ $pharmacy->theme_color ?? '#2563eb' }}"></i>
                    @endif
                </div>
                <div class="text-center sm:text-left pb-2 flex-1">
                    <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">{{ $pharmacy->name }}</h1>
                    <p class="text-slate-500 text-sm mt-1 flex items-center justify-center sm:justify-start gap-2">
                        <i class="fas fa-map-marker-alt text-slate-400"></i> {{ $pharmacy->address }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 pb-2">
                    <span class="px-4 py-2 rounded-full text-sm font-bold {{ $isOpen ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200' }}">
                        <i class="fas fa-{{ $isOpen ? 'door-open' : 'door-closed' }} mr-1"></i>
                        {{ $isOpen ? 'Open Now' : 'Closed' }}
                    </span>
                    @if($ratings['count'] > 0)
                        <span class="px-4 py-2 rounded-full text-sm font-bold bg-amber-50 text-amber-700 border border-amber-200">
                            <i class="fas fa-star mr-1"></i> {{ $ratings['overall'] }} ({{ $ratings['count'] }})
                        </span>
                    @endif
                    
                    <div class="flex items-center gap-2 ml-auto sm:ml-0 mt-2 sm:mt-0 w-full sm:w-auto">
                        @auth
                            <button onclick="toggleFavorite({{ $pharmacy->id }})" class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-sm font-bold bg-rose-50 text-rose-600 hover:bg-rose-100 border border-rose-200 transition-colors">
                                <i class="far fa-heart mr-1" id="favIcon"></i> Favorite
                            </button>
                            <form action="{{ route('messages.store') }}" method="POST" class="flex-1 sm:flex-none">
                                @csrf
                                <input type="hidden" name="pharmacy_id" value="{{ $pharmacy->id }}">
                                <input type="hidden" name="message" value="Hi, I'd like to inquire about your medicines.">
                                <button type="submit" class="w-full px-4 py-2 rounded-xl text-sm font-bold bg-blue-600 text-white hover:bg-blue-700 shadow-md shadow-blue-200 transition-colors text-center flex items-center justify-center">
                                    <i class="fas fa-envelope mr-1"></i> Message
                                </button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-sm font-bold bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors text-center">
                                <i class="fas fa-sign-in-alt mr-1"></i> Login to Message
                            </a>
                        @endauth
                        
                        <button onclick="shareProfile()" class="px-3 py-2 rounded-xl text-sm font-bold bg-slate-50 text-slate-600 hover:bg-slate-100 border border-slate-200 transition-colors" title="Share Pharmacy">
                            <i class="fas fa-share-alt"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 space-y-6">

        <!-- Info Cards Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Contact -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Contact</p>
                @if($pharmacy->phone)
                    <p class="text-sm font-bold text-slate-700 flex items-center gap-2 mb-1">
                        <i class="fas fa-phone text-blue-500 w-4"></i> {{ $pharmacy->phone }}
                    </p>
                @endif
                @if($pharmacy->email)
                    <p class="text-sm font-bold text-slate-700 flex items-center gap-2">
                        <i class="fas fa-envelope text-blue-500 w-4"></i> {{ $pharmacy->email }}
                    </p>
                @endif
            </div>

            <!-- Operating Hours -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Operating Hours</p>
                @if($pharmacy->operating_hours)
                    <p class="text-sm font-bold text-slate-700 flex items-center gap-2">
                        <i class="fas fa-clock text-emerald-500 w-4"></i>
                        {{ $pharmacy->operating_hours['open'] ?? 'N/A' }} - {{ $pharmacy->operating_hours['close'] ?? 'N/A' }}
                    </p>
                @else
                    <p class="text-sm text-slate-400">Not specified</p>
                @endif
            </div>

            <!-- Medicines Count -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Available Medicines</p>
                <p class="text-2xl font-black text-blue-600">{{ $pharmacy->medicines->count() }}</p>
                <p class="text-xs text-slate-500 mt-1">items in stock</p>
            </div>

            <!-- Delivery -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Delivery</p>
                <span class="inline-block px-3 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-full border border-emerald-200">
                    <i class="fas fa-motorcycle mr-1"></i> Via Maxim
                </span>
                <p class="text-xs text-slate-500 mt-1">₱50 flat delivery fee</p>
            </div>
        </div>

        <!-- Two Column Layout: Map + Ratings -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Map -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-100">
                    <h2 class="font-black text-slate-800"><i class="fas fa-map-marked-alt text-blue-500 mr-2"></i>Location</h2>
                </div>
                <div id="pharmacy-map" class="h-64 sm:h-80"></div>
            </div>

            <!-- Rating Breakdown -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <h2 class="font-black text-slate-800 mb-4"><i class="fas fa-star text-amber-500 mr-2"></i>Rating Breakdown</h2>

                @if($ratings['count'] > 0)
                    <div class="text-center mb-6">
                        <p class="text-5xl font-black text-slate-800">{{ $ratings['overall'] }}</p>
                        <div class="flex items-center justify-center gap-1 mt-2">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fas fa-star text-lg {{ $i <= round($ratings['overall']) ? 'text-amber-400' : 'text-slate-200' }}"></i>
                            @endfor
                        </div>
                        <p class="text-sm text-slate-500 mt-1">Based on {{ $ratings['count'] }} reviews</p>
                    </div>

                    <div class="space-y-3">
                        @php
                            $ratingCategories = [
                                'Medicine Availability' => $ratings['medicine_availability'],
                                'Price' => $ratings['price_rating'],
                                'Customer Service' => $ratings['customer_service'],
                                'Accuracy' => $ratings['accuracy'],
                                'Delivery Speed' => $ratings['delivery_speed'],
                            ];
                        @endphp
                        @foreach($ratingCategories as $label => $value)
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-slate-500 w-36 shrink-0">{{ $label }}</span>
                                <div class="flex-1 bg-slate-100 rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full transition-all" style="width: {{ ($value / 5) * 100 }}%; background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}"></div>
                                </div>
                                <span class="text-xs font-black text-slate-700 w-8 text-right">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8 text-slate-400">
                        <i class="far fa-star text-4xl mb-3"></i>
                        <p class="font-medium">No reviews yet</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Available Medicines -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-black text-slate-800"><i class="fas fa-pills text-indigo-500 mr-2"></i>Available Medicines</h2>
                <a href="{{ route('public.pharmacy.catalog', $pharmacy->id) }}" class="text-sm font-bold text-blue-600 hover:text-blue-800 transition-colors">
                    View Full Catalog <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            @if($pharmacy->medicines->isEmpty())
                <div class="p-8 text-center text-slate-400">
                    <i class="fas fa-pills text-3xl mb-3"></i>
                    <p class="font-medium">No medicines currently in stock</p>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5">
                    @foreach($pharmacy->medicines->take(9) as $med)
                        <div class="bg-slate-50 rounded-xl border border-slate-100 p-4 hover:border-blue-200 hover:shadow-sm transition-all">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-bold text-slate-800 text-sm line-clamp-1">{{ $med->brand_name ?? $med->generic_name }}</h3>
                                    @if($med->brand_name && $med->generic_name)
                                        <p class="text-[10px] text-slate-400 font-medium">{{ $med->generic_name }}</p>
                                    @endif
                                </div>
                                @if($med->prescription_required)
                                    <span class="px-2 py-0.5 bg-rose-50 text-rose-600 text-[9px] font-bold rounded-full border border-rose-200 shrink-0">Rx</span>
                                @endif
                            </div>
                            <div class="flex items-end justify-between mt-3">
                                <div>
                                    <p class="text-lg font-black text-blue-600">₱{{ number_format($med->pivot->selling_price, 2) }}</p>
                                    @if($med->strength)
                                        <p class="text-[10px] text-slate-400 font-medium">{{ $med->strength }} · {{ $med->dosage_form }}</p>
                                    @endif
                                </div>
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full {{ $med->pivot->quantity_on_hand > 10 ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600' }}">
                                    {{ $med->pivot->quantity_on_hand > 10 ? 'In Stock' : 'Low Stock' }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Customer Reviews -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
            <div class="p-5 border-b border-slate-100">
                <h2 class="font-black text-slate-800"><i class="fas fa-comments text-violet-500 mr-2"></i>Customer Reviews</h2>
            </div>

            @if($reviews->isEmpty())
                <div class="p-8 text-center text-slate-400">
                    <i class="far fa-comment-dots text-4xl mb-3"></i>
                    <p class="font-medium">No reviews yet. Be the first to review!</p>
                </div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($reviews as $review)
                        <div class="p-5">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-slate-100 text-slate-500 rounded-full flex items-center justify-center font-bold text-sm">
                                        {{ substr($review->user->name ?? 'U', 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800 text-sm">{{ $review->user->name ?? 'Anonymous' }}</p>
                                        <p class="text-[10px] text-slate-400">{{ $review->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-0.5">
                                    @for($i = 1; $i <= 5; $i++)
                                        <i class="fas fa-star text-xs {{ $i <= ($review->pharmacy_overall ?? $review->rating) ? 'text-amber-400' : 'text-slate-200' }}"></i>
                                    @endfor
                                </div>
                            </div>
                            @if($review->comment)
                                <p class="text-sm text-slate-600 ml-12">{{ $review->comment }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </main>

    <!-- Map & Actions Script -->
    <script>
        const map = L.map('pharmacy-map').setView([{{ $pharmacy->latitude }}, {{ $pharmacy->longitude }}], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);
        L.marker([{{ $pharmacy->latitude }}, {{ $pharmacy->longitude }}])
            .addTo(map)
            .bindPopup('<strong>{{ $pharmacy->name }}</strong><br>{{ $pharmacy->address }}')
            .openPopup();

        function shareProfile() {
            if (navigator.share) {
                navigator.share({
                    title: '{{ $pharmacy->name }} on GEORX',
                    text: 'Check out this pharmacy on GEORX Medicine Hub!',
                    url: window.location.href,
                }).catch(console.error);
            } else {
                navigator.clipboard.writeText(window.location.href);
                alert('Link copied to clipboard!');
            }
        }

        function toggleFavorite(id) {
            // Placeholder for favorite toggle logic
            const icon = document.getElementById('favIcon');
            if(icon.classList.contains('far')) {
                icon.classList.replace('far', 'fas');
                alert('Added to favorites!');
            } else {
                icon.classList.replace('fas', 'far');
                alert('Removed from favorites.');
            }
        }
    </script>
</body>
</html>
