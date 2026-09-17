@extends('pharmacy.layouts.app')
@section('title', 'Audit Logs')

@section('content')
<div class="mb-8">
    <h2 class="text-3xl font-black text-slate-800 tracking-tight">Audit Logs</h2>
    <p class="text-slate-500 mt-2 font-medium">Track all system actions and inventory modifications.</p>
</div>

<div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-600">
            <thead class="bg-slate-50 border-b border-slate-200 text-xs uppercase font-black text-slate-500 tracking-wider">
                <tr>
                    <th scope="col" class="px-6 py-4">Date & Time</th>
                    <th scope="col" class="px-6 py-4">User</th>
                    <th scope="col" class="px-6 py-4">Action</th>
                    <th scope="col" class="px-6 py-4">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($logs as $log)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap font-medium text-slate-800">
                        {{ $log->created_at->format('M d, Y h:i A') }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs">
                                {{ substr($log->user->name, 0, 1) }}
                            </div>
                            <span class="font-bold text-slate-700">{{ $log->user->name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold uppercase tracking-wider">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-slate-600">{{ $log->details }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-slate-50 mb-4">
                            <i class="fas fa-clipboard-list text-2xl text-slate-300"></i>
                        </div>
                        <p class="text-slate-500 font-medium">No audit logs recorded yet.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($logs->hasPages())
    <div class="px-6 py-4 border-t border-slate-200 bg-slate-50/50">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection
