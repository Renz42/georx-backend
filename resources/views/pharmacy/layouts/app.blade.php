<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', 'Pharmacy Portal') — MedLocator</title>
    <meta name="description" content="MedLocator Pharmacy Partner Portal — Manage your inventory, orders, and customer messages.">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body { font-family: 'Outfit', sans-serif; }
        /* Custom scrollbar for a cleaner look */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    @stack('styles')
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' },
                        clinical: {
                            navy: '#0F172A',
                            surface: '#1E293B',
                            mint: '#E6F4EA',
                            pine: '#0D7A5F',
                            pineHover: '#0B6E53',
                            border: '#E2E8F0',
                            canvas: '#F8FAFC',
                            success: '#059669'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F8FAFC] h-screen flex flex-col overflow-hidden text-slate-800">

    <!-- TOP NAVBAR (Image 2: Executive Obsidian Navy #0F172A) -->
    <nav class="bg-[#0F172A] text-white h-[70px] flex items-center px-6 shadow-xs shrink-0 z-50 border-b border-slate-800 relative">
        <div class="w-full flex justify-between items-center max-w-[1800px] mx-auto">
            
            <div class="flex items-center gap-4">
                <button onclick="toggleAdminSidebar()" class="lg:hidden text-slate-300 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-slate-800" aria-label="Toggle navigation menu">
                    <i class="fas fa-bars text-xl"></i>
                </button>

                <!-- LOGO CONTAINER -->
                <div class="w-11 h-11 rounded-xl flex items-center justify-center overflow-hidden shrink-0 border border-slate-700 bg-slate-800/80 shadow-xs hidden sm:flex">
                    @if(Auth::user()->pharmacy && Auth::user()->pharmacy->logo_url)
                        <img src="{{ Auth::user()->pharmacy->logo_url }}" alt="Logo" class="w-full h-full object-cover bg-white">
                    @else
                        <i class="fas fa-clinic-medical text-sky-400 text-lg"></i>
                    @endif
                </div>

                <div>
                    <h1 class="text-base sm:text-lg font-bold tracking-tight text-white leading-none truncate max-w-[200px] sm:max-w-md">
                        {{ Auth::user()->pharmacy->name ?? 'Pharmacy Portal' }}
                    </h1>
                    <p class="text-slate-400 text-[10px] font-bold mt-1 uppercase tracking-widest">Partner Dashboard</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <!-- User Information Badge (Image 2: Sleek Slate Surface) -->
                <div class="flex items-center gap-2.5 bg-slate-800/70 border border-slate-700/80 px-3.5 py-2 rounded-xl shadow-xs hidden md:flex">
                    <i class="fas fa-user-circle text-slate-400 text-base"></i>
                    <span class="text-xs font-semibold text-slate-200 tracking-wide">{{ Auth::user()->name }}</span>
                </div>
                
                <!-- Logout Button (Image 2: Professional Bordered Pill) -->
                <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="button" id="logoutBtn" onclick="openLogoutModal()" 
                            class="border border-slate-700 hover:border-slate-600 bg-slate-800/40 hover:bg-slate-800 text-slate-300 hover:text-white px-3.5 sm:px-4 py-2 rounded-xl text-xs font-semibold transition-all flex items-center gap-2 shadow-xs active:scale-95">
                        <i class="fas fa-sign-out-alt text-slate-400 text-xs"></i> 
                        <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- BOTTOM SECTION (Sidebar + Main Content) -->
    <div class="flex flex-1 overflow-hidden relative">
        
        <!-- Mobile Sidebar Backdrop -->
        <div id="adminSidebarBackdrop" onclick="toggleAdminSidebar()" class="fixed inset-0 bg-slate-900/50 z-30 hidden lg:hidden backdrop-blur-xs transition-opacity opacity-0"></div>

        <!-- SIDEBAR (Image 2: Crisp Canvas with Soft-Mint & Deep-Pine Active Pill + 3px Left Indicator) -->
        <aside id="adminSidebar" class="absolute lg:relative top-0 left-0 h-full z-40 w-72 bg-white border-r border-slate-200/90 overflow-y-auto flex flex-col shrink-0 py-6 custom-scrollbar shadow-[2px_0_16px_rgba(15,23,42,0.03)] transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
            <div class="px-5 mb-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 px-3">Main Menu</p>
                
                @php
                    $isDashboard = request()->is('portal/dashboard') || request()->is('pharmacy/dashboard') || request()->routeIs('*.dashboard');
                    $isInventory = request()->is('portal/inventory*') || request()->is('pharmacy/inventory*') || request()->routeIs('*.inventory*');
                    $isOrders = request()->routeIs('*.orders*') || request()->is('portal/orders*') || request()->is('pharmacy/orders*');
                    $isMessages = request()->routeIs('*.messages*') || request()->is('portal/messages*') || request()->is('pharmacy/messages*');
                    $isAudit = request()->routeIs('*.audit_logs*') || request()->is('portal/audit_logs*') || request()->is('pharmacy/audit_logs*');
                    $isReviews = request()->routeIs('*.reviews*') || request()->is('portal/reviews*') || request()->is('pharmacy/reviews*');
                    $isCatalog = request()->routeIs('*.catalog*') || request()->is('portal/catalog*') || request()->is('pharmacy/catalog*');
                    $isProfile = request()->is('portal/profile*') || request()->is('pharmacy/profile*') || request()->routeIs('*.profile*');
                    $isSettings = request()->routeIs('*.settings*') || request()->is('portal/settings*') || request()->is('pharmacy/settings*');
                @endphp

                <!-- Dashboard Link -->
                <div class="relative mb-1">
                    @if($isDashboard)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.dashboard') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isDashboard ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-tachometer-alt w-5 text-center text-base {{ $isDashboard ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Dashboard</span>
                    </a>
                </div>

                <!-- Inventory Link -->
                <div class="relative mb-1">
                    @if($isInventory)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.inventory') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isInventory ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-pills w-5 text-center text-base {{ $isInventory ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>My Inventory</span>
                    </a>
                </div>

                <!-- Incoming Orders Link -->
                <div class="relative mb-1">
                    @if($isOrders)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.orders') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isOrders ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-clipboard-list w-5 text-center text-base {{ $isOrders ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Incoming Orders</span>
                    </a>
                </div>

                <!-- Messages Link -->
                <div class="relative mb-1">
                    @if($isMessages)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.messages.index') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isMessages ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-envelope w-5 text-center text-base {{ $isMessages ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Messages</span>
                    </a>
                </div>

                <!-- Audit Logs Link -->
                @if(Auth::user()->isPharmacyOwner() || Auth::user()->isPharmacist())
                <div class="relative mb-1">
                    @if($isAudit)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.audit_logs') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isAudit ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-history w-5 text-center text-base {{ $isAudit ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Audit Logs</span>
                    </a>
                </div>
                @endif

                <!-- Reviews Link -->
                @if(Auth::user()->isPharmacyOwner() || Auth::user()->isPharmacist())
                <div class="relative mb-1">
                    @if($isReviews)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.reviews') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isReviews ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-star w-5 text-center text-base {{ $isReviews ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Customer Reviews</span>
                    </a>
                </div>
                @endif

                <!-- Catalog Link -->
                <div class="relative mb-1">
                    @if($isCatalog)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.catalog') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isCatalog ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-book-medical w-5 text-center text-base {{ $isCatalog ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Medicine Catalog</span>
                    </a>
                </div>

                @if(Auth::user()->isPharmacyOwner())
                <!-- Pharmacy Profile Link -->
                <div class="relative mb-1">
                    @if($isProfile)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.profile') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isProfile ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-store w-5 text-center text-base {{ $isProfile ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Pharmacy Profile</span>
                    </a>
                </div>

                <!-- Settings Link -->
                <div class="relative mb-1">
                    @if($isSettings)
                        <span class="absolute -left-5 top-2 bottom-2 w-1 rounded-r-full bg-[#0D7A5F]" aria-hidden="true"></span>
                    @endif
                    <a href="{{ route('portal.settings') }}" 
                       class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-xs sm:text-sm font-semibold transition-all {{ $isSettings ? 'bg-[#E6F4EA] text-[#0D7A5F] font-bold shadow-2xs' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 group' }}">
                        <i class="fas fa-cog w-5 text-center text-base {{ $isSettings ? 'text-[#0D7A5F]' : 'text-slate-400 group-hover:text-slate-600' }}"></i> 
                        <span>Settings</span>
                    </a>
                </div>
                @endif
            </div>

            <div class="mt-auto px-5 pt-6 border-t border-slate-100">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 px-3">Support</p>
                <a href="{{ url('/') }}" target="_blank" class="flex items-center gap-3.5 px-3.5 py-3 rounded-xl text-slate-600 hover:bg-slate-50 hover:text-slate-900 text-xs sm:text-sm font-semibold transition-all group">
                    <i class="fas fa-external-link-alt w-5 text-center text-base text-slate-400 group-hover:text-slate-600"></i> 
                    <span>View Public Map</span>
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 overflow-y-auto p-6 md:p-8 lg:p-10 relative bg-[#F8FAFC] custom-scrollbar w-full">
            <div class="w-full max-w-[1600px] mx-auto">
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Javascript for Mobile Sidebar Toggle -->
    <script>
        function toggleAdminSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('adminSidebarBackdrop');
            
            sidebar.classList.toggle('-translate-x-full');
            
            if (backdrop.classList.contains('hidden')) {
                backdrop.classList.remove('hidden');
                setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
            } else {
                backdrop.classList.add('opacity-0');
                setTimeout(() => backdrop.classList.add('hidden'), 300);
            }
        }
    </script>
    
    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-200"
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="logoutModalTitle" 
         aria-describedby="logoutModalDesc">
        
        <div id="logoutModalCard" 
             class="w-full max-w-sm bg-white rounded-2xl p-6 sm:p-7 shadow-2xl border border-slate-100 text-center transform scale-95 transition-transform duration-200 text-slate-800">
            
            <!-- Soft Rose Warning Badge -->
            <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto mb-4 text-xl">
                <i class="fas fa-sign-out-alt"></i>
            </div>

            <!-- Title -->
            <h3 id="logoutModalTitle" class="text-xl font-black text-slate-900 tracking-tight">
                Log Out?
            </h3>

            <!-- Description -->
            <p id="logoutModalDesc" class="text-slate-500 text-sm font-medium mt-2 leading-relaxed">
                Are you sure you want to log out of your GEORX account?
            </p>

            <!-- Action Buttons -->
            <div class="mt-6 flex flex-col-reverse sm:flex-row items-center justify-center gap-2.5">
                <!-- No, Cancel Button -->
                <button type="button" 
                        id="cancelLogoutBtn"
                        onclick="closeLogoutModal()" 
                        class="w-full sm:w-auto flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 px-4 rounded-xl border border-slate-200/80 transition-all text-sm focus:outline-none focus:ring-2 focus:ring-slate-400">
                    No, Cancel
                </button>

                <!-- Yes, Log Out Button -->
                <button type="button" 
                        id="confirmLogoutBtn"
                        onclick="submitLogout()" 
                        class="w-full sm:w-auto flex-1 bg-rose-600 hover:bg-rose-700 text-white font-bold py-2.5 px-4 rounded-xl shadow-sm hover:shadow transition-all text-sm flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-rose-500 active:scale-[0.99]">
                    <span id="confirmLogoutText">Yes, Log Out</span>
                    <i id="confirmLogoutSpinner" class="fas fa-spinner fa-spin hidden text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Script -->
    <script>
        let triggerLogoutBtn = null;

        function openLogoutModal() {
            triggerLogoutBtn = document.activeElement;
            const modal = document.getElementById('logoutModal');
            const card = document.getElementById('logoutModalCard');
            const cancelBtn = document.getElementById('cancelLogoutBtn');
            
            if (modal && card) {
                modal.classList.remove('opacity-0', 'pointer-events-none');
                modal.classList.add('opacity-100');
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
                
                if (cancelBtn) {
                    setTimeout(() => cancelBtn.focus(), 50);
                }
            }
        }

        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            const card = document.getElementById('logoutModalCard');
            
            if (modal && card) {
                modal.classList.add('opacity-0', 'pointer-events-none');
                modal.classList.remove('opacity-100');
                card.classList.add('scale-95');
                card.classList.remove('scale-100');
                
                if (triggerLogoutBtn) {
                    triggerLogoutBtn.focus();
                }
            }
        }

        function submitLogout() {
            const confirmBtn = document.getElementById('confirmLogoutBtn');
            const confirmText = document.getElementById('confirmLogoutText');
            const confirmSpinner = document.getElementById('confirmLogoutSpinner');
            const form = document.getElementById('logoutForm');

            if (confirmBtn && form) {
                confirmBtn.disabled = true;
                confirmBtn.classList.add('opacity-75', 'cursor-not-allowed');
                if (confirmText) confirmText.textContent = 'Logging out...';
                if (confirmSpinner) confirmSpinner.classList.remove('hidden');

                form.submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('logoutModal');
            const card = document.getElementById('logoutModalCard');

            if (modal && card) {
                modal.addEventListener('click', function (e) {
                    if (!card.contains(e.target)) {
                        closeLogoutModal();
                    }
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    if (modal && !modal.classList.contains('pointer-events-none')) {
                        closeLogoutModal();
                    }
                }
            });
        });
    </script>
    
    @stack('scripts')
</body>
</html>
