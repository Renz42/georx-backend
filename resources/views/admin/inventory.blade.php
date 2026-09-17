<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory - {{ $pharmacy->name }}</title>
    
    
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background-color: #f8fafc;
            color: #334155;
        }

        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type=number] {
            -moz-appearance: textfield;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
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
<body class="min-h-screen flex flex-col">
    
    <nav class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-6 py-4 shadow-lg sticky top-0 z-30">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 p-2 rounded-lg">
                    <i class="fas fa-boxes-stacked text-lg"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Inventory Manager</h1>
                    <p class="text-blue-200 text-xs font-medium">Super Admin Panel</p>
                </div>
            </div>
            <a href="{{ route('admin.pharmacies') }}" class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2">
                <i class="fas fa-arrow-left"></i> Back to Pharmacies
            </a>
        </div>
    </nav>

    <main class="flex-grow max-w-6xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8 relative z-10">
        
        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm">
                <i class="fas fa-check-circle text-emerald-500 mr-3 text-lg"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-4 rounded-xl mb-6 shadow-sm">
                <div class="flex items-center gap-2 mb-2 font-bold">
                    <i class="fas fa-exclamation-triangle text-red-500"></i> Please fix the following errors:
                </div>
                <ul class="list-disc list-inside text-sm space-y-1 ml-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-8 mt-2 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $pharmacy->name }}</h2>
                <p class="text-slate-500 font-medium mt-1 flex items-center gap-2">
                    <i class="fas fa-map-marker-alt text-blue-500"></i> {{ $pharmacy->address }}
                </p>
            </div>
            <button type="button" onclick="openMedicineModal()" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-sm hover:shadow shrink-0">
                <i class="fas fa-plus-circle text-lg"></i> Register New Medicine
            </button>
        </div>

        <form action="{{ route('admin.pharmacies.updateInventory', $pharmacy) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden flex flex-col mb-8">
                
                <div class="px-6 py-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-clipboard-list text-blue-600"></i> Active Inventory
                        </h3>
                        <p class="text-slate-500 text-sm mt-1 font-medium">Update stock quantities and pricing for this specific branch.</p>
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-2.5 rounded-xl font-bold flex items-center justify-center gap-2 transition-all shadow-sm hover:shadow shrink-0">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Medicine Name</th>
                                <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Category</th>
                                <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider w-40">Price (₱)</th>
                                <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider w-40">Stock Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($medicines as $medicine)
                                @php
                                    $existingInventory = $pharmacy->medicines->firstWhere('id', $medicine->id);
                                    $price = $existingInventory ? $existingInventory->pivot->price : '';
                                    $quantity = $existingInventory ? $existingInventory->pivot->stock_quantity : 0;
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-800">{{ $medicine->name }}</div>
                                        <div class="text-xs text-slate-500 font-medium mt-0.5">{{ $medicine->generic_name }}</div>
                                        <input type="hidden" name="medicines[{{ $loop->index }}][medicine_id]" value="{{ $medicine->id }}">
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-violet-50 text-violet-700">
                                            {{ $medicine->category }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-medium text-sm">₱</span>
                                            <input type="number" step="0.01" min="0"
                                                   name="medicines[{{ $loop->index }}][price]"
                                                   value="{{ $price }}"
                                                   placeholder="0.00"
                                                   class="w-full pl-7 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <input type="number" min="0"
                                               name="medicines[{{ $loop->index }}][stock_quantity]"
                                               value="{{ $quantity }}"
                                               placeholder="0"
                                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    
                    @if($medicines->isEmpty())
                        <div class="p-8 text-center flex flex-col items-center">
                            <i class="fas fa-folder-open text-4xl text-slate-300 mb-3"></i>
                            <p class="text-slate-600 font-bold">No medicines in the database.</p>
                            <p class="text-slate-500 text-sm mt-1">Register a new medicine type above to start tracking inventory.</p>
                        </div>
                    @endif
                </div>
            </div>
        </form>
    </main>

    <div id="addMedicineModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300 flex items-center justify-center px-4">
        
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden transform scale-95 transition-transform duration-300 flex flex-col max-h-[90vh] border border-slate-200" id="modalContent">
            
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4 flex justify-between items-center shrink-0">
                <div>
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-pills"></i> Register New Medicine
                    </h3>
                    <p class="text-blue-200 text-xs font-medium mt-0.5">Add to master database</p>
                </div>
                <button type="button" onclick="closeMedicineModal()" class="text-blue-100 hover:text-white bg-white/10 hover:bg-white/20 rounded-lg w-8 h-8 flex items-center justify-center transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="{{ route('admin.medicines.store') }}" method="POST" class="p-6 sm:p-8 overflow-y-auto flex-grow">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Medicine Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" required
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-800 placeholder-slate-400"
                               placeholder="e.g., Paracetamol 500mg">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Generic Name</label>
                        <input type="text" name="generic_name"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-800 placeholder-slate-400"
                               placeholder="e.g., Paracetamol">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Category <span class="text-red-500">*</span></label>
                        <select name="category" required
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-800 appearance-none cursor-pointer">
                            <option value="">Select category...</option>
                            <option value="Pain Relief">Pain Relief</option>
                            <option value="Antibiotics">Antibiotics</option>
                            <option value="Cardiovascular">Cardiovascular</option>
                            <option value="Diabetes">Diabetes</option>
                            <option value="Respiratory">Respiratory</option>
                            <option value="Gastrointestinal">Gastrointestinal</option>
                            <option value="Vitamins">Vitamins</option>
                            <option value="Allergy">Allergy</option>
                            <option value="Skin Care">Skin Care</option>
                            <option value="First Aid">First Aid</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Dosage Form</label>
                        <select name="dosage_form"
                                class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-800 appearance-none cursor-pointer">
                            <option value="">Select form...</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Capsule">Capsule</option>
                            <option value="Syrup">Syrup</option>
                            <option value="Injection">Injection</option>
                            <option value="Cream">Cream</option>
                            <option value="Ointment">Ointment</option>
                            <option value="Drops">Drops</option>
                            <option value="Inhaler">Inhaler</option>
                            <option value="Patch">Patch</option>
                            <option value="Powder">Powder</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Strength</label>
                        <input type="text" name="strength"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-800 placeholder-slate-400"
                               placeholder="e.g., 500mg, 100ml">
                    </div>
                    
                    <div class="flex items-center pt-8">
                        <label class="flex items-center cursor-pointer group">
                            <input type="checkbox" name="requires_prescription" value="1"
                                   class="w-5 h-5 text-blue-600 border-slate-300 rounded focus:ring-blue-500 cursor-pointer">
                            <span class="ml-3 text-sm font-bold text-slate-700 group-hover:text-blue-600 transition-colors">Requires Prescription (Rx)</span>
                        </label>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Description</label>
                        <textarea name="description" rows="2"
                                  class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-medium text-slate-800 placeholder-slate-400"
                                  placeholder="Brief description or notes about the medicine..."></textarea>
                    </div>
                </div>
                
                <div class="mt-8 pt-5 border-t border-slate-100 flex justify-end gap-3">
                    <button type="button" onclick="closeMedicineModal()" class="px-6 py-2.5 rounded-xl font-bold text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition">
                        Cancel
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-2.5 rounded-xl font-bold flex items-center gap-2 transition-all shadow-sm hover:shadow">
                        <i class="fas fa-plus"></i> Save to Database
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openMedicineModal() {
            const modal = document.getElementById('addMedicineModal');
            const content = document.getElementById('modalContent');
            
            modal.classList.remove('hidden');
            
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
            
            document.body.style.overflow = 'hidden';
        }

        function closeMedicineModal() {
            const modal = document.getElementById('addMedicineModal');
            const content = document.getElementById('modalContent');
            
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto';
            }, 300);
        }

        document.getElementById('addMedicineModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMedicineModal();
            }
        });
    </script>
</body>
</html>
