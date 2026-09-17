<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>MedLocator — Find Medicines Near You</title>

<meta name="description" content="MedLocator helps you find pharmacies and available medicines near your location in real time.">


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
    </script><style>.leaflet-control-container { z-index: 10 !important; }

/* Custom Scrollbar */

.custom-scrollbar::-webkit-scrollbar { width: 5px; }

.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }

.custom-scrollbar::-webkit-scrollbar-thumb { background: #BFE8D6; border-radius: 10px; }

.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #63C6A7; }


/* Leaflet Popup Premium Styling */

.leaflet-popup-content-wrapper {

border-radius: 1.25rem !important;

padding: 0 !important;

box-shadow: 0 10px 40px rgba(31,46,44,0.18) !important;

border: 1px solid #BFE8D6 !important;

overflow: hidden;

}

.leaflet-popup-content { margin: 0 !important; width: auto !important; min-width: 220px; }

.leaflet-popup-tip { background: #ffffff !important; border: 1px solid #BFE8D6 !important; box-shadow: none !important; }

.leaflet-popup-close-button { top: 8px !important; right: 10px !important; font-size: 18px !important; color: #1F2E2C !important; }


/* Marker Pulse Animation */

@keyframes markerPulse {

0% { transform: scale(1); opacity: 0.6; }

50% { transform: scale(1.8); opacity: 0; }

100% { transform: scale(1); opacity: 0; }

}

.marker-pulse-ring {

position: absolute; top: 50%; left: 50%;

width: 36px; height: 36px;

margin-top: -18px; margin-left: -18px;

border-radius: 50%;

background: rgba(99,198,167,0.35);

animation: markerPulse 2s ease-out infinite;

pointer-events: none;

}


/* Card Entrance Animation */

@keyframes cardSlideUp {

from { opacity: 0; transform: translateY(24px); }

to { opacity: 1; transform: translateY(0); }

}

.pharmacy-card-enter { animation: cardSlideUp 0.4s cubic-bezier(0.22, 1, 0.36, 1) both; }


/* Toast Notification */

.toast-notification {

position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%) translateY(100px);

z-index: 9999; padding: 1rem 1.75rem; border-radius: 1rem;

font-weight: 700; font-size: 0.8125rem;

display: flex; align-items: center; gap: 0.75rem;

box-shadow: 0 12px 40px rgba(31,46,44,0.2);

transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.4s ease;

opacity: 0; pointer-events: none; max-width: 420px;

}

.toast-notification.toast-visible { transform: translateX(-50%) translateY(0); opacity: 1; pointer-events: auto; }

.toast-success { background: #ffffff; color: #2F7E6A; border: 1px solid #BFE8D6; }

.toast-error { background: #ffffff; color: #dc2626; border: 1px solid #fecaca; }

.toast-info { background: #ffffff; color: #1F2E2C; border: 1px solid #BFE8D6; }

</style>

</head>

<body class="bg-geo-100 overflow-hidden text-geo-900">


<script>

const isLoggedIn = {{ auth()->check() ? 'true' : 'false' }};

const userRole = '{{ auth()->user()->role ?? "guest" }}';

</script>


<div class="flex h-screen w-full relative">


<div id="mobileBackdrop" onclick="closeSidebar()" class="fixed inset-0 bg-geo-900/40 backdrop-blur-sm z-[40] opacity-0 pointer-events-none transition-opacity duration-300 md:hidden"></div>


<aside id="sidebar" class="absolute md:relative z-[50] w-full sm:w-[420px] h-full bg-geo-100 flex flex-col transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out shadow-[4px_0_24px_rgba(31,46,44,0.08)] flex-shrink-0 border-r border-geo-400">


<div class="p-6 border-b border-geo-400 bg-white rounded-br-[2rem] z-10 shadow-sm relative">

<div class="flex justify-between items-center mb-6">

<h1 class="text-2xl font-extrabold text-geo-900 flex items-center gap-3 tracking-tight">

<div class="w-10 h-10 bg-geo-500 text-geo-900 rounded-2xl flex items-center justify-center text-xl shadow-md shadow-geo-500/20 rotate-3 shrink-0">

<i class="fas fa-prescription-bottle-alt"></i>

</div>

<span class="text-xl">MedLocator</span>

</h1>

<button onclick="closeSidebar()" class="md:hidden w-10 h-10 flex justify-center items-center rounded-full bg-geo-100 text-geo-600 hover:bg-geo-400 hover:text-geo-900 transition-colors">

<i class="fas fa-times text-lg"></i>

</button>

</div>


<div class="space-y-4">

<!-- Search Input -->

<div class="relative group">

<i class="fas fa-search absolute left-5 top-1/2 -translate-y-1/2 text-geo-500 group-focus-within:text-geo-600 transition-colors"></i>

<input type="text" id="searchInput" placeholder="What medicine do you need?" 

class="w-full pl-12 pr-4 py-4 bg-geo-100/50 border-2 border-transparent rounded-2xl focus:outline-none focus:bg-white focus:border-geo-500 focus:ring-4 focus:ring-geo-500/20 transition-all font-semibold text-geo-900 placeholder:text-geo-900/40 shadow-inner text-base">


<!-- Autocomplete Dropdown (Hidden by default) -->

<div id="autocompleteDropdown" class="hidden absolute top-full left-0 right-0 mt-2 bg-white border border-geo-400 rounded-xl shadow-xl z-50 overflow-hidden">

<div class="max-h-60 overflow-y-auto custom-scrollbar" id="autocompleteList"></div>

</div>

</div>


<!-- Category Chips -->

<div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 snap-x" id="categoryChips">

<button class="category-chip snap-start active bg-geo-600 text-white px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors" data-category="">All</button>

<button class="category-chip snap-start bg-geo-100 text-geo-600 hover:bg-geo-400 px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors" data-category="Pain Relief">Pain Relief</button>

<button class="category-chip snap-start bg-geo-100 text-geo-600 hover:bg-geo-400 px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors" data-category="Antibiotics">Antibiotics</button>

<button class="category-chip snap-start bg-geo-100 text-geo-600 hover:bg-geo-400 px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors" data-category="Vitamins">Vitamins</button>

<button class="category-chip snap-start bg-geo-100 text-geo-600 hover:bg-geo-400 px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors" data-category="First Aid">First Aid</button>

<button class="category-chip snap-start bg-geo-100 text-geo-600 hover:bg-geo-400 px-4 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-colors" data-category="Cough & Cold">Cough & Cold</button>

</div>


<div class="flex gap-3 bg-white p-3 rounded-2xl border border-geo-400 shadow-sm">

<!-- Sort Dropdown -->

<div class="flex-1">

<label class="text-[10px] font-bold text-geo-900/70 uppercase tracking-wider mb-1 block">Sort By</label>

<select id="sortInput" class="w-full bg-geo-100 border-none rounded-xl focus:outline-none focus:ring-2 focus:ring-geo-500 px-3 py-2.5 text-xs font-bold text-geo-600 cursor-pointer appearance-none">

<option value="distance">Nearest Distance</option>

<option value="price">Lowest Price</option>

<option value="availability">Highest Stock</option>

</select>

</div>


<!-- Service Area (replaces Radius) -->
<div class="flex-1 border-l border-geo-400 pl-3 flex flex-col justify-center">
<label class="text-[10px] font-bold text-geo-900/70 uppercase tracking-wider block mb-1">Serving</label>
<span class="text-xs font-black text-geo-600"><i class="fas fa-map-pin text-[10px]"></i> Brgy. Alijis</span>
</div>

</div>


<button onclick="performSearch()" class="w-full bg-geo-600 hover:bg-geo-900 text-geo-100 font-black py-3.5 rounded-xl shadow-md shadow-geo-600/20 transition-all active:scale-[0.98] text-sm flex justify-center items-center gap-2">

<i class="fas fa-search-location"></i> Find Medicines

</button>

</div>

</div>


<div id="pharmacyList" class="flex-1 overflow-y-auto custom-scrollbar p-5 bg-geo-100 space-y-4">


<div id="welcomeState" class="flex flex-col h-full space-y-4">

<div class="bg-white p-5 rounded-2xl border border-geo-400 shadow-sm text-center">

<div class="w-16 h-16 mx-auto bg-geo-100 rounded-full flex items-center justify-center mb-3">

<i class="fas fa-map-marked-alt text-2xl text-geo-600"></i>

</div>

<h3 class="font-extrabold text-geo-900 text-lg tracking-tight mb-1">Find Medicines Fast</h3>

<p class="text-xs font-medium text-geo-900/70 leading-relaxed">Search to instantly locate available stock in pharmacies near you.</p>

</div>


<div class="bg-white p-5 rounded-2xl border border-geo-400 shadow-sm">

<h4 class="font-extrabold text-geo-900 text-xs uppercase tracking-wider mb-3 flex items-center gap-2 text-geo-600">

<i class="fas fa-fire"></i> Popular Searches

</h4>

<div class="flex flex-wrap gap-2">

<button onclick="document.getElementById('searchInput').value='Paracetamol'; performSearch();" class="bg-geo-100 text-geo-600 hover:bg-geo-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Paracetamol</button>

<button onclick="document.getElementById('searchInput').value='Amoxicillin'; performSearch();" class="bg-geo-100 text-geo-600 hover:bg-geo-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Amoxicillin</button>

<button onclick="document.getElementById('searchInput').value='Vitamin C'; performSearch();" class="bg-geo-100 text-geo-600 hover:bg-geo-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Vitamin C</button>

<button onclick="document.getElementById('searchInput').value='Ibuprofen'; performSearch();" class="bg-geo-100 text-geo-600 hover:bg-geo-600 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-colors">Ibuprofen</button>

</div>

</div>


@auth

@php

$recentSearches = \App\Models\SearchLog::where('user_id', auth()->id())

->select('term')

->distinct()

->latest()

->take(3)

->pluck('term');

@endphp

@if($recentSearches->isNotEmpty())

<div class="bg-white p-5 rounded-2xl border border-geo-400 shadow-sm">

<h4 class="font-extrabold text-geo-900 text-xs uppercase tracking-wider mb-3 flex items-center gap-2 text-geo-600">

<i class="fas fa-history"></i> Recent Searches

</h4>

<div class="space-y-1">

@foreach($recentSearches as $term)

<button onclick="document.getElementById('searchInput').value='{{ $term }}'; performSearch();" class="w-full text-left px-3 py-2 rounded-lg hover:bg-geo-100 text-sm font-semibold text-geo-900/80 hover:text-geo-600 transition-colors flex items-center gap-3">

<i class="fas fa-search text-[10px] text-geo-500"></i> {{ $term }}

</button>

@endforeach

</div>

</div>

@endif

@endauth

</div>


</div>


@auth

<div class="p-5 border-t border-geo-400 bg-white">

<form method="POST" action="{{ route('logout') }}">

@csrf

<button type="submit" class="w-full flex items-center justify-center gap-3 py-3.5 px-4 rounded-xl text-geo-600 hover:bg-red-50 hover:text-red-600 font-bold transition-all border border-geo-400 hover:border-red-200">

<i class="fas fa-sign-out-alt"></i>

<span>Sign Out of Account</span>

</button>

</form>

</div>

@endauth

</aside>


<main class="flex-1 relative w-full h-full bg-geo-100">


<div class="absolute top-24 left-1/2 transform -translate-x-1/2 z-[60] w-full max-w-md px-4 flex flex-col gap-2 pointer-events-none">

@if(session('error'))

<div class="bg-white border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-2xl flex items-center shadow-2xl pointer-events-auto animate-bounce">

<i class="fas fa-exclamation-triangle mr-3 text-red-500 text-xl shrink-0"></i>

<span class="font-bold">{{ session('error') }}</span>

</div>

@endif


@if($errors->any())

<div class="bg-white border-l-4 border-red-500 text-red-700 px-6 py-4 rounded-2xl flex flex-col shadow-2xl pointer-events-auto">

<div class="flex items-center mb-1">

<i class="fas fa-exclamation-circle mr-3 text-red-500 text-xl shrink-0"></i>

<span class="font-bold">Please fix the following:</span>

</div>

<ul class="list-disc pl-9 text-sm font-medium">

@foreach($errors->all() as $error)

<li>{{ $error }}</li>

@endforeach

</ul>

</div>

@endif

</div>


<div id="map" class="w-full h-full z-0"></div>


<button onclick="openSidebar()" class="md:hidden absolute top-6 left-6 z-[30] w-14 h-14 bg-white/90 backdrop-blur-md text-geo-600 rounded-2xl shadow-[0_4px_20px_rgba(31,46,44,0.15)] border border-geo-400 flex justify-center items-center hover:text-geo-900 transition-colors">

<i class="fas fa-search text-xl"></i>

</button>


<div class="absolute top-6 right-6 z-[30] flex flex-col items-end gap-4">

@auth

@php

$notifications = auth()->user()->unreadNotifications ?? collect();

@endphp

<div class="flex gap-4">

<div class="relative">

<button onclick="toggleNotifications()" class="w-14 h-14 bg-white/90 backdrop-blur-md text-geo-600 rounded-2xl shadow-[0_4px_20px_rgba(31,46,44,0.15)] border border-geo-400 flex justify-center items-center hover:text-geo-900 transition group">

<i class="fas fa-bell text-xl group-hover:scale-110 transition-transform"></i>

@if($notifications->count() > 0)

<span class="absolute top-3 right-3 w-3 h-3 bg-red-500 rounded-full border-2 border-white shadow-sm"></span>

@endif

</button>


<div id="notificationsPopup" class="absolute top-16 right-0 w-80 sm:w-96 max-h-96 overflow-y-auto bg-white rounded-3xl shadow-2xl border border-geo-400 transform scale-95 opacity-0 pointer-events-none transition-all origin-top-right z-50 custom-scrollbar">

<div class="p-5 border-b border-geo-400 flex justify-between items-center sticky top-0 bg-white/95 backdrop-blur-sm z-10">

<h3 class="font-extrabold text-geo-900 text-lg">Notifications</h3>

<button onclick="toggleNotifications()" class="w-8 h-8 rounded-full bg-geo-100 text-geo-600 hover:text-geo-900 hover:bg-geo-400 flex items-center justify-center transition-colors"><i class="fas fa-times"></i></button>

</div>


<div class="flex flex-col">

@forelse($notifications as $notification)

@php

$nData = is_array($notification->data) ? $notification->data : [];

@endphp

<a href="{{ $nData['url'] ?? '#' }}" class="p-5 border-b border-geo-100 hover:bg-geo-100 transition-colors flex gap-4 group">

<div class="mt-1 shrink-0 w-10 h-10 rounded-xl bg-geo-100 flex items-center justify-center group-hover:bg-geo-500 group-hover:text-white transition-colors text-geo-600">

<i class="{{ $nData['icon'] ?? 'fas fa-bell' }} text-lg"></i>

</div>

<div>

<p class="text-sm font-extrabold text-geo-900 mb-0.5">{{ $nData['title'] ?? 'New Notification' }}</p>

<p class="text-xs text-geo-900/70 font-medium leading-snug">{{ $nData['message'] ?? 'You have a new update.' }}</p>

<p class="text-[9px] font-bold text-geo-500 mt-2 uppercase tracking-widest">{{ optional($notification->created_at)->diffForHumans() ?? 'Just now' }}</p>

</div>

</a>

@empty

<div class="p-10 text-center text-geo-900/50">

<div class="w-16 h-16 bg-geo-100 rounded-2xl flex items-center justify-center mx-auto mb-3">

<i class="far fa-bell-slash text-2xl text-geo-500"></i>

</div>

<p class="font-bold text-sm">You're all caught up!</p>

</div>

@endforelse

</div>

</div>

</div>


<a href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : (auth()->user()->role === 'pharmacy_admin' ? route('portal.dashboard') : route('user.dashboard')) }}" 

class="w-14 h-14 bg-geo-600 text-geo-100 rounded-2xl shadow-[0_4px_20px_rgba(47,126,106,0.4)] border border-geo-900/10 flex justify-center items-center hover:bg-geo-900 transition hover:scale-105" title="Go to Dashboard">

<i class="fas fa-user text-xl"></i>

</a>

</div>

@else

<a href="{{ route('login') }}" class="bg-white/90 backdrop-blur-md text-geo-600 px-6 py-4 rounded-2xl shadow-[0_4px_20px_rgba(31,46,44,0.15)] border border-geo-400 flex justify-center items-center hover:text-geo-900 hover:bg-white transition font-black text-sm">

<i class="fas fa-sign-in-alt mr-2"></i> Sign In

</a>

@endauth


<button onclick="locateUser()" class="w-14 h-14 bg-white/90 backdrop-blur-md text-geo-600 rounded-2xl shadow-[0_4px_20px_rgba(31,46,44,0.15)] border border-geo-400 flex justify-center items-center hover:text-geo-900 hover:bg-white transition group mt-2" title="Locate Me">

<i class="fas fa-location-arrow text-xl group-hover:scale-110 transition-transform"></i>

</button>

</div>

</main>

</div>


<div id="guestModal" class="fixed inset-0 bg-geo-900/60 backdrop-blur-md z-[100] hidden flex items-center justify-center p-4">

<div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden transform scale-95 transition-transform duration-300 text-center p-8 border-t-4 border-geo-500" id="guestModalContent">

<div class="w-20 h-20 bg-geo-100 text-geo-600 rounded-2xl flex items-center justify-center mx-auto mb-6">

<i class="fas fa-user-lock text-3xl"></i>

</div>

<h3 class="text-2xl font-extrabold text-geo-900 mb-2 tracking-tight">Sign In Required</h3>

<p class="text-geo-900/60 mb-8 text-sm font-medium">To keep your reservations secure and track your pickups, please sign in to your free patient account.</p>

<div class="flex flex-col gap-3">

<a href="{{ route('login') }}" class="w-full bg-geo-600 hover:bg-geo-900 text-geo-100 font-black py-4 rounded-xl shadow-lg shadow-geo-600/20 transition-all flex justify-center items-center text-base">

Log In to Account

</a>

<a href="{{ route('register') }}" class="w-full bg-white hover:bg-geo-100 text-geo-600 font-bold py-4 rounded-xl transition-all flex justify-center items-center text-base border-2 border-geo-500">

Create Free Account

</a>

<button onclick="closeGuestModal()" class="mt-3 text-sm text-geo-900/40 hover:text-geo-900 font-bold transition-colors">

Maybe Later

</button>

</div>

</div>

</div>


<script>

const bacolodBounds = L.latLngBounds([10.5500, 122.8800], [10.7500, 123.0500]);
const map = L.map('map', { zoomControl: false, minZoom: 14 }).setView([10.6385, 122.9566], 15); 

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

// Load and display Barangay Alijis geofence boundary
fetch('/geojson/alijis_boundary.geojson')
    .then(response => response.json())
    .then(geojsonData => {
        const boundaryLayer = L.geoJSON(geojsonData, {
            style: {
                color: '#2F7E6A',
                weight: 3,
                fillColor: '#BFE8D6',
                fillOpacity: 0.15,
                dashArray: '5, 8'
            }
        }).addTo(map);

        const bounds = boundaryLayer.getBounds();
        // Focus map view exactly on Barangay Alijis bounds
        map.fitBounds(bounds, { padding: [10, 10] });

        // Lock map view panning to Barangay Alijis
        map.setMaxBounds(bounds.pad(0.08));
    })
    .catch(err => console.error('Error loading Barangay Alijis boundary geojson:', err));


let pharmacyMarkers = [];

let userMarker = null;

let userAccuracyCircle = null;

let currentLat = null;

let currentLng = null;


// Pulse-animated pharmacy marker

const pharmacyIcon = L.divIcon({

html: '<div style="position:relative;width:36px;height:36px;"><div class="marker-pulse-ring"></div><div style="background-color:#2F7E6A;width:36px;height:36px;border-radius:50%;border:3px solid white;box-shadow:0 4px 12px rgba(31,46,44,0.35);display:flex;align-items:center;justify-content:center;position:relative;z-index:2;"><i class="fas fa-clinic-medical" style="color:#E9F7F2;font-size:13px;"></i></div></div>',

className: '',

iconSize: [36, 36],

iconAnchor: [18, 18],

popupAnchor: [0, -20]

});


// Toast notification system (replaces alert())

function showToast(message, type = 'info', duration = 3500) {

const existing = document.querySelector('.toast-notification');

if (existing) existing.remove();

const iconMap = { success: 'fa-check-circle', error: 'fa-exclamation-triangle', info: 'fa-info-circle' };

const toast = document.createElement('div');

toast.className = `toast-notification toast-${type}`;

toast.innerHTML = `<i class="fas ${iconMap[type] || iconMap.info} text-lg"></i><span>${message}</span>`;

document.body.appendChild(toast);

requestAnimationFrame(() => { requestAnimationFrame(() => { toast.classList.add('toast-visible'); }); });

setTimeout(() => { toast.classList.remove('toast-visible'); setTimeout(() => toast.remove(), 400); }, duration);

}


function openSidebar() { 

document.getElementById('sidebar').classList.remove('-translate-x-full'); 

const backdrop = document.getElementById('mobileBackdrop');

backdrop.classList.remove('opacity-0', 'pointer-events-none'); 

backdrop.classList.add('opacity-100', 'pointer-events-auto'); 

}


function closeSidebar() { 

document.getElementById('sidebar').classList.add('-translate-x-full'); 

const backdrop = document.getElementById('mobileBackdrop');

backdrop.classList.remove('opacity-100', 'pointer-events-auto'); 

backdrop.classList.add('opacity-0', 'pointer-events-none'); 

}


function toggleNotifications() { 

const popup = document.getElementById('notificationsPopup');

if(popup) {

popup.classList.toggle('opacity-0'); 

popup.classList.toggle('opacity-100'); 

popup.classList.toggle('scale-95'); 

popup.classList.toggle('scale-100'); 

popup.classList.toggle('pointer-events-none'); 

}

}


function locateUser() {
    if (!navigator.geolocation) {
        showToast('Geolocation is not supported by your browser.', 'error');
        map.flyTo([10.6385, 122.9566], 16, { animate: true });
        return;
    }

    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        showToast('Geolocation requires HTTPS or localhost. Defaulting to Barangay Alijis center.', 'warning', 4000);
        map.flyTo([10.6385, 122.9566], 16, { animate: true });
        return;
    }

    showToast('Detecting your location...', 'info', 1500);

    let resolved = false;

    const setPositionOnMap = (lat, lng, isExact = true) => {
        if (resolved) return;
        resolved = true;

        currentLat = lat;
        currentLng = lng;

        if (!bacolodBounds.contains(L.latLng(currentLat, currentLng))) {
            currentLat = 10.6385;
            currentLng = 122.9566;
        }

        map.flyTo([currentLat, currentLng], 16, { animate: true, duration: 1.2 });

        if (userMarker) {
            userMarker.setLatLng([currentLat, currentLng]);
            if (userAccuracyCircle) userAccuracyCircle.setLatLng([currentLat, currentLng]);
        } else {
            userMarker = L.circleMarker([currentLat, currentLng], {
                radius: 8,
                fillColor: "#63C6A7",
                color: "#ffffff",
                weight: 3,
                opacity: 1,
                fillOpacity: 1
            }).addTo(map);

            userAccuracyCircle = L.circle([currentLat, currentLng], {
                radius: 150,
                fillColor: "#BFE8D6",
                color: "#63C6A7",
                weight: 1,
                opacity: 0.5,
                fillOpacity: 0.3
            }).addTo(map);
        }

        if (isExact) {
            showToast('Location pinpointed successfully!', 'success', 2500);
        } else {
            showToast('Centered on Barangay Alijis service area.', 'info', 2500);
        }
    };

    // Safety timeout: If browser location hangs > 3 seconds, default to Alijis center immediately
    const fallbackTimer = setTimeout(() => {
        if (!resolved) {
            setPositionOnMap(10.6385, 122.9566, false);
        }
    }, 3000);

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            clearTimeout(fallbackTimer);
            setPositionOnMap(pos.coords.latitude, pos.coords.longitude, true);
        },
        (err) => {
            clearTimeout(fallbackTimer);
            setPositionOnMap(10.6385, 122.9566, false);
        },
        { enableHighAccuracy: false, timeout: 3000, maximumAge: 30000 }
    );
}

