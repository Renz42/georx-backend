<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Dashboard - GEORX: A Medicine Hub Portal</title>
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
    </script><style>.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        /* Smooth tab transitions */
        .tab-content { transition: opacity 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 overflow-x-hidden text-sm sm:text-base pb-20 lg:pb-0">

    <x-navbar />

    <div class="flex min-h-screen">
        <div class="flex-1 flex flex-col min-w-0 transition-all duration-300">
            
            <header class="lg:hidden h-16 bg-white/90 backdrop-blur-md border-b border-slate-200 hidden items-center justify-center px-6 sticky top-0 z-30 shadow-sm">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-black shadow-md shadow-blue-200">
                        <i class="fas fa-user-circle text-sm"></i>
                    </div>
                    <span class="font-black text-lg text-slate-800 tracking-tight">My Profile</span>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-8 md:p-10 max-w-5xl mx-auto w-full space-y-6 sm:space-y-8 relative">
                
                @if(session('success'))
                    <div class="bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 px-6 py-4 rounded-2xl flex items-center shadow-sm">
                        <i class="fas fa-check-circle mr-3 text-emerald-500 text-xl shrink-0"></i>
                        <span class="font-bold">{{ session('success') }}</span>
                    </div>
                @endif

                <div class="bg-white rounded-[2rem] p-6 sm:p-10 border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center sm:items-start justify-between gap-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-blue-50 rounded-full blur-3xl -mr-20 -mt-20 opacity-60 pointer-events-none"></div>
                    
                    <div class="flex flex-col sm:flex-row items-center sm:items-start gap-5 relative z-10 text-center sm:text-left w-full sm:w-auto">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-3xl shadow-inner border border-blue-100 shrink-0">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="pt-1">
                            <p class="text-[10px] sm:text-xs font-black text-blue-500 uppercase tracking-widest mb-1">Welcome back,</p>
                            <h1 class="text-xl sm:text-3xl font-black text-slate-900 tracking-tight leading-none mb-2">{{ $user->name ?? 'User' }}</h1>
                            <p class="text-xs sm:text-sm text-slate-500 font-medium flex items-center justify-center sm:justify-start gap-2">
                                <i class="fas fa-envelope text-slate-400"></i> {{ $user->email ?? 'email@example.com' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-10">
                    <h2 class="text-xl sm:text-2xl font-black text-slate-800 mb-6"><i class="fas fa-heart text-rose-500 mr-2"></i> Favorite Pharmacies</h2>
                    
                    @if($favorites->isEmpty())
                        <div class="py-12 text-center bg-slate-50 rounded-2xl border border-slate-100">
                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200 shadow-sm">
                                <i class="far fa-heart text-2xl text-slate-300"></i>
                            </div>
                            <h3 class="text-lg font-black text-slate-700">No favorites yet</h3>
                            <p class="text-slate-500 mt-2 text-sm max-w-sm mx-auto">
                                Save your preferred pharmacies to quickly access them later.
                            </p>
                            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mt-6 bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl font-bold transition-all shadow-md shadow-blue-200">
                                <i class="fas fa-search"></i> Find Pharmacies
                            </a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($favorites as $pharmacy)
                                <div class="bg-slate-50 border border-slate-200 p-5 rounded-2xl hover:border-blue-300 hover:shadow-md transition-all">
                                    <h3 class="font-black text-lg text-slate-800">{{ $pharmacy->name }}</h3>
                                    <p class="text-sm text-slate-500 mt-1 mb-3 line-clamp-2"><i class="fas fa-map-marker-alt text-slate-400 w-4"></i> {{ $pharmacy->address }}</p>
                                    
                                    <div class="flex justify-between items-center pt-3 border-t border-slate-200">
                                        <a href="{{ route('public.pharmacy.catalog', $pharmacy->id) }}" class="text-sm font-bold text-blue-600 hover:text-blue-800">
                                            View Catalog <i class="fas fa-arrow-right ml-1 text-xs"></i>
                                        </a>
                                        <form action="{{ route('favorites.remove') }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="pharmacy_id" value="{{ $pharmacy->id }}">
                                            <button type="submit" class="text-rose-500 hover:bg-rose-50 p-2 rounded-full transition-colors" title="Remove from favorites">
                                                <i class="fas fa-heart"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </main>
        </div>
    </div>

    <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white/90 backdrop-blur-lg border-t border-slate-200 flex justify-around items-center h-16 z-50 px-2 pb-safe shadow-[0_-4px_20px_rgba(0,0,0,0.05)]">
        
        <a href="{{ url('/') }}" class="flex flex-col items-center justify-center w-full h-full text-slate-400 hover:text-blue-500 transition-colors">
            <i class="fas fa-map-marked-alt text-xl mb-1"></i>
            <span class="text-[9px] font-bold uppercase tracking-widest">Map</span>
        </a>

        <a href="{{ route('cart.index') }}" class="flex flex-col items-center justify-center w-full h-full text-slate-400 hover:text-blue-500 transition-colors relative">
            <i class="fas fa-shopping-cart text-xl mb-1"></i>
            <span class="text-[9px] font-bold uppercase tracking-widest">Cart</span>
            @if(($cartCount ?? 0) > 0)
                <span class="absolute top-1 right-1/4 w-4 h-4 bg-rose-500 text-white text-[8px] font-black rounded-full flex items-center justify-center">{{ $cartCount }}</span>
            @endif
        </a>

        <a href="{{ route('user.dashboard') }}" class="flex flex-col items-center justify-center w-full h-full text-blue-600 transition-colors relative">
            <div class="absolute -top-3 bg-blue-600 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg shadow-blue-200 border-4 border-slate-50">
                <i class="fas fa-user-circle text-lg"></i>
            </div>
            <span class="text-[9px] font-black uppercase tracking-widest mt-6">Profile</span>
        </a>

        <a href="{{ route('user.orders') }}" class="flex flex-col items-center justify-center w-full h-full text-slate-400 hover:text-blue-500 transition-colors">
            <i class="fas fa-receipt text-xl mb-1"></i>
            <span class="text-[9px] font-bold uppercase tracking-widest">Orders</span>
        </a>

        <a href="{{ route('messages.index') }}" class="flex flex-col items-center justify-center w-full h-full text-slate-400 hover:text-blue-500 transition-colors">
            <i class="fas fa-envelope text-xl mb-1"></i>
            <span class="text-[9px] font-bold uppercase tracking-widest">Chat</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="w-full h-full">
            @csrf
            <button type="submit" class="flex flex-col items-center justify-center w-full h-full text-slate-400 hover:text-rose-500 transition-colors">
                <i class="fas fa-sign-out-alt text-xl mb-1"></i>
                <span class="text-[9px] font-bold uppercase tracking-widest">Out</span>
            </button>
        </form>

    </nav>
</body>
</html>
