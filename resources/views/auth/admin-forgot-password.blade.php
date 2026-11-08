<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Password Recovery — GEORX</title>
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
                            forest: '#0A4D44',
                            dark: '#06332E',
                            primary: '#2F7E6A',
                            mint: '#63C6A7',
                            palemint: '#E9F7F2',
                            maintext: '#0F172A',
                            mutedtext: '#64748B'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #E9F7F2;
            min-height: 100vh;
        }
        @media (prefers-reduced-motion: reduce) {
            * {
                transition-duration: 0.01ms !important;
                animation-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="relative min-h-screen flex flex-col justify-center items-center p-4 sm:p-6 md:p-8 font-sans text-geo-maintext antialiased selection:bg-geo-primary selection:text-white overflow-x-hidden">

    <!-- ================================================================= -->
    <!-- TOPOGRAPHIC CONTOUR MAP VECTOR BACKGROUND                         -->
    <!-- ================================================================= -->
    <div class="absolute inset-0 pointer-events-none select-none overflow-hidden z-0" aria-hidden="true">
        <svg class="w-full h-full object-cover opacity-60" viewBox="0 0 1440 900" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path d="M-80 60 C 120 40, 240 180, 280 340 C 310 460, 240 600, 100 700 C -20 780, -120 740, -180 680" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.55" fill="none"/>
            <path d="M-50 100 C 140 85, 200 200, 240 330 C 270 430, 210 540, 90 620 C -10 690, -90 660, -140 610" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.5" fill="none"/>
            <path d="M-20 140 C 150 130, 170 230, 200 320 C 230 400, 180 490, 80 550 C 0 600, -60 580, -100 540" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.45" fill="none"/>
            <path d="M10 180 C 130 170, 140 250, 160 310 C 180 370, 150 430, 70 470 C 10 510, -30 490, -60 460" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.4" fill="none"/>
            <path d="M40 220 C 110 210, 110 260, 120 300 C 130 340, 110 370, 60 400 C 20 420, -10 410, -20 390" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.35" fill="none"/>
            <path d="M 1520 80 C 1320 60, 1200 190, 1160 350 C 1130 480, 1200 620, 1340 720 C 1460 800, 1560 760, 1620 700" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.55" fill="none"/>
            <path d="M 1490 120 C 1300 100, 1230 220, 1200 340 C 1170 440, 1230 550, 1350 630 C 1450 700, 1530 670, 1580 620" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.5" fill="none"/>
            <path d="M 1460 160 C 1280 140, 1260 250, 1230 330 C 1200 410, 1250 490, 1340 550 C 1420 600, 1480 580, 1520 540" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.45" fill="none"/>
            <path d="M 1430 200 C 1290 180, 1280 270, 1260 320 C 1240 380, 1270 430, 1330 470 C 1390 510, 1430 490, 1460 460" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.4" fill="none"/>
            <path d="M 1400 240 C 1310 220, 1300 280, 1290 310 C 1270 340, 1290 380, 1320 410 C 1360 430, 1390 420, 1410 400" stroke="#63C6A7" stroke-width="1.25" stroke-opacity="0.35" fill="none"/>
            <path d="M -100 820 C 250 850, 480 720, 720 740 C 960 760, 1180 870, 1550 810" stroke="#63C6A7" stroke-width="1" stroke-opacity="0.3" fill="none"/>
            <path d="M -80 860 C 270 890, 500 760, 740 780 C 980 800, 1200 910, 1570 850" stroke="#63C6A7" stroke-width="1" stroke-opacity="0.25" fill="none"/>
            <path d="M -120 70 C 300 40, 600 130, 850 110 C 1100 90, 1350 20, 1560 50" stroke="#63C6A7" stroke-width="1" stroke-opacity="0.25" fill="none"/>
        </svg>
    </div>

    <!-- ================================================================= -->
    <!-- MAIN HORIZONTAL SPLIT CARD CONTAINER                              -->
    <!-- ================================================================= -->
    <main class="relative z-10 max-w-[880px] w-full my-auto flex flex-col items-center">

        <div class="w-full bg-white rounded-[2rem] sm:rounded-[2.25rem] shadow-[0_25px_60px_-15px_rgba(10,77,68,0.20)] border border-slate-200/70 overflow-hidden flex flex-col md:flex-row transition-all">

            <!-- LEFT PANEL: Deep Botanical Teal Gradient Admin Branding -->
            <div class="md:w-5/12 lg:w-5/12 relative flex flex-col items-center justify-center p-8 sm:p-10 md:p-12 text-center text-white overflow-hidden select-none"
                 style="background: linear-gradient(150deg, #0A4D44 0%, #06332E 100%);">

                <div class="absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(99,198,167,0.18)_0%,transparent_70%)] pointer-events-none" aria-hidden="true"></div>

                <div class="relative z-10 flex flex-col items-center my-auto py-2">
                    <div class="w-32 sm:w-36 md:w-40 h-32 sm:h-36 md:h-40 flex items-center justify-center">
                        <img src="{{ asset('images/georx-logo.png') }}"
                             alt="GeoRx - Pharmacy & Healthcare Delivery Platform Logo"
                             class="max-w-full max-h-full object-contain drop-shadow-[0_12px_24px_rgba(0,0,0,0.4)] pointer-events-none"
                             width="160"
                             height="160"
                             draggable="false">
                    </div>

                    <span class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white mt-4 drop-shadow-sm font-sans">
                        GeoRx
                    </span>

                    <div class="mt-3 text-center">
                        <h2 class="text-xs sm:text-sm font-extrabold tracking-[0.2em] text-[#63C6A7] uppercase">
                            System Administration
                        </h2>
                        <p class="text-[11px] sm:text-xs text-[#E9F7F2]/80 mt-0.5 font-normal">
                            Platform management & security.
                        </p>
                    </div>
                </div>
            </div>

            <!-- RIGHT PANEL: Forgot Password Form Panel -->
            <div class="md:w-7/12 lg:w-7/12 bg-white p-7 sm:p-9 md:p-12 flex flex-col justify-center text-left">

                <div class="mb-4">
                    <h1 class="text-2xl sm:text-3xl font-extrabold text-[#0F172A] tracking-tight mb-1">
                        Forgot Password
                    </h1>
                    <p class="text-[#64748B] text-xs sm:text-sm font-normal leading-relaxed">
                        Enter your administrator email address. If an eligible account matches, a six-digit recovery code will be sent.
                    </p>
                </div>

                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-50 border border-slate-200/90 text-[#64748B] text-[11px] font-semibold mb-6 w-fit select-none">
                    <i class="fas fa-shield-halved text-[10px] text-[#2F7E6A]" aria-hidden="true"></i>
                    <span>OTP-Protected Admin Recovery — Valid for 10 minutes</span>
                </div>

                @if(session('error'))
                    <div class="mb-5 p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs sm:text-sm flex items-start gap-2.5" role="alert">
                        <i class="fas fa-circle-exclamation mt-0.5 text-red-500 text-base shrink-0" aria-hidden="true"></i>
                        <span class="font-semibold leading-relaxed">{{ session('error') }}</span>
                    </div>
                @endif

                @if(session('status'))
                    <div class="mb-5 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs sm:text-sm flex items-start gap-2.5" role="alert">
                        <i class="fas fa-circle-info mt-0.5 text-emerald-600 text-base shrink-0" aria-hidden="true"></i>
                        <span class="font-semibold leading-relaxed">{{ session('status') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.password.email') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="adminResetEmail" class="block text-xs sm:text-sm font-bold text-[#0F172A] mb-1.5">
                            Admin Email
                        </label>
                        <input type="email" id="adminResetEmail" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                            class="w-full px-4 py-3.5 bg-white border border-slate-300 rounded-xl text-[#0F172A] placeholder:text-slate-400 font-medium focus:outline-none focus:ring-2 focus:ring-[#2F7E6A]/30 focus:border-[#2F7E6A] transition-all text-sm shadow-sm"
                            placeholder="admin@georx.com">
                        @error('email')
                            <p class="text-red-600 text-xs font-semibold mt-1.5 flex items-center gap-1" role="alert">
                                <i class="fas fa-circle-exclamation text-[10px]" aria-hidden="true"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                            class="w-full bg-[#2F7E6A] hover:bg-[#0A4D44] text-white font-bold py-3.5 px-4 rounded-xl shadow-[0_4px_14px_rgba(47,126,106,0.30)] hover:shadow-[0_6px_20px_rgba(10,77,68,0.40)] focus:outline-none focus:ring-4 focus:ring-[#2F7E6A]/30 transition-all active:scale-[0.99] text-sm tracking-wide">
                            Send Recovery Code
                        </button>
                    </div>

                    <div class="text-center pt-3">
                        <a href="{{ route('admin.login') }}"
                           class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-[#64748B] hover:text-[#2F7E6A] hover:underline transition-colors">
                            <i class="fas fa-arrow-left text-[10px]" aria-hidden="true"></i>
                            <span>Back to Admin Sign In</span>
                        </a>
                    </div>
                </form>

            </div>
        </div>

        <a href="{{ url('/') }}"
           class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-[#64748B] hover:text-[#2F7E6A] transition-colors mt-6 z-10 select-none group">
            <i class="fas fa-arrow-left text-xs transition-transform group-hover:-translate-x-1" aria-hidden="true"></i>
            <span>Return to Map Search</span>
        </a>
    </main>

</body>
</html>