// Category Selection Logic

let activeCategory = '';

document.querySelectorAll('.category-chip').forEach(btn => {

btn.addEventListener('click', function() {

document.querySelectorAll('.category-chip').forEach(b => {

b.classList.remove('bg-geo-600', 'text-white', 'active');

b.classList.add('bg-geo-100', 'text-geo-600');

});

this.classList.remove('bg-geo-100', 'text-geo-600');

this.classList.add('bg-geo-600', 'text-white', 'active');

activeCategory = this.dataset.category;

performSearch();

});

});


// Trigger search on enter

document.getElementById('searchInput').addEventListener('keypress', function (e) {

if (e.key === 'Enter') {

performSearch();

document.getElementById('searchInput').blur();

}

});


// Re-trigger search on sort change

document.getElementById('sortInput').addEventListener('change', performSearch);



function performSearch() {

const medicine = document.getElementById('searchInput').value;

// Removed: if (!medicine) return; -> we allow blank to search by category alone

const searchLat = currentLat || 10.6385; const searchLng = currentLng || 122.9566;


const sort = document.getElementById('sortInput').value;

const listDiv = document.getElementById('pharmacyList');


// Loading State UI updated to palette

listDiv.innerHTML = `<div class="flex flex-col items-center justify-center py-16"><div class="w-12 h-12 border-4 border-geo-400 border-t-geo-500 rounded-full animate-spin mb-4"></div><p class="text-geo-600 font-bold text-sm">Searching pharmacies...</p></div>`;

if(window.innerWidth < 768) closeSidebar();


fetch(`/api/pharmacies/nearby?lat=${searchLat}&lng=${searchLng}&medicine=${encodeURIComponent(medicine)}&sort=${sort}&category=${encodeURIComponent(activeCategory)}`)

.then(res => res.json())

.then(data => {

pharmacyMarkers.forEach(m => map.removeLayer(m)); pharmacyMarkers = []; listDiv.innerHTML = '';

if (!data.pharmacies || data.pharmacies.length === 0) { 

if(window.innerWidth < 768) openSidebar(); 

// Out of Stock UI adjusted

listDiv.innerHTML = `<div class="bg-red-50 border border-red-100 p-8 rounded-[2rem] text-center shadow-inner mt-4"><div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center mx-auto mb-3 shadow-sm rotate-3"><i class="fas fa-box-open text-red-400 text-2xl"></i></div><p class="text-red-800 font-extrabold text-lg">Out of Stock</p><p class="text-red-600/80 text-xs mt-1 font-medium">We couldn't find this medicine nearby.</p></div>`; 

return; 

}


// Results counter header

const count = data.pharmacies.length;

listDiv.innerHTML = `

<div class="flex items-center justify-between mb-4 px-1">

<p class="text-sm font-extrabold text-geo-900"><span class="text-geo-600">${count}</span> ${count === 1 ? 'pharmacy' : 'pharmacies'} found</p>

<span class="text-[10px] font-bold text-geo-900/40 uppercase tracking-widest">${document.getElementById('searchInput').value}</span>

</div>`;


data.pharmacies.forEach((pharmacy, index) => {

const med = pharmacy.medicines && pharmacy.medicines.length > 0 ? pharmacy.medicines[0] : null;

const addr = pharmacy.address || 'Address not available';


// Enhanced popup with name, price, address, and unit

let popupHtml = `

<div style="padding:16px 18px;font-family:Outfit,sans-serif;">

<p style="font-weight:800;font-size:14px;color:#1F2E2C;margin:0 0 4px 0;">${pharmacy.name}</p>

<p style="font-size:11px;color:#1F2E2C99;margin:0 0 8px 0;line-height:1.4;">${addr}</p>`;


if (med) {

popupHtml += `

<p style="font-weight:800;font-size:18px;color:#2F7E6A;margin:0;display:flex;align-items:baseline;gap:4px;">

₱${parseFloat(med.pivot.selling_price).toFixed(2)}

<span style="font-size:10px;color:rgba(31,46,44,0.5);text-transform:uppercase;letter-spacing:0.05em;">/ ${med.pivot.unit || 'Piece'}</span>

</p>`;

} else {

popupHtml += `<p style="font-size:11px;font-weight:700;color:#2F7E6A;margin:0;">Catalog Available</p>`;

}


popupHtml += `</div>`;


const marker = L.marker([pharmacy.latitude, pharmacy.longitude], { icon: pharmacyIcon })

.addTo(map)

.bindPopup(popupHtml);


// Fly to on marker click

marker.on('click', function() {

map.flyTo([pharmacy.latitude, pharmacy.longitude], 16, { animate: true, duration: 0.8 });

});


pharmacyMarkers.push(marker);


let visualMediaHtml = '';

if (pharmacy.storefront_image) {

visualMediaHtml = `<img src="/storage/${pharmacy.storefront_image}" alt="Storefront" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">`;

} else {

visualMediaHtml = `<iframe width="100%" height="100%" frameborder="0" style="border:0; pointer-events: none;" src="https://maps.google.com/maps?q=${pharmacy.latitude},${pharmacy.longitude}&t=k&z=19&ie=UTF8&iwloc=&output=embed"></iframe>`;

}


// Premium pharmacy card with address, animations, and enhanced buttons

let cardHtml = `

<div class="pharmacy-card-enter" style="animation-delay: ${index * 80}ms;">

<div class="w-full h-40 bg-geo-400 rounded-[2rem] shadow-[0_2px_10px_rgba(31,46,44,0.05)] border border-geo-400 overflow-hidden relative cursor-pointer mb-2 group" onclick="focusPharmacy(${pharmacy.latitude}, ${pharmacy.longitude})">

${visualMediaHtml}

<div class="absolute inset-0 bg-geo-900/10 group-hover:bg-transparent transition-colors duration-300 pointer-events-none"></div>

<div class="absolute top-4 right-4 bg-geo-500/90 backdrop-blur-sm text-geo-900 text-[10px] px-3 py-1.5 rounded-full font-black uppercase tracking-wider shadow-sm pointer-events-none border border-white/50">

Open

</div>

</div>


<div class="pharmacy-card-body bg-white rounded-[2rem] shadow-sm border border-geo-400 p-6 flex flex-col gap-4 hover:border-geo-500 hover:shadow-md transition-all duration-300 relative z-10 -mt-6" data-coords="${pharmacy.latitude},${pharmacy.longitude}">

<div class="flex justify-between items-start">

<div class="flex-1 pr-4">

<h3 class="font-extrabold text-geo-900 text-lg leading-tight mb-1">${pharmacy.name}</h3>

<p class="text-[11px] text-geo-600 font-bold flex items-center gap-1.5 mb-1">

<i class="fas fa-map-marker-alt opacity-70"></i> ${pharmacy.distance.toFixed(1)} km away

</p>

<p class="text-[10px] text-geo-900/50 font-medium leading-snug line-clamp-2">

<i class="fas fa-location-dot opacity-50 mr-1"></i>${addr}

</p>

</div>`;


if (med) {

cardHtml += `

<div class="text-right shrink-0">

<p class="text-[9px] font-bold text-geo-900/50 uppercase tracking-widest mb-0.5 line-clamp-1">${med.brand_name || med.generic_name}</p>

<p class="font-black text-geo-600 text-2xl leading-none flex items-baseline gap-1 justify-end">

₱${parseFloat(med.pivot.selling_price).toFixed(2)}

<span class="text-[10px] font-bold text-geo-900/50 uppercase tracking-widest">/ ${med.pivot.unit || 'Piece'}</span>

</p>

</div>

</div>


<div class="flex items-center">

<span class="bg-geo-100 text-geo-600 border border-geo-400 text-[10px] px-3 py-1.5 rounded-xl font-extrabold uppercase tracking-wider flex items-center gap-1.5 shadow-sm">

<i class="fas fa-box-open"></i> ${med.pivot.quantity_on_hand} ${med.pivot.unit ? med.pivot.unit + '(s)' : 'Piece(s)'} in stock

</span>

</div>`;

} else {

cardHtml += `

<div class="text-right shrink-0 flex items-center">

<span class="bg-geo-100 text-geo-600 border border-geo-400 text-[10px] px-3 py-1.5 rounded-xl font-extrabold uppercase tracking-wider shadow-sm">

View Catalog

</span>

</div>

</div>`;

}


cardHtml += `

<div class="flex gap-2 pt-3 border-t border-geo-100">

<a href="/pharmacy/${pharmacy.id}/catalog" class="w-full bg-geo-600 hover:bg-geo-900 text-geo-100 font-black py-3.5 rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-xs uppercase tracking-wide hover:shadow-lg active:scale-[0.97]">

<i class="fas fa-info-circle"></i> Details

</a>

</div>

</div>

</div>

</div>

`;


listDiv.innerHTML += cardHtml;

});


if (pharmacyMarkers.length > 0) { 

const group = new L.featureGroup(pharmacyMarkers); 

if (userMarker) group.addLayer(userMarker); 

map.fitBounds(group.getBounds(), { padding: [50, 50] }); 

}

if(window.innerWidth < 768) openSidebar(); 

});

}


function focusPharmacy(lat, lng) { if(window.innerWidth < 768) closeSidebar(); map.flyTo([lat, lng], 17, { animate: true, duration: 1 }); }


function closeGuestModal() { document.getElementById('guestModal').classList.add('hidden'); }


document.getElementById('searchInput').addEventListener('keypress', function (e) { if (e.key === 'Enter') { e.preventDefault(); performSearch(); } });

</script>

</body>

</html>


The above content shows the entire, complete file contents of the requested file.
