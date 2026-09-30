<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — GEORX Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    @stack('styles')
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 font-sans">

    <!-- TOP NAVBAR -->
    <nav class="bg-white border-b border-slate-200 px-6 h-16 flex items-center sticky top-0 z-40 shadow-sm">
        <div class="w-full flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-indigo-600 text-white p-2 rounded-lg">
                    <i class="fas fa-shield-alt text-sm"></i>
                </div>
                <div>
                    <h1 class="text-base font-black text-slate-800 tracking-tight leading-none">GEORX</h1>
                    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest leading-none mt-0.5">Super Admin Panel</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2 text-slate-600 text-sm font-semibold hidden sm:flex">
                    <i class="fas fa-user-circle text-slate-400"></i>
                    {{ Auth::user()->name ?? 'Admin' }}
                </div>
                <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="button" id="logoutBtn" onclick="openLogoutModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 border border-slate-200">
                        <i class="fas fa-sign-out-alt text-slate-500"></i> <span class="hidden sm:inline">Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- MAIN BODY -->
    <div class="flex">

        <!-- SIDEBAR -->
        <aside class="w-60 bg-white border-r border-slate-200 min-h-[calc(100vh-4rem)] pt-6 flex-shrink-0 sticky top-16 self-start">
            <nav class="px-3 space-y-0.5">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-3">Main</p>

                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-tachometer-alt w-4 text-center {{ request()->routeIs('admin.dashboard') ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    Dashboard
                </a>

                <a href="{{ route('admin.pharmacies') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.pharmacies*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-store w-4 text-center {{ request()->routeIs('admin.pharmacies*') ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    Pharmacies
                </a>

                <a href="{{ route('admin.users') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.users*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-users w-4 text-center {{ request()->routeIs('admin.users*') ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    Users
                </a>

                <a href="{{ route('admin.drivers') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.drivers*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-motorcycle w-4 text-center {{ request()->routeIs('admin.drivers*') ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    Drivers
                </a>

                <a href="{{ route('admin.medicines') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.medicines*') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-pills w-4 text-center {{ request()->routeIs('admin.medicines*') ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    Medicines
                </a>

                <a href="{{ route('admin.reports') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.reports') ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-chart-bar w-4 text-center {{ request()->routeIs('admin.reports') ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    Reports
                </a>

                <div class="border-t border-slate-100 my-4"></div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 px-3">System</p>

                <a href="{{ route('admin.settings') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-colors {{ request()->routeIs('admin.settings') ? 'bg-amber-50 text-amber-700 border border-amber-100' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    <i class="fas fa-sliders-h w-4 text-center {{ request()->routeIs('admin.settings') ? 'text-amber-600' : 'text-slate-400' }}"></i>
                    Settings
                </a>

                <a href="{{ url('/') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-colors">
                    <i class="fas fa-globe w-4 text-center text-slate-400"></i>
                    Public Map
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 p-8 overflow-x-hidden">
            <div class="max-w-7xl mx-auto">
                @if(session('success'))
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm text-sm font-semibold">
                        <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm text-sm font-semibold">
                        <i class="fas fa-exclamation-circle mr-3 text-red-500"></i>
                        {{ session('error') }}
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal" 
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm opacity-0 pointer-events-none transition-opacity duration-200"
         role="dialog" 
         aria-modal="true" 
         aria-labelledby="logoutModalTitle" 
         aria-describedby="logoutModalDesc">
        
        <div id="logoutModalCard" 
             class="w-full max-w-sm bg-white rounded-2xl p-6 sm:p-7 shadow-2xl border border-slate-100 text-center transform scale-95 transition-transform duration-200">
            
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
