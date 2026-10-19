@extends('pharmacy.layouts.app')
@section('title', 'Dashboard')

@section('content')
    <!-- ⚠️ PENDING APPROVAL BANNER -->
    @if(!$pharmacy->is_approved)
        <div class="bg-amber-50 border-l-4 border-amber-400 p-6 rounded-2xl mb-8 shadow-sm">
            <div class="flex gap-4">
                <div class="bg-amber-100 p-3 rounded-full h-fit text-amber-600">
                    <i class="fas fa-hourglass-half text-xl"></i>
                </div>
                <div>
                    <h3 class="text-amber-800 font-bold text-lg">Account Pending Verification</h3>
                    <p class="text-amber-700 text-sm mt-1 leading-relaxed">
                        Your pharmacy details are currently being reviewed by our Super Admin. 
                        You can manage your inventory now, but your pharmacy will not appear on the public map until it is approved.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- Success Messages -->
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-2xl mb-8 flex items-center shadow-sm">
            <i class="fas fa-check-circle mr-3 text-emerald-500 text-lg"></i>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    <!-- Clean Enterprise Welcome Banner -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-8 shadow-xs border border-slate-200/80">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Overview & Analytics</p>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Welcome back, {{ auth()->user()->name }}</h2>
                <p class="text-slate-500 mt-1.5 text-sm font-normal">Here is your real-time performance overview for <span class="font-bold text-slate-700">{{ $pharmacy->name }}</span>.</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap sm:flex-nowrap">
                <a href="{{ route('pharmacy.report') }}" target="_blank" class="bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 px-4 py-2.5 rounded-xl border border-slate-200/90 text-xs sm:text-sm font-bold shadow-2xs flex items-center gap-2 transition-all">
                    <i class="fas fa-download text-slate-400 text-xs"></i> 
                    <span>PDF Report</span>
                </a>
                <div class="bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-200/90 text-slate-600 text-xs sm:text-sm font-semibold shadow-2xs shrink-0 flex items-center gap-2">
                    <i class="far fa-calendar-alt text-slate-400 text-xs"></i> {{ now()->format('M d, Y') }}
                </div>
            </div>
        </div>
    </div>

    <!-- Analytics Top Row Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- Total Revenue -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6 hover:shadow-sm transition-all duration-300 md:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Sales Revenue</p>
                <div class="bg-emerald-50 text-emerald-600 p-2.5 rounded-xl border border-emerald-100">
                    <i class="fas fa-wallet text-lg"></i>
                </div>
            </div>
            <p class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">₱{{ number_format($totalRevenue, 2) }}</p>
            <div class="mt-4 flex items-center text-[10px] text-emerald-700 font-bold uppercase tracking-wider bg-emerald-50 border border-emerald-100 w-fit px-3 py-1 rounded-full">
                <i class="fas fa-arrow-trend-up mr-1.5 text-emerald-600"></i> +₱{{ number_format($todayRevenue, 2) }} Today
            </div>
        </div>

        <!-- In Stock -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6 hover:shadow-sm transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">In Stock</p>
                <div class="bg-[#E6F4EA] text-[#0D7A5F] p-2.5 rounded-xl border border-emerald-100">
                    <i class="fas fa-pills text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-slate-900">{{ $stats['medicines_in_stock'] }}</p>
            <p class="text-xs text-slate-500 font-medium mt-2">Available items in inventory</p>
        </div>

        <!-- Low/Out of Stock -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6 hover:shadow-sm transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Needs Attention</p>
                <div class="bg-amber-50 text-amber-600 p-2.5 rounded-xl border border-amber-100">
                    <i class="fas fa-exclamation-triangle text-lg"></i>
                </div>
            </div>
            <p class="text-3xl font-extrabold text-amber-600">{{ $stats['low_stock'] }} <span class="text-lg text-slate-400 font-bold">/</span> <span class="text-rose-600">{{ $stats['out_of_stock'] }}</span></p>
            <p class="text-[10px] text-slate-500 font-medium mt-2 uppercase tracking-widest">Low Stock / Empty</p>
        </div>
    </div>

    <!-- Charts and Tables Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-12">
        <!-- Sales Chart -->
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-xs border border-slate-200/80 p-6">
            <h3 class="font-bold text-slate-900 text-xs sm:text-sm uppercase tracking-tight mb-6 flex items-center gap-2">
                <i class="fas fa-chart-line text-[#0D7A5F]"></i> Monthly Sales Trend
            </h3>
            <div class="h-[300px] w-full">
                <canvas id="salesChart"></canvas>
            </div>
        </div>

        <!-- Fast Moving Items -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col">
            <div class="px-6 py-5 bg-slate-50/50 border-b border-slate-100">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">
                    <i class="fas fa-fire text-amber-500 mr-2"></i> Top 5 Fast-Moving Items
                </h3>
            </div>
            <div class="p-6 flex-1">
                @if($topMedicines->isEmpty())
                    <div class="text-center py-10">
                        <i class="fas fa-box-open text-3xl text-slate-300 mb-3"></i>
                        <p class="text-slate-500 text-sm font-medium">No sales data yet.</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($topMedicines as $index => $item)
                            <div class="flex items-center gap-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-xs shrink-0
                                    {{ $index === 0 ? 'bg-amber-100 text-amber-600' : ($index === 1 ? 'bg-slate-100 text-slate-600' : 'bg-orange-50 text-orange-600') }}">
                                    #{{ $index + 1 }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-slate-800 truncate text-sm">{{ $item->medicine->brand_name ?? $item->medicine->generic_name }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ $item->medicine->generic_name }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-black text-emerald-600">{{ $item->total_sold }} sold</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Include Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('salesChart').getContext('2d');
        
        const labels = {!! json_encode(array_column($monthlySales, 'month')) !!};
        const data = {!! json_encode(array_column($monthlySales, 'revenue')) !!};
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: data,
                    borderColor: '#0D7A5F',
                    backgroundColor: 'rgba(13, 122, 95, 0.08)',
                    borderWidth: 2.5,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0D7A5F',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { family: 'Inter', size: 13, weight: 'bold' },
                        bodyFont: { family: 'Inter', size: 14, weight: 'bold' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return '₱ ' + context.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', weight: '600' }, color: '#64748b' }
                    },
                    y: {
                        border: { display: false },
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            font: { family: 'Inter', weight: '600' },
                            color: '#64748b',
                            callback: function(value) { return '₱' + value.toLocaleString(); }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
@endsection
