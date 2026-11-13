<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public const GENERIC_PATIENT_RESET_REQUEST_MESSAGE = 'If an eligible patient account is associated with that email address, a six-digit verification code has been sent.';
    public const GENERIC_PATIENT_RESEND_MESSAGE = 'A new six-digit verification code has been sent to your email.';
    protected const DUMMY_CACHE_PREFIX = 'patient_pwd_dummy:';
    protected function normalizeEmail(mixed $email): string
    {
        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    public function register(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => $this->normalizeEmail($request->input('email'))]);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $normalizedEmail = $this->normalizeEmail($request->input('email'));
        $existingUser = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if ($existingUser) {
            if (
                $existingUser->role === User::ROLE_CUSTOMER
                && $existingUser->account_status === User::STATUS_PENDING_VERIFICATION
                && $existingUser->email_verified_at === null
            ) {
                return response()->json([
                    'status'               => 'error',
                    'verification_pending' => true,
                    'message'              => 'An account with this email is already awaiting verification. Please sign in to receive a new verification code.',
                    'errors'               => [
                        'email' => ['An account with this email is already awaiting verification. Please sign in to receive a new verification code.'],
                    ],
                ], 422);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'The email has already been taken.',
                'errors'  => [
                    'email' => ['The email has already been taken.'],
                ],
            ], 422);
        }

        $cleanPhone = is_string($request->phone) ? trim($request->phone) : null;

        // Create user with pending_verification status and unverified email
        try {
            $user = User::create([
                'name'              => trim((string) $request->name),
                'email'             => $normalizedEmail,
                'phone'             => $cleanPhone !== '' ? $cleanPhone : null,
                'password'          => Hash::make($request->password),
                'role'              => User::ROLE_CUSTOMER,
                'account_status'    => User::STATUS_PENDING_VERIFICATION,
                'email_verified_at' => null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The email has already been taken.',
                'errors'  => [
                    'email' => ['The email has already been taken.'],
                ],
            ], 422);
        }

        // Issue 6-digit OTP challenge via EmailOtpService
        $otpResult = EmailOtpService::createChallenge($user, EmailOtpService::PURPOSE_REGISTRATION);

        if (!empty($otpResult['delivery_failed'])) {
            if (empty($otpResult['ambiguous_timeout'])) {
                try {
                    EmailOtpChallenge::where('user_id', $user->id)->delete();
                    $user->delete();
                } catch (\Throwable $cleanupException) {
                    Log::warning("Failed cleanup of unverified user [{$user->id}] after OTP delivery failure: " . $cleanupException->getMessage());
                }

                return response()->json([
                    'status'  => 'error',
                    'message' => $otpResult['error'] ?? 'Unable to send verification email right now. Please try again shortly.',
                ], 503);
            }

            return response()->json([
                'status'             => 'error',
                'ambiguous_timeout'  => true,
                'message'            => $otpResult['error'] ?? 'Email delivery timed out. If a code arrives you may use it, or sign in to request a new code.',
                'challenge_token'    => $otpResult['challenge_token'] ?? null,
                'masked_email'       => $otpResult['masked_email'] ?? EmailOtpService::maskEmail($normalizedEmail),
                'expires_in_seconds' => $otpResult['expires_in_seconds'] ?? (EmailOtpService::EXPIRATION_MINUTES * 60),
                'resend_in_seconds'  => $otpResult['resend_in_seconds'] ?? EmailOtpService::RESEND_COOLDOWN_SECONDS,
            ], 503);
        }

        return response()->json([
            'status'             => 'verification_required',
            'message'            => 'Account created. Please enter the six-digit verification code sent to your email.',
            'challenge_token'    => $otpResult['challenge_token'],
            'masked_email'       => $otpResult['masked_email'],
            'expires_in_seconds' => $otpResult['expires_in_seconds'] ?? 600,
            'resend_in_seconds'  => $otpResult['resend_in_seconds'] ?? 60,
        ], 202);
    }

    public function login(Request $request)
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => $this->normalizeEmail($request->input('email'))]);
        }

        $validator = Validator::make($request->all(), [
            'email'    => 'required|string|email',
            'password' => 'required|string',
            'role'     => 'nullable|string|in:customer,driver',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $normalizedEmail = $this->normalizeEmail($request->input('email'));
        $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        // Validate user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $requestedRole = $request->input('role', 'customer');

        if ($requestedRole === 'driver') {
            // Check driver role
            if ($user->role !== User::ROLE_DELIVERY_PARTNER && $user->role !== 'driver') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'This account is not registered as a GeoRx driver.'
                ], 403);
            }

            // Check account status
            $driverProfile = $user->driverProfile;
            if (!$driverProfile || $driverProfile->account_status !== 'approved') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Driver account is pending admin approval or suspended.'
                ], 403);
            }
        } else {
            // Customer role verification
            if (in_array($user->role, [
                User::ROLE_ADMINISTRATOR,
                User::ROLE_PHARMACY_OWNER,
                User::ROLE_PHARMACIST,
                User::ROLE_PHARMACY_STAFF
            ])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Pharmacy owners and admins should use the Web Portal.'
                ], 403);
            }

            // Check if patient email requires verification
            if ($user->account_status === User::STATUS_PENDING_VERIFICATION || $user->email_verified_at === null) {
                $otpResult = EmailOtpService::createChallenge($user, EmailOtpService::PURPOSE_EMAIL_VERIFICATION);

                if (!empty($otpResult['delivery_failed'])) {
                    $failurePayload = [
                        'status'            => 'error',
                        'ambiguous_timeout' => !empty($otpResult['ambiguous_timeout']),
                        'message'           => $otpResult['error'] ?? 'Unable to send verification email right now. Please try again shortly.',
                    ];

                    if (!empty($otpResult['ambiguous_timeout']) && !empty($otpResult['challenge_token'])) {
                        $failurePayload['challenge_token'] = $otpResult['challenge_token'];
                        $failurePayload['masked_email'] = $otpResult['masked_email'];
                        $failurePayload['expires_in_seconds'] = $otpResult['expires_in_seconds'] ?? (EmailOtpService::EXPIRATION_MINUTES * 60);
                        $failurePayload['resend_in_seconds'] = $otpResult['resend_in_seconds'] ?? EmailOtpService::RESEND_COOLDOWN_SECONDS;
                    }

                    return response()->json($failurePayload, 503);
                }

                return response()->json([
                    'status'                => 'verification_required',
                    'verification_required' => true,
                    'message'               => 'Please enter the six-digit verification code sent to your email to activate your account.',
                    'challenge_token'       => $otpResult['challenge_token'],
                    'masked_email'          => $otpResult['masked_email'],
                    'expires_in_seconds'    => $otpResult['expires_in_seconds'] ?? 600,
                    'resend_in_seconds'     => $otpResult['resend_in_seconds'] ?? 60,
                ], 202);
            }
        }

        // Generate Sanctum Bearer Token for fully verified accounts
        $token = $user->createToken('mobile_app_token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Logged in successfully',
            'user'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'role'           => $user->role,
                'driver_profile' => $user->driverProfile,
            ],
            'token'   => $token,
        ], 200);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
            'otp'             => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $issuedToken = null;
        $verifiedUser = null;

        $result = EmailOtpService::verifyAndExecute(
            challengeToken: $request->challenge_token,
            submittedOtp: $request->otp,
            expectedPurpose: EmailOtpService::ALLOWED_REGISTRATION_VERIFICATION_PURPOSES,
            onVerified: function (?User $user, EmailOtpChallenge $challenge) use (&$issuedToken, &$verifiedUser): bool {
                if (
                    !$user
                    || !EmailOtpService::purposeMatches(
                        (string) $challenge->purpose,
                        EmailOtpService::ALLOWED_REGISTRATION_VERIFICATION_PURPOSES
                    )
                ) {
                    return false;
                }

                if (
                    $user->role !== User::ROLE_CUSTOMER
                    || in_array($user->account_status, [
                        User::STATUS_SUSPENDED,
                        User::STATUS_REJECTED,
                        User::STATUS_PENDING_REVIEW,
                    ], true)
                ) {
                    return false;
                }

                // Mark user verified and active
                $user->update([
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'account_status'    => User::STATUS_ACTIVE,
                ]);

                // Generate Sanctum Bearer Token only for valid registration/email_verification challenges
                $issuedToken = $user->createToken('mobile_app_token')->plainTextToken;
                $verifiedUser = $user;

                return true;
            }
        );

        if (!$result['success']) {
            return response()->json([
                'status'             => 'error',
                'message'            => $result['error'],
                'attempts_remaining' => $result['attempts_remaining'] ?? 0,
            ], $result['status']);
        }

        /** @var User $user */
        $user = $verifiedUser ?? $result['user'];

        return response()->json([
            'status'  => 'success',
            'message' => 'Email verified successfully! You are now logged in.',
            'user'    => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'role'           => $user->role,
                'driver_profile' => $user->driverProfile,
            ],
            'token'   => $issuedToken,
        ], 200);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = EmailOtpService::resendOtp(
            challengeToken: $request->challenge_token,
            expectedPurpose: EmailOtpService::ALLOWED_REGISTRATION_VERIFICATION_PURPOSES
        );

        if (!$result['success']) {
            return response()->json([
                'status'            => 'error',
                'message'           => $result['error'],
                'seconds_remaining' => $result['seconds_remaining'] ?? 0,
            ], $result['status']);
        }

        return response()->json([
            'status'             => 'success',
            'message'            => $result['message'],
            'resend_in_seconds'  => $result['resend_in_seconds'],
            'expires_in_seconds' => $result['expires_in_seconds'],
        ], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        if ($request->user() && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }
        
        return response()->json([
            'status'  => 'success',
            'message' => 'Logged out successfully'
        ], 200);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'driver_profile' => $user->driverProfile,
            ]
        ]);
    }

    public function toggleOnline(Request $request)
    {
        $user = $request->user();
        $profile = $user->driverProfile;

        if (!$profile) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver profile not found'
            ], 404);
        }

        $profile->is_online = !$profile->is_online;
        $profile->save();

        return response()->json([
            'status' => 'success',
            'is_online' => $profile->is_online,
            'message' => $profile->is_online ? 'You are now ONLINE' : 'You are now OFFLINE'
        ]);
    }

    // =============================================
    // PATIENT MOBILE PASSWORD RECOVERY (OTP-BASED)
    // =============================================

    /**
     * Determine whether the given user is an eligible active patient/customer account
     * allowed to use the mobile password recovery endpoints.
     */
    protected function isUserEligibleForPatientRecovery(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role !== User::ROLE_CUSTOMER) {
            return false;
        }

        if ($user->account_status !== User::STATUS_ACTIVE) {
            return false;
        }

        if ($user->email_verified_at === null) {
            return false;
        }

        return true;
    }

    /**
     * Create a non-authorizing dummy challenge state in shared cache so unknown or
     * ineligible emails receive an indistinguishable response and cooldown/attempt lifecycle
     * without creating database rows orresetting any account.
     */
    protected function createDummyResetChallenge(string $email): string
    {
        EmailOtpService::hashOtp((string) random_int(100000, 999999));
        $rawToken = Str::random(64);
        $tokenHash = EmailOtpService::hashChallengeToken($rawToken);
        $nowTs = now()->timestamp;
        $expiresAtTs = now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp;

        Cache::put(
            self::DUMMY_CACHE_PREFIX . $tokenHash,
            [
                'is_dummy'     => true,
                'masked_email' => EmailOtpService::maskEmail($email),
                'attempts'     => 0,
                'send_count'   => 1,
                'last_sent_at' => $nowTs,
                'expires_at'   => $expiresAtTs,
            ],
            now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES + 5)
        );

        return $rawToken;
    }

    /**
     * POST /api/auth/password/forgot
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $normalizedEmail = mb_strtolower(trim((string) $request->input('email')));
        $maskedEmail = EmailOtpService::maskEmail($normalizedEmail);

        /** @var User|null $user */
        $user = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();

        if ($this->isUserEligibleForPatientRecovery($user)) {
            $otpResult = EmailOtpService::createChallenge($user, EmailOtpService::PURPOSE_PASSWORD_RESET);

            if (!empty($otpResult['delivery_failed']) || empty($otpResult['challenge_token'])) {
                $challengeToken = $this->createDummyResetChallenge($normalizedEmail);
            } else {
                $challengeToken = $otpResult['challenge_token'];
            }
        } else {
            $challengeToken = $this->createDummyResetChallenge($normalizedEmail);
        }

        return response()->json([
            'status'             => 'verification_required',
            'message'            => self::GENERIC_PATIENT_RESET_REQUEST_MESSAGE,
            'challenge_token'    => $challengeToken,
            'masked_email'       => $maskedEmail,
            'expires_in_seconds' => EmailOtpService::EXPIRATION_MINUTES * 60,
            'resend_in_seconds'  => EmailOtpService::RESEND_COOLDOWN_SECONDS,
        ], 202);
    }

    /**
     * POST /api/auth/password/resend
     */
    public function resendPasswordOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $challengeToken = trim((string) $request->input('challenge_token'));
        $tokenHash = EmailOtpService::hashChallengeToken($challengeToken);
        $dummyCacheKey = self::DUMMY_CACHE_PREFIX . $tokenHash;

        // 1. Handle non-authorizing dummy challenge token identically
        $dummyContext = Cache::get($dummyCacheKey);
        if (is_array($dummyContext) && !empty($dummyContext['is_dummy'])) {
            $nowTs = now()->timestamp;
            if ($nowTs >= (int) ($dummyContext['expires_at'] ?? 0)) {
                Cache::forget($dummyCacheKey);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Verification code has expired. Please request a new code.',
                ], 410);
            }

            $elapsed = max(0, $nowTs - (int) ($dummyContext['last_sent_at'] ?? 0));
            if ($elapsed < EmailOtpService::RESEND_COOLDOWN_SECONDS) {
                $remaining = EmailOtpService::RESEND_COOLDOWN_SECONDS - $elapsed;
                return response()->json([
                    'status'            => 'error',
                    'message'           => "Please wait {$remaining} second(s) before requesting another code.",
                    'seconds_remaining' => $remaining,
                ], 429);
            }

            $sendCount = (int) ($dummyContext['send_count'] ?? 1);
            if ($sendCount >= EmailOtpService::MAX_RESENDS) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Maximum code requests reached for this session. Please request a new password reset.',
                ], 429);
            }

            EmailOtpService::hashOtp((string) random_int(100000, 999999));
            $dummyContext['send_count'] = $sendCount + 1;
            $dummyContext['attempts'] = 0;
            $dummyContext['last_sent_at'] = $nowTs;
            $dummyContext['expires_at'] = now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES)->timestamp;

            Cache::put(
                $dummyCacheKey,
                $dummyContext,
                now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES + 5)
            );

            return response()->json([
                'status'             => 'success',
                'message'            => self::GENERIC_PATIENT_RESEND_MESSAGE,
                'resend_in_seconds'  => EmailOtpService::RESEND_COOLDOWN_SECONDS,
                'expires_in_seconds' => EmailOtpService::EXPIRATION_MINUTES * 60,
            ], 200);
        }

        // 2. Inspect persisted challenge and enforce purpose + patient eligibility
        /** @var EmailOtpChallenge|null $challengeRecord */
        $challengeRecord = EmailOtpChallenge::with('user')
            ->where('challenge_token_hash', $tokenHash)
            ->first();

        if (!$challengeRecord) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid verification session.',
            ], 404);
        }

        if (!EmailOtpService::purposeMatches((string) $challengeRecord->purpose, EmailOtpService::PURPOSE_PASSWORD_RESET)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Verification purpose mismatch.',
            ], 400);
        }

        if (!$this->isUserEligibleForPatientRecovery($challengeRecord->user)) {
            $challengeRecord->update(['expires_at' => now()]);
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid verification session.',
            ], 404);
        }

        $result = EmailOtpService::resendOtp(
            challengeToken: $challengeToken,
            expectedPurpose: EmailOtpService::PURPOSE_PASSWORD_RESET
        );

        if (!$result['success']) {
            if (($result['status'] ?? 500) === 503) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Unable to send a new verification code right now. Your previous code remains valid, or you may try again shortly.',
                ], 503);
            }

            $payload = [
                'status'  => 'error',
                'message' => $result['error'],
            ];
            if (isset($result['seconds_remaining'])) {
                $payload['seconds_remaining'] = $result['seconds_remaining'];
            }

            return response()->json($payload, $result['status']);
        }

        return response()->json([
            'status'             => 'success',
            'message'            => self::GENERIC_PATIENT_RESEND_MESSAGE,
            'resend_in_seconds'  => $result['resend_in_seconds'],
            'expires_in_seconds' => $result['expires_in_seconds'],
        ], 200);
    }

    /**
     * POST /api/auth/password/verify-otp
     *
     * Validates that the submitted OTP is authentic, unexpired, and within attempt limits
     * for the provided password recovery challenge token WITHOUT consuming the challenge prematurely.
     * Allows mobile Screen 03 to verify the code and block navigation if invalid.
     */
    public function verifyPasswordResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string'],
            'otp'             => ['required', 'string', 'digits:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $challengeToken = trim((string) $request->input('challenge_token'));
        $submittedOtp = trim((string) $request->input('otp'));
        $tokenHash = EmailOtpService::hashChallengeToken($challengeToken);
        $dummyCacheKey = self::DUMMY_CACHE_PREFIX . $tokenHash;

        // 1. Handle non-authorizing dummy challenge token identically (never passes verification)
        $dummyContext = Cache::get($dummyCacheKey);
        if (is_array($dummyContext) && !empty($dummyContext['is_dummy'])) {
            EmailOtpService::hashOtp($submittedOtp);
            $nowTs = now()->timestamp;

            if ($nowTs >= (int) ($dummyContext['expires_at'] ?? 0)) {
                Cache::forget($dummyCacheKey);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Verification code has expired. Please request a new code.',
                ], 410);
            }

            $attempts = (int) ($dummyContext['attempts'] ?? 0);
            if ($attempts >= EmailOtpService::MAX_ATTEMPTS) {
                return response()->json([
                    'status'             => 'error',
                    'message'            => 'Too many invalid attempts. This session is locked. Please request a new code.',
                    'attempts_remaining' => 0,
                ], 429);
            }

            $attempts++;
            $dummyContext['attempts'] = $attempts;
            $remaining = max(0, EmailOtpService::MAX_ATTEMPTS - $attempts);

            Cache::put(
                $dummyCacheKey,
                $dummyContext,
                now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES + 5)
            );

            $errorMessage = $remaining > 0
                ? "Incorrect verification code. {$remaining} attempt(s) remaining."
                : 'Incorrect verification code. Maximum attempts exceeded. Please request a new code.';

            return response()->json([
                'status'             => 'error',
                'message'            => $errorMessage,
                'attempts_remaining' => $remaining,
            ], 422);
        }

        // 2. Validate real challenge token and OTP without consuming
        $result = EmailOtpService::verifyOtpWithoutConsuming(
            challengeToken: $challengeToken,
            submittedOtp: $submittedOtp,
            expectedPurpose: EmailOtpService::PURPOSE_PASSWORD_RESET
        );

        if (!$result['success']) {
            $payload = [
                'status'  => 'error',
                'message' => $result['error'],
            ];
            if (isset($result['attempts_remaining'])) {
                $payload['attempts_remaining'] = $result['attempts_remaining'];
            }
            return response()->json($payload, $result['status']);
        }

        // 3. Confirm user eligibility
        if (!$this->isUserEligibleForPatientRecovery($result['user'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid or expired verification session.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Verification code confirmed successfully.',
        ], 200);
    }

    /**
     * POST /api/auth/password/reset
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_token' => ['required', 'string'],
            'otp'             => ['required', 'string', 'digits:6'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $challengeToken = trim((string) $request->input('challenge_token'));
        $submittedOtp = trim((string) $request->input('otp'));
        $newPassword = (string) $request->input('password');
        $tokenHash = EmailOtpService::hashChallengeToken($challengeToken);
        $dummyCacheKey = self::DUMMY_CACHE_PREFIX . $tokenHash;

        // 1. Handle non-authorizing dummy challenge token identically (never resets any account)
        $dummyContext = Cache::get($dummyCacheKey);
        if (is_array($dummyContext) && !empty($dummyContext['is_dummy'])) {
            EmailOtpService::hashOtp($submittedOtp);
            $nowTs = now()->timestamp;

            if ($nowTs >= (int) ($dummyContext['expires_at'] ?? 0)) {
                Cache::forget($dummyCacheKey);
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Verification code has expired. Please request a new code.',
                ], 410);
            }

            $attempts = (int) ($dummyContext['attempts'] ?? 0);
            if ($attempts >= EmailOtpService::MAX_ATTEMPTS) {
                return response()->json([
                    'status'             => 'error',
                    'message'            => 'Too many invalid attempts. This session is locked. Please request a new code.',
                    'attempts_remaining' => 0,
                ], 429);
            }

            $attempts++;
            $dummyContext['attempts'] = $attempts;
            $remaining = max(0, EmailOtpService::MAX_ATTEMPTS - $attempts);

            Cache::put(
                $dummyCacheKey,
                $dummyContext,
                now()->addMinutes(EmailOtpService::EXPIRATION_MINUTES + 5)
            );

            $errorMessage = $remaining > 0
                ? "Incorrect verification code. {$remaining} attempt(s) remaining."
                : 'Incorrect verification code. Maximum attempts exceeded. Please request a new code.';

            return response()->json([
                'status'             => 'error',
                'message'            => $errorMessage,
                'attempts_remaining' => $remaining,
            ], 422);
        }

        // 2. Atomic verification & password reset for real challenges
        try {
            $verifyResult = EmailOtpService::verifyAndExecute(
                challengeToken: $challengeToken,
                submittedOtp: $submittedOtp,
                expectedPurpose: EmailOtpService::PURPOSE_PASSWORD_RESET,
                onVerified: function (?User $verifiedUser, EmailOtpChallenge $challenge) use ($newPassword): bool {
                    if (
                        !$verifiedUser
                        || !EmailOtpService::purposeMatches(
                            (string) $challenge->purpose,
                            EmailOtpService::PURPOSE_PASSWORD_RESET
                        )
                    ) {
                        return false;
                    }

                    /** @var User|null $lockedUser */
                    $lockedUser = User::where('id', $verifiedUser->id)->lockForUpdate()->first();

                    if (!$this->isUserEligibleForPatientRecovery($lockedUser)) {
                        return false;
                    }

                    // Update ONLY password and remember_token (preserve role, account_status, and email_verified_at)
                    $lockedUser->forceFill([
                        'password'       => Hash::make($newPassword),
                        'remember_token' => Str::random(60),
                    ])->save();

                    // Invalidate any other outstanding password_reset challenges for this user
                    EmailOtpChallenge::where('user_id', $lockedUser->id)
                        ->where('purpose', EmailOtpService::PURPOSE_PASSWORD_RESET)
                        ->whereNull('consumed_at')
                        ->where('id', '!=', $challenge->id)
                        ->update([
                            'expires_at'  => now(),
                            'consumed_at' => now(),
                        ]);

                    // Revoke all existing Sanctum personal access tokens for this user
                    if (Schema::hasTable('personal_access_tokens')) {
                        $lockedUser->tokens()->delete();
                    }

                    // Revoke all database sessions for this user across all guards
                    $this->revokeUserDatabaseSessions($lockedUser);

                    return true;
                }
            );
        } catch (\Throwable $e) {
            Log::error('Patient mobile password reset transaction rolled back for user: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'Unable to reset your password right now. Your verification code has not been used; please try again.',
            ], 500);
        }

        if (!$verifyResult['success']) {
            $payload = [
                'status'  => 'error',
                'message' => $verifyResult['error'],
            ];
            if (isset($verifyResult['attempts_remaining'])) {
                $payload['attempts_remaining'] = $verifyResult['attempts_remaining'];
            }

            return response()->json($payload, $verifyResult['status']);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Your password has been reset successfully. Please sign in with your new password.',
        ], 200);
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

        DB::table($sessionTable)
            ->where('user_id', $user->id)
            ->delete();

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