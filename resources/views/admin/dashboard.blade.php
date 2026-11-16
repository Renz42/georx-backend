@extends('admin.layouts.app')

@section('title', 'Super Admin Dashboard')

@section('content')
@php
    $adminName = auth()->user()->name ?? 'Super Admin';
    $todayFormatted = now()->format('F d, Y');

    $revenueValue = (float) ($platformRevenue ?? $completedOrderValue ?? 0);
    $deliveredCount = (int) ($deliveredOrders ?? 0);
    $activeOrderCount = (int) ($pendingOrders ?? 0);
    $totalOrderCount = (int) ($totalOrders ?? 0);
    $cancelledOrderCount = (int) ($rejectedOrCancelledOrders ?? 0);
    $medicineGmvValue = (float) ($medicineSalesGmv ?? 0);
    $courierFeesValue = (float) ($estimatedDeliveryFees ?? 0);

    $registeredUsersCount = (int) ($stats['users'] ?? 0);
    $verifiedUsersCount = (int) ($stats['verified_users'] ?? 0);
    $activeUsersCount = (int) ($stats['active_users'] ?? 0);

    $totalPharmaciesCount = (int) ($stats['pharmacies'] ?? 0);
    $activePharmaciesCount = (int) ($stats['active_pharmacies'] ?? 0);
    $pendingPharmaciesCount = (int) ($stats['pending_pharmacies'] ?? 0);

    $strictRoleCounts = [
        'Customers' => (int) ($roleCounts['Customers'] ?? 0),
        'Pharmacy Admin' => (int) ($roleCounts['Pharmacy Admin'] ?? 0),
        'Administrator' => (int) ($roleCounts['Administrator'] ?? 0),
    ];
    $roleTotal = array_sum($strictRoleCounts);
    $roleColors = [
        'Customers' => '#2F7E6A',
        'Pharmacy Admin' => '#4F46E5',
        'Administrator' => '#0F172A',
    ];

    $growthSeries = collect($monthlyGrowth ?? [])->values();
    $growthLabels = $growthSeries->pluck('month')->values()->all();
    $growthUsers = $growthSeries->pluck('users')->map(fn ($v) => (int) $v)->values()->all();
    $growthOrders = $growthSeries->pluck('orders')->map(fn ($v) => (int) $v)->values()->all();
    $growthRangeLabel = $growthSeries->count() >= 2
        ? ($growthLabels[0] . ' – ' . $growthLabels[count($growthLabels) - 1])
        : ($growthLabels[0] ?? 'Last 6 Months');

    $firstNonEmpty = static function (array $candidates, string $fallback): string {
        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return $fallback;
    };

    $readField = static fn ($source, string $key) => is_object($source)
        ? ($source->{$key} ?? null)
        : (is_array($source) ? ($source[$key] ?? null) : null);

    $normalizedSearched = [];
    foreach (collect($mostSearched ?? [])->take(8) as $key => $entry) {
        if (is_numeric($entry) && is_string($key)) {
            $normalizedSearched[] = ['term' => $key, 'count' => (int) $entry];
        } elseif (is_array($entry) || is_object($entry)) {
            $normalizedSearched[] = [
                'term' => $firstNonEmpty([
                    $readField($entry, 'term'),
                    $readField($entry, 'query'),
                    $readField($entry, 'search_query'),
                    $readField($entry, 'keyword'),
                ], 'Unknown'),
                'count' => (int) ($readField($entry, 'count') ?? $readField($entry, 'total') ?? $readField($entry, 'searches') ?? $readField($entry, 'search_count') ?? 0),
            ];
        }
    }

    $normalizedOrdered = [];
    foreach (collect($mostOrdered ?? [])->take(8) as $key => $entry) {
        if (is_numeric($entry) && is_string($key)) {
            $normalizedOrdered[] = ['name' => $key, 'units' => (int) $entry];
        } elseif (is_array($entry) || is_object($entry)) {
            $med = $readField($entry, 'medicine');
            $normalizedOrdered[] = [
                'name' => $firstNonEmpty([
                    $readField($med, 'brand_name'),
                    $readField($med, 'generic_name'),
                    $readField($entry, 'name'),
                    $readField($entry, 'medicine_name'),
                    $readField($entry, 'brand_name'),
                    $readField($entry, 'generic_name'),
                ], 'Medicine'),
                'units' => (int) ($readField($entry, 'units') ?? $readField($entry, 'total_quantity') ?? $readField($entry, 'total_qty') ?? $readField($entry, 'quantity') ?? $readField($entry, 'total_sold') ?? $readField($entry, 'count') ?? 0),
            ];
        }
    }

    $recentOrdersList = collect($recentOrders ?? [])->take(8);

    $dashboardChartPayload = [
        'growth' => [
            'labels' => $growthLabels,
            'users' => $growthUsers,
            'orders' => $growthOrders,
        ],
        'roles' => [
            'labels' => array_keys($strictRoleCounts),
            'counts' => array_values($strictRoleCounts),
            'colors' => array_values($roleColors),
            'total' => $roleTotal,
        ],
    ];
