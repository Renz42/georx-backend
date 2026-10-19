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
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl mb-8 flex items-center shadow-2xs font-bold text-xs sm:text-sm">
                <i class="fas fa-check-circle mr-3 text-emerald-600 text-lg"></i>
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($medicines as $medicine)
                <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden flex flex-col hover:shadow-sm transition-shadow">
                    
                    <form action="{{ route('pharmacy.catalog.update', $medicine->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
                        @csrf
                        
                        <div class="h-48 bg-slate-50 border-b border-slate-100 relative group flex items-center justify-center">
                            @if($medicine->image)
                                <img src="{{ asset('storage/' . $medicine->image) }}" alt="Medicine" class="w-full h-full object-cover">
                            @else
                                <div class="text-center opacity-50 group-hover:opacity-100 transition-opacity">
                                    <i class="fas fa-camera text-3xl text-slate-400 mb-2 block"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">No Photo</span>
                                </div>
                            @endif
                            
                            <input type="file" id="image_{{ $medicine->id }}" name="image" accept="image/*" class="hidden" onchange="this.form.submit()">
                            
                            <label for="image_{{ $medicine->id }}" class="absolute bottom-3 right-3 bg-[#0D7A5F] hover:bg-[#0B6E53] text-white w-9 h-9 rounded-full flex items-center justify-center cursor-pointer shadow-sm transition-all active:scale-95" title="Upload New Photo">
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
