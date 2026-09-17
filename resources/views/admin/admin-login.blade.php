<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - GEORX: A Medicine Hub Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <style>
        body { font-family: 'Outfit', sans-serif; }
        /* Custom checkbox color to match the palette */
        input[type="checkbox"] { accent-color: #2F7E6A; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Outfit', 'sans-serif'] },
                    colors: {
                        geo: { 900: '#0F172A', 800: '#1E293B', 700: '#1F2E2C', 600: '#2F7E6A', 500: '#63C6A7', 400: '#BFE8D6', 300: '#A0D8C4', 100: '#E9F7F2', 50: '#F8FAFC' }
                    }
                }
            }
        }
    </script></head>
<body class="bg-geo-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-geo-500 text-geo-900 rounded-2xl flex items-center justify-center mx-auto mb-5 shadow-lg shadow-geo-500/20 rotate-3 transition-transform hover:rotate-0 duration-300">
                <i class="fas fa-prescription-bottle-alt text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold text-geo-900 tracking-tight mb-2">Welcome back</h1>
            <p class="text-geo-600 font-medium text-sm px-4">Sign in to manage your inventory and help Bacolod City stay healthy.</p>
        </div>

        <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(191,232,214,0.6)] p-8 border border-geo-400/50">
            
            <div class="bg-geo-100/60 border border-geo-400 text-geo-900 px-4 py-3.5 rounded-xl mb-6 text-sm flex gap-3 leading-relaxed items-start">
                <i class="fas fa-shield-check mt-0.5 text-geo-600 text-lg"></i>
                <p><strong>Secure Access:</strong> This portal is dedicated to our partnered pharmacy owners and system admins.</p>
            </div>

            @if(session('error'))
                <div class="bg-red-50 border border-red-100 text-red-600 px-4 py-3 rounded-xl mb-6 text-sm font-medium flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login') }}">
                @csrf
                
                <div class="mb-5">
                    <label class="block text-sm font-bold text-geo-900 mb-2">Email Address</label>
                    <div class="relative group">
                        <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-geo-500 group-focus-within:text-geo-600 transition-colors"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required 
                            class="w-full pl-11 pr-4 py-3 bg-geo-50 border border-geo-400 rounded-xl focus:ring-2 focus:ring-geo-500/50 focus:border-geo-500 focus:bg-white outline-none text-geo-900 transition-all font-medium placeholder:text-slate-400" 
                            placeholder="dr.smith@pharmacy.com">
                    </div>
                    @error('email') <p class="text-red-500 text-xs font-bold mt-2">{{ $message }}</p> @enderror
                </div>

                <div class="mb-5">
                    <label class="block text-sm font-bold text-geo-900 mb-2">Password</label>
                    <div class="relative group">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-geo-500 group-focus-within:text-geo-600 transition-colors"></i>
                        <input type="password" name="password" required 
                            class="w-full pl-11 pr-4 py-3 bg-geo-50 border border-geo-400 rounded-xl focus:ring-2 focus:ring-geo-500/50 focus:border-geo-500 focus:bg-white outline-none text-geo-900 transition-all font-medium placeholder:text-slate-400" 
                            placeholder="••••••••">
                    </div>
                </div>

                <div class="flex items-center justify-between mb-8">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-geo-400 cursor-pointer">
                        <span class="text-sm font-medium text-geo-900/70 group-hover:text-geo-900 transition-colors">Remember me</span>
                    </label>
                    
                    <a href="{{ route('password.request') ?? '#' }}" class="text-sm font-bold text-geo-600 hover:text-geo-900 transition-colors">
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="w-full bg-geo-600 hover:bg-geo-900 text-geo-100 font-bold py-3.5 rounded-xl shadow-lg shadow-geo-600/20 transition-all flex justify-center items-center gap-2 transform active:scale-[0.98]">
                    Sign In <i class="fas fa-arrow-right text-sm ml-1"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-geo-100 text-center">
                <p class="text-geo-900/60 text-sm mb-4 font-medium">Not in our network yet?</p>
                <a href="{{ route('pharmacy.register') }}" class="w-full bg-transparent border-2 border-geo-500 hover:bg-geo-100 text-geo-600 font-bold py-3 rounded-xl transition-all flex justify-center items-center gap-2 block">
                    <i class="fas fa-handshake"></i> Partner Your Pharmacy
                </a>
            </div>
        </div>

        <div class="text-center mt-8">
            <a href="{{ url('/') }}" class="text-geo-600 hover:text-geo-900 font-semibold text-sm transition-colors flex items-center justify-center gap-2 group">
                <i class="fas fa-arrow-left transform group-hover:-translate-x-1 transition-transform"></i> Return to Public Search
            </a>
        </div>
    </div>

</body>
</html>
