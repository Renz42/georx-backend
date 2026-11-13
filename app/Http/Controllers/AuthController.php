<?php

namespace App\Http\Controllers;

use App\Models\DriverProfile;
use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Models\Pharmacy;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public const ADMIN_RESET_SESSION_KEY = 'admin_password_reset_context';
    public const PHARMACY_RESET_SESSION_KEY = 'pharmacy_password_reset_context';
    public const GENERIC_RESET_REQUEST_MESSAGE = 'If an eligible GEORX account matches that email address, a password recovery code will be sent.';
    public const GENERIC_RESEND_MESSAGE = 'If an eligible GEORX account matches that email address, a new six-digit verification code has been sent.';
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
        $credentials['email'] = mb_strtolower(trim((string) $credentials['email']));

        $matchedUser = User::whereRaw('LOWER(email) = ?', [$credentials['email']])->first();
        $attemptCredentials = [
            'email'    => $matchedUser ? $matchedUser->email : $credentials['email'],
            'password' => $credentials['password'],
        ];

        if (Auth::guard('web')->attempt($attemptCredentials, $request->boolean('remember'))) {
            $user = Auth::guard('web')->user();
            
            // SECURITY CHECK: Kick out Admins trying to use the Patient door
            if ($user->role !== User::ROLE_CUSTOMER) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
            }

            // Check if patient email is pending verification
            if ($user->account_status === User::STATUS_PENDING_VERIFICATION) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->with('error', 'Your patient account is pending email verification. Please verify using the code sent to your email.');
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
                return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
            }

            Auth::shouldUse('admin');
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
                return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
            }

            if ($user->pharmacy && !$user->pharmacy->is_approved) {
                Auth::guard('pharmacy')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => 'Your pharmacy account is currently pending approval.'
                ])->onlyInput('email');
            }

            Auth::shouldUse('pharmacy');
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
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8|confirmed',
        ]);

        $normalizedEmail = mb_strtolower(trim($validated['email']));
        if (User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->exists()) {
            return back()
                ->withErrors(['email' => 'The email has already been taken.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        $user = User::create([
            'name'           => trim($validated['name']),
            'email'          => $normalizedEmail,
            'phone'          => !empty($validated['phone']) ? trim((string) $validated['phone']) : null,
            'password'       => Hash::make($validated['password']),
            'role'           => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_PENDING_VERIFICATION,
        ]);

        // Issue 6-digit OTP verification code
        $otpResult = \App\Services\EmailOtpService::createChallenge($user, 'registration');

        if (!empty($otpResult['delivery_failed']) && empty($otpResult['ambiguous_timeout'])) {
            try {
                EmailOtpChallenge::where('user_id', $user->id)->delete();
                $user->delete();
            } catch (\Throwable $cleanupException) {
                Log::warning("Failed web unverified user cleanup [{$user->id}]: " . $cleanupException->getMessage());
            }

            return back()
                ->withErrors(['email' => $otpResult['error'] ?? 'Unable to send verification email right now. Please try again shortly.'])
                ->withInput($request->except('password', 'password_confirmation'));
        }

        return redirect()->route('login')->with('success', 'Account created! Please check your email for the verification code to activate your account.');
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

    // =============================================
    // 9. ADMIN & PHARMACY PORTAL PASSWORD RECOVERY
    // =============================================

    public function showAdminForgotPassword(Request $request)
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->intended('/admin/dashboard');
        }

        return view('auth.admin-forgot-password');
    }

    public function sendAdminResetOtp(Request $request)
    {
        return $this->handleForgotPasswordRequest($request, 'admin');
    }

    public function showAdminResetPassword(Request $request)
    {
        return $this->showPortalResetPassword($request, 'admin');
    }

    public function resetAdminPassword(Request $request)
    {
        return $this->handlePortalPasswordReset($request, 'admin');
    }

    public function resendAdminResetOtp(Request $request)
    {
        return $this->handlePortalResendOtp($request, 'admin');
    }

    public function showPharmacyForgotPassword(Request $request)
    {
        if (Auth::guard('pharmacy')->check()) {
            return redirect()->intended('/pharmacy/dashboard');
        }

        return view('auth.pharmacy-forgot-password');
    }

    public function sendPharmacyResetOtp(Request $request)
    {
        return $this->handleForgotPasswordRequest($request, 'pharmacy');
    }

    public function showPharmacyResetPassword(Request $request)
    {
        return $this->showPortalResetPassword($request, 'pharmacy');
    }

    public function resetPharmacyPassword(Request $request)
    {
        return $this->handlePortalPasswordReset($request, 'pharmacy');
    }

    public function resendPharmacyResetOtp(Request $request)
    {
        return $this->handlePortalResendOtp($request, 'pharmacy');
    }

    protected function getResetSessionKey(string $portal): string
    {
        return $portal === 'admin'
            ? self::ADMIN_RESET_SESSION_KEY
            : self::PHARMACY_RESET_SESSION_KEY;
    }

    protected function isUserEligibleForPortal(?User $user, string $portal): bool
    {
        if (!$user) {
            return false;
        }

        if (in_array($user->account_status, [
            User::STATUS_SUSPENDED,
            User::STATUS_REJECTED,
            User::STATUS_PENDING_REVIEW,
            User::STATUS_PENDING_VERIFICATION,
        ], true)) {
            return false;
        }

        if ($portal === 'admin') {
            return $user->role === User::ROLE_ADMINISTRATOR;
        }

        if ($portal === 'pharmacy') {
            if (!in_array($user->role, [
                User::ROLE_PHARMACY_OWNER,
                User::ROLE_PHARMACIST,
                User::ROLE_PHARMACY_STAFF,
            ], true)) {
                return false;
            }

            $pharmacy = $user->pharmacy;
            if (!$user->pharmacy_id || !$pharmacy) {
                return false;
            }

            if (!$pharmacy->is_approved || !$pharmacy->is_active) {
                return false;
            }

            $pharmacyStatus = strtolower(trim((string) ($pharmacy->status ?? '')));
            if (in_array($pharmacyStatus, ['suspended', 'rejected', 'pending'], true)) {
                return false;
            }

            return true;
        }

        return false;
    }

    protected function handleForgotPasswordRequest(Request $request, string $portal)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = trim($validated['email']);
        $sessionKey = $this->getResetSessionKey($portal);
        $user = User::with('pharmacy')->where('email', $email)->first();

        if ($this->isUserEligibleForPortal($user, $portal)) {
            $otpResult = EmailOtpService::createChallenge($user, 'password_reset');

            if (!empty($otpResult['delivery_failed']) || empty($otpResult['challenge_token'])) {
                $request->session()->put($sessionKey, [
                    'portal'          => $portal,
                    'user_id'         => null,
                    'is_dummy'        => true,
                    'challenge_token' => null,
                    'masked_email'    => $otpResult['masked_email'] ?? EmailOtpService::maskEmail($email),
                    'attempts'        => 0,
                    'send_count'      => 1,
                    'last_sent_at'    => now()->timestamp,
                    'expires_at'      => now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp,
                ]);
            } else {
                $request->session()->put($sessionKey, [
                    'portal'          => $portal,
                    'user_id'         => $user->id,
                    'is_dummy'        => false,
                    'challenge_token' => $otpResult['challenge_token'],
                    'masked_email'    => $otpResult['masked_email'],
                    'attempts'        => 0,
                    'send_count'      => 1,
                    'last_sent_at'    => now()->timestamp,
                    'expires_at'      => now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp,
                ]);
            }
        } else {
            // Perform equivalent cryptographic hashing operations to reduce timing differences
            EmailOtpService::hashOtp((string) random_int(100000, 999999));
            EmailOtpService::hashChallengeToken(Str::random(64));

            $request->session()->put($sessionKey, [
                'portal'          => $portal,
                'user_id'         => null,
                'is_dummy'        => true,
                'challenge_token' => null,
                'masked_email'    => EmailOtpService::maskEmail($email),
                'attempts'        => 0,
                'send_count'      => 1,
                'last_sent_at'    => now()->timestamp,
                'expires_at'      => now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp,
            ]);
        }

        return redirect()->route("{$portal}.password.reset")
            ->with('status', self::GENERIC_RESET_REQUEST_MESSAGE);
    }

    protected function showPortalResetPassword(Request $request, string $portal)
    {
        if (Auth::guard($portal)->check()) {
            return redirect()->intended($portal === 'admin' ? '/admin/dashboard' : '/pharmacy/dashboard');
        }

        $sessionKey = $this->getResetSessionKey($portal);
        $context = $request->session()->get($sessionKey);

        if (!is_array($context) || ($context['portal'] ?? '') !== $portal || empty($context['masked_email'])) {
            return redirect()->route("{$portal}.password.request")
                ->with('error', 'Please enter your email address to request a password recovery code first.');
        }

        $elapsed = max(0, now()->timestamp - (int) ($context['last_sent_at'] ?? 0));
        $resendInSeconds = max(0, EmailOtpService::RESEND_COOLDOWN_SECONDS - $elapsed);

        return view("auth.{$portal}-reset-password", [
            'maskedEmail'     => $context['masked_email'],
            'resendInSeconds' => $resendInSeconds,
        ]);
    }

    protected function handlePortalResendOtp(Request $request, string $portal)
    {
        $sessionKey = $this->getResetSessionKey($portal);
        $context = $request->session()->get($sessionKey);

        if (!is_array($context) || ($context['portal'] ?? '') !== $portal) {
            return redirect()->route("{$portal}.password.request")
                ->with('error', 'Invalid or expired verification session. Please request a new code.');
        }

        if (!empty($context['is_dummy']) || empty($context['challenge_token'])) {
            if (now()->timestamp >= (int) ($context['expires_at'] ?? 0)) {
                $request->session()->forget($sessionKey);
                return redirect()->route("{$portal}.password.request")
                    ->with('error', 'Verification code has expired. Please request a new code.');
            }

            $elapsed = max(0, now()->timestamp - (int) ($context['last_sent_at'] ?? 0));
            if ($elapsed < EmailOtpService::RESEND_COOLDOWN_SECONDS) {
                $remaining = EmailOtpService::RESEND_COOLDOWN_SECONDS - $elapsed;
                return back()->with('error', "Please wait {$remaining} second(s) before requesting another code.");
            }

            $sendCount = (int) ($context['send_count'] ?? 1);
            if ($sendCount >= EmailOtpService::MAX_RESENDS) {
                return back()->with('error', 'Maximum code requests reached for this session. Please request a new password reset.');
            }

            EmailOtpService::hashOtp((string) random_int(100000, 999999));
            $context['send_count'] = $sendCount + 1;
            $context['attempts'] = 0;
            $context['last_sent_at'] = now()->timestamp;
            $context['expires_at'] = now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp;
            $request->session()->put($sessionKey, $context);

            return back()->with('status', self::GENERIC_RESEND_MESSAGE);
        }

        $challengeToken = (string) $context['challenge_token'];
        $tokenHash = EmailOtpService::hashChallengeToken($challengeToken);
        $challengeRecord = EmailOtpChallenge::with('user.pharmacy')
            ->where('challenge_token_hash', $tokenHash)
            ->first();

        if (
            !$challengeRecord
            || $challengeRecord->purpose !== 'password_reset'
            || empty($context['user_id'])
            || (int) $challengeRecord->user_id !== (int) $context['user_id']
            || !$this->isUserEligibleForPortal($challengeRecord->user, $portal)
        ) {
            $request->session()->forget($sessionKey);
            return redirect()->route("{$portal}.password.request")
                ->with('error', 'Invalid or expired verification session. Please request a new code.');
        }

        $result = EmailOtpService::resendOtp($challengeToken);

        if (!$result['success']) {
            if ($result['status'] === 429 || $result['status'] === 409) {
                return back()->with('error', $result['error']);
            }

            if ($result['status'] === 404 || $result['status'] === 410) {
                $request->session()->forget($sessionKey);
                return redirect()->route("{$portal}.password.request")
                    ->with('error', $result['error']);
            }

            // Status 503 (Brevo delivery error): preserve uniform response without penalizing
            // send_count or cooldown so the previously delivered OTP remains valid and retryable.
            return back()->with('status', self::GENERIC_RESEND_MESSAGE);
        }

        $context['last_sent_at'] = now()->timestamp;
        $context['send_count'] = (int) ($context['send_count'] ?? 1) + 1;
        $context['attempts'] = 0;
        $context['expires_at'] = now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp;
        $request->session()->put($sessionKey, $context);

        return back()->with('status', self::GENERIC_RESEND_MESSAGE);
    }

    protected function handlePortalPasswordReset(Request $request, string $portal)
    {
        $sessionKey = $this->getResetSessionKey($portal);
        $context = $request->session()->get($sessionKey);

        if (!is_array($context) || ($context['portal'] ?? '') !== $portal) {
            return redirect()->route("{$portal}.password.request")
                ->with('error', 'Your password recovery session has expired or is invalid. Please request a new recovery code.');
        }

        $validated = $request->validate([
            'otp'      => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Handle dummy reset state (unknown or wrong-role account) with identical behavior
        if (!empty($context['is_dummy']) || empty($context['challenge_token'])) {
            EmailOtpService::hashOtp($validated['otp']);

            if (now()->timestamp >= (int) ($context['expires_at'] ?? 0)) {
                $request->session()->forget($sessionKey);
                return redirect()->route("{$portal}.password.request")
                    ->with('error', 'Verification code has expired. Please request a new code.');
            }

            $attempts = (int) ($context['attempts'] ?? 0);
            if ($attempts >= EmailOtpService::MAX_ATTEMPTS) {
                $request->session()->forget($sessionKey);
                return redirect()->route("{$portal}.password.request")
                    ->with('error', 'Too many invalid attempts. This session is locked. Please request a new code.');
            }

            $attempts++;
            $context['attempts'] = $attempts;
            $remaining = max(0, EmailOtpService::MAX_ATTEMPTS - $attempts);
            $request->session()->put($sessionKey, $context);

            $errorMessage = $remaining > 0
                ? "Incorrect verification code. {$remaining} attempt(s) remaining."
                : "Incorrect verification code. Maximum attempts exceeded. Please request a new code.";

            return back()->withErrors(['otp' => $errorMessage]);
        }

        $challengeToken = (string) $context['challenge_token'];
        $tokenHash = EmailOtpService::hashChallengeToken($challengeToken);
        $challengeRecord = EmailOtpChallenge::with('user.pharmacy')
            ->where('challenge_token_hash', $tokenHash)
            ->first();

        if (
            !$challengeRecord
            || !$challengeRecord->user
            || empty($context['user_id'])
            || (int) $challengeRecord->user_id !== (int) $context['user_id']
        ) {
            $request->session()->forget($sessionKey);
            return redirect()->route("{$portal}.password.request")
                ->with('error', 'Invalid or expired verification session.');
        }

        if (!$this->isUserEligibleForPortal($challengeRecord->user, $portal)) {
            $challengeRecord->update(['expires_at' => now()]);
            $request->session()->forget($sessionKey);
            return redirect()->route("{$portal}.password.request")
                ->with('error', 'Invalid or expired verification session.');
        }

        try {
            $verifyResult = EmailOtpService::verifyAndExecute(
                challengeToken: $challengeToken,
                submittedOtp: $validated['otp'],
                expectedPurpose: 'password_reset',
                onVerified: function (?User $verifiedUser) use ($portal, $validated): bool {
                    if (!$verifiedUser) {
                        return false;
                    }

                    /** @var User|null $lockedUser */
                    $lockedUser = User::with('pharmacy')->lockForUpdate()->find($verifiedUser->id);

                    if (!$this->isUserEligibleForPortal($lockedUser, $portal)) {
                        return false;
                    }

                    $lockedUser->forceFill([
                        'password'       => Hash::make($validated['password']),
                        'remember_token' => Str::random(60),
                    ])->save();

                    $this->revokeUserDatabaseSessions($lockedUser);

                    // Revoke existing Sanctum personal access tokens for this user
                    if (Schema::hasTable('personal_access_tokens')) {
                        $lockedUser->tokens()->delete();
                    }

                    return true;
                }
            );
        } catch (\Throwable $e) {
            Log::error("Password reset transaction rolled back for user [{$challengeRecord->user_id}]: " . $e->getMessage());
            return back()->with('error', 'Unable to update your password right now. Your verification code has not been used; please try again.');
        }

        if (!$verifyResult['success']) {
            if ($verifyResult['status'] === 422) {
                return back()->withErrors(['otp' => $verifyResult['error']]);
            }

            $request->session()->forget($sessionKey);
            return redirect()->route("{$portal}.password.request")
                ->with('error', $verifyResult['error']);
        }

        $request->session()->forget($sessionKey);

        if (Auth::guard($portal)->check()) {
            Auth::guard($portal)->logout();
        }

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route("{$portal}.login")
            ->with('success', 'Your password has been reset successfully. Please sign in with your new password.');
    }

    /**
     * Revoke all database sessions belonging to the user across all guards,
     * including historical multi-guard sessions whose `user_id` column is null.
     * Never calls `unserialize()` on session payloads.
     */
    protected function revokeUserDatabaseSessions(User $user): void
    {
        $sessionTable = config('session.table', 'sessions');
        if (!Schema::hasTable($sessionTable)) {
            return;
        }

        // 1. Delete all sessions explicitly indexed with this user's ID
        DB::table($sessionTable)
            ->where('user_id', $user->id)
            ->delete();

        // 2. Safely inspect unencrypted legacy multi-guard sessions where user_id is null
        if (config('session.encrypt', false)) {
            return;
        }

        $patterns = [];
        $strUserId = (string) $user->id;
        foreach (['admin', 'pharmacy', 'web', 'driver'] as $guardName) {
            try {
                $guardKey = Auth::guard($guardName)->getName();
                $keyPrefix = 's:' . strlen($guardKey) . ':"' . $guardKey . '";';
                $patterns[] = $keyPrefix . 'i:' . (int) $user->id . ';';
                $patterns[] = $keyPrefix . 's:' . strlen($strUserId) . ':"' . $strUserId . '";';
            } catch (\Throwable $e) {
                continue;
            }
        }

        if (empty($patterns)) {
            return;
        }

        $lifetimeMinutes = max(1, (int) config('session.lifetime', 120));
        $activeCutoff = now()->subMinutes($lifetimeMinutes)->getTimestamp();

        DB::table($sessionTable)
            ->whereNull('user_id')
            ->where('last_activity', '>=', $activeCutoff)
            ->select(['id', 'payload'])
            ->chunkById(100, function ($nullUserSessions) use ($sessionTable, $patterns) {
                $matchingSessionIds = [];

                foreach ($nullUserSessions as $row) {
                    if (!is_string($row->payload) || $row->payload === '') {
                        continue;
                    }

                    $decoded = base64_decode($row->payload, true);
                    if (!is_string($decoded) || $decoded === '') {
                        continue;
                    }

                    foreach ($patterns as $pattern) {
                        if (str_contains($decoded, $pattern)) {
                            $matchingSessionIds[] = $row->id;
                            break;
                        }
                    }
                }

                if (!empty($matchingSessionIds)) {
                    DB::table($sessionTable)
                        ->whereIn('id', $matchingSessionIds)
                        ->delete();
                }
            }, 'id');
    }
}