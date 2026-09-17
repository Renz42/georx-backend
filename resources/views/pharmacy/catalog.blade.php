@extends('pharmacy.layouts.app') 
@section('title', 'Medicine Catalog')

@section('content')
    <div class="max-w-6xl mx-auto">
        
        <div class="mb-8 flex justify-between items-end">
            <div>
                <h2 class="text-3xl font-black text-slate-800 tracking-tight">Medicine Catalog</h2>
                <p class="text-slate-500 mt-1 font-medium text-sm">Upload photos and manage clinical descriptions for your patients.</p>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-2xl mb-8 flex items-center shadow-sm font-bold">
                <i class="fas fa-check-circle mr-3 text-emerald-500 text-xl"></i>
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse($medicines as $medicine)
                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                    
                    <form action="{{ route('pharmacy.catalog.update', $medicine->id) }}" method="POST" enctype="multipart/form-data" class="flex flex-col h-full">
                        @csrf
                        
                        <div class="h-48 bg-slate-50 border-b border-slate-100 relative group flex items-center justify-center">
                            @if($medicine->image)
                                <img src="{{ asset('storage/' . $medicine->image) }}" alt="Medicine" class="w-full h-full object-cover">
                            @else
                                <div class="text-center opacity-50 group-hover:opacity-100 transition-opacity">
                                    <i class="fas fa-camera text-4xl text-slate-400 mb-2 block"></i>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">No Photo</span>
                                </div>
                            @endif
                            
                            <input type="file" id="image_{{ $medicine->id }}" name="image" accept="image/*" class="hidden" onchange="this.form.submit()">
                            
                            <label for="image_{{ $medicine->id }}" class="absolute bottom-3 right-3 bg-blue-600 hover:bg-blue-700 text-white w-10 h-10 rounded-full flex items-center justify-center cursor-pointer shadow-lg transition-colors" title="Upload New Photo">
                                <i class="fas fa-upload text-sm"></i>
                            </label>
                        </div>

                        <div class="p-6 flex-1 flex flex-col">
                            <div class="mb-4">
                                <div class="flex justify-between items-start">
                                    <h3 class="font-black text-slate-800 text-lg leading-tight">{{ $medicine->brand_name ? $medicine->brand_name . ' / ' : '' }}{{ $medicine->generic_name }}</h3>
                                </div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">{{ $medicine->strength ?? '' }} {{ $medicine->dosage_form ?? '' }}</p>
                            </div>

                            <div class="flex-1 flex flex-col">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Clinical Description & Warnings</label>
                                <textarea name="description" rows="3" placeholder="Add usage instructions, warnings, or detailed descriptions here..." class="w-full flex-1 bg-slate-50 border border-slate-200 rounded-xl p-3 text-sm focus:ring-2 focus:ring-blue-500 outline-none text-slate-700 custom-scrollbar">{{ $medicine->description }}</textarea>
                            </div>

                            <div class="mt-4 pt-4 border-t border-slate-100 text-right">
                                <button type="submit" class="bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 font-bold px-5 py-2.5 rounded-xl text-xs uppercase tracking-widest transition-colors border border-transparent hover:border-blue-200">
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
