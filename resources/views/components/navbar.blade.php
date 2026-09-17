<nav class="bg-geo-600 text-white px-6 py-4 shadow-lg sticky top-0 z-40">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <!-- Logo / Brand -->
        <a href="{{ url('/') }}" class="flex items-center gap-3 hover:opacity-90 transition-opacity">
            <div class="bg-white/20 p-2 rounded-lg backdrop-blur-sm">
                <i class="fas fa-prescription-bottle-alt text-lg"></i>
            </div>
            <div>
                <h1 class="text-xl font-black tracking-tight">MedLocator</h1>
                @auth
                    <p class="text-geo-100 text-[10px] font-bold uppercase tracking-widest">
                        @if(auth()->user()->isAdministrator()) Admin Panel
                        @elseif(auth()->user()->isPharmacyOwner() || auth()->user()->isPharmacist()) Pharmacy Portal
                        @elseif(auth()->user()->isDeliveryPartner()) Delivery Portal
                        @else Patient Portal
                        @endif
                    </p>
                @endauth
            </div>
        </a>

        <!-- Desktop Menu -->
        <div class="hidden lg:flex items-center gap-6">
            @guest
                <a href="{{ url('/') }}" class="font-semibold text-white/90 hover:text-white transition">Home</a>
                <a href="{{ route('login') }}" class="font-bold text-white hover:text-geo-400 transition">Login</a>
                <a href="{{ route('register') }}" class="bg-white text-geo-600 px-5 py-2 rounded-xl font-bold hover:bg-geo-100 shadow-sm transition transform hover:-translate-y-0.5">Register</a>
            @else
                <!-- Role-Specific Links -->
                @if(auth()->user()->isAdministrator())
                    <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Dashboard</a>
                    <a href="{{ route('admin.pharmacies') }}" class="{{ request()->routeIs('admin.pharmacies*') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Pharmacies</a>
                    <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Users</a>
                    <a href="{{ route('admin.medicines') }}" class="{{ request()->routeIs('admin.medicines*') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Medicines</a>
                
                @elseif(auth()->user()->isPharmacyOwner() || auth()->user()->isPharmacist() || auth()->user()->isPharmacyStaff())
                    <a href="{{ route('pharmacy.dashboard') }}" class="{{ request()->routeIs('portal.dashboard') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Dashboard</a>
                    <a href="{{ route('pharmacy.inventory') }}" class="{{ request()->routeIs('portal.inventory') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Inventory</a>
                    <a href="{{ route('pharmacy.orders') }}" class="{{ request()->routeIs('portal.orders') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Orders</a>
                    <a href="{{ route('pharmacy.messages.index') }}" class="{{ request()->routeIs('portal.messages.*') ? 'font-bold text-white relative' : 'font-medium text-white/80 hover:text-white relative' }}">
                        Messages
                        @php $unread = \App\Models\Conversation::where('pharmacy_id', auth()->user()->pharmacy_id)->get()->sum(fn($c) => $c->unreadCountFor(auth()->id())); @endphp
                        @if($unread > 0) <span class="absolute -top-2 -right-3 bg-red-500 text-white text-[10px] px-1.5 rounded-full font-bold">{{ $unread }}</span> @endif
                    </a>
                
                @elseif(auth()->user()->isDeliveryPartner())
                    <a href="{{ route('delivery.dashboard') }}" class="font-bold text-white">Deliveries</a>
                
                @else
                    <!-- Customer -->
                    <a href="{{ url('/') }}" class="font-medium text-white/80 hover:text-white">Map Search</a>
                    <a href="{{ route('user.dashboard') }}" class="{{ request()->routeIs('user.dashboard') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Profile</a>
                    <a href="{{ route('user.orders') }}" class="{{ request()->routeIs('user.orders') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">My Orders</a>
                    <a href="{{ route('user.restock-alerts') }}" class="{{ request()->routeIs('user.restock-alerts') ? 'font-bold text-white' : 'font-medium text-white/80 hover:text-white' }}">Restock Alerts</a>
                    <a href="{{ route('cart.index') }}" class="relative font-medium text-white/80 hover:text-white">
                        Cart
                        @php $cart = \App\Models\CartItem::where('user_id', auth()->id())->sum('quantity'); @endphp
                        @if($cart > 0) <span class="absolute -top-2 -right-3 bg-red-500 text-white text-[10px] px-1.5 rounded-full font-bold">{{ $cart }}</span> @endif
                    </a>
                    <a href="{{ route('messages.index') }}" class="relative font-medium text-white/80 hover:text-white">
                        Messages
                        @php $unread = \App\Models\Conversation::where('user_id', auth()->id())->get()->sum(fn($c) => $c->unreadCountFor(auth()->id())); @endphp
                        @if($unread > 0) <span class="absolute -top-2 -right-3 bg-red-500 text-white text-[10px] px-1.5 rounded-full font-bold">{{ $unread }}</span> @endif
                    </a>
                @endif
            @endguest

            @auth
                <!-- User Profile & Logout -->
                <div class="flex items-center gap-4 ml-4 pl-4 border-l border-white/20">
                    <div class="flex items-center gap-2 bg-black/10 px-3 py-1.5 rounded-xl border border-white/10">
                        <i class="fas fa-user-circle text-white/80"></i>
                        <span class="text-sm font-bold">{{ auth()->user()->name }}</span>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="bg-black/20 hover:bg-black/40 px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </button>
                    </form>
                </div>
            @endauth
        </div>

        <!-- Mobile Menu Button -->
        <div class="lg:hidden flex items-center">
            <button id="mobileMenuBtn" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')" class="text-white p-2">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
    </div>
