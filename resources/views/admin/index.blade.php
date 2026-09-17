<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pharmacies - Admin</title>
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
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-blue-600 text-white px-6 py-3 shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <i class="fas fa-hospital-user text-xl"></i>
                </div>
                <h1 class="text-lg font-bold tracking-tight">Pharmacy Management</h1>
            </div>
            <a href="{{ url('/') }}" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2">
                <i class="fas fa-map"></i> View Map
            </a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900">Pharmacies</h2>
                <p class="text-gray-500 mt-1 font-medium">Directory of active medicine providers in Bacolod City</p>
            </div>
            <a href="{{ route('admin.pharmacies.create') }}" 
               class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold flex items-center justify-center gap-2 transition shadow-lg shadow-blue-200">
                <i class="fas fa-plus-circle"></i> Add New Pharmacy
            </a>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Pharmacy Info</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Contact Details</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-center">Medicines</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Status</th>
                            <th class="px-6 py-4 text-xs font-bold text-gray-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($pharmacies as $pharmacy)
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-5">
                                <div class="font-bold text-gray-900 text-base">{{ $pharmacy->name }}</div>
                                <div class="text-sm text-gray-500 mt-1 max-w-xs leading-snug">
                                    <i class="fas fa-map-marker-alt text-gray-300 mr-1"></i> {{ $pharmacy->address }}
                                </div>
                                <div class="mt-2 inline-flex items-center gap-2 px-2 py-1 bg-gray-100 rounded text-[10px] font-mono text-gray-500 uppercase">
                                    {{ number_format($pharmacy->latitude, 4) }}, {{ number_format($pharmacy->longitude, 4) }}
                                </div>
                            </td>
                            <td class="px-6 py-5">
                                <div class="space-y-1.5">
                                    @if($pharmacy->phone)
                                    <div class="text-sm text-gray-700 font-medium flex items-center gap-2">
                                        <i class="fas fa-phone-alt text-blue-500 w-4"></i> {{ $pharmacy->phone }}
                                    </div>
                                    @endif
                                    @if($pharmacy->email)
                                    <div class="text-sm text-gray-500 flex items-center gap-2">
                                        <i class="fas fa-envelope text-gray-400 w-4"></i> {{ $pharmacy->email }}
                                    </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-5 text-center">
                                <a href="{{ route('admin.pharmacies.inventory', $pharmacy) }}" 
                                   class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-full text-xs font-bold hover:bg-blue-100 transition border border-blue-100">
                                    <i class="fas fa-pills mr-1.5"></i> {{ $pharmacy->medicines->count() }} Items
                                </a>
                            </td>
                            <td class="px-6 py-5 text-sm">
                                @if($pharmacy->is_active)
                                    <span class="flex items-center gap-1.5 text-emerald-600 font-bold">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Active
                                    </span>
                                @else
                                    <span class="flex items-center gap-1.5 text-gray-400 font-bold">
                                        <span class="w-2 h-2 rounded-full bg-gray-300"></span> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('admin.pharmacies.edit', $pharmacy) }}" 
                                       class="p-2 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit Info">
                                        <i class="fas fa-pen-to-square"></i>
                                    </a>
                                    <form action="{{ route('admin.pharmacies.destroy', $pharmacy) }}" method="POST" 
                                          onsubmit="return confirm('Delete this pharmacy and all its inventory records?');" class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete">
                                            <i class="fas fa-trash-can"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20">
                                <div class="flex flex-col items-center justify-center text-center">
                                    <div class="bg-gray-50 p-6 rounded-full mb-4">
                                        <i class="fas fa-store-slash text-4xl text-gray-200"></i>
                                    </div>
                                    <h3 class="text-lg font-bold text-gray-900">No pharmacies found</h3>
                                    <p class="text-gray-500 max-w-xs mt-1">Your pharmacy directory is currently empty. Start by adding your first location.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>     
