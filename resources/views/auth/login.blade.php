<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Sign In - GEORX</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 100: '#E9F7F2', 50: '#F8FAFC' }
                    },
                    animation: {
                        'blob': 'blob 7s infinite',
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                    },
                    keyframes: {
                        blob: {
                            '0%': { transform: 'translate(0px, 0px) scale(1)' },
                            '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                            '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                            '100%': { transform: 'translate(0px, 0px) scale(1)' },
                        },
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .input-glass {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-geo-50 min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans text-geo-900">

    <!-- Background Orbs -->
    <div class="absolute top-0 -left-4 w-72 h-72 bg-geo-500 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob"></div>
    <div class="absolute top-0 -right-4 w-72 h-72 bg-teal-400 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob animation-delay-2000"></div>
    <div class="absolute -bottom-8 left-20 w-72 h-72 bg-emerald-400 rounded-full mix-blend-multiply filter blur-2xl opacity-30 animate-blob animation-delay-4000"></div>

    <div class="max-w-md w-full relative z-10 animate-fade-in-up">
        
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-geo-500 to-geo-600 text-white rounded-[1.5rem] flex items-center justify-center mx-auto mb-5 shadow-xl shadow-geo-500/30 transform rotate-3 hover:rotate-0 transition-all duration-300">
                <i class="fas fa-user-injured text-3xl"></i>
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight mb-2 text-geo-900">Patient Portal</h1>
            <p class="text-geo-600 font-medium text-base">Sign in to track your medicine reservations.</p>
        </div>

        <div class="glass-panel rounded-3xl shadow-2xl p-8 sm:p-10 relative overflow-hidden">
            <!-- Shimmer effect -->
            <div class="absolute inset-0 bg-gradient-to-br from-white/40 to-transparent pointer-events-none"></div>

            @if(session('error'))
                <div class="bg-red-50/80 backdrop-blur-sm border border-red-200 text-red-600 px-5 py-4 rounded-2xl mb-6 text-sm font-semibold flex items-center gap-3 animate-fade-in-up shadow-sm">
                    <i class="fas fa-exclamation-circle text-red-500 text-lg shrink-0"></i> {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="relative z-10">
                @csrf
                
                <div class="mb-5">
                    <label class="block text-sm font-bold text-geo-800 mb-2">Email Address</label>
                    <div class="relative group">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-geo-400 group-focus-within:text-geo-600 transition-colors duration-300"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required 
                            class="w-full pl-11 pr-4 py-3.5 input-glass border border-white/60 rounded-2xl focus:ring-4 focus:ring-geo-500/20 focus:border-geo-500 outline-none text-geo-900 transition-all font-medium placeholder:text-geo-900/40 hover:bg-white/70 focus:bg-white" 
                            placeholder="your@email.com">
                    </div>
                    @error('email') <p class="text-red-500 text-xs font-bold mt-2 ml-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-8">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-bold text-geo-800">Password</label>
                        <a href="#" class="text-xs font-bold text-geo-600 hover:text-geo-500 transition-colors">Forgot?</a>
                    </div>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-geo-400 group-focus-within:text-geo-600 transition-colors duration-300"></i>
                        <input type="password" name="password" required 
                            class="w-full pl-11 pr-4 py-3.5 input-glass border border-white/60 rounded-2xl focus:ring-4 focus:ring-geo-500/20 focus:border-geo-500 outline-none text-geo-900 transition-all font-medium placeholder:text-geo-900/40 hover:bg-white/70 focus:bg-white" 
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full bg-geo-700 hover:bg-geo-900 text-white font-bold py-4 rounded-2xl shadow-xl shadow-geo-900/20 transition-all flex justify-center items-center gap-2 group active:scale-[0.98]">
                    Sign In 
                    <i class="fas fa-arrow-right text-sm transform group-hover:translate-x-1 transition-transform"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-geo-900/10 text-center relative z-10">
                <p class="text-geo-900/60 text-sm mb-3 font-medium">New to GEORX?</p>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center w-full bg-white/60 hover:bg-white text-geo-700 font-bold py-3.5 rounded-2xl transition-all border border-white shadow-sm hover:shadow-md">
                    Create a free account
                </a>
            </div>
        </div>

        <div class="text-center mt-8 animate-fade-in-up" style="animation-delay: 0.2s;">
            <a href="{{ url('/') }}" class="text-geo-600 hover:text-geo-900 font-bold text-sm transition-colors inline-flex items-center gap-2 group bg-white/50 hover:bg-white px-5 py-2.5 rounded-full backdrop-blur-sm border border-white/50 shadow-sm">
                <i class="fas fa-arrow-left transform group-hover:-translate-x-1 transition-transform"></i> Back to Map Search
            </a>
        </div>
    </div>
</body>
</html>
