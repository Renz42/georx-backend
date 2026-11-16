@extends('pharmacy.layouts.app') 
@section('title', 'Medicine Catalog')

@section('content')
    <div class="max-w-6xl mx-auto">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Prescription Directory</p>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Medicine Catalog</h2>
                <p class="text-slate-500 mt-1 text-sm font-normal">Upload prescription photos and clinical descriptions for patient education.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl mb-6 flex items-center shadow-2xs font-bold text-xs sm:text-sm">
                <i class="fas fa-check-circle mr-3 text-emerald-600 text-lg"></i>
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-5 py-3.5 rounded-2xl mb-6 flex items-center shadow-2xs font-bold text-xs sm:text-sm">
                <i class="fas fa-exclamation-triangle mr-3 text-amber-600 text-lg"></i>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-5 py-3.5 rounded-2xl mb-6 flex items-center shadow-2xs font-bold text-xs sm:text-sm">
                <i class="fas fa-exclamation-circle mr-3 text-rose-600 text-lg"></i>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-5 py-3.5 rounded-2xl mb-8 flex items-center shadow-2xs font-bold text-xs sm:text-sm">
                <i class="fas fa-exclamation-circle mr-3 text-rose-600 text-lg"></i>
                <div class="flex-1">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div id="catalogUploadClientError" class="hidden bg-rose-50 border border-rose-200 text-rose-800 px-5 py-3.5 rounded-2xl mb-6 flex items-center shadow-2xs font-bold text-xs sm:text-sm" role="alert">
            <i class="fas fa-exclamation-circle mr-3 text-rose-600 text-lg"></i>
            <span id="catalogUploadClientErrorText"></span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($medicines as $medicine)
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col hover:shadow-sm transition-shadow">
                    
                    <form action="{{ (request()->routeIs('portal.*') || request()->is('portal/*')) ? route('portal.catalog.update', $medicine->id) : route('pharmacy.catalog.update', $medicine->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
                        @csrf
                        
                        <div class="h-48 bg-slate-50 border-b border-slate-100 relative group flex items-center justify-center p-4 overflow-hidden">
                            @if($medicine->image_url)
                                <img src="{{ $medicine->image_url }}"
                                     data-original-src="{{ $medicine->image_url }}"
                                     @if($medicine->fallback_image_url) data-fallback-src="{{ $medicine->fallback_image_url }}" @endif
                                     data-retry-step="0"
                                     alt="{{ $medicine->generic_name ?? 'Medicine Photo' }}"
                                     class="w-full h-full object-contain object-center transition-transform duration-300"
                                     onerror="handleCatalogImageError(this)">
                            @endif
                            <div data-image-placeholder class="{{ $medicine->image_url ? 'hidden' : '' }} text-center opacity-60 group-hover:opacity-100 transition-opacity">
                                <i class="fas fa-camera text-3xl text-slate-400 mb-2 block"></i>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block">No Photo</span>
                                <span class="text-[10px] font-medium text-slate-400 mt-1 block">JPG, PNG, WEBP &bull; Max 2MB</span>
                            </div>
                            
                            <input type="file" id="image_{{ $medicine->id }}" name="image" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="handleCatalogImageSelect(this)">
                            
                            <label for="image_{{ $medicine->id }}" id="upload_label_{{ $medicine->id }}" class="absolute bottom-3 right-3 bg-[#0D7A5F] hover:bg-[#0B6E53] text-white w-9 h-9 rounded-full flex items-center justify-center cursor-pointer shadow-sm transition-all active:scale-95" title="Upload New Photo (JPG, PNG, WEBP, Max 2MB)">
                                <i class="fas fa-upload text-xs"></i>
                            </label>
                        </div>

                        <div class="p-6 flex-1 flex flex-col">
                            <div class="mb-4">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-bold text-slate-900 text-base leading-tight">{{ $medicine->brand_name ? $medicine->brand_name . ' / ' : '' }}{{ $medicine->generic_name }}</h3>
                                </div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">{{ $medicine->strength ?? '' }} {{ $medicine->dosage_form ?? '' }}</p>
                            </div>

                            <div class="flex-1 flex flex-col">
                                <label class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1.5 block">Clinical Description & Warnings</label>
                                <textarea name="description" rows="3" placeholder="Add usage instructions, warnings, or detailed descriptions here..." class="w-full flex-1 bg-slate-50 border border-slate-200/90 rounded-xl p-3 text-xs sm:text-sm focus:ring-2 focus:ring-[#0D7A5F]/20 focus:border-[#0D7A5F] outline-none text-slate-700 custom-scrollbar">{{ $medicine->description }}</textarea>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100 text-right">
                                <button type="submit" class="bg-slate-100 hover:bg-[#E6F4EA] text-slate-700 hover:text-[#0D7A5F] font-bold px-4 py-2 rounded-xl text-xs uppercase tracking-wider transition-all border border-transparent hover:border-emerald-200/60 shadow-2xs">
                                    Save Description
                                </button>
                            </div>
                        </div>
                    </form>

                </div>
            @empty
                <div class="col-span-full py-12 text-center">
                    <i class="fas fa-pills text-4xl text-slate-300 mb-3"></i>
                    <p class="text-slate-500 font-medium">You don't have any medicines in your inventory yet.</p>
                </div>
            @endforelse
        </div>

    </div>
