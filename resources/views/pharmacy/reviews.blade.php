@extends('pharmacy.layouts.app')
@section('title', 'Customer Reviews')

@section('content')
    <!-- Page Header -->
    <div class="mb-10 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <h2 class="text-3xl font-black text-slate-800 tracking-tight">Customer Reviews</h2>
            <p class="text-slate-500 mt-1 font-medium">Monitor patient feedback and satisfaction ratings for your pharmacy.</p>
        </div>
    </div>

    <!-- Rating Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <!-- Overall Rating -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">
            <div class="text-4xl font-black text-slate-800 mb-1">{{ $ratings['pharmacy_overall'] > 0 ? number_format($ratings['pharmacy_overall'], 1) : '--' }}</div>
            <div class="flex justify-center gap-0.5 mb-2">
                @for ($i = 1; $i <= 5; $i++)
                    @if ($i <= round($ratings['pharmacy_overall']))
                        <i class="fas fa-star text-amber-400 text-sm"></i>
                    @else
                        <i class="fas fa-star text-slate-200 text-sm"></i>
                    @endif
                @endfor
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Overall Rating</p>
            <p class="text-xs text-slate-500 font-medium mt-1">{{ $ratings['count'] }} {{ Str::plural('review', $ratings['count']) }}</p>
        </div>

        <!-- Customer Service -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">
            <div class="text-3xl font-black text-slate-800 mb-1">{{ $ratings['customer_service'] > 0 ? number_format($ratings['customer_service'], 1) : '--' }}</div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-2 mb-2">
                <div class="h-2 rounded-full transition-all duration-500" style="width: {{ ($ratings['customer_service'] / 5) * 100 }}%; background-color: {{ Auth::user()->pharmacy->theme_color ?? '#2563eb' }};"></div>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Quality</p>
        </div>

        <!-- Medicine Availability -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">
            <div class="text-3xl font-black text-slate-800 mb-1">{{ $ratings['medicine_availability'] > 0 ? number_format($ratings['medicine_availability'], 1) : '--' }}</div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-2 mb-2">
                <div class="h-2 rounded-full transition-all duration-500" style="width: {{ ($ratings['medicine_availability'] / 5) * 100 }}%; background-color: {{ Auth::user()->pharmacy->theme_color ?? '#2563eb' }};"></div>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Availability</p>
        </div>

        <!-- Price Rating -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 text-center">
            <div class="text-3xl font-black text-slate-800 mb-1">{{ $ratings['price_rating'] > 0 ? number_format($ratings['price_rating'], 1) : '--' }}</div>
            <div class="w-full bg-slate-100 rounded-full h-2 mt-2 mb-2">
                <div class="h-2 rounded-full transition-all duration-500" style="width: {{ ($ratings['price_rating'] / 5) * 100 }}%; background-color: {{ Auth::user()->pharmacy->theme_color ?? '#2563eb' }};"></div>
            </div>
            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Pricing</p>
        </div>
    </div>

    <!-- Detailed Category Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Left: Category Breakdown -->
        <div class="lg:col-span-1 bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
                <h3 class="font-black text-slate-800 flex items-center gap-2 text-sm uppercase tracking-tight">
                    <i class="fas fa-chart-bar text-blue-600"></i> Rating Breakdown
                </h3>
            </div>
            <div class="p-6 space-y-5">
                @php
                    $categories = [
                        ['label' => 'Pharmacy Overall', 'key' => 'pharmacy_overall', 'icon' => 'fa-store'],
                        ['label' => 'Customer Service', 'key' => 'customer_service', 'icon' => 'fa-headset'],
                        ['label' => 'Medicine Availability', 'key' => 'medicine_availability', 'icon' => 'fa-pills'],
                        ['label' => 'Price Rating', 'key' => 'price_rating', 'icon' => 'fa-tag'],
                        ['label' => 'Accuracy', 'key' => 'accuracy', 'icon' => 'fa-bullseye'],
                        ['label' => 'Medicine Condition', 'key' => 'medicine_condition', 'icon' => 'fa-box'],
                    ];
                @endphp

                @foreach($categories as $cat)
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <i class="fas {{ $cat['icon'] }} text-slate-400 text-xs w-4 text-center"></i>
                                <span class="text-xs font-bold text-slate-600">{{ $cat['label'] }}</span>
                            </div>
                            <span class="text-xs font-black text-slate-800">{{ $ratings[$cat['key']] > 0 ? number_format($ratings[$cat['key']], 1) : '--' }}</span>
                        </div>
                        <div class="w-full bg-slate-100 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-700 ease-out" 
                                 style="width: {{ $ratings[$cat['key']] > 0 ? ($ratings[$cat['key']] / 5) * 100 : 0 }}%; background-color: {{ Auth::user()->pharmacy->theme_color ?? '#2563eb' }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Right: Recent Reviews Feed -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <h3 class="font-black text-slate-800 flex items-center gap-2 text-sm uppercase tracking-tight">
                    <i class="fas fa-comments text-blue-600"></i> Recent Reviews
                </h3>
                <span class="text-xs font-bold text-slate-400 bg-slate-100 px-3 py-1 rounded-lg">{{ $reviews->count() }} shown</span>
            </div>

            <div class="flex-1 overflow-y-auto max-h-[600px] custom-scrollbar">
                @forelse($reviews as $review)
                    <div class="p-6 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-start gap-4">
                            <!-- User Avatar -->
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 text-white text-sm font-black shadow-sm"
                                 style="background-color: {{ Auth::user()->pharmacy->theme_color ?? '#2563eb' }};">
                                {{ strtoupper(substr($review->user->name ?? 'A', 0, 1)) }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <h4 class="text-sm font-black text-slate-800 truncate">{{ $review->user->name ?? 'Anonymous Patient' }}</h4>
                                    <time class="text-[10px] font-bold text-slate-400 uppercase tracking-widest whitespace-nowrap">{{ $review->created_at->diffForHumans() }}</time>
                                </div>

                                <!-- Star Rating -->
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="flex gap-0.5">
                                        @php $displayRating = $review->pharmacy_overall ?? $review->rating ?? 0; @endphp
                                        @for ($i = 1; $i <= 5; $i++)
                                            @if ($i <= round($displayRating))
                                                <i class="fas fa-star text-amber-400 text-xs"></i>
                                            @else
                                                <i class="fas fa-star text-slate-200 text-xs"></i>
                                            @endif
                                        @endfor
                                    </div>
                                    <span class="text-xs font-black text-slate-500">{{ number_format($displayRating, 1) }}</span>

                                    @if($review->order_id)
                                        <span class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">
                                            <i class="fas fa-receipt mr-0.5"></i> Order #{{ $review->order_id }}
                                        </span>
                                    @else
                                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">
                                            <i class="fas fa-store mr-0.5"></i> Direct Review
                                        </span>
                                    @endif
                                </div>

                                <!-- Comment -->
                                @if($review->comment)
                                    <p class="text-sm text-slate-600 leading-relaxed font-medium">{{ $review->comment }}</p>
                                @else
                                    <p class="text-xs text-slate-400 italic font-medium">No written feedback provided.</p>
                                @endif

                                <!-- Micro Ratings (if available) -->
                                @if($review->customer_service || $review->medicine_availability || $review->price_rating || $review->accuracy)
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if($review->customer_service)
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">
                                                <i class="fas fa-headset text-slate-400 mr-1"></i> Service: {{ $review->customer_service }}/5
                                            </span>
                                        @endif
                                        @if($review->medicine_availability)
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">
                                                <i class="fas fa-pills text-slate-400 mr-1"></i> Availability: {{ $review->medicine_availability }}/5
                                            </span>
                                        @endif
                                        @if($review->price_rating)
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">
                                                <i class="fas fa-tag text-slate-400 mr-1"></i> Price: {{ $review->price_rating }}/5
                                            </span>
                                        @endif
                                        @if($review->accuracy)
                                            <span class="text-[10px] font-bold text-slate-500 bg-slate-50 px-2 py-1 rounded-lg border border-slate-100">
                                                <i class="fas fa-bullseye text-slate-400 mr-1"></i> Accuracy: {{ $review->accuracy }}/5
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <!-- Empty State -->
                    <div class="p-12 text-center">
                        <div class="w-20 h-20 mx-auto rounded-full bg-slate-100 flex items-center justify-center mb-4">
                            <i class="fas fa-star-half-alt text-3xl text-slate-300"></i>
                        </div>
                        <h4 class="text-lg font-black text-slate-700 mb-1">No Reviews Yet</h4>
                        <p class="text-sm text-slate-500 font-medium max-w-sm mx-auto leading-relaxed">
                            Once patients leave feedback about your pharmacy, their reviews will appear here. Focus on providing excellent service to earn your first review.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Delivery Ratings Summary (if applicable) -->
    @if($ratings['delivery_overall'] > 0)
    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50">
            <h3 class="font-black text-slate-800 flex items-center gap-2 text-sm uppercase tracking-tight">
                <i class="fas fa-truck text-blue-600"></i> Delivery Experience Ratings
            </h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="text-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="text-2xl font-black text-slate-800 mb-1">{{ number_format($ratings['delivery_speed'], 1) }}</div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Delivery Speed</p>
                </div>
                <div class="text-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="text-2xl font-black text-slate-800 mb-1">{{ number_format($ratings['driver_professionalism'], 1) }}</div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Driver Professionalism</p>
                </div>
                <div class="text-center p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <div class="text-2xl font-black text-slate-800 mb-1">{{ number_format($ratings['delivery_overall'], 1) }}</div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Delivery Overall</p>
                </div>
            </div>
        </div>
    </div>
    @endif
@endsection
