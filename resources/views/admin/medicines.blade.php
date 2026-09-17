<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicine Master List - Super Admin</title>
    
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
                   class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-slate-50 text-slate-600 hover:text-slate-800 mb-2 transition-all">
                    <i class="fas fa-store"></i>Pharmacies
                </a>
                <a href="{{ route('admin.medicines') }}" 
                   class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-50 text-blue-700 font-semibold mb-2 border border-blue-100">
                    <i class="fas fa-pills"></i>Medicine List
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
            @if(session('success'))
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm">
                    <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
                    <span class="font-medium">{{ session('success') }}</span>
                </div>
            @endif

            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Medicine Master List</h2>
                    <p class="text-slate-500 mt-1">{{ $medicines->count() }} medicines in database</p>
                </div>
                <button onclick="document.getElementById('addMedicineModal').classList.remove('hidden')"
                   class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl font-semibold flex items-center gap-2 transition-all shadow-sm hover:shadow">
                    <i class="fas fa-plus"></i>Add Medicine
                </button>
            </div>

            <!-- Search -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 mb-6">
                <form method="GET" class="flex gap-4">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" value="{{ request('search') }}" 
                               class="w-full pl-11 pr-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                               placeholder="Search medicines...">
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl font-semibold transition-all">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Generic Name</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Pharmacies</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($medicines as $medicine)
                                <tr class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-slate-800">{{ $medicine->name }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ $medicine->generic_name ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <span class="bg-violet-50 text-violet-700 text-sm px-3 py-1 rounded-lg font-medium">
                                            {{ $medicine->category ?? 'General' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600 max-w-xs truncate">
                                        {{ $medicine->description ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="bg-blue-50 text-blue-700 text-sm px-3 py-1 rounded-lg font-medium">
                                            {{ $medicine->pharmacies->count() }} pharmacies
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex gap-2">
                                            <button onclick="editMedicine({{ $medicine->id }}, '{{ addslashes($medicine->name) }}', '{{ addslashes($medicine->generic_name ?? '') }}', '{{ addslashes($medicine->category ?? '') }}', '{{ addslashes($medicine->description ?? '') }}')"
                                               class="text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded-lg transition-all" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="{{ route('admin.medicines.destroy', $medicine) }}" method="POST" 
                                                  onsubmit="return confirm('Delete this medicine?');" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 hover:bg-red-50 p-2 rounded-lg transition-all" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                        <i class="fas fa-pills text-4xl mb-3 text-slate-300"></i>
                                        <p class="font-semibold text-slate-600">No medicines yet</p>
                                        <p class="text-sm">Click "Add Medicine" to get started</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Medicine Modal -->
    <div id="addMedicineModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-lg mx-4 border border-slate-200">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-xl font-bold text-slate-800">Add New Medicine</h3>
                    <p class="text-slate-500 text-sm mt-1">Add to the master database</p>
                </div>
                <button onclick="document.getElementById('addMedicineModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-lg transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" action="{{ route('admin.medicines.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-slate-700 font-semibold mb-2">Medicine Name *</label>
                    <input type="text" name="name" required
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                        placeholder="e.g., Biogesic">
                </div>
                <div class="mb-4">
                    <label class="block text-slate-700 font-semibold mb-2">Generic Name</label>
                    <input type="text" name="generic_name"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                        placeholder="e.g., Paracetamol">
                </div>
                <div class="mb-4">
                    <label class="block text-slate-700 font-semibold mb-2">Category</label>
                    <input type="text" name="category"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                        placeholder="e.g., Pain Relief">
                </div>
                <div class="mb-6">
                    <label class="block text-slate-700 font-semibold mb-2">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                        placeholder="Brief description..."></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('addMedicineModal').classList.add('hidden')"
                        class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 px-4 rounded-xl transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-4 rounded-xl transition-all shadow-sm">
                        Add Medicine
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Medicine Modal -->
    <div id="editMedicineModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-lg mx-4 border border-slate-200">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h3 class="text-xl font-bold text-slate-800">Edit Medicine</h3>
                    <p class="text-slate-500 text-sm mt-1">Update medicine details</p>
                </div>
                <button onclick="document.getElementById('editMedicineModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-lg transition-all">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" id="editMedicineForm">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label class="block text-slate-700 font-semibold mb-2">Medicine Name *</label>
                    <input type="text" name="name" id="edit_name" required
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
                <div class="mb-4">
                    <label class="block text-slate-700 font-semibold mb-2">Generic Name</label>
                    <input type="text" name="generic_name" id="edit_generic_name"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
                <div class="mb-4">
                    <label class="block text-slate-700 font-semibold mb-2">Category</label>
                    <input type="text" name="category" id="edit_category"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                </div>
                <div class="mb-6">
                    <label class="block text-slate-700 font-semibold mb-2">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"></textarea>
                </div>
                <div class="flex gap-3">
                    <button type="button" onclick="document.getElementById('editMedicineModal').classList.add('hidden')"
                        class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold py-3 px-4 rounded-xl transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold py-3 px-4 rounded-xl transition-all shadow-sm">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editMedicine(id, name, genericName, category, description) {
            document.getElementById('editMedicineForm').action = '/admin/medicines/' + id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_generic_name').value = genericName;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_description').value = description;
            document.getElementById('editMedicineModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
