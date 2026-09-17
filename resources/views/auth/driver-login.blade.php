<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Portal — GEORX: A Medicine Hub Portal</title>
    <meta name="description" content="GEORX Driver Portal login. Sign in to access your delivery dashboard.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        rider: {
                            950: '#0D0F1A',
                            900: '#111827',
                            800: '#1C2333',
                            700: '#263045',
                            600: '#3B5BDB',
                            500: '#4C6EF5',
                            400: '#748FFC',
                            300: '#A5B4FC',
                            100: '#EEF2FF',
                        }
                    },
                    animation: {
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                        'spin-slow': 'spin 8s linear infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%':   { opacity: '0', transform: 'translateY(24px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)'    },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .glass {
            background: rgba(28, 35, 51, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(75, 110, 245, 0.2);
            box-shadow: 0 32px 64px -16px rgba(0,0,0,0.7), inset 0 1px 0 rgba(255,255,255,0.05);
        }
        .input-field {
            background: rgba(13, 15, 26, 0.7);
            border: 1px solid rgba(75, 110, 245, 0.25);
            transition: all 0.25s ease;
        }
        .input-field:focus {
            background: rgba(13, 15, 26, 0.9);
            border-color: rgba(75, 110, 245, 0.7);
            box-shadow: 0 0 0 3px rgba(75, 110, 245, 0.15);
        }
        .hex-bg {
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%234C6EF5' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="bg-rider-950 min-h-screen flex items-center justify-center p-4 relative overflow-hidden hex-bg text-white">

    {{-- Ambient glow orbs --}}
    <div class="absolute top-[-10%] left-[-5%] w-[500px] h-[500px] bg-rider-600 rounded-full filter blur-[120px] opacity-10 animate-pulse-slow pointer-events-none"></div>
    <div class="absolute bottom-[-10%] right-[-5%] w-[400px] h-[400px] bg-violet-700 rounded-full filter blur-[120px] opacity-10 animate-pulse-slow pointer-events-none" style="animation-delay:2s;"></div>

    <div class="max-w-md w-full relative z-10 animate-fade-in-up">

        {{-- Brand Header --}}
        <div class="text-center mb-8">
            <div class="relative inline-block mb-5">
                <div class="w-20 h-20 bg-gradient-to-br from-rider-600 to-violet-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-rider-600/30 rotate-3 hover:rotate-0 transition-transform duration-300 mx-auto">
                    <i class="fas fa-motorcycle text-3xl text-white"></i>
                </div>
                <div class="absolute -top-1 -right-1 w-6 h-6 bg-emerald-400 rounded-full flex items-center justify-center shadow-lg shadow-emerald-400/30 animate-pulse-slow">
                    <i class="fas fa-circle text-[6px] text-emerald-900"></i>
                </div>
            </div>
            <h1 class="text-3xl font-black tracking-tight text-white mb-1">GEORX <span class="text-rider-400">Rider</span></h1>
            <p class="text-slate-400 text-sm font-medium">Driver Portal — Barangay Alijis, Bacolod City</p>
        </div>

        {{-- Login Card --}}
        <div class="glass rounded-3xl p-8 sm:p-10 relative overflow-hidden">
            {{-- Top gradient line --}}
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-rider-500 to-transparent"></div>

            {{-- Info Badge --}}
            <div class="flex items-start gap-3 bg-rider-600/10 border border-rider-500/20 rounded-2xl px-4 py-3.5 mb-7 text-sm">
                <i class="fas fa-shield-alt text-rider-400 mt-0.5 shrink-0"></i>
                <p class="text-slate-300 leading-relaxed">
                    This portal is for <strong class="text-rider-300">approved GEORX Drivers</strong> only. New riders must register and await admin approval.
                </p>
            </div>

            {{-- Flash Messages --}}
            @if(session('success'))
                <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 px-4 py-3.5 rounded-2xl mb-6 text-sm font-semibold animate-fade-in-up">
                    <i class="fas fa-check-circle text-emerald-400 shrink-0"></i>
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="flex items-center gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-300 px-4 py-3.5 rounded-2xl mb-6 text-sm font-semibold animate-fade-in-up">
                    <i class="fas fa-exclamation-triangle text-rose-400 shrink-0"></i>
                    {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('driver.login') }}" id="driver-login-form">
                @csrf

                {{-- Email --}}
                <div class="mb-5">
                    <label for="driver-email" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">
                        Driver Email
                    </label>
                    <div class="relative group">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors duration-200 text-sm"></i>
                        <input
                            id="driver-email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            placeholder="you@driver.com"
                            class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl outline-none text-white text-sm font-medium placeholder:text-slate-600 focus:ring-0"
                        >
                    </div>
                    @error('email')
                        <p class="text-rose-400 text-xs font-bold mt-2">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="mb-8">
                    <label for="driver-password" class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2.5">
                        Password
                    </label>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors duration-200 text-sm"></i>
                        <input
                            id="driver-password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl outline-none text-white text-sm font-medium placeholder:text-slate-600 focus:ring-0"
                        >
                    </div>
                </div>

                {{-- Submit --}}
                <button
                    type="submit"
                    id="driver-login-btn"
                    class="w-full bg-gradient-to-r from-rider-600 to-violet-600 hover:from-rider-500 hover:to-violet-500 text-white font-black py-4 rounded-2xl shadow-xl shadow-rider-600/30 transition-all duration-200 flex items-center justify-center gap-2.5 active:scale-[0.98] group"
                >
                    <i class="fas fa-sign-in-alt group-hover:translate-x-0.5 transition-transform"></i>
                    Sign In to Driver Portal
                </button>
            </form>

            {{-- Register Link --}}
            <div class="mt-7 pt-6 border-t border-white/5 text-center">
                <p class="text-slate-500 text-sm mb-3">New GEORX driver?</p>
                <a
                    href="{{ route('driver.register') }}"
                    id="driver-register-link"
                    class="inline-flex items-center justify-center w-full border border-rider-500/30 hover:border-rider-400 text-rider-300 hover:text-rider-200 font-bold py-3 rounded-xl transition-all duration-200 text-sm gap-2 hover:bg-rider-600/10"
                >
                    <i class="fas fa-user-plus text-xs"></i>
                    Apply as a Driver
                </a>
            </div>
        </div>

        {{-- Back link --}}
        <div class="text-center mt-6">
            <a href="{{ url('/') }}" class="text-slate-500 hover:text-white text-sm font-medium transition-colors inline-flex items-center gap-2 group">
                <i class="fas fa-arrow-left text-xs group-hover:-translate-x-1 transition-transform"></i>
                Return to Map
            </a>
        </div>

    </div>
</body>
</html>
