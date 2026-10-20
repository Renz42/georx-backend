<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Portal — GEORX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
                            200: '#D5F0E5',
                            100: '#E9F7F2',
                            50: '#F5FBF8'
                        },
                        brand: {
                            emerald: '#00897B',
                            emeraldDark: '#00796B',
                            emeraldLight: '#E0F2F1'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        /* Smooth subtle gradient background */
        body {
            background: radial-gradient(circle at 50% 50%, #F8FCFA 0%, #EEF8F4 50%, #E6F5F0 100%);
            min-height: 100vh;
        }

        /* Ambient floating effect for background watermark crosses */
        @media (prefers-reduced-motion: reduce) {
            .ambient-cross {
                animation: none !important;
            }
        }
    </style>
</head>
<body class="relative min-h-screen flex flex-col justify-center items-center p-4 sm:p-6 md:p-8 font-sans text-geo-900 antialiased selection:bg-geo-500 selection:text-white overflow-x-hidden">

    <!-- ================================================================= -->
    <!-- DECORATIVE BACKGROUND MEDICAL CROSSES & CONSTELLATION NETWORK      -->
    <!-- Lightweight inline SVGs, pointer-events: none, behind all content -->
    <!-- ================================================================= -->

    <!-- Left Watermark Cross -->
    <div class="ambient-cross absolute left-[-160px] sm:left-[-120px] md:left-[-60px] lg:left-[-20px] xl:left-[3%] top-1/2 -translate-y-1/2 w-[340px] sm:w-[420px] md:w-[480px] lg:w-[540px] h-[340px] sm:h-[420px] md:h-[480px] lg:h-[540px] pointer-events-none select-none z-0 opacity-40 md:opacity-60 lg:opacity-75" aria-hidden="true">
        <svg viewBox="0 0 400 400" class="w-full h-full" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <clipPath id="leftCrossClip">
                    <rect x="145" y="10" width="110" height="380" rx="28" />
                    <rect x="10" y="145" width="380" height="110" rx="28" />
                </clipPath>
            </defs>
            <!-- Cross Outline & Soft Mint Fill -->
            <rect x="145" y="10" width="110" height="380" rx="28" stroke="#63C6A7" stroke-width="1.75" stroke-opacity="0.35" fill="#E9F7F2" fill-opacity="0.25" />
            <rect x="10" y="145" width="380" height="110" rx="28" stroke="#63C6A7" stroke-width="1.75" stroke-opacity="0.35" fill="#E9F7F2" fill-opacity="0.25" />
            <!-- Constellation Network Mesh clipped inside the cross -->
            <g clip-path="url(#leftCrossClip)" stroke="#2F7E6A" stroke-width="1" stroke-opacity="0.32" fill="#2F7E6A">
                <!-- Primary Nodes & Triangular Connections -->
                <line x1="200" y1="35" x2="165" y2="85" />
                <line x1="200" y1="35" x2="235" y2="85" />
                <line x1="165" y1="85" x2="235" y2="85" />
                <line x1="165" y1="85" x2="200" y2="135" />
                <line x1="235" y1="85" x2="200" y2="135" />
                <line x1="200" y1="135" x2="155" y2="155" />
                <line x1="200" y1="135" x2="245" y2="155" />

                <!-- Left Arm Network -->
                <line x1="35" y1="200" x2="85" y2="165" />
                <line x1="35" y1="200" x2="85" y2="235" />
                <line x1="85" y1="165" x2="85" y2="235" />
                <line x1="85" y1="165" x2="135" y2="200" />
                <line x1="85" y1="235" x2="135" y2="200" />
                <line x1="135" y1="200" x2="155" y2="155" />
                <line x1="135" y1="200" x2="155" y2="245" />

                <!-- Center Intersection Network -->
                <line x1="155" y1="155" x2="245" y2="155" />
                <line x1="155" y1="155" x2="200" y2="200" />
                <line x1="245" y1="155" x2="200" y2="200" />
                <line x1="155" y1="245" x2="200" y2="200" />
                <line x1="245" y1="245" x2="200" y2="200" />
                <line x1="155" y1="245" x2="245" y2="245" />

                <!-- Right Arm Network -->
                <line x1="365" y1="200" x2="315" y2="165" />
                <line x1="365" y1="200" x2="315" y2="235" />
                <line x1="315" y1="165" x2="315" y2="235" />
                <line x1="315" y1="165" x2="265" y2="200" />
                <line x1="315" y1="235" x2="265" y2="200" />
                <line x1="265" y1="200" x2="245" y2="155" />
                <line x1="265" y1="200" x2="245" y2="245" />

                <!-- Bottom Arm Network -->
                <line x1="200" y1="365" x2="165" y2="315" />
                <line x1="200" y1="365" x2="235" y2="315" />
                <line x1="165" y1="315" x2="235" y2="315" />
                <line x1="165" y1="315" x2="200" y2="265" />
                <line x1="235" y1="315" x2="200" y2="265" />
                <line x1="200" y1="265" x2="155" y2="245" />
                <line x1="200" y1="265" x2="245" y2="245" />

                <!-- Constellation Nodes (Points) -->
                <circle cx="200" cy="35" r="3.5" fill-opacity="0.6" />
                <circle cx="165" cy="85" r="3" fill-opacity="0.5" />
                <circle cx="235" cy="85" r="3" fill-opacity="0.5" />
                <circle cx="200" cy="135" r="3.5" fill-opacity="0.6" />
                <circle cx="35" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="85" cy="165" r="3" fill-opacity="0.5" />
                <circle cx="85" cy="235" r="3" fill-opacity="0.5" />
                <circle cx="135" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="155" cy="155" r="3.5" fill-opacity="0.7" />
                <circle cx="245" cy="155" r="3.5" fill-opacity="0.7" />
                <circle cx="200" cy="200" r="4" fill-opacity="0.8" />
                <circle cx="155" cy="245" r="3.5" fill-opacity="0.7" />
                <circle cx="245" cy="245" r="3.5" fill-opacity="0.7" />
                <circle cx="265" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="315" cy="165" r="3" fill-opacity="0.5" />
                <circle cx="315" cy="235" r="3" fill-opacity="0.5" />
                <circle cx="365" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="200" cy="265" r="3.5" fill-opacity="0.6" />
                <circle cx="165" cy="315" r="3" fill-opacity="0.5" />
                <circle cx="235" cy="315" r="3" fill-opacity="0.5" />
                <circle cx="200" cy="365" r="3.5" fill-opacity="0.6" />
            </g>
        </svg>
    </div>

    <!-- Right Watermark Cross -->
    <div class="ambient-cross absolute right-[-160px] sm:right-[-120px] md:right-[-60px] lg:right-[-20px] xl:right-[3%] top-1/2 -translate-y-1/2 w-[340px] sm:w-[420px] md:w-[480px] lg:w-[540px] h-[340px] sm:h-[420px] md:h-[480px] lg:h-[540px] pointer-events-none select-none z-0 opacity-40 md:opacity-60 lg:opacity-75" aria-hidden="true">
        <svg viewBox="0 0 400 400" class="w-full h-full" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <clipPath id="rightCrossClip">
                    <rect x="145" y="10" width="110" height="380" rx="28" />
                    <rect x="10" y="145" width="380" height="110" rx="28" />
                </clipPath>
            </defs>
            <!-- Cross Outline & Soft Mint Fill -->
            <rect x="145" y="10" width="110" height="380" rx="28" stroke="#63C6A7" stroke-width="1.75" stroke-opacity="0.35" fill="#E9F7F2" fill-opacity="0.25" />
            <rect x="10" y="145" width="380" height="110" rx="28" stroke="#63C6A7" stroke-width="1.75" stroke-opacity="0.35" fill="#E9F7F2" fill-opacity="0.25" />
            <!-- Constellation Network Mesh clipped inside the cross -->
            <g clip-path="url(#rightCrossClip)" stroke="#2F7E6A" stroke-width="1" stroke-opacity="0.32" fill="#2F7E6A">
                <!-- Diagonal network connections for visual variety -->
                <line x1="200" y1="35" x2="165" y2="85" />
                <line x1="200" y1="35" x2="235" y2="85" />
                <line x1="165" y1="85" x2="235" y2="85" />
                <line x1="165" y1="85" x2="200" y2="135" />
                <line x1="235" y1="85" x2="200" y2="135" />
                <line x1="200" y1="135" x2="155" y2="155" />
                <line x1="200" y1="135" x2="245" y2="155" />

                <line x1="35" y1="200" x2="85" y2="165" />
                <line x1="35" y1="200" x2="85" y2="235" />
                <line x1="85" y1="165" x2="85" y2="235" />
                <line x1="85" y1="165" x2="135" y2="200" />
                <line x1="85" y1="235" x2="135" y2="200" />
                <line x1="135" y1="200" x2="155" y2="155" />
                <line x1="135" y1="200" x2="155" y2="245" />

                <line x1="155" y1="155" x2="245" y2="155" />
                <line x1="155" y1="155" x2="200" y2="200" />
                <line x1="245" y1="155" x2="200" y2="200" />
                <line x1="155" y1="245" x2="200" y2="200" />
                <line x1="245" y1="245" x2="200" y2="200" />
                <line x1="155" y1="245" x2="245" y2="245" />

                <line x1="365" y1="200" x2="315" y2="165" />
                <line x1="365" y1="200" x2="315" y2="235" />
                <line x1="315" y1="165" x2="315" y2="235" />
                <line x1="315" y1="165" x2="265" y2="200" />
                <line x1="315" y1="235" x2="265" y2="200" />
                <line x1="265" y1="200" x2="245" y2="155" />
                <line x1="265" y1="200" x2="245" y2="245" />

                <line x1="200" y1="365" x2="165" y2="315" />
                <line x1="200" y1="365" x2="235" y2="315" />
                <line x1="165" y1="315" x2="235" y2="315" />
                <line x1="165" y1="315" x2="200" y2="265" />
                <line x1="235" y1="315" x2="200" y2="265" />
                <line x1="200" y1="265" x2="155" y2="245" />
                <line x1="200" y1="265" x2="245" y2="245" />

                <circle cx="200" cy="35" r="3.5" fill-opacity="0.6" />
                <circle cx="165" cy="85" r="3" fill-opacity="0.5" />
                <circle cx="235" cy="85" r="3" fill-opacity="0.5" />
                <circle cx="200" cy="135" r="3.5" fill-opacity="0.6" />
                <circle cx="35" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="85" cy="165" r="3" fill-opacity="0.5" />
                <circle cx="85" cy="235" r="3" fill-opacity="0.5" />
                <circle cx="135" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="155" cy="155" r="3.5" fill-opacity="0.7" />
                <circle cx="245" cy="155" r="3.5" fill-opacity="0.7" />
                <circle cx="200" cy="200" r="4" fill-opacity="0.8" />
                <circle cx="155" cy="245" r="3.5" fill-opacity="0.7" />
                <circle cx="245" cy="245" r="3.5" fill-opacity="0.7" />
                <circle cx="265" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="315" cy="165" r="3" fill-opacity="0.5" />
                <circle cx="315" cy="235" r="3" fill-opacity="0.5" />
                <circle cx="365" cy="200" r="3.5" fill-opacity="0.6" />
                <circle cx="200" cy="265" r="3.5" fill-opacity="0.6" />
                <circle cx="165" cy="315" r="3" fill-opacity="0.5" />
                <circle cx="235" cy="315" r="3" fill-opacity="0.5" />
                <circle cx="200" cy="365" r="3.5" fill-opacity="0.6" />
            </g>
        </svg>
    </div>

    <!-- ================================================================= -->
    <!-- MAIN CONTENT CARD CONTAINER                                       -->
    <!-- ================================================================= -->
    <main class="relative z-10 max-w-[430px] w-full my-auto">
        
        <!-- Main Login Card -->
        <div class="bg-white rounded-2xl sm:rounded-3xl p-7 sm:p-9 md:p-10 shadow-[0_20px_50px_rgba(15,23,42,0.07)] border border-slate-100 text-center transition-all">
            
            <!-- Compact Brand Header: Rounded Emerald Medical Cross + "GeoRx" Typography -->
            <div class="flex items-center justify-center gap-3 mb-5">
                <svg class="w-9 h-9 text-[#00897B] shrink-0" viewBox="0 0 36 36" fill="currentColor" aria-hidden="true">
                    <path d="M14 4a3 3 0 0 1 6 0v8h8a3 3 0 0 1 0 6h-8v8a3 3 0 0 1-6 0v-8H6a3 3 0 0 1 0-6h8V4z" />
                </svg>
                <span class="text-3xl font-extrabold tracking-tight text-[#0F172A]">GeoRx</span>
            </div>

            <!-- Title & Subtitle -->
            <h1 class="text-xl sm:text-2xl font-black text-[#0F172A] tracking-wider uppercase mb-1">
                Pharmacy Portal
            </h1>
            <p class="text-slate-500 text-xs sm:text-sm font-normal mb-6">
                Manage your pharmacy and orders.
            </p>

            <!-- Session Error Alert Banner -->
            @if(session('error'))
                <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5 text-left" role="alert">
                    <i class="fas fa-circle-exclamation mt-0.5 text-red-500 text-base shrink-0" aria-hidden="true"></i>
                    <span class="font-semibold leading-relaxed">{{ session('error') }}</span>
                </div>
            @endif

            <!-- Session Success Alert Banner (e.g. from registration redirect) -->
            @if(session('success'))
                <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5 text-left" role="alert">
                    <i class="fas fa-circle-check mt-0.5 text-emerald-600 text-base shrink-0" aria-hidden="true"></i>
                    <span class="font-semibold leading-relaxed">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('status'))
                <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5 text-left" role="alert">
                    <i class="fas fa-circle-info mt-0.5 text-emerald-600 text-base shrink-0" aria-hidden="true"></i>
                    <span class="font-semibold leading-relaxed">{{ session('status') }}</span>
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('pharmacy.login') }}" class="space-y-4 text-left">
                @csrf

                <!-- Pharmacy Email Input -->
                <div>
                    <label for="pharmacyEmail" class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1.5">
                        Pharmacy Email
                    </label>
                    <input type="email" id="pharmacyEmail" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                        class="w-full px-4 py-3 bg-[#F8FAFC] focus:bg-white border border-slate-200 rounded-xl text-slate-900 placeholder:text-slate-400 font-medium focus:outline-none focus:ring-2 focus:ring-[#00897B]/30 focus:border-[#00897B] transition-all text-sm shadow-sm"
                        placeholder="store@pharmacy.com">
                    @error('email')
                        <p class="text-red-600 text-xs font-semibold mt-1.5 flex items-center gap-1" role="alert">
                            <i class="fas fa-circle-exclamation text-[10px]" aria-hidden="true"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Password Input with Eye Visibility Toggle -->
                <div>
                    <label for="pharmacyPassword" class="block text-xs sm:text-sm font-semibold text-slate-700 mb-1.5">
                        Password
                    </label>
                    <div class="relative">
                        <input type="password" id="pharmacyPassword" name="password" required autocomplete="current-password"
                            class="w-full pl-4 pr-11 py-3 bg-[#F8FAFC] focus:bg-white border border-slate-200 rounded-xl text-slate-900 placeholder:text-slate-400 font-medium focus:outline-none focus:ring-2 focus:ring-[#00897B]/30 focus:border-[#00897B] transition-all text-sm shadow-sm"
                            placeholder="••••••••">
                        <button type="button" id="togglePasswordBtn" aria-label="Toggle password visibility" aria-pressed="false"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 focus:outline-none focus:text-[#00897B] p-1.5 transition-colors">
                            <i id="passwordEyeIcon" class="fas fa-eye-slash text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                    @error('password')
                        <p class="text-red-600 text-xs font-semibold mt-1.5 flex items-center gap-1" role="alert">
                            <i class="fas fa-circle-exclamation text-[10px]" aria-hidden="true"></i> {{ $message }}
                        </p>
                    @enderror
                </div>

                <!-- Action Button -->
                <div class="pt-2">
                    <button type="submit" 
                        class="w-full bg-[#00897B] hover:bg-[#00796B] text-white font-bold py-3.5 px-4 rounded-xl shadow-[0_4px_14px_rgba(0,137,123,0.35)] hover:shadow-[0_6px_20px_rgba(0,137,123,0.45)] focus:outline-none focus:ring-4 focus:ring-[#00897B]/30 transition-all active:scale-[0.99] text-sm tracking-wide">
                        Sign In
                    </button>
                </div>

                <!-- Secondary Contextual Action Link -->
                <div class="text-center pt-2">
                    <p class="text-xs sm:text-sm text-slate-600 font-medium">
                        New pharmacy? 
                        <a href="{{ route('pharmacy.register') }}" class="font-bold text-[#00897B] hover:text-[#00796B] hover:underline transition-colors">
                            Register here
                        </a>
                    </p>
                </div>
            </form>

            <!-- Supporting Information & Trust Micro-Badge -->
            <div class="mt-6 pt-5 border-t border-slate-100 flex flex-col items-center gap-2.5">
                <!-- Forgot Password Destination -->
                <a href="mailto:support@georx.com?subject=GEORX%20Pharmacy%20Portal%20Password%20Reset%20Request" 
                   class="text-xs font-medium text-slate-500 hover:text-[#00897B] transition-colors">
                    Forgot password?
                </a>

                <!-- Truthful, Neutral Security Statement (No unverified claims) -->
                <div class="inline-flex items-center gap-1.5 text-[11px] sm:text-xs text-slate-400 font-normal">
                    <i class="fas fa-lock text-[10px] text-slate-400" aria-hidden="true"></i>
                    <span>Secure access to the GEORX Pharmacy Portal</span>
                </div>
            </div>
        </div>

        <!-- Back to Map Search Link -->
        <div class="text-center mt-6">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                <i class="fas fa-arrow-left text-[10px]" aria-hidden="true"></i> Return to Map Search
            </a>
        </div>
    </main>

    <!-- Password Visibility Toggle Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const passwordInput = document.getElementById('pharmacyPassword');
            const eyeIcon = document.getElementById('passwordEyeIcon');

            if (toggleBtn && passwordInput && eyeIcon) {
                toggleBtn.addEventListener('click', function (e) {
                    e.preventDefault(); // Guarantee form is never submitted
                    const isPassword = passwordInput.getAttribute('type') === 'password';
                    passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                    toggleBtn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
                    eyeIcon.classList.toggle('fa-eye', isPassword);
                    eyeIcon.classList.toggle('fa-eye-slash', !isPassword);
                });
            }
        });
    </script>
</body>
</html>
