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
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 h-screen flex flex-col overflow-hidden text-slate-800">

    <!-- TOP NAVBAR -->
    <nav class="text-white h-[72px] flex items-center px-6 shadow-sm shrink-0 z-50 transition-colors duration-300 relative" 
         style="background-color: {{ Auth::user()->pharmacy->theme_color ?? '#2563eb' }}; border-bottom: 1px solid rgba(0,0,0,0.1);">
        <div class="w-full flex justify-between items-center max-w-[1800px] mx-auto">
            
            <div class="flex items-center gap-4">
                <button onclick="toggleAdminSidebar()" class="lg:hidden text-white/90 hover:text-white transition-colors p-1">
                    <i class="fas fa-bars text-2xl"></i>
                </button>

                <!-- LOGO CONTAINER -->
                <div class="w-12 h-12 rounded-xl shadow-inner flex items-center justify-center overflow-hidden shrink-0 border-2 border-white/20 bg-white/10 backdrop-blur-sm hidden sm:flex">
                    @if(Auth::user()->pharmacy->logo)
                        <img src="{{ asset('storage/' . Auth::user()->pharmacy->logo) }}" alt="Logo" class="w-full h-full object-cover bg-white">
                    @else
                        <i class="fas fa-clinic-medical text-white text-xl shadow-sm"></i>
                    @endif
                </div>

                <div>
                    <h1 class="text-xl font-black tracking-tight leading-none drop-shadow-sm truncate max-w-[200px] sm:max-w-md">{{ Auth::user()->pharmacy->name ?? 'Pharmacy Portal' }}</h1>
                    <p class="text-white/80 text-[10px] font-bold mt-1 uppercase tracking-widest">Partner Dashboard</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 sm:gap-4">
                <!-- User Badge -->
                <div class="flex items-center gap-2 bg-black/10 backdrop-blur-sm px-4 py-2.5 rounded-xl border border-white/10 shadow-inner hidden md:flex">
                    <i class="fas fa-user-circle text-white/80 text-lg"></i>
                    <span class="text-sm font-bold tracking-wide">{{ Auth::user()->name }}</span>
                </div>
                
                <!-- Logout Button -->
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-black/20 hover:bg-black/40 px-4 sm:px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 border border-white/10 shadow-sm active:scale-95">
                        <i class="fas fa-sign-out-alt"></i> <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- BOTTOM SECTION (Sidebar + Main Content) -->
    <div class="flex flex-1 overflow-hidden relative">
        
        <!-- Mobile Sidebar Backdrop -->
        <div id="adminSidebarBackdrop" onclick="toggleAdminSidebar()" class="fixed inset-0 bg-slate-900/40 z-30 hidden lg:hidden backdrop-blur-sm transition-opacity opacity-0"></div>

        <!-- SIDEBAR -->
        <aside id="adminSidebar" class="absolute lg:relative top-0 left-0 h-full z-40 w-72 bg-white border-r border-slate-200 overflow-y-auto flex flex-col shrink-0 py-6 custom-scrollbar shadow-[4px_0_24px_rgba(0,0,0,0.05)] transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
            <div class="px-5 mb-2">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-2">Main Menu</p>
                
                <!-- Dashboard Link -->
                <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->is('portal/dashboard') || request()->is('pharmacy/dashboard') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold' }}">
                    <i class="fas fa-tachometer-alt w-5 text-center text-lg {{ request()->is('portal/dashboard') || request()->is('pharmacy/dashboard') ? 'text-blue-600' : 'text-slate-400' }}"></i> 
                    Dashboard
                </a>

                <!-- Inventory Link -->
                <a href="{{ route('portal.inventory') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->is('portal/inventory') || request()->is('pharmacy/inventory') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold' }}">
                    <i class="fas fa-pills w-5 text-center text-lg {{ request()->is('portal/inventory') || request()->is('pharmacy/inventory') ? 'text-blue-600' : 'text-slate-400' }}"></i> 
                    My Inventory
                </a>

                <!-- Orders Link -->
                <a href="{{ route('portal.orders') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->routeIs('*.orders') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold group' }}">
                    <i class="fas fa-clipboard-list w-5 text-center text-lg {{ request()->routeIs('*.orders') ? 'text-blue-600' : 'text-slate-400 group-hover:text-blue-500' }} transition-colors"></i> 
                    Incoming Orders
                </a>

                <!-- Messages Link -->
                <a href="{{ route('portal.messages.index') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->routeIs('*.messages.*') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold group' }}">
                    <i class="fas fa-envelope w-5 text-center text-lg {{ request()->routeIs('*.messages.*') ? 'text-blue-600' : 'text-slate-400 group-hover:text-blue-500' }} transition-colors"></i> 
                    Messages
                </a>

                <!-- Audit Logs Link -->
                @if(Auth::user()->isPharmacyOwner() || Auth::user()->isPharmacist())
                <a href="{{ route('portal.audit_logs') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->routeIs('*.audit_logs') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold group' }}">
                    <i class="fas fa-history w-5 text-center text-lg {{ request()->routeIs('*.audit_logs') ? 'text-blue-600' : 'text-slate-400 group-hover:text-blue-500' }} transition-colors"></i> 
                    Audit Logs
                </a>
                @endif

                <!-- Catalog Link -->
                <a href="{{ route('portal.catalog') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->routeIs('*.catalog') ? 'bg-blue-50 text-blue-700 font-bold border border-blue-100 shadow-sm' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold group' }}">
                    <i class="fas fa-book-medical w-5 text-center text-lg {{ request()->routeIs('*.catalog') ? 'text-blue-600' : 'text-slate-400 group-hover:text-blue-500' }} transition-colors"></i> 
                    Medicine Catalog
                </a>
                @if(Auth::user()->isPharmacyOwner())
                <a href="{{ route('portal.profile') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->is('portal/profile') || request()->is('pharmacy/profile') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold' }}">
                    <i class="fas fa-store w-5 text-center text-lg {{ request()->is('portal/profile') || request()->is('pharmacy/profile') ? 'text-blue-600' : 'text-slate-400' }}"></i> 
                    Pharmacy Profile
                </a>

                <!-- Settings Link -->
                <a href="{{ route('portal.settings') }}" class="flex items-center gap-4 px-4 py-3.5 rounded-xl mb-1 transition-all {{ request()->routeIs('*.settings') ? 'bg-blue-50 text-blue-700 font-bold shadow-sm border-blue-200 border' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-semibold group' }}">
                    <i class="fas fa-cog w-5 text-center text-lg {{ request()->routeIs('*.settings') ? 'text-blue-600' : 'text-slate-400 group-hover:text-blue-500' }} transition-colors"></i> 
                    Settings
                </a>
                @endif
            </div>

            <div class="mt-auto px-5 pt-6 border-t border-slate-100">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-2">Support</p>
                <a href="{{ url('/') }}" target="_blank" class="flex items-center gap-4 px-4 py-3.5 rounded-xl text-slate-500 hover:bg-slate-50 hover:text-slate-800 font-bold transition-all group">
                    <i class="fas fa-external-link-alt w-5 text-center text-lg text-slate-400 group-hover:text-blue-500"></i> 
                    View Public Map
                </a>
            </div>
        </aside>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 overflow-y-auto p-6 md:p-8 lg:p-12 relative bg-slate-50/50 custom-scrollbar w-full">
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
    
    @stack('scripts')
</body>
</html>
