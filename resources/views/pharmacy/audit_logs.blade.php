@extends('pharmacy.layouts.app')
@section('title', 'Audit Logs')

@section('content')
<div class="mb-8">
    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-1">Security & Traceability</p>
    <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Audit Logs</h2>
    <p class="text-slate-500 mt-1 text-sm font-normal">Track all operational actions, inventory modifications, and security events.</p>
</div>

<div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 border-b border-slate-200/80 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                <tr>
                    <th scope="col" class="px-6 py-4">Date & Time</th>
                    <th scope="col" class="px-6 py-4">User</th>
                    <th scope="col" class="px-6 py-4">Action</th>
                    <th scope="col" class="px-6 py-4">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800 text-xs">
                        {{ $log->created_at->format('M d, Y h:i A') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-full bg-[#E6F4EA] text-[#0D7A5F] border border-emerald-100 flex items-center justify-center font-bold text-xs shrink-0">
                                {{ substr($log->user->name, 0, 1) }}
                            </div>
                            <span class="font-bold text-slate-800 text-xs">{{ $log->user->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-[10px] font-bold uppercase tracking-wider border border-slate-200/60">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-xs text-slate-600 font-medium">
                        {{ $log->details }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center">
                        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-slate-100 mb-3">
                            <i class="fas fa-clipboard-list text-xl text-slate-400"></i>
                        </div>
                        <p class="text-slate-500 text-xs font-medium">No audit logs recorded yet.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($logs->hasPages())
    <div class="px-6 py-4 border-t border-slate-200/80 bg-slate-50/50">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
