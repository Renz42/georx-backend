@extends('admin.layouts.app')

@section('content')
    <!-- Welcome Banner -->
    <div class="bg-white rounded-3xl p-8 sm:p-10 mb-8 shadow-sm border border-slate-200">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-800 tracking-tight">Super Admin Dashboard</h2>
                <p class="text-slate-500 mt-2 font-medium">Welcome back, <span class="text-slate-700 font-bold">{{ auth()->user()->name }}</span>. Here is your platform overview.</p>
            </div>
            <div class="bg-slate-50 px-5 py-3 rounded-2xl border border-slate-200 text-slate-600 text-sm font-bold shadow-sm shrink-0 flex items-center">
                <i class="far fa-calendar-alt mr-2 opacity-80"></i> {{ now()->format('F d, Y') }}
            </div>
        </div>
    </div>

    <!-- Top Row: KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Platform Revenue -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-all col-span-1 sm:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Platform Revenue</p>
                <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl">
                    <i class="fas fa-coins text-xl"></i>
                </div>
            </div>
            <p class="text-4xl font-black text-slate-800 tracking-tight">₱{{ number_format($platformRevenue, 2) }}</p>
            <div class="flex items-center gap-4 mt-4">
                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3 py-1 rounded-full">
                    <i class="fas fa-check-circle mr-1"></i> {{ $deliveredOrders }} delivered
                </span>
                <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1 rounded-full">
                    <i class="fas fa-clock mr-1"></i> {{ $pendingOrders }} active
                </span>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Orders</p>
                <div class="bg-indigo-50 text-indigo-600 p-3 rounded-xl">
                    <i class="fas fa-shopping-bag text-xl"></i>
                </div>
            </div>
            <p class="text-3xl font-black text-slate-800">{{ $totalOrders }}</p>
            <p class="text-xs text-slate-500 font-medium mt-2">Across all pharmacies</p>
        </div>

        <!-- Registered Users -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Registered Users</p>
                <div class="bg-blue-50 text-blue-600 p-3 rounded-xl">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <p class="text-3xl font-black text-slate-800">{{ $stats['users'] }}</p>
            <p class="text-xs text-slate-500 font-medium mt-2">All roles combined</p>
        </div>
    </div>

    <!-- Second Row: Pharmacy Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
        <!-- Total Pharmacies -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Total Pharmacies</p>
                <div class="bg-indigo-50 text-indigo-600 p-3 rounded-xl"><i class="fas fa-store text-xl"></i></div>
            </div>
            <p class="text-3xl font-black text-indigo-600">{{ $stats['pharmacies'] }}</p>
            <p class="text-xs text-slate-500 font-medium mt-2">Registered on platform</p>
        </div>

        <!-- Active / Approved -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Active</p>
                <div class="bg-emerald-50 text-emerald-600 p-3 rounded-xl"><i class="fas fa-check-circle text-xl"></i></div>
            </div>
            <p class="text-3xl font-black text-emerald-600">{{ $stats['active_pharmacies'] }}</p>
            <p class="text-xs text-slate-500 font-medium mt-2">Visible on public map</p>
        </div>

        <!-- Pending Approval -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 hover:shadow-md transition-all">
            <div class="flex items-center justify-between mb-3">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">Pending Review</p>
                <div class="bg-amber-50 text-amber-600 p-3 rounded-xl"><i class="fas fa-hourglass-half text-xl"></i></div>
            </div>
            <p class="text-3xl font-black text-amber-600">{{ $stats['pending_pharmacies'] }}</p>
            <a href="{{ route('admin.pharmacies') }}" class="text-xs text-amber-600 font-bold mt-2 inline-block hover:underline">
                <i class="fas fa-arrow-right mr-1"></i> Review now
            </a>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Platform Growth Chart -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
            <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight mb-6">
                <i class="fas fa-chart-area text-indigo-500 mr-2"></i> Platform Growth (6 Months)
            </h3>
            <div class="h-[300px] w-full">
                <canvas id="growthChart"></canvas>
            </div>
        </div>

        <!-- User Role Distribution (Doughnut) -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 flex flex-col">
            <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight mb-6">
                <i class="fas fa-chart-pie text-violet-500 mr-2"></i> User Role Distribution
            </h3>
            <div class="flex-1 flex items-center justify-center">
                <canvas id="roleChart" class="max-h-[260px]"></canvas>
            </div>
        </div>
    </div>

    <!-- Phase 3: Lightweight Reporting Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- Most Searched Medicines -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">
                    <i class="fas fa-search text-blue-500 mr-2"></i>Most Searched
                </h3>
            </div>
            <div class="divide-y divide-slate-50">
                @forelse($mostSearched as $idx => $search)
                <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 text-[10px] font-black flex items-center justify-center">{{ $idx + 1 }}</span>
                        <span class="text-sm font-bold text-slate-700">{{ $search->term }}</span>
                    </div>
                    <span class="text-xs font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded-full">{{ $search->search_count }}×</span>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-search text-2xl text-slate-300 mb-2"></i>
                    <p class="text-slate-400 text-sm font-medium">No searches yet</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Most Ordered Medicines -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">
                    <i class="fas fa-fire text-orange-500 mr-2"></i>Most Ordered
                </h3>
            </div>
            <div class="divide-y divide-slate-50">
                @forelse($mostOrdered as $idx => $item)
                <div class="px-6 py-3 flex items-center justify-between hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-3">
                        <span class="w-6 h-6 rounded-full bg-orange-50 text-orange-600 text-[10px] font-black flex items-center justify-center">{{ $idx + 1 }}</span>
                        <span class="text-sm font-bold text-slate-700 line-clamp-1">{{ $item->medicine->brand_name ?? $item->medicine->generic_name ?? 'N/A' }}</span>
                    </div>
                    <span class="text-xs font-bold text-slate-400 bg-slate-50 px-2 py-1 rounded-full">{{ $item->total_qty }} units</span>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <i class="fas fa-pills text-2xl text-slate-300 mb-2"></i>
                    <p class="text-slate-400 text-sm font-medium">No orders yet</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Inventory Status -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">
                    <i class="fas fa-warehouse text-violet-500 mr-2"></i>Inventory Status
                </h3>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center justify-between bg-slate-50 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Total Medicines</p>
                            <p class="text-xl font-black text-slate-800">{{ $stats['medicines'] }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between bg-amber-50 rounded-xl p-4 border border-amber-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div>
                            <p class="text-xs text-amber-600 font-bold uppercase">Low Stock Items</p>
                            <p class="text-xl font-black text-amber-700">{{ $lowStockCount }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-between bg-rose-50 rounded-xl p-4 border border-rose-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div>
                            <p class="text-xs text-rose-600 font-bold uppercase">Out of Stock</p>
                            <p class="text-xl font-black text-rose-700">{{ $outOfStockCount }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 pb-12">
        <!-- Recent Orders -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 bg-slate-50/50 border-b border-slate-100">
                <h3 class="font-black text-slate-800 text-sm uppercase tracking-tight">
                    <i class="fas fa-stream text-blue-500 mr-2"></i> Recent Order Activity
                </h3>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recentOrders as $order)
                <div class="px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-xs shrink-0
                            {{ $order->status === 'delivered' ? 'bg-emerald-100 text-emerald-600' : ($order->status === 'pending' ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600') }}">
                            @if($order->status === 'delivered')
                                <i class="fas fa-check"></i>
                            @elseif($order->status === 'pending')
                                <i class="fas fa-clock"></i>
                            @else
                                <i class="fas fa-truck"></i>
                            @endif
                        </div>
                        <div>
                            <p class="font-bold text-slate-800 text-sm">Order #{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</p>
                            <p class="text-xs text-slate-500">{{ $order->user->name ?? 'Guest' }} → {{ $order->pharmacy->name ?? 'N/A' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-black text-slate-800 text-sm">₱{{ number_format($order->total_amount, 2) }}</p>
                        <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-full
                            {{ $order->status === 'delivered' ? 'bg-emerald-100 text-emerald-700' : ($order->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700') }}">
                            {{ $order->status }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-inbox text-3xl text-slate-300 mb-3"></i>
                    <p class="text-slate-500 font-medium">No orders placed yet.</p>
                </div>
                @endforelse
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="space-y-4">
            <h3 class="font-black text-slate-800 px-2 tracking-tight text-sm uppercase">Quick Actions</h3>
            
            <a href="{{ route('admin.pharmacies') }}" class="group block bg-white border border-slate-200 p-5 rounded-3xl hover:shadow-lg hover:border-indigo-300 transition-all">
                <div class="flex items-center gap-4">
                    <div class="bg-indigo-600 text-white p-3.5 rounded-2xl shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-store text-lg"></i>
                    </div>
                    <div>
                        <p class="font-black text-slate-800">Manage Pharmacies</p>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">Approve, suspend, or edit</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.users') }}" class="group block bg-white border border-slate-200 p-5 rounded-3xl hover:shadow-lg hover:border-blue-300 transition-all">
                <div class="flex items-center gap-4">
                    <div class="bg-blue-600 text-white p-3.5 rounded-2xl shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-users-cog text-lg"></i>
                    </div>
                    <div>
                        <p class="font-black text-slate-800">Manage Users</p>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">View all system users</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('admin.medicines') }}" class="group block bg-white border border-slate-200 p-5 rounded-3xl hover:shadow-lg hover:border-emerald-300 transition-all">
                <div class="flex items-center gap-4">
                    <div class="bg-emerald-600 text-white p-3.5 rounded-2xl shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-pills text-lg"></i>
                    </div>
                    <div>
                        <p class="font-black text-slate-800">Medicine Database</p>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">{{ $stats['medicines'] }} registered items</p>
                    </div>
                </div>
            </a>

            <a href="{{ url('/') }}" class="group block bg-white border border-slate-200 p-5 rounded-3xl hover:shadow-lg hover:border-violet-300 transition-all">
                <div class="flex items-center gap-4">
                    <div class="bg-violet-600 text-white p-3.5 rounded-2xl shadow-lg group-hover:scale-110 transition-transform">
                        <i class="fas fa-map-marked-alt text-lg"></i>
                    </div>
                    <div>
                        <p class="font-black text-slate-800">View Public Map</p>
                        <p class="text-xs text-slate-400 font-medium mt-0.5">User-facing pharmacy locator</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Platform Growth Line Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthLabels = {!! json_encode(array_column($monthlyGrowth, 'month')) !!};
        const growthUsers = {!! json_encode(array_column($monthlyGrowth, 'users')) !!};
        const growthOrders = {!! json_encode(array_column($monthlyGrowth, 'orders')) !!};

        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: growthLabels,
                datasets: [
                    {
                        label: 'New Users',
                        data: growthUsers,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.08)',
                        borderWidth: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#4f46e5',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Orders',
                        data: growthOrders,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.08)',
                        borderWidth: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#059669',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: 'Inter', weight: '600', size: 12 }, usePointStyle: true, padding: 20 }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleFont: { family: 'Inter', weight: 'bold' },
                        bodyFont: { family: 'Inter', weight: 'bold' },
                        padding: 12,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { family: 'Inter', weight: '600' }, color: '#64748b' } },
                    y: { border: { display: false }, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Inter', weight: '600' }, color: '#64748b' }, beginAtZero: true }
                }
            }
        });

        // User Role Doughnut Chart
        const roleCtx = document.getElementById('roleChart').getContext('2d');
        const roleLabels = {!! json_encode(array_keys($roleCounts)) !!};
        const roleData = {!! json_encode(array_values($roleCounts)) !!};

        new Chart(roleCtx, {
            type: 'doughnut',
            data: {
                labels: roleLabels,
                datasets: [{
                    data: roleData,
                    backgroundColor: ['#3b82f6', '#8b5cf6', '#6366f1', '#a78bfa', '#10b981', '#f59e0b'],
                    borderWidth: 0,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'Inter', weight: '600', size: 11 }, usePointStyle: true, padding: 12 }
                    }
                }
            }
        });
    </script>
@endsection
