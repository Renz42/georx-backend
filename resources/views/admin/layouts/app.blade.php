<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — GEORX Super Admin</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'] },
                    colors: {
                        geo: {
                            900: '#0F172A',
                            800: '#1E293B',
                            700: '#1F2E2C',
                            600: '#2F7E6A',
                            500: '#63C6A7',
                            400: '#BFE8D6',
                            300: '#A0D8C4',
                            100: '#E9F7F2',
                            50: '#F8FAFC'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    @stack('styles')
</head>
<body class="bg-[#F8FAFC] min-h-screen text-[#0F172A] antialiased">

    <div class="min-h-screen flex bg-[#F8FAFC]">
        <!-- Mobile Sidebar Backdrop -->
        <div id="mobileSidebarBackdrop"
             onclick="closeMobileSidebar()"
             class="fixed inset-0 bg-slate-900/40 backdrop-blur-[1px] z-40 hidden lg:hidden transition-opacity"></div>

        <!-- COLLAPSIBLE LEFT SIDEBAR (260px fixed on desktop, slide-over drawer on mobile/tablet) -->
        <aside id="adminSidebar"
               class="fixed inset-y-0 left-0 z-50 w-[260px] bg-white border-r border-[#E2E8F0] flex flex-col justify-between transition-all duration-200 -translate-x-full lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen shrink-0 overflow-hidden">

            <!-- Top Brand + Navigation -->
            <div class="flex flex-col min-h-0 flex-1 overflow-y-auto">
                <!-- Brand Header -->
                <div class="px-5 h-[72px] border-b border-[#E2E8F0] flex items-center justify-between shrink-0">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-white border border-[#E2E8F0] p-1 shadow-2xs flex items-center justify-center shrink-0">
                            <img src="{{ asset('images/georx-logo.png') }}" alt="GEORX Logo" class="w-full h-full object-contain">
                        </div>
                        <div class="min-w-0">
                            <span class="block text-base font-extrabold text-[#0F172A] tracking-tight leading-none">GEORX</span>
                            <span class="block text-[10px] font-bold text-[#2F7E6A] uppercase tracking-widest leading-none mt-1">SUPER ADMIN PANEL</span>
                        </div>
                    </a>

                    <button type="button"
                            onclick="toggleDesktopSidebar()"
                            class="hidden lg:inline-flex items-center justify-center w-8 h-8 rounded-lg text-[#64748B] hover:text-[#0F172A] hover:bg-slate-100 transition-colors shrink-0"
                            title="Collapse sidebar"
                            aria-label="Collapse sidebar">
                        <i class="fas fa-angles-left text-xs"></i>
                    </button>

                    <button type="button"
                            onclick="closeMobileSidebar()"
                            class="lg:hidden inline-flex items-center justify-center w-8 h-8 rounded-lg text-[#64748B] hover:text-[#0F172A] hover:bg-slate-100 transition-colors shrink-0"
                            aria-label="Close navigation menu">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Sidebar Navigation -->
                <nav class="px-3.5 py-5 space-y-6 flex-1" aria-label="Super Admin Sidebar">
                    <!-- MAIN -->
                    <div>
                        <p class="px-3 mb-2 text-[10px] font-bold uppercase tracking-widest text-[#64748B]">MAIN</p>
                        <div class="space-y-1">
                            <a href="{{ route('admin.dashboard') }}"
                               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 shadow-2xs' : 'text-[#64748B] hover:bg-slate-50 hover:text-[#0F172A]' }}">
                                <i class="fas fa-tachometer-alt w-4 text-center text-xs {{ request()->routeIs('admin.dashboard') ? 'text-[#2F7E6A]' : 'text-slate-400' }}"></i>
                                <span>Dashboard</span>
                            </a>

                            <a href="{{ route('admin.pharmacies') }}"
                               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.pharmacies*') ? 'bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 shadow-2xs' : 'text-[#64748B] hover:bg-slate-50 hover:text-[#0F172A]' }}">
                                <i class="fas fa-store w-4 text-center text-xs {{ request()->routeIs('admin.pharmacies*') ? 'text-[#2F7E6A]' : 'text-slate-400' }}"></i>
                                <span>Pharmacies</span>
                            </a>

                            <a href="{{ route('admin.users') }}"
                               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.users*') ? 'bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 shadow-2xs' : 'text-[#64748B] hover:bg-slate-50 hover:text-[#0F172A]' }}">
                                <i class="fas fa-users w-4 text-center text-xs {{ request()->routeIs('admin.users*') ? 'text-[#2F7E6A]' : 'text-slate-400' }}"></i>
                                <span>Users</span>
                            </a>

                            <a href="{{ route('admin.reports') }}"
                               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.reports') ? 'bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 shadow-2xs' : 'text-[#64748B] hover:bg-slate-50 hover:text-[#0F172A]' }}">
                                <i class="fas fa-chart-bar w-4 text-center text-xs {{ request()->routeIs('admin.reports') ? 'text-[#2F7E6A]' : 'text-slate-400' }}"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                    </div>

                    <!-- SYSTEM -->
                    <div class="pt-4 border-t border-[#E2E8F0]">
                        <p class="px-3 mb-2 text-[10px] font-bold uppercase tracking-widest text-[#64748B]">SYSTEM</p>
                        <div class="space-y-1">
                            <a href="{{ route('admin.settings') }}"
                               class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.settings') ? 'bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 shadow-2xs' : 'text-[#64748B] hover:bg-slate-50 hover:text-[#0F172A]' }}">
                                <i class="fas fa-sliders-h w-4 text-center text-xs {{ request()->routeIs('admin.settings') ? 'text-[#2F7E6A]' : 'text-slate-400' }}"></i>
                                <span>Settings</span>
                            </a>
                        </div>
                    </div>
                </nav>
            </div>

            <!-- Bottom Super Admin Profile Card + Logout -->
            <div class="p-3.5 border-t border-[#E2E8F0] bg-[#F8FAFC]/80 shrink-0">
                <div class="bg-white border border-[#E2E8F0] rounded-2xl p-3.5 shadow-2xs">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 flex items-center justify-center font-bold text-xs shrink-0">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-[#0F172A] truncate">{{ Auth::user()->name ?? 'Super Admin' }}</p>
                            <p class="text-[11px] font-medium text-[#64748B] truncate">Administrator</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40 shrink-0" title="Account Active">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#2F7E6A]"></span>
                            Active
                        </span>
                    </div>

                    <form id="logoutForm" action="{{ route('logout') }}" method="POST" class="mt-3">
                        @csrf
                        <button type="button"
                                id="logoutBtn"
                                onclick="openLogoutModal()"
                                class="w-full bg-slate-50 hover:bg-rose-50 text-[#0F172A] hover:text-rose-700 border border-[#E2E8F0] hover:border-rose-200 px-3 py-2 rounded-xl text-xs font-semibold transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-sign-out-alt text-[11px]"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- FLUID MAIN CONTENT SHELL -->
        <div class="flex-1 min-w-0 flex flex-col min-h-screen">
            <!-- Mobile / Tablet Compact Top Bar -->
            <header class="lg:hidden bg-white border-b border-[#E2E8F0] px-4 h-14 flex items-center justify-between sticky top-0 z-30">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button"
                            onclick="openMobileSidebar()"
                            class="w-9 h-9 rounded-xl border border-[#E2E8F0] bg-slate-50 hover:bg-slate-100 text-[#0F172A] flex items-center justify-center shrink-0"
                            aria-label="Open navigation menu">
                        <i class="fas fa-bars text-sm"></i>
                    </button>
                    <div class="min-w-0">
                        <span class="text-sm font-extrabold text-[#0F172A] tracking-tight">GEORX</span>
                        <span class="text-[10px] font-bold text-[#2F7E6A] uppercase tracking-wider ml-1.5">Admin</span>
                    </div>
                </div>

                <button type="button"
                        onclick="openLogoutModal()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-[#0F172A] text-xs font-semibold border border-[#E2E8F0]">
                    <i class="fas fa-sign-out-alt text-[#64748B]"></i>
                    <span>Logout</span>
                </button>
            </header>

            <!-- Floating Reopen Trigger When Desktop Sidebar Is Collapsed -->
            <button type="button"
                    id="desktopSidebarExpandBtn"
                    onclick="toggleDesktopSidebar()"
                    class="hidden fixed top-4 left-4 z-30 w-9 h-9 rounded-xl bg-white border border-[#E2E8F0] shadow-sm text-[#2F7E6A] hover:bg-[#E9F7F2] items-center justify-center transition-colors"
                    title="Expand sidebar"
                    aria-label="Expand sidebar">
                <i class="fas fa-bars text-xs"></i>
            </button>

            <!-- Balanced Main Container -->
            <main class="flex-1 min-w-0 w-full">
                <div class="max-w-[1440px] w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    @if(session('success'))
                        <div class="bg-[#E9F7F2] border border-[#63C6A7]/50 text-[#2F7E6A] px-4 py-3 rounded-2xl mb-5 flex items-center shadow-2xs text-sm font-semibold">
                            <i class="fas fa-check-circle mr-2.5 shrink-0"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-2xl mb-5 flex items-center shadow-2xs text-sm font-semibold">
                            <i class="fas fa-exclamation-circle mr-2.5 text-rose-500 shrink-0"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs opacity-0 pointer-events-none transition-opacity duration-200"
         role="dialog"
         aria-modal="true"
         aria-labelledby="logoutModalTitle"
         aria-describedby="logoutModalDesc">

        <div id="logoutModalCard"
             class="w-full max-w-sm bg-white rounded-2xl p-6 sm:p-7 shadow-2xl border border-[#E2E8F0] text-center transform scale-95 transition-transform duration-200">

            <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-100 text-rose-500 flex items-center justify-center mx-auto mb-4 text-xl">
                <i class="fas fa-sign-out-alt"></i>
            </div>

            <h3 id="logoutModalTitle" class="text-xl font-bold text-[#0F172A] tracking-tight">
                Log Out?
            </h3>

            <p id="logoutModalDesc" class="text-[#64748B] text-sm font-medium mt-2 leading-relaxed">
                Are you sure you want to log out of your GEORX Super Admin session?
            </p>

            <div class="mt-6 flex flex-col-reverse sm:flex-row items-center justify-center gap-2.5">
                <button type="button"
                        id="cancelLogoutBtn"
                        onclick="closeLogoutModal()"
                        class="w-full sm:w-auto flex-1 bg-slate-100 hover:bg-slate-200 text-[#0F172A] font-semibold py-2.5 px-4 rounded-xl border border-[#E2E8F0] transition-all text-sm">
                    No, Cancel
                </button>

                <button type="button"
                        id="confirmLogoutBtn"
                        onclick="submitLogout()"
                        class="w-full sm:w-auto flex-1 bg-rose-600 hover:bg-rose-700 text-white font-semibold py-2.5 px-4 rounded-xl shadow-xs transition-all text-sm flex items-center justify-center gap-2">
                    <span id="confirmLogoutText">Yes, Log Out</span>
                    <i id="confirmLogoutSpinner" class="fas fa-spinner fa-spin hidden text-xs"></i>
                </button>
            </div>
        </div>
    </div>

    <script>
        let triggerLogoutBtn = null;
        let desktopSidebarCollapsed = false;

        function openMobileSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (sidebar) {
                sidebar.classList.remove('-translate-x-full');
            }
            if (backdrop) {
                backdrop.classList.remove('hidden');
            }
        }

        function closeMobileSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (sidebar) {
                sidebar.classList.add('-translate-x-full');
            }
            if (backdrop) {
                backdrop.classList.add('hidden');
            }
        }

        function toggleDesktopSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const expandBtn = document.getElementById('desktopSidebarExpandBtn');
            if (!sidebar) return;

            desktopSidebarCollapsed = !desktopSidebarCollapsed;

            if (desktopSidebarCollapsed) {
                sidebar.classList.remove('w-[260px]');
                sidebar.classList.add('lg:w-0', 'lg:border-r-0');
                if (expandBtn) {
                    expandBtn.classList.remove('hidden');
                    expandBtn.classList.add('lg:inline-flex');
                }
            } else {
                sidebar.classList.add('w-[260px]');
                sidebar.classList.remove('lg:w-0', 'lg:border-r-0');
                if (expandBtn) {
                    expandBtn.classList.add('hidden');
                    expandBtn.classList.remove('lg:inline-flex');
                }
            }
        }

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
                    const modal = document.getElementById('logoutModal');
                    if (modal && !modal.classList.contains('pointer-events-none')) {
                        closeLogoutModal();
                    }
                    closeMobileSidebar();
                }
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
