@extends('pharmacy.layouts.app')
@section('title', 'Pharmacy Settings')

@section('content')
    <div class="max-w-5xl mx-auto pb-12">
        
        <!-- Page Header -->
        <div class="mb-8 border-b border-slate-200 pb-6 flex justify-between items-end">
            <div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tight">System Settings</h2>
                <p class="text-slate-500 mt-1 font-medium text-sm">Manage your storefront branding, visibility, and operational preferences.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-2xl mb-8 flex items-center shadow-sm font-bold animate-pulse-once">
                <i class="fas fa-check-circle mr-3 text-emerald-500 text-xl"></i>
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('pharmacy.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf
            
            <!-- SECTION 1: Storefront Branding -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-blue-100 text-blue-600 p-2 rounded-xl shadow-inner"><i class="fas fa-paint-brush"></i></div>
                        <h3 class="font-black text-slate-800 text-lg">Storefront Branding</h3>
                    </div>
                </div>
                
                <div class="p-8">
                    <!-- LOGO UPLOAD SECTION -->
                    <div class="flex flex-col sm:flex-row gap-8 items-center border-b border-slate-100 pb-8 mb-8">
                        <!-- Logo Preview Circle -->
                        <div class="relative w-24 h-24 rounded-full border-4 border-slate-50 shadow-md bg-white flex items-center justify-center shrink-0 overflow-hidden group">
                            <img id="logoPreview" src="{{ $pharmacy->logo ? asset('storage/' . $pharmacy->logo) : '' }}" 
                                 class="{{ $pharmacy->logo ? 'block' : 'hidden' }} w-full h-full object-cover">
                                 
                            <i id="logoPlaceholder" class="fas fa-clinic-medical text-3xl text-slate-300 {{ $pharmacy->logo ? 'hidden' : 'block' }}"></i>
                        </div>
                        
                        <div>
                            <h4 class="text-base font-black text-slate-800 mb-1">Pharmacy Logo</h4>
                            <p class="text-sm text-slate-500 mb-3 leading-relaxed max-w-md">Upload a high-quality logo. Square images work best. Max size: 2MB (JPEG, PNG, WEBP).</p>
                            
                            <div class="flex items-center gap-3">
                                <!-- Hidden File Input -->
                                <input type="file" id="logoUpload" name="logo" accept="image/*" class="hidden">
                                <label for="logoUpload" class="bg-white border border-slate-200 text-slate-600 font-bold px-4 py-2 rounded-lg text-xs cursor-pointer hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm inline-flex items-center gap-2">
                                    <i class="fas fa-upload"></i> Choose Image
                                </label>
                                
                                <!-- ✨ NEW: REMOVE LOGO BUTTON ✨ -->
                                <button type="button" id="removeLogoBtn" class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg transition-colors {{ $pharmacy->logo ? 'block' : 'hidden' }}">
                                    <i class="fas fa-trash-alt mr-1"></i> Remove
                                </button>

                                <!-- Hidden input to send deletion command to server -->
                                <input type="hidden" name="remove_logo" id="removeLogoInput" value="0">
                            </div>
                        </div>
                    <!-- COVER PHOTO UPLOAD SECTION -->
                    <div class="flex flex-col sm:flex-row gap-8 items-center border-b border-slate-100 pb-8 mb-8">
                        <div class="relative w-48 h-24 rounded-xl border border-slate-200 shadow-sm bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden group">
                            <img id="coverPreview" src="{{ $pharmacy->cover_photo ? asset('storage/' . $pharmacy->cover_photo) : '' }}" 
                                 class="{{ $pharmacy->cover_photo ? 'block' : 'hidden' }} w-full h-full object-cover">
                                 
                            <i id="coverPlaceholder" class="fas fa-image text-3xl text-slate-300 {{ $pharmacy->cover_photo ? 'hidden' : 'block' }}"></i>
                        </div>
                        
                        <div>
                            <h4 class="text-base font-black text-slate-800 mb-1">Storefront / Cover Photo</h4>
                            <p class="text-sm text-slate-500 mb-3 leading-relaxed max-w-md">Upload a high-quality photo of your pharmacy's storefront. Max size: 2MB (JPEG, PNG, WEBP).</p>
                            
                            <div class="flex items-center gap-3">
                                <input type="file" id="coverUpload" name="cover_photo" accept="image/*" class="hidden">
                                <label for="coverUpload" class="bg-white border border-slate-200 text-slate-600 font-bold px-4 py-2 rounded-lg text-xs cursor-pointer hover:bg-slate-50 hover:text-blue-600 transition-colors shadow-sm inline-flex items-center gap-2">
                                    <i class="fas fa-upload"></i> Choose Image
                                </label>
                                
                                <button type="button" id="removeCoverBtn" class="text-xs font-bold text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-2 rounded-lg transition-colors {{ $pharmacy->cover_photo ? 'block' : 'hidden' }}">
                                    <i class="fas fa-trash-alt mr-1"></i> Remove
                                </button>
                                <input type="hidden" name="remove_cover_photo" id="removeCoverInput" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- COLOR PICKER SECTION -->
                    <div class="flex flex-col md:flex-row gap-10 items-start">
                        <div class="flex-1">
                            <label class="block text-sm font-black text-slate-700 mb-2 uppercase tracking-wide">Primary Brand Color</label>
                            <p class="text-slate-500 text-sm mb-6 leading-relaxed">
                                Choose the exact color that matches your pharmacy's brand identity. This will instantly apply to your public catalog, buttons, and this admin portal's header.
                            </p>
                            
                            <!-- Interactive Color Picker WITH RESET BUTTON -->
                            <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-200 w-fit shadow-sm">
                                <div class="relative w-14 h-14 rounded-xl overflow-hidden shadow-inner border-[3px] border-white cursor-pointer ring-2 ring-slate-200 hover:ring-blue-400 transition-all">
                                    <input type="color" id="colorPicker" name="theme_color" value="{{ $pharmacy->theme_color ?? '#2563eb' }}" 
                                           class="absolute -top-2 -left-2 w-20 h-20 cursor-pointer border-0 p-0">
                                </div>
                                <div class="px-2 flex flex-col justify-center">
                                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-0.5">Hex Code</p>
                                    <div class="flex items-center gap-3">
                                        <div id="hexDisplay" class="text-lg font-black text-slate-700 uppercase tracking-widest font-mono">
                                            {{ $pharmacy->theme_color ?? '#2563EB' }}
                                        </div>
                                        <button type="button" id="resetColorBtn" class="text-[10px] bg-slate-200 hover:bg-slate-300 hover:text-blue-700 text-slate-500 px-2 py-1.5 rounded font-bold uppercase tracking-wider transition-colors shadow-inner" title="Reset to standard Pharmacy Blue">
                                            <i class="fas fa-undo mr-1"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Live Visual Preview Container -->
                        <div class="w-full md:w-80 bg-slate-50 rounded-3xl p-6 border border-slate-200 shadow-inner shrink-0 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-slate-300 to-transparent"></div>
                            
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Live Preview Component
                            </p>
                            
                            <!-- Preview Header Bar -->
                            <div id="previewBg" class="h-16 rounded-xl w-full shadow-md mb-4 transition-colors duration-200 flex items-center px-4" style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                <div class="w-8 h-8 rounded-full bg-white/20 border border-white/30"></div>
                                <div class="ml-3 h-2 w-20 rounded-full bg-white/80"></div>
                            </div>
                            
                            <!-- Preview Outline Button -->
                            <div id="previewBtn" class="h-12 rounded-xl w-full flex items-center justify-center text-sm font-black transition-colors duration-200 shadow-sm" 
                                 style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }}1A; border: 2px solid {{ $pharmacy->theme_color ?? '#2563eb' }}; color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                Example Button
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- SECTION 2: Store Visibility -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                    <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex items-center gap-3">
                        <div class="bg-emerald-100 text-emerald-600 p-2 rounded-xl shadow-inner"><i class="fas fa-store"></i></div>
                        <h3 class="font-black text-slate-800 text-lg">Store Operations</h3>
                    </div>
                    
                    <div class="p-8 flex-1 flex flex-col justify-between gap-6">
                        <div>
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-base font-black text-slate-800">Global Store Visibility</h4>
                                <!-- Professional Tailwind Toggle -->
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $pharmacy->is_active ? 'checked' : '' }}>
                                    <div class="w-14 h-7 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all shadow-inner"
                                         style="{{ $pharmacy->is_active ? 'background-color: '.($pharmacy->theme_color ?? '#2563eb').';' : '' }}"
                                         id="visibilityToggleBg"></div>
                                </label>
                            </div>
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Turn this off to instantly hide your pharmacy from the public map and catalog search. Useful for holidays, emergencies, or off-hours.
                            </p>
                        </div>
                        
                        <div class="pt-6 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-slate-800">Auto-Approve Reservations</h4>
                                <label class="relative inline-flex items-center cursor-pointer opacity-50" title="Coming Soon">
                                    <input type="checkbox" class="sr-only peer" disabled>
                                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400">Automatically accept incoming medicine reservations without manual review. <span class="font-bold text-blue-500">(Feature coming soon)</span></p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Inventory Alerts -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
                    <div class="px-8 py-5 border-b border-slate-100 bg-slate-50 flex items-center gap-3">
                        <div class="bg-amber-100 text-amber-600 p-2 rounded-xl shadow-inner"><i class="fas fa-boxes"></i></div>
                        <h3 class="font-black text-slate-800 text-lg">Inventory Preferences</h3>
                    </div>
                    
                    <div class="p-8 flex-1">
                        <div class="mb-6">
                            <label class="block text-sm font-black text-slate-800 mb-2">Global Low-Stock Threshold</label>
                            <p class="text-sm text-slate-500 mb-4 leading-relaxed">
                                Set the default quantity at which items trigger a "Low Stock" warning on your dashboard.
                            </p>
                            <div class="flex items-center gap-3">
                                <input type="number" value="10" min="1" class="w-24 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl font-black text-lg text-slate-700 focus:ring-2 outline-none text-center"
                                       style="--tw-ring-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                                <span class="text-slate-500 font-bold text-sm">units remaining</span>
                            </div>
                        </div>

                        <div class="p-4 bg-blue-50 rounded-2xl border border-blue-100 flex gap-3">
                            <i class="fas fa-info-circle text-blue-500 mt-0.5"></i>
                            <p class="text-xs text-blue-800 font-medium leading-relaxed">Note: You can override this threshold for individual high-priority medicines directly from the "My Inventory" page.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Floating Save Bar -->
            <div class="sticky bottom-6 mt-8 bg-white/90 backdrop-blur-lg p-5 rounded-[2rem] border border-slate-200 shadow-2xl flex justify-between items-center z-40">
                <div class="flex items-center gap-3 ml-4 hidden sm:flex">
                    <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 shadow-inner">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <p class="text-sm font-black text-slate-800">Unsaved Changes</p>
                        <p class="text-xs font-medium text-slate-500">Don't forget to save your preferences.</p>
                    </div>
                </div>
                
                <button type="submit" class="w-full sm:w-auto text-white font-black px-10 py-4 rounded-2xl shadow-xl transition-all active:scale-95 flex items-center justify-center gap-3 hover:brightness-110" 
                        style="background-color: {{ $pharmacy->theme_color ?? '#2563eb' }};">
                    <i class="fas fa-save text-lg"></i> 
                    <span>Save All Settings</span>
                </button>
            </div>
        </form>

    </div>

    <!-- Live Preview & Reset Javascript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // LOGO PREVIEW & REMOVAL LOGIC
            const logoUpload = document.getElementById('logoUpload');
            const logoPreview = document.getElementById('logoPreview');
            const logoPlaceholder = document.getElementById('logoPlaceholder');
            const removeLogoBtn = document.getElementById('removeLogoBtn');
            const removeLogoInput = document.getElementById('removeLogoInput');

            if (logoUpload) {
                // When a new image is selected
                logoUpload.addEventListener('change', function(event) {
                    if(event.target.files.length > 0){
                        const src = URL.createObjectURL(event.target.files[0]);
                        logoPreview.src = src;
                        logoPreview.classList.remove('hidden');
                        logoPreview.classList.add('block');
                        
                        if(logoPlaceholder) {
                            logoPlaceholder.classList.add('hidden');
                            logoPlaceholder.classList.remove('block');
                        }
                        
                        // We are uploading a new one, so reset the removal flag
                        removeLogoInput.value = '0';
                        if(removeLogoBtn) {
                            removeLogoBtn.classList.remove('hidden');
                            removeLogoBtn.classList.add('block');
                        }
                    }
                });
            }

            if (removeLogoBtn) {
                // When "Remove" is clicked
                removeLogoBtn.addEventListener('click', function() {
                    // Hide image, show placeholder
                    logoPreview.classList.add('hidden');
                    logoPreview.classList.remove('block');
                    logoPreview.src = '';
                    
                    if(logoPlaceholder) {
                        logoPlaceholder.classList.remove('hidden');
                        logoPlaceholder.classList.add('block');
                    }
                    
                    // Clear the file input
                    if(logoUpload) logoUpload.value = '';
                    
                    // Trigger the backend to delete the logo
                    removeLogoInput.value = '1';
                    
                    // Hide this remove button
                    this.classList.add('hidden');
                    this.classList.remove('block');
                });
            }

            // COVER PHOTO PREVIEW & REMOVAL LOGIC
            const coverUpload = document.getElementById('coverUpload');
            const coverPreview = document.getElementById('coverPreview');
            const coverPlaceholder = document.getElementById('coverPlaceholder');
            const removeCoverBtn = document.getElementById('removeCoverBtn');
            const removeCoverInput = document.getElementById('removeCoverInput');

            if (coverUpload) {
                coverUpload.addEventListener('change', function(event) {
                    if(event.target.files.length > 0){
                        const src = URL.createObjectURL(event.target.files[0]);
                        coverPreview.src = src;
                        coverPreview.classList.remove('hidden');
                        coverPreview.classList.add('block');
                        
                        if(coverPlaceholder) {
                            coverPlaceholder.classList.add('hidden');
                            coverPlaceholder.classList.remove('block');
                        }
                        
                        removeCoverInput.value = '0';
                        if(removeCoverBtn) {
                            removeCoverBtn.classList.remove('hidden');
                            removeCoverBtn.classList.add('block');
                        }
                    }
                });
            }

            if (removeCoverBtn) {
                removeCoverBtn.addEventListener('click', function() {
                    coverPreview.classList.add('hidden');
                    coverPreview.classList.remove('block');
                    coverPreview.src = '';
                    
                    if(coverPlaceholder) {
                        coverPlaceholder.classList.remove('hidden');
                        coverPlaceholder.classList.add('block');
                    }
                    
                    if(coverUpload) coverUpload.value = '';
                    removeCoverInput.value = '1';
                    
                    this.classList.add('hidden');
                    this.classList.remove('block');
                });
            }

            // THEME COLOR LOGIC
            const colorInput = document.getElementById('colorPicker');
            const hexDisplay = document.getElementById('hexDisplay');
            const previewBg = document.getElementById('previewBg');
            const previewBtn = document.getElementById('previewBtn');
            const visibilityToggleBg = document.getElementById('visibilityToggleBg');
            const resetColorBtn = document.getElementById('resetColorBtn');

            const DEFAULT_PHARMACY_BLUE = '#2563EB';

            function updateColorUI(hexColor) {
                hexDisplay.textContent = hexColor.toUpperCase();
                previewBg.style.backgroundColor = hexColor;
                previewBtn.style.color = hexColor;
                previewBtn.style.borderColor = hexColor;
                previewBtn.style.backgroundColor = hexColor + '1A';
                
                if(document.querySelector('input[name="is_active"]').checked) {
                    visibilityToggleBg.style.backgroundColor = hexColor;
                }
            }

            colorInput.addEventListener('input', function(e) {
                updateColorUI(e.target.value);
            });

            if(resetColorBtn) {
                resetColorBtn.addEventListener('click', function() {
                    colorInput.value = DEFAULT_PHARMACY_BLUE;
                    updateColorUI(DEFAULT_PHARMACY_BLUE);
                });
            }
            
            const toggleInput = document.querySelector('input[name="is_active"]');
            toggleInput.addEventListener('change', function() {
                if(this.checked) {
                    visibilityToggleBg.style.backgroundColor = colorInput.value;
                } else {
                    visibilityToggleBg.style.backgroundColor = ''; 
                }
            });
        });
    </script>
@endsection