</nav>

<!-- Mobile Menu Dropdown -->
<div id="mobileMenu" class="hidden lg:hidden bg-geo-900 text-white absolute w-full z-30 shadow-xl border-b border-white/10">
    <div class="px-4 pt-2 pb-6 space-y-1">
        @guest
            <a href="{{ url('/') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Home</a>
            <a href="{{ route('login') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Login</a>
            <a href="{{ route('register') }}" class="block px-3 py-3 rounded-lg font-medium bg-geo-500 text-geo-900 mt-2">Register</a>
        @else
            <div class="px-3 py-3 mb-2 border-b border-white/10 flex items-center gap-3">
                <i class="fas fa-user-circle text-2xl text-geo-500"></i>
                <div>
                    <div class="font-bold">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-white/60">{{ auth()->user()->email }}</div>
                </div>
            </div>

            @if(auth()->user()->isAdministrator())
                <a href="{{ route('admin.dashboard') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Dashboard</a>
                <a href="{{ route('admin.pharmacies') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Pharmacies</a>
                <a href="{{ route('admin.users') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Users</a>
            @elseif(auth()->user()->isPharmacyOwner() || auth()->user()->isPharmacist() || auth()->user()->isPharmacyStaff())
                <a href="{{ route('pharmacy.dashboard') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Dashboard</a>
                <a href="{{ route('pharmacy.inventory') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Inventory</a>
                <a href="{{ route('pharmacy.orders') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Orders</a>
                <a href="{{ route('pharmacy.messages.index') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Messages</a>
            @elseif(auth()->user()->isDeliveryPartner())
                <a href="{{ route('delivery.dashboard') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Deliveries</a>
            @else
                <a href="{{ url('/') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Map Search</a>
                <a href="{{ route('user.dashboard') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">My Profile</a>
                <a href="{{ route('user.orders') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">My Orders</a>
                <a href="{{ route('cart.index') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Cart</a>
                <a href="{{ route('messages.index') }}" class="block px-3 py-3 rounded-lg font-medium hover:bg-white/10">Messages</a>
            @endif

            <form action="{{ route('logout') }}" method="POST" class="mt-4 px-3">
                @csrf
                <button type="submit" class="w-full text-left py-3 font-bold text-red-400 hover:text-red-300">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </button>
            </form>
        @endauth
    </div>
</div>
