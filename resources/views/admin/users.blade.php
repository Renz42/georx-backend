@extends('admin.layouts.app')

@section('content')
    @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl mb-6 flex items-center shadow-sm">
            <i class="fas fa-check-circle mr-3 text-emerald-500"></i>
            <span class="font-medium">{{ session('success') }}</span>
        </div>
    @endif

    <div class="mb-6">
        <h2 class="text-3xl font-bold text-[#122056] tracking-tight">Registered Users</h2>
        <p class="text-[#122056]/60 mt-1 font-medium">View detailed profiles and manage user access rights</p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-[#EEEFFD] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap">
                <thead class="bg-geo-50 border-b border-[#EEEFFD]">
                    <tr>
                        <th class="px-6 py-4 text-[10px] font-black text-[#122056]/50 uppercase tracking-widest">User Name</th>
                        <th class="px-6 py-4 text-[10px] font-black text-[#122056]/50 uppercase tracking-widest">Contact Info</th>
                        
                        <th class="px-6 py-4 text-[10px] font-black text-[#122056]/50 uppercase tracking-widest">Account Type</th>
                        
                        <th class="px-6 py-4 text-[10px] font-black text-[#122056]/50 uppercase tracking-widest">Joined Date</th>
                        <th class="px-6 py-4 text-[10px] font-black text-[#122056]/50 uppercase tracking-widest text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#EEEFFD]">
                    @if(isset($usersList) && $usersList->count() > 0)
                        @foreach($usersList as $sysUser)
                            <tr class="hover:bg-geo-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-[#5B65DC]/10 flex items-center justify-center text-[#5B65DC] font-black text-sm">
                                            {{ strtoupper(substr($sysUser->name, 0, 1)) }}
                                        </div>
                                        <span class="font-bold text-[#122056]">{{ $sysUser->name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-semibold text-[#122056]">{{ $sysUser->email }}</p>
                                </td>
                                
                                <td class="px-6 py-4">
                                    @if($sysUser->role === 'pharmacy_admin' || $sysUser->role === 'pharmacy_owner')
                                        <span class="bg-blue-50 text-blue-600 border border-blue-200 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest flex items-center gap-1 w-max">
                                            <i class="fas fa-store"></i> Pharmacy Admin
                                        </span>
                                    @elseif($sysUser->role === 'super_admin' || $sysUser->role === 'administrator')
                                        <span class="bg-purple-50 text-purple-600 border border-purple-200 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest flex items-center gap-1 w-max">
                                            <i class="fas fa-shield-alt"></i> Super Admin
                                        </span>
                                    @elseif($sysUser->role === 'driver' || $sysUser->role === 'delivery_partner')
                                        <span class="bg-indigo-50 text-indigo-600 border border-indigo-200 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest flex items-center gap-1 w-max">
                                            <i class="fas fa-motorcycle"></i> Driver
                                        </span>
                                    @else
                                        <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest flex items-center gap-1 w-max">
                                            <i class="fas fa-user"></i> Patient
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-sm font-medium text-[#122056]/70">
                                    {{ $sysUser->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex justify-center gap-2">
                                        <button onclick="viewUserModal({{ json_encode($sysUser) }})" 
                                                class="p-2 text-[#5B65DC] hover:bg-[#5B65DC]/10 rounded-lg transition-colors" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        
                                        <form action="{{ route('admin.users.destroy', $sysUser->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this user profile?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Delete User">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-slate-400 font-medium">
                                No patients registered on the locator network yet.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <div id="viewUserModal" class="hidden fixed inset-0 bg-[#122056]/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-[#EEEFFD] flex justify-between items-center bg-geo-50">
                <h3 class="text-lg font-black text-[#122056] flex items-center gap-2">
                    <i class="fas fa-id-card text-[#5B65DC]"></i> User Profile
                </h3>
                <button onclick="closeUserModal()" class="w-8 h-8 rounded-full bg-white border border-[#EEEFFD] text-[#122056]/40 hover:text-red-500 hover:bg-red-50 transition-colors flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-8 flex flex-col items-center text-center">
                <div id="modal-avatar" class="w-24 h-24 rounded-full bg-[#5B65DC]/10 flex items-center justify-center text-[#5B65DC] font-black text-4xl mb-4 border-4 border-white shadow-md">
                    U
                </div>
                <h4 id="modal-name" class="text-2xl font-black text-[#122056] mb-1">User Name</h4>
                
                <p id="modal-role" class="text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-6 border">Role</p>

                <div class="w-full bg-geo-50 border border-[#EEEFFD] rounded-2xl p-4 text-left space-y-4">
                    <div>
                        <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest mb-1">Email Address</p>
                        <p id="modal-email" class="text-sm font-bold text-[#122056] flex items-center gap-2">
                            <i class="fas fa-envelope text-[#5B65DC]/50"></i> email@example.com
                        </p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-[#122056]/40 uppercase tracking-widest mb-1">Account Created On</p>
                        <p id="modal-date" class="text-sm font-bold text-[#122056] flex items-center gap-2">
                            <i class="fas fa-calendar-alt text-[#5B65DC]/50"></i> Jan 01, 2026
                        </p>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t border-[#EEEFFD] bg-geo-50 flex justify-end">
                <button onclick="closeUserModal()" class="px-6 py-2.5 bg-[#5B65DC] hover:bg-[#4a54c4] text-white font-bold rounded-xl transition-colors shadow-md">
                    Done
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function viewUserModal(user) {
            document.getElementById('modal-name').innerText = user.name;
            document.getElementById('modal-avatar').innerText = user.name.charAt(0).toUpperCase();
            document.getElementById('modal-email').innerHTML = `<i class="fas fa-envelope text-[#5B65DC]/50 mr-2"></i> ${user.email}`;
            
            // Format the Date
            let joinDate = new Date(user.created_at);
            let formattedDate = joinDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            document.getElementById('modal-date').innerHTML = `<i class="fas fa-calendar-alt text-[#5B65DC]/50 mr-2"></i> ${formattedDate}`;
            
            // ✨ NEW: Dynamic Role Logic for Modal ✨
            let roleBadge = document.getElementById('modal-role');
            if (user.role === 'pharmacy_admin' || user.role === 'pharmacy_owner') {
                roleBadge.innerText = 'Pharmacy Admin';
                roleBadge.className = 'text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-6 border bg-blue-50 text-blue-600 border-blue-200';
            } else if (user.role === 'super_admin' || user.role === 'administrator') {
                roleBadge.innerText = 'Super Admin';
                roleBadge.className = 'text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-6 border bg-purple-50 text-purple-600 border-purple-200';
            } else if (user.role === 'driver' || user.role === 'delivery_partner') {
                roleBadge.innerText = 'Driver';
                roleBadge.className = 'text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-6 border bg-indigo-50 text-indigo-600 border-indigo-200';
            } else {
                roleBadge.innerText = 'Patient User';
                roleBadge.className = 'text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-widest mb-6 border bg-emerald-50 text-emerald-600 border-emerald-200';
            }

            document.getElementById('viewUserModal').classList.remove('hidden');
        }

        function closeUserModal() {
            document.getElementById('viewUserModal').classList.add('hidden');
        }
    </script>
@endpush