@endphp

<div class="space-y-6">
    {{-- Top Executive Header Card --}}
    <section class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold tracking-tight text-[#0F172A]">Super Admin Dashboard</h1>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#E9F7F2] text-[#2F7E6A] text-xs font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2F7E6A] animate-pulse"></span>
                        Platform Live
                    </span>
                </div>
                <p class="mt-1 text-sm text-[#64748B]">
                    Welcome back, <span class="font-semibold text-[#0F172A]">{{ $adminName }}</span>. Here is your platform overview.
                </p>
            </div>

            <div class="inline-flex items-center gap-2 self-start sm:self-auto px-3.5 py-2 rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] text-xs font-medium text-[#0F172A] shrink-0">
                <svg class="w-4 h-4 text-[#2F7E6A]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>{{ $todayFormatted }}</span>
            </div>
        </div>
    </section>

    {{-- Row 1: Primary KPI Cards (3 Equal Columns) --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Card 1: Total Platform Revenue --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Total Platform Revenue</span>
                    <div class="w-10 h-10 rounded-xl bg-[#E9F7F2] text-[#2F7E6A] flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-[#0F172A]">
                    ₱{{ number_format($revenueValue, 2) }}
                </p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#E9F7F2] text-[#2F7E6A] text-xs font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#2F7E6A]"></span>
                        {{ number_format($deliveredCount) }} delivered
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200/70 text-xs font-semibold">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        {{ number_format($activeOrderCount) }} active
                    </span>
                </div>
            </div>
            <p class="mt-4 pt-3 border-t border-[#E2E8F0] text-xs text-[#64748B]">
                Medicine GMV: <span class="font-semibold text-[#0F172A]">₱{{ number_format($medicineGmvValue, 2) }}</span>
                <span class="mx-1.5 text-slate-300">·</span>
                Courier Fees: <span class="font-semibold text-[#0F172A]">₱{{ number_format($courierFeesValue, 2) }}</span>
            </p>
        </article>

        {{-- Card 2: Total Orders --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Total Orders</span>
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-[#0F172A]">
                    {{ number_format($totalOrderCount) }}
                </p>
                <p class="mt-2 text-sm text-[#64748B]">Across all pharmacies</p>
            </div>
            <p class="mt-4 pt-3 border-t border-[#E2E8F0] text-xs text-[#64748B]">
                <span class="font-semibold text-[#2F7E6A]">{{ number_format($deliveredCount) }}</span> completed
                <span class="mx-1.5 text-slate-300">·</span>
                <span class="font-semibold text-[#0F172A]">{{ number_format($cancelledOrderCount) }}</span> cancelled / rejected
            </p>
        </article>

        {{-- Card 3: Registered Users --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-start justify-between gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Registered Users</span>
                    <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
                <p class="mt-3 text-3xl font-bold tracking-tight text-[#0F172A]">
                    {{ number_format($registeredUsersCount) }}
                </p>
                <p class="mt-2 text-sm text-[#64748B]">All roles combined</p>
            </div>
            <p class="mt-4 pt-3 border-t border-[#E2E8F0] text-xs text-[#64748B]">
                <span class="font-semibold text-[#2F7E6A]">{{ number_format($verifiedUsersCount) }}</span> verified
                <span class="mx-1.5 text-slate-300">·</span>
                <span class="font-semibold text-[#0F172A]">{{ number_format($activeUsersCount) }}</span> active accounts
            </p>
        </article>
    </section>

    {{-- Row 2: Pharmacy Overview Cards (3 Equal Columns) --}}
    <section class="grid grid-cols-1 md:grid-cols-3 gap-5">
        {{-- Card 1: Total Pharmacies --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 p-5 flex items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Total Pharmacies</span>
                <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A]">
                    {{ number_format($totalPharmaciesCount) }}
                </p>
                <p class="mt-1 text-xs text-[#64748B]">Registered on platform</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
        </article>

        {{-- Card 2: Active Pharmacies --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 p-5 flex items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Active Pharmacies</span>
                <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A]">
                    {{ number_format($activePharmaciesCount) }}
                </p>
                <p class="mt-1 text-xs text-[#64748B]">Visible on public map</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-[#E9F7F2] text-[#2F7E6A] flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </article>

        {{-- Card 3: Pending Review --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm hover:shadow-md transition-all duration-200 p-5 flex items-center justify-between gap-4">
            <div>
                <span class="text-xs font-semibold uppercase tracking-wider text-[#64748B]">Pending Review</span>
                <p class="mt-2 text-2xl font-bold tracking-tight text-[#0F172A]">
                    {{ number_format($pendingPharmaciesCount) }}
                </p>
                <a href="{{ route('admin.pharmacies', ['status' => 'pending']) }}"
                   class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-[#2F7E6A] hover:text-[#246353] transition-colors">
                    Review now &rarr;
                </a>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </article>
    </section>

    {{-- Row 3: Interactive Analytics (7 / 5 Split Grid) --}}
    <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {{-- Left: Platform Growth (6 Months) --}}
        <article class="lg:col-span-7 bg-white border border-[#E2E8F0] rounded-2xl shadow-sm p-6 flex flex-col">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-base font-bold text-[#0F172A]">Platform Growth (6 Months)</h2>
                    <p class="text-xs text-[#64748B] mt-0.5">Monthly new user registrations and orders</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="inline-flex items-center gap-4 text-xs font-medium text-[#64748B]">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#4F46E5]"></span>
                            New Users
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#2F7E6A]"></span>
                            Orders
                        </span>
                    </div>
                    <span class="px-2.5 py-1 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] text-[11px] font-medium text-[#64748B]">
                        {{ $growthRangeLabel }}
                    </span>
                </div>
            </div>

            <div class="relative w-full h-[270px] mt-5">
                <canvas id="platformGrowthChart" aria-label="Platform Growth over 6 months"></canvas>
            </div>
        </article>

        {{-- Right: User Role Distribution (Strictly 3 Roles) --}}
        <article class="lg:col-span-5 bg-white border border-[#E2E8F0] rounded-2xl shadow-sm p-6 flex flex-col justify-between">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-base font-bold text-[#0F172A]">User Role Distribution</h2>
                    <p class="text-xs text-[#64748B] mt-0.5">Operational accounts by role</p>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-[#F8FAFC] border border-[#E2E8F0] text-[11px] font-semibold text-[#0F172A]">
                    3 Roles
                </span>
            </div>

            <div class="relative w-full h-[200px] my-4 flex items-center justify-center">
                <canvas id="roleDistributionChart" aria-label="User Role Distribution"></canvas>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                    <span class="text-2xl font-bold tracking-tight text-[#0F172A]">{{ number_format($roleTotal) }}</span>
                    <span class="text-[11px] font-medium text-[#64748B]">Total users</span>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 pt-4 border-t border-[#E2E8F0]">
                @foreach ($strictRoleCounts as $roleLabel => $roleCount)
                    @php
                        $pct = $roleTotal > 0 ? number_format(($roleCount / $roleTotal) * 100, 1) . '%' : '0.0%';
                        $dotColor = $roleColors[$roleLabel] ?? '#2F7E6A';
                    @endphp
                    <div class="rounded-xl bg-[#F8FAFC] border border-[#E2E8F0] p-2.5">
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: {{ $dotColor }};"></span>
                            <span class="text-[11px] font-medium text-[#64748B] truncate" title="{{ $roleLabel }}">{{ $roleLabel }}</span>
                        </div>
                        <div class="mt-1.5 flex items-baseline justify-between gap-1">
                            <span class="text-sm font-bold text-[#0F172A]">{{ number_format($roleCount) }}</span>
                            <span class="text-[11px] font-semibold text-[#64748B]">{{ $pct }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </section>

    {{-- Row 4: Symmetrical 3-Column Activity & Rankings --}}
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        {{-- A. Most Searched --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm p-6 flex flex-col">
            <div class="flex items-center justify-between gap-2 pb-4 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="text-base font-bold text-[#0F172A]">Most Searched</h2>
                    <p class="text-xs text-[#64748B] mt-0.5">Normalized patient search demand</p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#F8FAFC] border border-[#E2E8F0] text-[11px] font-semibold text-[#64748B]">
                    Top 8
                </span>
            </div>

            @if (count($normalizedSearched) > 0)
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($normalizedSearched as $index => $item)
                        <li class="py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-6 h-6 rounded-lg {{ $index < 3 ? 'bg-[#E9F7F2] text-[#2F7E6A]' : 'bg-slate-100 text-[#64748B]' }} text-xs font-bold flex items-center justify-center shrink-0">
                                    {{ $index + 1 }}
                                </span>
                                <span class="text-sm font-medium text-[#0F172A] truncate" title="{{ $item['term'] }}">
                                    {{ $item['term'] }}
                                </span>
                            </div>
                            <span class="px-2 py-0.5 rounded-md bg-[#F8FAFC] border border-[#E2E8F0] text-xs font-semibold text-[#0F172A] shrink-0">
                                {{ number_format($item['count']) }}x
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="py-12 text-center text-sm text-[#64748B]">No search logs recorded yet.</div>
            @endif
        </article>

        {{-- B. Most Ordered --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm p-6 flex flex-col">
            <div class="flex items-center justify-between gap-2 pb-4 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="text-base font-bold text-[#0F172A]">Most Ordered</h2>
                    <p class="text-xs text-[#64748B] mt-0.5">Delivered order volume</p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#E9F7F2] text-[#2F7E6A] text-[11px] font-semibold">
                    Top 8
                </span>
            </div>

            @if (count($normalizedOrdered) > 0)
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($normalizedOrdered as $index => $item)
                        <li class="py-2.5 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-6 h-6 rounded-lg {{ $index < 3 ? 'bg-[#E9F7F2] text-[#2F7E6A]' : 'bg-slate-100 text-[#64748B]' }} text-xs font-bold flex items-center justify-center shrink-0">
                                    {{ $index + 1 }}
                                </span>
                                <span class="text-sm font-medium text-[#0F172A] truncate" title="{{ $item['name'] }}">
                                    {{ $item['name'] }}
                                </span>
                            </div>
                            <span class="px-2 py-0.5 rounded-md bg-[#E9F7F2] text-[#2F7E6A] text-xs font-semibold shrink-0">
                                {{ number_format($item['units']) }} {{ $item['units'] === 1 ? 'unit' : 'units' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="py-12 text-center text-sm text-[#64748B]">No delivered orders recorded yet.</div>
            @endif
        </article>

        {{-- C. Recent Order Activity --}}
        <article class="bg-white border border-[#E2E8F0] rounded-2xl shadow-sm p-6 flex flex-col">
            <div class="flex items-center justify-between gap-2 pb-4 border-b border-[#E2E8F0]">
                <div>
                    <h2 class="text-base font-bold text-[#0F172A]">Recent Order Activity</h2>
                    <p class="text-xs text-[#64748B] mt-0.5">Latest marketplace transactions</p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#F8FAFC] border border-[#E2E8F0] text-[11px] font-semibold text-[#64748B]">
                    Latest {{ $recentOrdersList->count() }}
                </span>
            </div>

            @if ($recentOrdersList->isNotEmpty())
                <ul class="mt-3 divide-y divide-slate-100">
                    @foreach ($recentOrdersList as $order)
                        @php
                            $orderCode = '#' . str_pad((string) ($order->id ?? 0), 5, '0', STR_PAD_LEFT);
                            $customerLabel = $order->user->name ?? 'Customer';
                            $pharmacyLabel = $order->pharmacy->name ?? 'Pharmacy';
                            $orderAmount = (float) ($order->total_amount ?? $order->total ?? 0);
                            $rawStatus = strtoupper((string) ($order->status ?? 'UNKNOWN'));

                            $statusClasses = match ($rawStatus) {
                                'DELIVERED', 'COMPLETED' => 'bg-[#E9F7F2] text-[#2F7E6A]',
                                'PENDING_CONFIRMATION', 'PENDING', 'PREPARING', 'OUT_FOR_DELIVERY', 'READY_FOR_PICKUP' => 'bg-amber-50 text-amber-700',
                                'REJECTED', 'CANCELLED' => 'bg-rose-50 text-rose-700',
                                default => 'bg-slate-100 text-[#64748B]',
                            };
                        @endphp
                        <li class="py-2.5 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-[#0F172A] shrink-0">{{ $orderCode }}</span>
                                    <span class="text-xs text-[#64748B] truncate" title="{{ $customerLabel }} → {{ $pharmacyLabel }}">
                                        {{ $customerLabel }} &rarr; {{ $pharmacyLabel }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs font-bold text-[#0F172A]">₱{{ number_format($orderAmount, 2) }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase {{ $statusClasses }}">
                                    {{ $rawStatus }}
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="py-12 text-center text-sm text-[#64748B]">No recent order activity yet.</div>
            @endif
        </article>
    </section>
</div>

<script id="georxDashboardChartData" type="application/json">@json($dashboardChartPayload)</script>
@endsection

@push('scripts')
<script>
(function () {
    function initDashboardCharts() {
        if (typeof window.Chart === 'undefined') {
            return;
        }

        const dataEl = document.getElementById('georxDashboardChartData');
        if (!dataEl) {
            return;
        }

        let payload;
        try {
            payload = JSON.parse(dataEl.textContent || '{}');
        } catch (err) {
            return;
        }

        const growthCanvas = document.getElementById('platformGrowthChart');
        if (growthCanvas && typeof growthCanvas.getContext === 'function') {
            const growthCtx = growthCanvas.getContext('2d');
            if (growthCtx) {
                const userGradient = growthCtx.createLinearGradient(0, 0, 0, 260);
                userGradient.addColorStop(0, 'rgba(79, 70, 229, 0.18)');
                userGradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

                const orderGradient = growthCtx.createLinearGradient(0, 0, 0, 260);
                orderGradient.addColorStop(0, 'rgba(47, 126, 106, 0.14)');
                orderGradient.addColorStop(1, 'rgba(47, 126, 106, 0.0)');

                new window.Chart(growthCtx, {
                    type: 'line',
                    data: {
                        labels: (payload.growth && payload.growth.labels) ? payload.growth.labels : [],
                        datasets: [
                            {
                                label: 'New Users',
                                data: (payload.growth && payload.growth.users) ? payload.growth.users : [],
                                borderColor: '#4F46E5',
                                backgroundColor: userGradient,
                                borderWidth: 2.5,
                                tension: 0.4,
                                fill: true,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#4F46E5',
                                pointBorderColor: '#FFFFFF',
                                pointBorderWidth: 1.5
                            },
                            {
                                label: 'Orders',
                                data: (payload.growth && payload.growth.orders) ? payload.growth.orders : [],
                                borderColor: '#2F7E6A',
                                backgroundColor: orderGradient,
                                borderWidth: 2.5,
                                tension: 0.4,
                                fill: true,
                                pointRadius: 3,
                                pointHoverRadius: 5,
                                pointBackgroundColor: '#2F7E6A',
                                pointBorderColor: '#FFFFFF',
                                pointBorderWidth: 1.5
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0F172A',
                                titleColor: '#F8FAFC',
                                bodyColor: '#E2E8F0',
                                padding: 10,
                                cornerRadius: 10
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                border: { display: false },
                                ticks: { color: '#64748B', font: { size: 11 } }
                            },
                            y: {
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: '#F1F5F9' },
                                ticks: {
                                    color: '#64748B',
                                    font: { size: 11 },
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }
        }

        const roleCanvas = document.getElementById('roleDistributionChart');
        if (roleCanvas && typeof roleCanvas.getContext === 'function') {
            const roleCtx = roleCanvas.getContext('2d');
            if (roleCtx && payload.roles) {
                const total = Number(payload.roles.total || 0);
                const hasData = total > 0;
                const chartLabels = hasData ? payload.roles.labels : ['No Users'];
                const chartData = hasData ? payload.roles.counts : [1];
                const chartColors = hasData ? payload.roles.colors : ['#E2E8F0'];

                new window.Chart(roleCtx, {
                    type: 'doughnut',
                    data: {
                        labels: chartLabels,
                        datasets: [{
                            data: chartData,
                            backgroundColor: chartColors,
                            borderColor: '#FFFFFF',
                            borderWidth: 3,
                            hoverOffset: hasData ? 4 : 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                enabled: hasData,
                                backgroundColor: '#0F172A',
                                titleColor: '#F8FAFC',
                                bodyColor: '#E2E8F0',
                                padding: 10,
                                cornerRadius: 10,
                                callbacks: {
                                    label: function (ctx) {
                                        const count = Number(ctx.parsed || 0);
                                        const pct = total > 0 ? ((count / total) * 100).toFixed(1) : '0.0';
                                        return ctx.label + ': ' + count + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
    }

    function boot() {
        if (typeof window.Chart !== 'undefined') {
            initDashboardCharts();
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
        script.async = true;
        script.onload = initDashboardCharts;
        document.head.appendChild(script);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
