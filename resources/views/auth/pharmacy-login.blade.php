<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Portal - GEORX</title>
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
            background: rgba(31, 46, 44, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 198, 167, 0.2);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .input-glass {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(99, 198, 167, 0.2);
        }
    </style>
</head>
<body class="bg-geo-900 min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans text-white">

    <!-- Background Orbs -->
    <div class="absolute top-0 -left-4 w-96 h-96 bg-geo-600 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-blob"></div>
    <div class="absolute bottom-0 -right-4 w-96 h-96 bg-teal-500 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>

    <div class="max-w-md w-full relative z-10 animate-fade-in-up">
        
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-geo-500 to-emerald-400 text-geo-900 rounded-[1.5rem] flex items-center justify-center mx-auto mb-5 shadow-xl shadow-geo-500/20 transform rotate-3 hover:rotate-0 transition-all duration-300">
                <i class="fas fa-store text-3xl"></i>
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight mb-2 text-white">Pharmacy Portal</h1>
            <p class="text-geo-400 font-medium text-base">Manage your GEORX inventory and orders.</p>
        </div>

        <div class="glass-panel rounded-3xl p-8 sm:p-10 relative overflow-hidden">
            <!-- Shimmer effect -->
            <div class="absolute inset-0 bg-gradient-to-br from-white/5 to-transparent pointer-events-none"></div>

            <div class="bg-geo-500/10 border border-geo-500/30 text-geo-100 px-5 py-4 rounded-2xl mb-8 text-sm flex gap-3 leading-relaxed items-start shadow-inner relative z-10">
                <i class="fas fa-shield-alt mt-0.5 text-geo-500 text-lg"></i>
                <p>This secure login is for <strong class="text-geo-500">Pharmacy Owners</strong> & staff only.</p>
            </div>

            @if(session('error'))
                <div class="bg-red-500/20 border border-red-500/30 text-red-200 px-5 py-4 rounded-2xl mb-6 text-sm font-semibold flex items-center gap-3 animate-fade-in-up shadow-sm">
                    <i class="fas fa-exclamation-triangle text-red-400 text-lg shrink-0"></i> {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('pharmacy.login') }}" class="relative z-10">
                @csrf
                
                <div class="mb-5">
                    <label class="block text-sm font-bold text-geo-100 mb-2">Pharmacy Email</label>
                    <div class="relative group">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-geo-500 group-focus-within:text-white transition-colors duration-300"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required 
                            class="w-full pl-11 pr-4 py-3.5 input-glass rounded-2xl focus:ring-2 focus:ring-geo-500/50 focus:border-geo-500 outline-none text-white transition-all font-medium placeholder:text-white/30 hover:bg-white/5 focus:bg-white/10" 
                            placeholder="store@pharmacy.com">
                    </div>
                    @error('email') <p class="text-red-400 text-xs font-bold mt-2 ml-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-8">
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-bold text-geo-100">Password</label>
                    </div>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-geo-500 group-focus-within:text-white transition-colors duration-300"></i>
                        <input type="password" name="password" required 
                            class="w-full pl-11 pr-4 py-3.5 input-glass rounded-2xl focus:ring-2 focus:ring-geo-500/50 focus:border-geo-500 outline-none text-white transition-all font-medium placeholder:text-white/30 hover:bg-white/5 focus:bg-white/10" 
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full bg-geo-500 hover:bg-geo-400 text-geo-900 font-black py-4 rounded-2xl shadow-lg shadow-geo-500/20 transition-all flex justify-center items-center gap-2 group active:scale-[0.98]">
                    Secure Sign In 
                    <i class="fas fa-shield-check text-sm"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/10 text-center relative z-10">
                <p class="text-white/60 text-sm mb-3 font-medium">Own a pharmacy in Bacolod?</p>
                <a href="{{ route('pharmacy.register') }}" class="inline-flex items-center justify-center w-full bg-transparent hover:bg-white/5 text-geo-400 font-bold py-3.5 rounded-2xl transition-all border border-geo-500/40 hover:border-geo-500">
                    Register your business
                </a>
            </div>
        </div>

        <div class="text-center mt-8 animate-fade-in-up" style="animation-delay: 0.2s;">
            <a href="{{ url('/') }}" class="text-geo-400 hover:text-white font-bold text-sm transition-colors inline-flex items-center gap-2 group">
                <i class="fas fa-arrow-left transform group-hover:-translate-x-1 transition-transform"></i> Return to Map
            </a>
        </div>
    </div>
</body>
</html>
