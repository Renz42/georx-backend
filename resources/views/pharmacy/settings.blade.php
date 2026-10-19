@extends('pharmacy.layouts.app')
@section('title', 'Pharmacy Settings')

@section('content')
    <div class="max-w-5xl mx-auto pb-12">
        
        <!-- Page Header -->
        <div class="mb-8 border-b border-slate-200/80 pb-6 flex justify-between items-end">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Configuration & Preferences</p>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">System Settings</h2>
                <p class="text-slate-500 mt-1 text-sm font-normal">Manage storefront branding, store operations, and operational preferences.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl mb-8 flex items-center shadow-2xs font-bold text-xs sm:text-sm animate-pulse-once">
                <i class="fas fa-check-circle mr-3 text-emerald-600 text-lg"></i>
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('pharmacy.settings.update') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf
            
            <!-- SECTION 1: Storefront Branding -->
            <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-[#E6F4EA] text-[#0D7A5F] border border-emerald-100 p-2 rounded-xl shadow-2xs"><i class="fas fa-paint-brush text-sm"></i></div>
                        <h3 class="font-bold text-slate-900 text-base">Storefront Branding</h3>
                    </div>
                </div>
                
                <div class="p-6 sm:p-8">
                    <!-- LOGO UPLOAD SECTION -->
                    <div class="flex flex-col sm:flex-row gap-6 sm:gap-8 items-center border-b border-slate-100 pb-8 mb-8">
                        <!-- Logo Preview Circle -->
                        <div class="relative w-20 h-20 rounded-2xl border border-slate-200 shadow-2xs bg-white flex items-center justify-center shrink-0 overflow-hidden group">
                            <img id="logoPreview" src="{{ $pharmacy->logo_url ?? '' }}" 
                                 class="{{ $pharmacy->logo ? 'block' : 'hidden' }} w-full h-full object-cover">
                                 
                            <i id="logoPlaceholder" class="fas fa-clinic-medical text-2xl text-slate-300 {{ $pharmacy->logo ? 'hidden' : 'block' }}"></i>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 mb-1">Pharmacy Logo</h4>
                            <p class="text-xs text-slate-500 mb-3 leading-relaxed max-w-md">Upload a high-quality logo. Square dimensions recommended. Max file size: 2MB.</p>
                            
                            <div class="flex items-center gap-3">
                                <!-- Hidden File Input -->
                                <input type="file" id="logoUpload" name="logo" accept="image/*" class="hidden">
                                <label for="logoUpload" class="bg-white border border-slate-200 text-slate-700 font-bold px-3.5 py-2 rounded-xl text-xs cursor-pointer hover:bg-slate-50 hover:text-[#0D7A5F] hover:border-slate-300 transition-colors shadow-2xs inline-flex items-center gap-2">
                                    <i class="fas fa-upload text-slate-400"></i> Choose Image
                                </label>
                                
                                <!-- REMOVE LOGO BUTTON -->
                                <button type="button" id="removeLogoBtn" class="text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3 py-2 rounded-xl transition-colors border border-rose-100 {{ $pharmacy->logo ? 'block' : 'hidden' }}">
                                    <i class="fas fa-trash-alt mr-1"></i> Remove
                                </button>

                                <!-- Hidden input to send deletion command to server -->
                                <input type="hidden" name="remove_logo" id="removeLogoInput" value="0">
                            </div>
                        </div>
                    </div>
                    <!-- COVER PHOTO UPLOAD SECTION -->
                    <div class="flex flex-col sm:flex-row gap-6 sm:gap-8 items-center border-b border-slate-100 pb-8 mb-8">
                        <div class="relative w-40 h-20 rounded-xl border border-slate-200 shadow-2xs bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden group">
                            <img id="coverPreview" src="{{ $pharmacy->cover_photo_url ?? '' }}" 
                                 class="{{ $pharmacy->cover_photo ? 'block' : 'hidden' }} w-full h-full object-cover">
                                 
                            <i id="coverPlaceholder" class="fas fa-image text-2xl text-slate-300 {{ $pharmacy->cover_photo ? 'hidden' : 'block' }}"></i>
                        </div>
                        
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 mb-1">Storefront / Cover Photo</h4>
                            <p class="text-xs text-slate-500 mb-3 leading-relaxed max-w-md">Upload a banner or storefront photo for your patient catalogue. Max file size: 2MB.</p>
                            
                            <div class="flex items-center gap-3">
                                <input type="file" id="coverUpload" name="cover_photo" accept="image/*" class="hidden">
                                <label for="coverUpload" class="bg-white border border-slate-200 text-slate-700 font-bold px-3.5 py-2 rounded-xl text-xs cursor-pointer hover:bg-slate-50 hover:text-[#0D7A5F] hover:border-slate-300 transition-colors shadow-2xs inline-flex items-center gap-2">
                                    <i class="fas fa-upload text-slate-400"></i> Choose Image
                                </label>
                                
                                <button type="button" id="removeCoverBtn" class="text-xs font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 px-3 py-2 rounded-xl transition-colors border border-rose-100 {{ $pharmacy->cover_photo ? 'block' : 'hidden' }}">
                                    <i class="fas fa-trash-alt mr-1"></i> Remove
                                </button>
                                <input type="hidden" name="remove_cover_photo" id="removeCoverInput" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- COLOR PICKER SECTION -->
                    <div class="flex flex-col md:flex-row gap-8 items-start">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-slate-700 mb-1 uppercase tracking-wider">Public Storefront Brand Color</label>
                            <p class="text-slate-500 text-xs mb-4 leading-relaxed">
                                Customize the accent color for your public medicine catalog, patient badges, and customer buttons.
                            </p>
                            
                            <!-- Interactive Color Picker WITH RESET BUTTON -->
                            <div class="flex items-center gap-4 bg-slate-50/80 p-3.5 rounded-xl border border-slate-200/80 w-fit shadow-2xs">
                                <div class="relative w-12 h-12 rounded-lg overflow-hidden shadow-inner border-2 border-white cursor-pointer ring-1 ring-slate-300 hover:ring-[#0D7A5F] transition-all">
                                    <input type="color" id="colorPicker" name="theme_color" value="{{ $pharmacy->theme_color ?? '#0D7A5F' }}" 
                                           class="absolute -top-2 -left-2 w-16 h-16 cursor-pointer border-0 p-0">
                                </div>
                                <div class="px-2 flex flex-col justify-center">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5">Hex Code</p>
                                    <div class="flex items-center gap-3">
                                        <div id="hexDisplay" class="text-sm font-bold text-slate-800 uppercase tracking-widest font-mono">
                                            {{ $pharmacy->theme_color ?? '#0D7A5F' }}
                                        </div>
                                        <button type="button" id="resetColorBtn" class="text-[10px] bg-slate-200 hover:bg-slate-300 hover:text-[#0D7A5F] text-slate-600 px-2 py-1 rounded font-bold uppercase tracking-wider transition-colors" title="Reset to standard Clinical Pine">
                                            <i class="fas fa-undo mr-1"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Live Visual Preview Container -->
                        <div class="w-full md:w-80 bg-slate-50 rounded-2xl p-5 border border-slate-200/80 shadow-2xs shrink-0 relative overflow-hidden">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Storefront Button Preview
                            </p>
                            
                            <!-- Preview Outline Button -->
                            <div id="previewBtn" class="h-10 rounded-xl w-full flex items-center justify-center text-xs font-bold transition-colors duration-200 shadow-2xs" 
                                 style="background-color: {{ $pharmacy->theme_color ?? '#0D7A5F' }}1A; border: 1.5px solid {{ $pharmacy->theme_color ?? '#0D7A5F' }}; color: {{ $pharmacy->theme_color ?? '#0D7A5F' }};">
                                Patient Add to Cart
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- SECTION 2: Store Visibility -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                        <div class="bg-emerald-50 text-emerald-600 border border-emerald-100 p-2 rounded-xl shadow-2xs"><i class="fas fa-store text-sm"></i></div>
                        <h3 class="font-bold text-slate-900 text-base">Store Operations</h3>
                    </div>
                    
                    <div class="p-6 sm:p-8 flex-1 flex flex-col justify-between gap-6">
                        <div>
                            <div class="flex items-start justify-between mb-2">
                                <h4 class="text-sm font-bold text-slate-900">Manual Store Override</h4>
                                <!-- Professional Tailwind Toggle -->
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $pharmacy->is_active ? 'checked' : '' }}>
                                    <div class="w-12 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all shadow-inner peer-checked:bg-[#0D7A5F]"
                                         id="visibilityToggleBg"></div>
                                </label>
                            </div>
                            <p class="text-xs text-slate-500 leading-relaxed mb-4">
                                Toggle this <strong>OFF</strong> to force your pharmacy offline regardless of operating hours. When <strong>ON</strong>, your pharmacy automatically displays as "Open" during operating hours.
                            </p>

                            <!-- Live Computed Status Indicator -->
                            @php
                                $statusState = $pharmacy->is_currently_open;
                            @endphp
                            <div class="flex items-center gap-3 p-3 rounded-xl border {{ $statusState === 'online' ? 'bg-emerald-50 border-emerald-200' : ($statusState === 'closed' ? 'bg-amber-50 border-amber-200' : 'bg-red-50 border-red-200') }}">
                                <div class="w-2.5 h-2.5 rounded-full shrink-0 {{ $statusState === 'online' ? 'bg-emerald-500 animate-pulse' : ($statusState === 'closed' ? 'bg-amber-500' : 'bg-red-500') }}"></div>
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wider {{ $statusState === 'online' ? 'text-emerald-700' : ($statusState === 'closed' ? 'text-amber-700' : 'text-red-700') }}">
                                        @if($statusState === 'online')
                                            Currently Open
                                        @elseif($statusState === 'closed')
                                            Closed (Outside Operating Hours)
                                        @else
                                            Offline (Manual Override)
                                        @endif
                                    </p>
                                    <p class="text-[10px] font-medium {{ $statusState === 'online' ? 'text-emerald-600' : ($statusState === 'closed' ? 'text-amber-600' : 'text-red-600') }}">
                                        @if(is_array($pharmacy->operating_hours) && isset($pharmacy->operating_hours['open']) && isset($pharmacy->operating_hours['close']))
                                            Hours: {{ \Carbon\Carbon::parse($pharmacy->operating_hours['open'])->format('h:i A') }} - {{ \Carbon\Carbon::parse($pharmacy->operating_hours['close'])->format('h:i A') }}
                                        @else
                                            Operating hours not configured
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Inventory Alerts -->
                <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-slate-100 bg-slate-50/50 flex items-center gap-3">
                        <div class="bg-amber-50 text-amber-600 border border-amber-100 p-2 rounded-xl shadow-2xs"><i class="fas fa-boxes text-sm"></i></div>
                        <h3 class="font-bold text-slate-900 text-base">Inventory Preferences</h3>
                    </div>
                    
                    <div class="p-6 sm:p-8 flex-1">
                        <div class="mb-6">
                            <label class="block text-xs font-bold text-slate-800 mb-1.5 uppercase tracking-wider">Global Low-Stock Threshold</label>
                            <p class="text-xs text-slate-500 mb-4 leading-relaxed">
                                Set the default quantity at which items trigger a "Low Stock" warning on your dashboard.
                            </p>
                            <div class="flex items-center gap-3">
                                <input type="number" value="10" min="1" class="w-20 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl font-bold text-base text-slate-800 focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F] outline-none text-center">
                                <span class="text-slate-500 font-semibold text-xs">units remaining</span>
                            </div>
                        </div>

                        <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200/80 flex gap-2.5">
                            <i class="fas fa-info-circle text-slate-400 mt-0.5 text-xs"></i>
                            <p class="text-xs text-slate-600 font-medium leading-relaxed">Note: You can override this threshold for individual high-priority medicines directly from the "My Inventory" page.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Floating Save Bar -->
            <div class="sticky bottom-6 mt-8 bg-white/95 backdrop-blur-md p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-lg flex justify-between items-center z-40">
                <div class="flex items-center gap-3 ml-2 hidden sm:flex">
                    <div class="w-9 h-9 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400">
                        <i class="fas fa-shield-alt text-sm"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-800">Unsaved Changes</p>
                        <p class="text-[11px] font-normal text-slate-400">Save your storefront preferences when ready.</p>
                    </div>
                </div>
                
                <button type="submit" class="w-full sm:w-auto bg-[#0D7A5F] hover:bg-[#0B6E53] text-white font-bold px-8 py-3 rounded-xl shadow-xs transition-all active:scale-95 flex items-center justify-center gap-2 text-xs sm:text-sm">
                    <i class="fas fa-save text-xs"></i> 
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
