<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply as Driver — GEORX: A Medicine Hub Portal</title>
    <meta name="description" content="Apply to become a GEORX delivery driver in Barangay Alijis, Bacolod City.">
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
                            950: '#0D0F1A', 900: '#111827', 800: '#1C2333',
                            600: '#3B5BDB', 500: '#4C6EF5', 400: '#748FFC',
                            300: '#A5B4FC', 100: '#EEF2FF',
                        }
                    },
                    animation: {
                        'fade-in-up': 'fadeInUp 0.5s ease-out forwards',
                    },
                    keyframes: {
                        fadeInUp: {
                            '0%':   { opacity: '0', transform: 'translateY(20px)' },
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
            border: 1px solid rgba(75, 110, 245, 0.2);
            box-shadow: 0 32px 64px -16px rgba(0,0,0,0.7);
        }
        .input-field {
            background: rgba(13, 15, 26, 0.7);
            border: 1px solid rgba(75, 110, 245, 0.2);
            transition: all 0.2s ease;
        }
        .input-field:focus {
            border-color: rgba(75, 110, 245, 0.6);
            box-shadow: 0 0 0 3px rgba(75, 110, 245, 0.12);
            outline: none;
        }
    </style>
</head>
<body class="bg-rider-950 min-h-screen py-10 px-4 text-white">
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-rider-600 via-violet-500 to-rider-600"></div>

    <div class="max-w-xl mx-auto animate-fade-in-up">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-gradient-to-br from-rider-600 to-violet-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl shadow-rider-600/20">
                <i class="fas fa-motorcycle text-2xl text-white"></i>
            </div>
            <h1 class="text-2xl font-black text-white mb-1">Apply as a GEORX Driver</h1>
            <p class="text-slate-400 text-sm">Submit your application. An admin will review and activate your account.</p>
        </div>

        {{-- Card --}}
        <div class="glass rounded-3xl p-8 sm:p-10">
            <div class="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-rider-500 to-transparent rounded-t-3xl"></div>

            @if($errors->any())
                <div class="bg-rose-500/10 border border-rose-500/20 text-rose-300 px-4 py-3.5 rounded-2xl mb-6 text-sm">
                    <p class="font-bold mb-1 flex items-center gap-2"><i class="fas fa-exclamation-circle"></i> Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-1 mt-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('driver.register') }}" id="driver-register-form">
                @csrf

                {{-- Section: Personal Info --}}
                <p class="text-[10px] font-black text-rider-400 uppercase tracking-widest mb-4">Personal Information</p>

                <div class="grid grid-cols-1 gap-5 mb-5">
                    {{-- Full Name --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Full Name</label>
                        <div class="relative group">
                            <i class="fas fa-user absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors text-sm"></i>
                            <input type="text" name="name" value="{{ old('name') }}" required
                                placeholder="Juan Dela Cruz"
                                class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                        </div>
                        @error('name') <p class="text-rose-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    {{-- Phone --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Phone Number</label>
                        <div class="relative group">
                            <i class="fas fa-phone absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors text-sm"></i>
                            <input type="text" name="phone" value="{{ old('phone') }}" required
                                placeholder="09171234567"
                                class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                        </div>
                        @error('phone') <p class="text-rose-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
                        <div class="relative group">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors text-sm"></i>
                            <input type="email" name="email" value="{{ old('email') }}" required
                                placeholder="you@example.com"
                                class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                        </div>
                        @error('email') <p class="text-rose-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Password</label>
                        <div class="relative group">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors text-sm"></i>
                            <input type="password" name="password" required
                                placeholder="Min. 8 characters"
                                class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                        </div>
                        @error('password') <p class="text-rose-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password Confirm --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Confirm Password</label>
                        <div class="relative group">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-rider-400 transition-colors text-sm"></i>
                            <input type="password" name="password_confirmation" required
                                placeholder="Re-enter password"
                                class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                        </div>
                    </div>
                </div>

                {{-- Section: Vehicle Info --}}
                <p class="text-[10px] font-black text-rider-400 uppercase tracking-widest mb-4 mt-6">Vehicle Information</p>

                <div class="grid grid-cols-1 gap-5 mb-8">
                    {{-- Vehicle Type --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Vehicle Type</label>
                        <div class="relative">
                            <i class="fas fa-car-side absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 text-sm pointer-events-none"></i>
                            <select name="vehicle_type" required
                                class="input-field w-full pl-11 pr-4 py-3.5 rounded-xl text-white text-sm font-medium appearance-none cursor-pointer">
                                <option value="" disabled {{ old('vehicle_type') ? '' : 'selected' }}>Select vehicle type...</option>
                                <option value="motorcycle" {{ old('vehicle_type') === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                                <option value="bicycle"    {{ old('vehicle_type') === 'bicycle'    ? 'selected' : '' }}>Bicycle</option>
                                <option value="e-bike"     {{ old('vehicle_type') === 'e-bike'     ? 'selected' : '' }}>E-Bike</option>
                                <option value="car"        {{ old('vehicle_type') === 'car'        ? 'selected' : '' }}>Car</option>
                            </select>
                        </div>
                        @error('vehicle_type') <p class="text-rose-400 text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>

                    {{-- Vehicle Make --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Brand / Make <span class="text-slate-600 normal-case font-normal">(optional)</span></label>
                        <input type="text" name="vehicle_make" value="{{ old('vehicle_make') }}"
                            placeholder="e.g. Honda, Yamaha"
                            class="input-field w-full px-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                    </div>

                    {{-- Vehicle Model --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Model <span class="text-slate-600 normal-case font-normal">(optional)</span></label>
                        <input type="text" name="vehicle_model" value="{{ old('vehicle_model') }}"
                            placeholder="e.g. Click 125i, Mio"
                            class="input-field w-full px-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                    </div>

                    {{-- Plate Number --}}
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Plate Number <span class="text-slate-600 normal-case font-normal">(optional)</span></label>
                        <input type="text" name="plate_number" value="{{ old('plate_number') }}"
                            placeholder="e.g. ABC-1234"
                            class="input-field w-full px-4 py-3.5 rounded-xl text-white text-sm font-medium placeholder:text-slate-600">
                    </div>
                </div>

                {{-- Terms notice --}}
                <div class="flex items-start gap-3 bg-slate-800/50 border border-slate-700 rounded-2xl px-4 py-3.5 mb-6 text-xs text-slate-400 leading-relaxed">
                    <i class="fas fa-info-circle text-rider-400 mt-0.5 shrink-0"></i>
                    <p>By submitting, you confirm that you are a legal resident of <strong class="text-slate-300">Barangay Alijis, Bacolod City</strong> and that the information provided is accurate. Your application will be reviewed by a GEORX administrator.</p>
                </div>

                {{-- Submit --}}
                <button type="submit" id="driver-register-btn"
                    class="w-full bg-gradient-to-r from-rider-600 to-violet-600 hover:from-rider-500 hover:to-violet-500 text-white font-black py-4 rounded-2xl shadow-xl shadow-rider-600/25 transition-all active:scale-[0.98] flex items-center justify-center gap-2.5">
                    <i class="fas fa-paper-plane"></i>
                    Submit Application
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('driver.login') }}" class="text-slate-500 hover:text-rider-300 text-sm font-medium transition-colors inline-flex items-center gap-2">
                    <i class="fas fa-arrow-left text-xs"></i> Already applied? Sign In
                </a>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="{{ url('/') }}" class="text-slate-600 hover:text-slate-400 text-sm inline-flex items-center gap-2 transition-colors">
                <i class="fas fa-map-marked-alt text-xs"></i> Return to GEORX Map
            </a>
        </div>
    </div>
</body>
</html>
