<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Portal — GEORX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: {
                            900: '#0F172A',
                            800: '#1E293B',
                            700: '#1F2E2C',
                            600: '#2F7E6A',
                            500: '#63C6A7',
                            400: '#BFE8D6',
                            300: '#A0D8C4',
                            100: '#E9F7F2',
                            50: '#F8FAFC'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-geo-100 min-h-screen flex flex-col justify-center items-center p-4 sm:p-6 md:p-8 font-sans text-geo-900 antialiased selection:bg-geo-500 selection:text-white">

    <div class="max-w-[420px] w-full my-auto">
        
        <!-- Main Login Card -->
        <div class="bg-white rounded-[2rem] p-7 sm:p-9 shadow-xl shadow-slate-200/70 border border-slate-200/70 text-center transition-all">
            
            <!-- Brand Header: G+ GEORX -->
            <div class="inline-flex items-center justify-center gap-2 mb-6">
                <div class="w-7 h-7 rounded-full bg-geo-500 flex items-center justify-center text-white shadow-sm shrink-0">
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                </div>
                <span class="text-xl font-black tracking-wider text-geo-900">GEORX</span>
            </div>

            <!-- Soft Mint Squircle Badge with Storefront Icon -->
            <div class="w-20 h-20 rounded-2xl bg-geo-100 flex items-center justify-center mx-auto mb-5 text-geo-600 border border-geo-400/40 shadow-inner">
                <i class="fas fa-store text-2xl text-geo-600"></i>
            </div>

            <!-- Title & Subtitle -->
            <h1 class="text-2xl sm:text-[1.75rem] font-extrabold text-geo-900 tracking-tight uppercase mb-1">
                Pharmacy Portal
            </h1>
            <p class="text-slate-500 text-sm font-medium mb-7">
                Manage your pharmacy and orders.
            </p>

            <!-- Error Banner -->
            @if(session('error'))
                <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5 text-left">
                    <i class="fas fa-circle-exclamation mt-0.5 text-red-500 text-base shrink-0"></i>
                    <span class="font-semibold leading-relaxed">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('pharmacy.login') }}" class="space-y-4 text-left">
                @csrf

                <!-- Pharmacy Email Input -->
                <div>
                    <label for="pharmacyEmail" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1.5">
                        Pharmacy Email
                    </label>
                    <input type="email" id="pharmacyEmail" name="email" value="{{ old('email') }}" required autocomplete="email"
                        class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-900 placeholder:text-slate-400 font-medium focus:outline-none focus:ring-2 focus:ring-geo-500/40 focus:border-geo-600 transition-all text-sm shadow-sm"
                        placeholder="store@pharmacy.com">
                    @error('email')
                        <p class="text-red-600 text-xs font-semibold mt-1.5 flex items-center gap-1">
                            <i class="fas fa-circle-exclamation text-[10px]"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Password Input with Eye Visibility Toggle -->
                <div>
                    <label for="pharmacyPassword" class="block text-xs sm:text-sm font-bold text-slate-700 mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <input type="password" id="pharmacyPassword" name="password" required autocomplete="current-password"
                            class="w-full pl-4 pr-11 py-3 bg-white border border-slate-200 rounded-xl text-slate-900 placeholder:text-slate-400 font-medium focus:outline-none focus:ring-2 focus:ring-geo-500/40 focus:border-geo-600 transition-all text-sm shadow-sm"
                            placeholder="••••••••">
                        <button type="button" id="togglePasswordBtn" aria-label="Toggle password visibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none focus:text-geo-600 p-1.5 transition-colors">
                            <i id="passwordEyeIcon" class="fas fa-eye-slash text-sm"></i>
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-2 space-y-2.5">
                    <!-- Primary Sign In Button -->
                    <button type="submit" 
                        class="w-full bg-geo-600 hover:bg-[#256857] text-white font-bold py-3.5 px-4 rounded-xl shadow-md hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-geo-500/30 transition-all active:scale-[0.99] text-sm tracking-wide">
                        Sign In
                    </button>

                    <!-- Secondary Register Button -->
                    <a href="{{ route('pharmacy.register') }}" 
                        class="w-full block text-center bg-geo-500 hover:bg-[#52b998] text-geo-900 font-bold py-3.5 px-4 rounded-xl shadow-sm hover:shadow transition-all active:scale-[0.99] text-sm tracking-wide">
                        Register
                    </a>
                </div>
            </form>

            <!-- Forgot Password Supporting Link -->
            <div class="mt-6 pt-4 border-t border-slate-100">
                <a href="mailto:support@georx.com?subject=GEORX%20Pharmacy%20Portal%20Password%20Reset%20Request" 
                   class="text-xs font-semibold text-slate-500 hover:text-geo-600 transition-colors">
                    Forgot password?
                </a>
            </div>
        </div>

        <!-- Back to Map Search Link -->
        <div class="text-center mt-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-xs font-bold text-geo-700 hover:text-geo-900 transition-colors">
                <i class="fas fa-arrow-left text-[10px]"></i> Return to Map Search
            </a>
        </div>
    </div>

    <!-- Password Visibility Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('pharmacyPassword');
            const eyeIcon = document.getElementById('passwordEyeIcon');

            if (toggleBtn && passwordInput && eyeIcon) {
                toggleBtn.addEventListener('click', function () {
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    eyeIcon.classList.toggle('fa-eye', isPassword);
                    eyeIcon.classList.toggle('fa-eye-slash', !isPassword);
                });
            }
        });
    </script>
</body>
</html>
