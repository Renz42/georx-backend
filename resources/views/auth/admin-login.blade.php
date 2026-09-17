<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Portal - GEORX</title>
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
                        'fade-in-up': 'fadeInUp 0.6s ease-out forwards',
                        'grid-move': 'gridMove 20s linear infinite',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%': { opacity: '0', transform: 'translateY(20px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' },
                        },
                        gridMove: {
                            '0%': { transform: 'translateY(0)' },
                            '100%': { transform: 'translateY(32px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 30px 60px -15px rgba(0, 0, 0, 0.7);
        }
        .input-glass {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .bg-grid {
            background-image: linear-gradient(to right, rgba(255,255,255,0.05) 1px, transparent 1px),
                              linear-gradient(to bottom, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="bg-[#050B14] min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans text-white">

    <!-- Grid Background -->
    <div class="absolute inset-0 bg-grid animate-grid-move opacity-50"></div>
    
    <!-- Central Glow -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-geo-600 rounded-full mix-blend-screen filter blur-[100px] opacity-20 pointer-events-none"></div>

    <div class="max-w-md w-full relative z-10 animate-fade-in-up">
        
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-br from-slate-700 to-slate-900 border border-slate-600 text-white rounded-full flex items-center justify-center mx-auto mb-5 shadow-2xl">
                <i class="fas fa-crown text-3xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold tracking-widest uppercase mb-1 text-white">System Admin</h1>
            <p class="text-slate-400 font-medium text-sm tracking-widest uppercase">Restricted Access</p>
        </div>

        <div class="glass-panel rounded-[2rem] p-8 sm:p-10 relative overflow-hidden">
            <!-- Top accent line -->
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-transparent via-geo-500 to-transparent"></div>

            @if(session('error'))
                <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-5 py-4 rounded-xl mb-8 text-sm font-semibold flex items-center gap-3 animate-fade-in-up">
                    <i class="fas fa-minus-circle text-lg shrink-0"></i> {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}">
                @csrf
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Admin Credential</label>
                    <div class="relative group">
                        <i class="fas fa-user-shield absolute left-5 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-geo-500 transition-colors duration-300"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required 
                            class="w-full pl-12 pr-5 py-4 input-glass rounded-xl focus:ring-1 focus:ring-geo-500 focus:border-geo-500 outline-none text-white transition-all font-mono text-sm placeholder:text-slate-600 focus:bg-white/5" 
                            placeholder="admin@georx.com">
                    </div>
                </div>

                <div class="mb-10">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Security Key</label>
                    <div class="relative group">
                        <i class="fas fa-key absolute left-5 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-geo-500 transition-colors duration-300"></i>
                        <input type="password" name="password" required 
                            class="w-full pl-12 pr-5 py-4 input-glass rounded-xl focus:ring-1 focus:ring-geo-500 focus:border-geo-500 outline-none text-white transition-all font-mono text-sm placeholder:text-slate-600 focus:bg-white/5" 
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="w-full bg-white text-geo-900 hover:bg-geo-400 font-black py-4 rounded-xl shadow-lg shadow-white/10 transition-all flex justify-center items-center gap-3 uppercase tracking-widest text-sm active:scale-[0.98]">
                    <i class="fas fa-fingerprint text-lg"></i> Authenticate
                </button>
            </form>
        </div>
    </div>
</body>
</html>
