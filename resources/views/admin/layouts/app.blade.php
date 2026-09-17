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
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 border border-slate-200">
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

    @stack('scripts')
</body>
</html>
