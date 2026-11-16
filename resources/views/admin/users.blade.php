@extends('admin.layouts.app')
@section('title', 'User Management')

@section('content')
    <!-- Page Header -->
    <div class="bg-white rounded-2xl p-6 sm:p-8 mb-6 shadow-xs border border-slate-200/80">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <p class="text-[11px] font-extrabold text-[#2F7E6A] uppercase tracking-widest mb-1">Identity &amp; Access Governance</p>
                <h2 class="text-2xl sm:text-3xl font-black text-[#0F172A] tracking-tight">Registered Users</h2>
                <p class="text-[#64748B] text-sm mt-1 font-medium">Audit platform accounts, verify role classifications, and manage account access states safely.</p>
            </div>
            @if(($stats['legacy_drivers_excluded'] ?? 0) > 0)
                <div class="bg-slate-50 px-4 py-2.5 rounded-xl border border-slate-200/80 text-xs text-[#64748B] font-medium">
                    <i class="fas fa-archive text-slate-400 mr-1.5"></i>
                    <strong class="text-[#0F172A]">{{ $stats['legacy_drivers_excluded'] }}</strong> historical driver accounts archived (excluded from operational KPIs)
                </div>
            @endif
        </div>
    </div>

    <!-- User Summary Strip -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Operational Users</p>
            <p class="text-2xl font-black text-[#0F172A] mt-1">{{ $stats['users'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Patients, Pharmacy Staff &amp; Admin</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Verified &amp; Active</p>
            <p class="text-2xl font-black text-[#2F7E6A] mt-1">{{ $stats['verified_users'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Confirmed operational accounts</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-amber-200/80 shadow-xs">
            <p class="text-[10px] font-black text-amber-700 uppercase tracking-widest">Pending Verification</p>
            <p class="text-2xl font-black text-amber-700 mt-1">{{ $stats['pending_verification_users'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Awaiting email OTP verification</p>
        </div>
        <div class="bg-white rounded-2xl p-4 border border-slate-200/80 shadow-xs">
            <p class="text-[10px] font-black text-[#64748B] uppercase tracking-widest">Suspended / Restricted</p>
            <p class="text-2xl font-black text-rose-600 mt-1">{{ $stats['suspended_users'] ?? 0 }}</p>
            <p class="text-[11px] text-[#64748B] mt-0.5">Access currently paused</p>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 p-5 mb-6">
        <form method="GET" action="{{ route('admin.users') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5 items-end">
            <div class="lg:col-span-4">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Search Users</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-[#64748B] text-xs"></i>
                    <input type="text" name="search" value="{{ $search ?? request('search') }}"
                           placeholder="Name, email address, phone, or user ID..."
                           class="w-full pl-9 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                </div>
            </div>

            <div class="lg:col-span-3">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Role Classification</label>
                <select name="role" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="all" {{ ($roleFilter ?? 'all') === 'all' ? 'selected' : '' }}>All Operational Roles ({{ $roleFilterCounts['all'] ?? 0 }})</option>
                    <option value="patient" {{ ($roleFilter ?? '') === 'patient' ? 'selected' : '' }}>Patients / Customers ({{ $roleFilterCounts['patient'] ?? 0 }})</option>
                    <option value="pharmacy_owner" {{ ($roleFilter ?? '') === 'pharmacy_owner' ? 'selected' : '' }}>Pharmacy Owners ({{ $roleFilterCounts['pharmacy_owner'] ?? 0 }})</option>
                    <option value="pharmacist" {{ ($roleFilter ?? '') === 'pharmacist' ? 'selected' : '' }}>Pharmacists ({{ $roleFilterCounts['pharmacist'] ?? 0 }})</option>
                    <option value="pharmacy_staff" {{ ($roleFilter ?? '') === 'pharmacy_staff' ? 'selected' : '' }}>Pharmacy Staff ({{ $roleFilterCounts['pharmacy_staff'] ?? 0 }})</option>
                    <option value="administrator" {{ ($roleFilter ?? '') === 'administrator' ? 'selected' : '' }}>Super Administrators ({{ $roleFilterCounts['administrator'] ?? 0 }})</option>
                    <option value="legacy_driver" {{ ($roleFilter ?? '') === 'legacy_driver' ? 'selected' : '' }}>Historical Drivers — Archived ({{ $roleFilterCounts['legacy_driver'] ?? 0 }})</option>
                </select>
            </div>

            <div class="lg:col-span-3">
                <label class="block text-[10px] font-black text-[#64748B] uppercase tracking-wider mb-1.5">Account Status</label>
                <select name="status" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-[#0F172A] focus:bg-white focus:outline-none focus:border-[#2F7E6A]">
                    <option value="all" {{ ($statusFilter ?? 'all') === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="active" {{ ($statusFilter ?? '') === 'active' ? 'selected' : '' }}>Active / Approved</option>
                    <option value="pending_verification" {{ ($statusFilter ?? '') === 'pending_verification' ? 'selected' : '' }}>Pending Verification</option>
                    <option value="suspended" {{ ($statusFilter ?? '') === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    <option value="rejected" {{ ($statusFilter ?? '') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>

            <div class="lg:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 bg-[#2F7E6A] hover:bg-[#256655] text-white font-bold py-2.5 px-4 rounded-xl text-sm transition-colors">
                    Apply
                </button>
                @if(!empty($search) || ($roleFilter ?? 'all') !== 'all' || ($statusFilter ?? 'all') !== 'all' || ($verificationFilter ?? 'all') !== 'all')
                    <a href="{{ route('admin.users') }}" class="bg-slate-100 hover:bg-slate-200 text-[#64748B] font-bold py-2.5 px-3 rounded-xl text-sm transition-colors flex items-center justify-center" title="Reset Filters">
                        <i class="fas fa-undo text-xs"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Users Directory Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200/80 overflow-hidden">
        <div class="px-6 py-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
            <p class="text-xs font-bold text-[#64748B]">
                @if(method_exists($usersList, 'firstItem'))
                    Showing <span class="text-[#0F172A] font-black">{{ $usersList->firstItem() ?? 0 }}–{{ $usersList->lastItem() ?? 0 }}</span> of <span class="text-[#0F172A] font-black">{{ $usersList->total() }}</span> accounts
                @else
                    Showing <span class="text-[#0F172A] font-black">{{ $usersList->count() }}</span> accounts
                @endif
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200/80 text-[10px] font-black text-[#64748B] uppercase tracking-widest">
                        <th class="px-6 py-3.5">User</th>
                        <th class="px-6 py-3.5">Contact</th>
                        <th class="px-6 py-3.5">Role Classification</th>
                        <th class="px-6 py-3.5">Account Status</th>
                        <th class="px-6 py-3.5">Joined Date</th>
                        <th class="px-6 py-3.5 text-right">Governance Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @php
                        $currentAdminId = (int) (auth('admin')->id() ?? auth()->id());
                    @endphp
                    @forelse($usersList as $sysUser)
                        @php
                            $roleMeta = \App\Http\Controllers\AdminController::classifyUserRole($sysUser->role);
                            $isSelf = (int) $sysUser->id === $currentAdminId;
                            $acctStatus = strtolower((string) ($sysUser->account_status ?? 'active'));

                            $userModalPayload = [
                                'id' => $sysUser->id,
                                'name' => $sysUser->name,
                                'email' => $sysUser->email,
                                'phone' => $sysUser->phone ?: 'Not Provided',
                                'role_label' => $roleMeta['label'],
                                'raw_role' => $sysUser->role,
                                'account_status' => ucfirst(str_replace('_', ' ', $acctStatus)),
                                'pharmacy_name' => $sysUser->pharmacy->name ?? null,
                                'created_at' => $sysUser->created_at ? $sysUser->created_at->format('M d, Y h:i A') : 'N/A',
                            ];
                        @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl bg-[#E9F7F2] text-[#2F7E6A] flex items-center justify-center font-black text-xs shrink-0">
                                        {{ strtoupper(substr($sysUser->name ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-[#0F172A] flex items-center gap-1.5">
                                            <span>{{ $sysUser->name }}</span>
                                            @if($isSelf)
                                                <span class="bg-[#0F172A] text-white text-[9px] font-black px-2 py-0.5 rounded-md uppercase">You</span>
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-[#64748B]">ID #{{ $sysUser->id }} @if($sysUser->pharmacy) &bull; {{ $sysUser->pharmacy->name }} @endif</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <p class="text-xs font-bold text-[#0F172A]">{{ $sysUser->email }}</p>
                                <p class="text-[11px] text-[#64748B]">{{ $sysUser->phone ?: 'No phone listed' }}</p>
                            </td>
                            <td class="px-6 py-4">
                                <span class="border {{ $roleMeta['badge_class'] }} text-[10px] font-black px-2.5 py-1 rounded-lg uppercase tracking-wider inline-flex items-center gap-1.5">
                                    <i class="{{ $roleMeta['icon'] }}"></i> {{ $roleMeta['label'] }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if(in_array($acctStatus, ['active', 'approved']))
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-[#E9F7F2] text-[#2F7E6A] border border-[#63C6A7]/40">
                                        <i class="fas fa-check-circle"></i> {{ ucfirst($acctStatus) }}
                                    </span>
                                @elseif($acctStatus === 'pending_verification')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                                        <i class="fas fa-clock"></i> Pending OTP
                                    </span>
                                @elseif($acctStatus === 'suspended')
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                                        <i class="fas fa-pause-circle"></i> Suspended
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 border border-slate-300">
                                        {{ ucfirst(str_replace('_', ' ', $acctStatus)) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs font-medium text-[#64748B]">
                                {{ $sysUser->created_at ? $sysUser->created_at->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button"
                                            onclick='viewUserModal(@json($userModalPayload))'
                                            class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-[#E9F7F2] text-[#0F172A] hover:text-[#2F7E6A] text-xs font-bold transition-colors"
                                            title="Inspect Profile">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    @if(!$isSelf)
                                        <form action="{{ route('admin.users.status', $sysUser->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            @if($acctStatus === 'suspended')
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit"
                                                        onclick="return confirm('Reactivate account access for {{ addslashes($sysUser->name) }}?');"
                                                        class="px-2.5 py-1.5 rounded-lg bg-[#E9F7F2] hover:bg-[#2F7E6A] text-[#2F7E6A] hover:text-white text-xs font-bold transition-colors"
                                                        title="Reactivate User">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            @else
                                                <input type="hidden" name="action" value="suspend">
                                                <button type="submit"
                                                        onclick="return confirm('Suspend account access for {{ addslashes($sysUser->name) }}? Historical records will be preserved.');"
                                                        class="px-2.5 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-xs font-bold transition-colors"
                                                        title="Suspend User">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            @endif
                                        </form>

                                        <form action="{{ route('admin.users.destroy', $sysUser->id) }}" method="POST"
                                              onsubmit="return confirm('Delete this user profile? Note: Users with historical orders or pharmacy records are protected from deletion.');"
                                              class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-xs font-bold transition-colors" title="Delete Unlinked User">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[11px] font-semibold text-[#64748B] px-2 py-1 bg-slate-50 rounded-lg border border-slate-200" title="Active Super Admin session cannot be self-deleted or self-suspended">
                                            Protected
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-14 text-center text-[#64748B] font-medium">
                                <i class="fas fa-users-slash text-3xl text-slate-300 mb-2 block"></i>
                                No user accounts matched your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($usersList, 'hasPages') && $usersList->hasPages())
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
                {{ $usersList->links() }}
            </div>
        @endif
    </div>

    <!-- User Profile Modal -->
    <div id="viewUserModal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                <h3 class="text-base font-black text-[#0F172A] flex items-center gap-2">
                    <i class="fas fa-id-card text-[#2F7E6A]"></i> Account Profile Inspection
                </h3>
                <button type="button" onclick="closeUserModal()" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-[#64748B] hover:text-[#0F172A] flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex items-center gap-4">
                    <div id="modal-avatar" class="w-14 h-14 rounded-2xl bg-[#E9F7F2] text-[#2F7E6A] flex items-center justify-center font-black text-xl shrink-0">
                        U
                    </div>
                    <div class="min-w-0">
                        <h4 id="modal-name" class="text-lg font-black text-[#0F172A] truncate">User Name</h4>
                        <p id="modal-role" class="text-xs font-bold text-[#2F7E6A]">Role</p>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-4 space-y-3 text-xs">
                    <div class="flex justify-between">
                        <span class="text-[#64748B] font-bold uppercase">User ID</span>
                        <span id="modal-id" class="font-mono font-bold text-[#0F172A]">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#64748B] font-bold uppercase">Email</span>
                        <span id="modal-email" class="font-bold text-[#0F172A]">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#64748B] font-bold uppercase">Phone</span>
                        <span id="modal-phone" class="font-bold text-[#0F172A]">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#64748B] font-bold uppercase">Status</span>
                        <span id="modal-status" class="font-bold text-[#0F172A]">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#64748B] font-bold uppercase">Associated Pharmacy</span>
                        <span id="modal-pharmacy" class="font-bold text-[#0F172A]">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#64748B] font-bold uppercase">Registered On</span>
                        <span id="modal-date" class="font-bold text-[#0F172A]">—</span>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex justify-end">
                <button type="button" onclick="closeUserModal()" class="px-5 py-2 bg-[#0F172A] text-white text-xs font-bold rounded-xl">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function viewUserModal(user) {
            document.getElementById('modal-id').textContent = '#' + user.id;
            document.getElementById('modal-name').textContent = user.name || 'N/A';
            document.getElementById('modal-avatar').textContent = (user.name || 'U').charAt(0).toUpperCase();
            document.getElementById('modal-email').textContent = user.email || 'N/A';
            document.getElementById('modal-phone').textContent = user.phone || 'Not Provided';
            document.getElementById('modal-role').textContent = user.role_label || user.raw_role;
            document.getElementById('modal-status').textContent = user.account_status || 'Active';
            document.getElementById('modal-pharmacy').textContent = user.pharmacy_name || 'None';
            document.getElementById('modal-date').textContent = user.created_at || 'N/A';
            document.getElementById('viewUserModal').classList.remove('hidden');
        }

        function closeUserModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }
    </script>
@endsection
