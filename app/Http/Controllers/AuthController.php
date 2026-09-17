<?php

namespace App\Http\Controllers;

use App\Models\DriverProfile;
use App\Models\User;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // =============================================
    // 1. REGULAR USER (PATIENT) LOGIN
    // =============================================

    public function showUserLogin(Request $request)
    {
        if (Auth::guard('web')->check()) {
            return redirect()->intended('/dashboard');
        }

        return view('auth.login');
    }

    public function loginUser(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('web')->user();
            
            // SECURITY CHECK: Kick out Admins trying to use the Patient door
            if ($user->role !== User::ROLE_CUSTOMER) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'This login page is for customers only.');
            }

            $request->session()->regenerate();
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    // =============================================
    // 2. ADMIN LOGIN
    // =============================================

    public function showAdminLogin(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->intended('/admin/dashboard');
        }

        return view('auth.admin-login');
    }

    public function loginAdmin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('admin')->user();

            if ($user->role !== User::ROLE_ADMINISTRATOR) {
                Auth::guard('admin')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Access Denied. Administrator only.');
            }

            $request->session()->regenerate();
            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    // =============================================
    // 3. PHARMACY LOGIN
    // =============================================

    public function showPharmacyLogin(Request $request)
    {
        if (Auth::guard('pharmacy')->check()) {
            return redirect()->intended('/pharmacy/dashboard');
        }

        return view('auth.pharmacy-login');
    }

    public function loginPharmacy(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('pharmacy')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('pharmacy')->user();

            if (!in_array($user->role, [User::ROLE_PHARMACY_OWNER, User::ROLE_PHARMACIST, User::ROLE_PHARMACY_STAFF])) {
                Auth::guard('pharmacy')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Access Denied. Pharmacy staff only.');
            }

            if ($user->pharmacy && !$user->pharmacy->is_approved) {
                Auth::guard('pharmacy')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => 'Your pharmacy account is currently pending approval.'
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            return redirect()->intended('/pharmacy/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    // =============================================
    // 4. LOGOUT LOGIC
    // =============================================

    public function logout(Request $request)
    {
        // Logout from whichever guard is active, or all of them
        if (Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/admin/login');
        }

        if (Auth::guard('pharmacy')->check()) {
            Auth::guard('pharmacy')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/pharmacy/login');
        }

        if (Auth::guard('driver')->check()) {
            Auth::guard('driver')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/driver/login');
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        
        return redirect('/');
    }

    // =============================================
    // 5. REGULAR USER REGISTRATION
    // =============================================

    public function showUserRegister(Request $request)
    {
        if (Auth::guard('web')->check()) {
            return redirect('/dashboard');
        }
        return view('auth.user-register');
    }

    public function registerUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_CUSTOMER,
        ]);

        // Sync user to Supabase Auth so cross-login on Mobile works seamlessly
        \App\Services\SupabaseAuthService::createOrSyncUser(
            $validated['email'],
            $validated['password'],
            $validated['name'],
            $validated['phone'] ?? ''
        );

        return redirect()->route('login')->with('success', 'Account created successfully! Please sign in to continue.');
    }

    // =============================================
    // 6. PHARMACY REGISTRATION
    // =============================================

    public function showPharmacyRegister(Request $request)
    {
        return view('auth.pharmacy-register');
    }

    public function registerPharmacy(Request $request)
    {
        $validated = $request->validate([
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'pharmacy_name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'owner_name' => 'nullable|string|max:255',
            'lto_number' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'open_time' => 'required|string',
            'close_time' => 'required|string',
        ]);

        if (!\App\Services\GeofenceService::isInsideAlijis((float)$validated['latitude'], (float)$validated['longitude'])) {
            return back()->withErrors([
                'latitude' => 'The coordinates provided are outside the boundary of Barangay Alijis. Only pharmacies located inside Barangay Alijis can register.'
            ])->withInput();
        }

        $pharmacy = Pharmacy::create([
            'name' => $validated['pharmacy_name'],
            'slug' => Str::slug($validated['pharmacy_name']) . '-' . Str::random(5),
            'address' => $validated['address'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'owner_name' => $validated['owner_name'] ?? $validated['admin_name'],
            'lto_number' => $validated['lto_number'] ?? null,
            'is_active' => false, 
            'is_approved' => false, 
            'operating_hours' => [
                'open' => \Carbon\Carbon::parse($validated['open_time'])->format('g:i A'),
                'close' => \Carbon\Carbon::parse($validated['close_time'])->format('g:i A'),
            ],
        ]);

        User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $pharmacy->id,
        ]);

        return redirect('/pharmacy/login')->with('success', 'Registration submitted! Please wait for the Administrator to approve your pharmacy before logging in.');
    }

    // =============================================
    // 7. DRIVER LOGIN
    // =============================================

    public function showDriverLogin(Request $request)
    {
        if (Auth::guard('driver')->check()) {
            return redirect()->intended('/driver/dashboard');
        }

        return view('auth.driver-login');
    }

    public function loginDriver(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::guard('driver')->attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::guard('driver')->user();

            // SECURITY CHECK 1: Role must be driver or delivery_partner
            if (! $user->isDriver()) {
                Auth::guard('driver')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'This login is for GEORX Drivers only.');
            }

            // SECURITY CHECK 2: Driver profile must exist and be approved
            $profile = $user->driverProfile;

            if (! $profile) {
                Auth::guard('driver')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Driver profile not found. Please contact support.');
            }

            if ($profile->account_status === User::STATUS_PENDING_REVIEW) {
                Auth::guard('driver')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Your driver account is pending admin approval. Please check back later.');
            }

            if ($profile->account_status === User::STATUS_SUSPENDED) {
                Auth::guard('driver')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Your driver account has been suspended. Please contact support.');
            }

            if ($profile->account_status === User::STATUS_REJECTED) {
                Auth::guard('driver')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Your driver application was not approved.');
            }

            if ($profile->account_status !== User::STATUS_APPROVED) {
                Auth::guard('driver')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Your driver account is not yet active.');
            }

            $request->session()->regenerate();
            return redirect()->intended('/driver/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    // =============================================
    // 8. DRIVER REGISTRATION
    // =============================================

    public function showDriverRegister(Request $request)
    {
        if (Auth::guard('driver')->check()) {
            return redirect('/driver/dashboard');
        }

        return view('auth.driver-register');
    }

    public function registerDriver(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'phone'        => 'required|string|max:20',
            'password'     => 'required|min:8|confirmed',
            'vehicle_type' => 'required|in:motorcycle,bicycle,e-bike,car',
            'vehicle_make' => 'nullable|string|max:100',
            'vehicle_model'=> 'nullable|string|max:100',
            'plate_number' => 'nullable|string|max:20',
        ]);

        // Create the user account (role = 'driver', account_status = 'pending_review')
        $user = User::create([
            'name'           => $validated['name'],
            'email'          => $validated['email'],
            'phone'          => $validated['phone'],
            'password'       => Hash::make($validated['password']),
            'role'           => User::ROLE_DRIVER,
            'account_status' => User::STATUS_PENDING_REVIEW,
        ]);

        // Create driver profile (account_status = 'pending_review' awaiting admin approval)
        DriverProfile::create([
            'user_id'        => $user->id,
            'vehicle_type'   => $validated['vehicle_type'],
            'vehicle_make'   => $validated['vehicle_make'] ?? null,
            'vehicle_model'  => $validated['vehicle_model'] ?? null,
            'plate_number'   => $validated['plate_number'] ?? null,
            'account_status' => DriverProfile::STATUS_PENDING_REVIEW,
            'is_online'      => false,
            'is_available'   => true,
        ]);

        return redirect('/driver/login')
            ->with('success', 'Application submitted! An administrator will review and activate your account.');
    }
}