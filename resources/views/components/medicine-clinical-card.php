<div class="max-w-md mx-auto bg-white rounded-3xl p-6 shadow-xl border border-slate-200 overflow-hidden relative">
    
    <!-- Medicine Header (Generic + Icon) -->
    <div class="flex items-start gap-4 mb-5">
        <div class="p-3 bg-blue-50 text-blue-600 rounded-full">
            <i class="fas fa-pills text-3xl"></i>
        </div>
        <div>
            <h1 class="text-3xl font-extrabold text-blue-900 tracking-tight leading-none">
                {{ strtoupper($medicine->brand_name ?? $medicine->generic_name) }}
            </h1>
            <p class="text-slate-500 text-sm font-semibold mt-1 uppercase tracking-wide">
                {{ $medicine->generic_name }} {{ $medicine->strength ?? '' }} {{ strtoupper($medicine->dosage_form ?? '') }}
            </p>
        </div>
    </div>

    <!-- Drug Classification Tag -->
    <div class="mb-5">
        <span class="bg-blue-50 text-blue-700 text-xs font-bold px-4 py-1 rounded-full uppercase tracking-wider">
            {{ strtoupper($medicine->drug_category ?? 'Uncategorized') }}
        </span>
    </div>

    <!-- Primary Use Description -->
    <div class="mb-5 space-y-2">
        <p class="text-sm font-extrabold text-slate-800">
            Primary use: <span class="text-slate-600 font-medium">{{ $medicine->primary_use ?? 'No description available for this medicine.' }}</span>
        </p>
        @if($medicine->description)
            <p class="text-sm text-slate-600 font-medium">
                {{ $medicine->description }}
            </p>
        @endif
    </div>

    <!-- Rx Prescription Required Status -->
    <div class="bg-slate-50 border border-slate-100 px-4 py-3 rounded-lg mb-6 flex items-center justify-between text-sm">
        <span class="font-semibold text-slate-700">
            <i class="fas fa-prescription mr-2 text-slate-400"></i>
            Prescription Required:
        </span>
        @if($medicine->prescription_required)
            <span class="text-red-600 font-bold uppercase tracking-wide">Yes</span>
        @else
            <span class="text-emerald-600 font-bold uppercase tracking-wide">No</span>
        @endif
    </div>

    <!-- Main Action Button -->
    <a href="{{ route('api.pharmacies.nearby', ['medicine_id' => $medicine->id]) }}" 
       class="w-full py-4 bg-blue-600 text-white rounded-2xl font-bold shadow-lg shadow-blue-200 hover:bg-blue-700 transition flex items-center justify-center gap-3">
        <i class="fas fa-map-marked-alt text-xl"></i>
        <span>FIND NEARBY PHARMACIES</span>
    </a>
</div>