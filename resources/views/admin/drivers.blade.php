@extends('admin.layouts.app')

@section('title', 'Driver Management — GEORX Admin')

@section('content')
<div class="space-y-8">

    {{-- Page Header --}}
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-black text-slate-800">Driver Management</h2>
            <p class="text-slate-500 text-sm mt-1">Review, approve, and manage GEORX delivery drivers.</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200 px-3 py-1.5 rounded-xl">
                {{ $pendingDrivers->count() }} Pending Review
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-5 py-4 rounded-2xl flex items-center gap-3">
            <i class="fas fa-check-circle text-emerald-500"></i>
            <span class="font-bold">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-700 px-5 py-4 rounded-2xl flex items-center gap-3">
            <i class="fas fa-exclamation-circle text-rose-500"></i>
            <span class="font-bold">{{ session('error') }}</span>
        </div>
    @endif

    {{-- ====================== PENDING REVIEW ====================== --}}
    <div>
        <h3 class="text-base font-black text-slate-700 mb-4 flex items-center gap-2">
            <span class="w-2.5 h-2.5 bg-amber-400 rounded-full animate-pulse"></span>
            Pending Approval ({{ $pendingDrivers->count() }})
        </h3>

        @if($pendingDrivers->isEmpty())
            <div class="bg-white border border-slate-200 rounded-2xl py-10 text-center text-slate-400 text-sm">
                <i class="fas fa-inbox text-3xl mb-2 block text-slate-300"></i>
                No pending driver applications.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($pendingDrivers as $driver)
                    <div class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                        <div class="bg-amber-50 px-5 py-3 border-b border-amber-100 flex items-center gap-2">
                            <i class="fas fa-clock text-amber-500 text-xs"></i>
                            <span class="text-xs font-black text-amber-700 uppercase tracking-widest">Pending Review</span>
                            <span class="ml-auto text-xs text-slate-400">{{ $driver->created_at->diffForHumans() }}</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 bg-gradient-to-br from-amber-100 to-amber-200 rounded-xl flex items-center justify-center shrink-0">
                                    <i class="fas fa-motorcycle text-amber-600 text-lg"></i>
                                </div>
                                <div>
                                    <p class="font-black text-slate-800">{{ $driver->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $driver->user->email }}</p>
                                    <p class="text-xs text-slate-500">{{ $driver->user->phone }}</p>
                                </div>
                            </div>
                            <div class="bg-slate-50 rounded-xl p-3 text-sm space-y-1 border border-slate-100">
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400 font-bold uppercase tracking-wide">Vehicle</span>
                                    <span class="text-slate-700 font-bold capitalize">{{ $driver->vehicle_type ?? '—' }}</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400 font-bold uppercase tracking-wide">Make/Model</span>
                                    <span class="text-slate-700 font-bold">{{ trim(($driver->vehicle_make ?? '') . ' ' . ($driver->vehicle_model ?? '')) ?: '—' }}</span>
                                </div>
                                <div class="flex justify-between text-xs">
                                    <span class="text-slate-400 font-bold uppercase tracking-wide">Plate</span>
                                    <span class="text-slate-700 font-bold font-mono">{{ $driver->plate_number ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2 pt-2">
                                <form action="{{ route('admin.drivers.approve', $driver->id) }}" method="POST" class="flex-1">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl text-xs flex items-center justify-center gap-1.5 transition-all active:scale-[0.97]">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form action="{{ route('admin.drivers.reject', $driver->id) }}" method="POST" class="flex-1">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                        onclick="return confirm('Reject this driver application?')"
                                        class="w-full py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 font-black rounded-xl text-xs flex items-center justify-center gap-1.5 transition-all">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- ====================== APPROVED DRIVERS ====================== --}}
    <div>
        <h3 class="text-base font-black text-slate-700 mb-4 flex items-center gap-2">
            <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full"></span>
            Active Drivers ({{ $approvedDrivers->count() }})
        </h3>

        @if($approvedDrivers->isEmpty())
            <div class="bg-white border border-slate-200 rounded-2xl py-10 text-center text-slate-400 text-sm">
                <i class="fas fa-user-slash text-3xl mb-2 block text-slate-300"></i>
                No approved drivers yet.
            </div>
        @else
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Driver</th>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest hidden md:table-cell">Vehicle</th>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($approvedDrivers as $driver)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-800">{{ $driver->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $driver->user->email }}</p>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell">
                                    <p class="font-medium capitalize text-slate-700">{{ $driver->vehicle_type ?? '—' }}</p>
                                    <p class="text-xs text-slate-500 font-mono">{{ $driver->plate_number ?? '—' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-xs font-black {{ $driver->is_online ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-slate-500 bg-slate-100 border-slate-200' }} border px-2.5 py-1 rounded-full">
                                        {{ $driver->is_online ? 'Online' : 'Offline' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <form action="{{ route('admin.drivers.suspend', $driver->id) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                            onclick="return confirm('Suspend {{ $driver->user->name }}?')"
                                            class="text-xs font-bold text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 border border-amber-200 px-3 py-1.5 rounded-xl transition-colors">
                                            <i class="fas fa-ban mr-1"></i> Suspend
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ====================== SUSPENDED / REJECTED ====================== --}}
    @if($suspendedDrivers->isNotEmpty())
        <div>
            <h3 class="text-base font-black text-slate-700 mb-4 flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-rose-400 rounded-full"></span>
                Suspended / Rejected ({{ $suspendedDrivers->count() }})
            </h3>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Driver</th>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest hidden md:table-cell">Reason</th>
                            <th class="text-left px-5 py-3 text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($suspendedDrivers as $driver)
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-slate-800">{{ $driver->user->name }}</p>
                                    <p class="text-xs text-slate-500">{{ $driver->user->email }}</p>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell">
                                    <span class="text-xs font-black {{ $driver->account_status === 'rejected' ? 'text-rose-600 bg-rose-50 border-rose-200' : 'text-amber-600 bg-amber-50 border-amber-200' }} border px-2.5 py-1 rounded-full capitalize">
                                        {{ $driver->account_status }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    {{-- Re-approve suspended driver --}}
                                    @if($driver->account_status === 'suspended')
                                        <form action="{{ route('admin.drivers.approve', $driver->id) }}" method="POST" class="inline">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                class="text-xs font-bold text-emerald-600 hover:text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3 py-1.5 rounded-xl transition-colors">
                                                <i class="fas fa-undo mr-1"></i> Re-Activate
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>
@endsection