@endsection

@push('scripts')
<script>
    function handleCatalogImageError(img) {
        if (!img) {
            return;
        }

        const step = parseInt(img.dataset.retryStep || '0', 10);
        const originalSrc = img.dataset.originalSrc || '';
        const fallbackSrc = img.dataset.fallbackSrc || '';

        if (step === 0 && originalSrc) {
            img.dataset.retryStep = '1';
            let retrySrc;
            try {
                const parsedUrl = new URL(originalSrc, window.location.origin);
                parsedUrl.searchParams.set('retry', '1');
                retrySrc = parsedUrl.toString();
            } catch (e) {
                const hashIdx = originalSrc.indexOf('#');
                const base = hashIdx === -1 ? originalSrc : originalSrc.slice(0, hashIdx);
                const hash = hashIdx === -1 ? '' : originalSrc.slice(hashIdx);
                retrySrc = base + (base.indexOf('?') === -1 ? '?' : '&') + 'retry=1' + hash;
            }
            setTimeout(function () {
                img.src = retrySrc;
            }, 250);
            return;
        }

        if (step <= 1 && fallbackSrc && img.getAttribute('src') !== fallbackSrc) {
            img.dataset.retryStep = '2';
            img.src = fallbackSrc;
            return;
        }

        img.dataset.retryStep = '3';
        img.onerror = null;
        img.classList.add('hidden');

        const container = img.parentElement;
        if (container) {
            const placeholder = container.querySelector('[data-image-placeholder]');
            if (placeholder) {
                placeholder.classList.remove('hidden');
            }
        }
    }

    function showCatalogUploadError(message) {
        const box = document.getElementById('catalogUploadClientError');
        const text = document.getElementById('catalogUploadClientErrorText');
        if (box && text) {
            text.textContent = message;
            box.classList.remove('hidden');
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function handleCatalogImageSelect(input) {
        if (!input || !input.files || !input.files.length) {
            return;
        }

        const file = input.files[0];
        const form = input.form;
        if (!form || form.dataset.uploading === '1') {
            return;
        }

        const maxBytes = 2 * 1024 * 1024; // Matches backend max:2048 (2MB)
        const allowedMimes = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'];
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        const allowedExts = ['jpg', 'jpeg', 'png', 'webp'];

        if ((file.type && !allowedMimes.includes(file.type.toLowerCase())) || !allowedExts.includes(ext)) {
            input.value = '';
            showCatalogUploadError('Please select a valid JPG, PNG, or WEBP medicine photo.');
            return;
        }

        if (file.size > maxBytes) {
            input.value = '';
            showCatalogUploadError('Medicine photo exceeds the 2MB limit. Please choose a smaller image.');
            return;
        }

        const errorBox = document.getElementById('catalogUploadClientError');
        if (errorBox) {
            errorBox.classList.add('hidden');
        }

        form.dataset.uploading = '1';
        const label = document.querySelector('label[for="' + input.id + '"]');
        if (label) {
            label.classList.add('opacity-70', 'pointer-events-none');
            label.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i>';
        }

        form.submit();
    }
</script>
@endpush
