<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Application - Super Admin</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body { font-family: 'Outfit', sans-serif; }
    </style>
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
    </script></head>
<body class="bg-slate-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 shadow-lg">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <i class="fas fa-shield-alt text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Super Admin</h1>
                    <p class="text-blue-200 text-xs font-medium">GEORX: A Medicine Hub Portal</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2 bg-white/10 px-3 py-2 rounded-lg">
                    <i class="fas fa-user-shield text-blue-200"></i>
                    <span class="text-sm font-medium">{{ Auth::user()->name }}</span>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Sidebar + Content -->
    <div class="flex">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-sm border-r border-slate-200 min-h-screen">
            <nav class="p-4">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 px-4">Main Menu</p>
                <a href="{{ route('admin.dashboard') }}" 
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 text-slate-600 hover:text-slate-800 mb-2 transition-all">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
                <a href="{{ route('admin.pharmacies') }}" 
                   class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-50 text-blue-700 font-semibold mb-2 border border-blue-100">
                    <i class="fas fa-store"></i>Pharmacies
                </a>

            
                <div class="border-t border-slate-200 my-4"></div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 px-4">Quick Links</p>
                <a href="{{ url('/') }}" 
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 text-slate-500 hover:text-slate-700 transition-all">
                    <i class="fas fa-map"></i>View Public Map
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8">
            <div class="max-w-4xl mx-auto">
                
                <a href="{{ route('admin.pharmacies') }}" class="text-blue-600 hover:underline mb-6 inline-block"><i class="fas fa-arrow-left mr-2"></i> Back to Pharmacies</a>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="bg-amber-50 border-b border-amber-100 p-6 flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-amber-800">Pending Application Review</h2>
                            <p class="text-amber-600 text-sm mt-1">Please verify the credentials below before approving.</p>
                        </div>
                        <span class="bg-amber-200 text-amber-800 font-black uppercase tracking-widest text-xs px-3 py-1 rounded-full">Action Required</span>
                    </div>

                    <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Pharmacy Details -->
                        <div>
                            <h3 class="font-bold text-slate-800 border-b pb-2 mb-4">Pharmacy Information</h3>
                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">Pharmacy Name</p>
                            <p class="font-bold text-lg mb-4">{{ $pharmacy->name }}</p>

                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">Address</p>
                            <p class="mb-4">{{ $pharmacy->address }}</p>

                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">Contact Phone</p>
                            <p class="mb-4">{{ $pharmacy->phone }}</p>

                            <!-- ✨ NEW OPERATING HOURS DISPLAY ✨ -->
                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">Operating Hours</p>
                            <p class="mb-4">
                                @if(is_array($pharmacy->operating_hours) && isset($pharmacy->operating_hours['open']) && isset($pharmacy->operating_hours['close']))
                                    <span class="font-bold text-slate-700 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                                        {{ \Carbon\Carbon::parse($pharmacy->operating_hours['open'])->format('h:i A') }} 
                                        <span class="text-slate-400 mx-1">—</span> 
                                        {{ \Carbon\Carbon::parse($pharmacy->operating_hours['close'])->format('h:i A') }}
                                    </span>
                                @else
                                    <span class="text-rose-500 italic font-medium bg-rose-50 px-3 py-1.5 rounded-lg border border-rose-100">
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Not configured properly
                                    </span>
                                @endif
                            </p>
                        </div>

                        <!-- Legal/Owner Details -->
                        <div>
                            <h3 class="font-bold text-slate-800 border-b pb-2 mb-4">Legal & Ownership</h3>
                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">Owner Name</p>
                            <p class="font-bold mb-4">{{ $pharmacy->owner_name }}</p>

                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">LTO / License Number</p>
                            <p class="font-mono bg-slate-100 p-2 rounded border font-bold text-blue-600 mb-4">{{ $pharmacy->license_number ?? $pharmacy->lto_number ?? 'Not Provided' }}</p>
                            
                            <p class="text-xs text-slate-400 font-bold uppercase mb-1">Registered Email</p>
                            <p class="mb-4">{{ $pharmacy->email }}</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-slate-50 p-6 border-t border-slate-100 flex gap-4 justify-end">
                        <form action="{{ route('admin.pharmacies.reject', $pharmacy->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to REJECT this application?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="bg-white border-2 border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 font-bold py-3 px-6 rounded-xl transition">
                                <i class="fas fa-times mr-2"></i> Reject Application
                            </button>
                        </form>

                        <form action="{{ route('admin.pharmacies.approve', $pharmacy->id) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white shadow-lg shadow-emerald-200 font-bold py-3 px-8 rounded-xl transition">
                                <i class="fas fa-check mr-2"></i> Approve Pharmacy
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
