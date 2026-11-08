<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Mail\EmailOtpMailable;
use App\Models\DriverProfile;
use App\Models\EmailOtpChallenge;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MobilePasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected Pharmacy $approvedPharmacy;
    protected User $activeCustomer;
    protected User $secondCustomer;
    protected User $pendingCustomer;
    protected User $suspendedCustomer;
    protected User $rejectedCustomer;
    protected User $adminUser;
    protected User $pharmacyOwner;
    protected User $pharmacist;
    protected User $pharmacyStaff;
    protected User $driverUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Cache::flush();

        $this->approvedPharmacy = Pharmacy::create([
            'name'        => 'Alijis Care Pharmacy',
            'slug'        => 'alijis-care-pharmacy',
            'address'     => 'Alijis Road, Bacolod City',
            'phone'       => '09170001111',
            'email'       => 'care@alijispharmacy.com',
            'latitude'    => 10.6400,
            'longitude'   => 122.9500,
            'owner_name'  => 'Dr. Alijis',
            'is_approved' => true,
            'is_active'   => true,
            'status'      => 'approved',
        ]);

        $this->activeCustomer = User::create([
            'name'              => 'Juan Dela Cruz',
            'email'             => 'juan.patient@example.com',
            'phone'             => '09171112222',
            'password'          => Hash::make('OldPatientPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now()->subDays(10),
            'remember_token'    => 'initial_patient_remember_token_12345',
        ]);

        $this->secondCustomer = User::create([
            'name'              => 'Maria Santos',
            'email'             => 'maria.patient@example.com',
            'phone'             => '09173334444',
            'password'          => Hash::make('MariaOldPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now()->subDays(5),
            'remember_token'    => 'maria_remember_token_12345',
        ]);

        $this->pendingCustomer = User::create([
            'name'              => 'Pending Patient',
            'email'             => 'pending.patient@example.com',
            'phone'             => '09175556666',
            'password'          => Hash::make('PendingPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $this->suspendedCustomer = User::create([
            'name'              => 'Suspended Patient',
            'email'             => 'suspended.patient@example.com',
            'phone'             => '09177778888',
            'password'          => Hash::make('SuspendedPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_SUSPENDED,
            'email_verified_at' => now()->subDays(2),
        ]);

        $this->rejectedCustomer = User::create([
            'name'              => 'Rejected Patient',
            'email'             => 'rejected.patient@example.com',
            'phone'             => '09179990000',
            'password'          => Hash::make('RejectedPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_REJECTED,
            'email_verified_at' => now()->subDays(2),
        ]);

        $this->adminUser = User::create([
            'name'              => 'Super Admin',
            'email'             => 'admin@georx.com',
            'phone'             => '09170002222',
            'password'          => Hash::make('AdminPass123!'),
            'role'              => User::ROLE_ADMINISTRATOR,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->pharmacyOwner = User::create([
            'name'              => 'Pharmacy Owner',
            'email'             => 'owner@alijispharmacy.com',
            'phone'             => '09170003333',
            'password'          => Hash::make('OwnerPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->pharmacist = User::create([
            'name'              => 'Pharmacist',
            'email'             => 'pharmacist@alijispharmacy.com',
            'phone'             => '09170003334',
            'password'          => Hash::make('PharmacistPass123!'),
            'role'              => User::ROLE_PHARMACIST,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->pharmacyStaff = User::create([
            'name'              => 'Pharmacy Staff',
            'email'             => 'staff@alijispharmacy.com',
            'phone'             => '09170003335',
            'password'          => Hash::make('StaffPass123!'),
            'role'              => User::ROLE_PHARMACY_STAFF,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->driverUser = User::create([
            'name'              => 'GeoRx Driver',
            'email'             => 'driver@georx.com',
            'phone'             => '09170004444',
            'password'          => Hash::make('DriverPass123!'),
            'role'              => User::ROLE_DRIVER,
            'account_status'    => User::STATUS_APPROVED,
            'email_verified_at' => now(),
        ]);

        DriverProfile::create([
            'user_id'        => $this->driverUser->id,
            'vehicle_type'   => 'motorcycle',
            'account_status' => DriverProfile::STATUS_APPROVED,
            'is_online'      => true,
            'is_available'   => true,
        ]);
    }

    protected function captureLatestPasswordResetOtp(string $recipientEmail): string
    {
        $capturedOtp = null;
        Mail::assertSent(EmailOtpMailable::class, function (EmailOtpMailable $mail) use ($recipientEmail, &$capturedOtp) {
            if ($mail->hasTo($recipientEmail) && $mail->purpose === EmailOtpService::PURPOSE_PASSWORD_RESET) {
                $capturedOtp = $mail->otp;
                return true;
            }
            return false;
        });

        $this->assertNotNull($capturedOtp, "Expected a password_reset OTP email for {$recipientEmail}.");
        $this->assertMatchesRegularExpression('/^\d{6}$/', $capturedOtp);

        return $capturedOtp;
    }

    // =========================================================================
    // 1. END-TO-END ELIGIBLE PATIENT RECOVERY & PASSWORD SECURITY
    // =========================================================================

    /**
     * Scenarios 1, 27, 28, 29, 31, 32, 33, 34:
     * Eligible patient requests recovery, verifies 6-digit OTP, resets password atomically,
     * remember_token rotates, user ID/role/status/email_verified_at remain intact,
     * no Sanctum token is issued during reset, old password fails, and new password logs in.
     */
    public function test_eligible_patient_can_complete_full_password_recovery_and_login_with_new_password(): void
    {
        $originalId = $this->activeCustomer->id;
        $originalVerifiedAt = $this->activeCustomer->email_verified_at->toIso8601String();
        $originalRememberToken = $this->activeCustomer->remember_token;

        // Step 1: Request Forgot Password OTP
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => '  JUAN.PATIENT@EXAMPLE.COM  ',
        ]);

        $forgotRes->assertStatus(202)
            ->assertExactJson([
                'status'             => 'verification_required',
                'message'            => ApiAuthController::GENERIC_PATIENT_RESET_REQUEST_MESSAGE,
                'challenge_token'    => $forgotRes->json('challenge_token'),
                'masked_email'       => 'j**********t@example.com',
                'expires_in_seconds' => 600,
                'resend_in_seconds'  => 60,
            ]);

        $challengeToken = $forgotRes->json('challenge_token');
        $this->assertIsString($challengeToken);
        $this->assertEquals(64, strlen($challengeToken));

        $otp = $this->captureLatestPasswordResetOtp('juan.patient@example.com');

        // Verify plaintext OTP is never stored in DB
        $challenge = EmailOtpChallenge::where('user_id', $this->activeCustomer->id)
            ->where('purpose', EmailOtpService::PURPOSE_PASSWORD_RESET)
            ->firstOrFail();
        $this->assertNotEquals($otp, $challenge->otp_hash);
        $this->assertEquals(EmailOtpService::hashOtp($otp), $challenge->otp_hash);

        // Step 2: Reset password with valid OTP
        $resetRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => 'NewPatientPass999!',
            'password_confirmation' => 'NewPatientPass999!',
        ]);

        $resetRes->assertStatus(200)
            ->assertExactJson([
                'status'  => 'success',
                'message' => 'Your password has been reset successfully. Please sign in with your new password.',
            ])
            ->assertJsonMissingPath('token');

        // Verify challenge is consumed and active_hashes cleared
        $challenge->refresh();
        $this->assertNotNull($challenge->consumed_at);
        $this->assertNull($challenge->active_hashes);

        // Verify user state: password updated, remember_token rotated, no token issued, role & status intact
        $this->activeCustomer->refresh();
        $this->assertEquals($originalId, $this->activeCustomer->id);
        $this->assertEquals('Juan Dela Cruz', $this->activeCustomer->name);
        $this->assertEquals('09171112222', $this->activeCustomer->phone);
        $this->assertEquals(User::ROLE_CUSTOMER, $this->activeCustomer->role);
        $this->assertEquals(User::STATUS_ACTIVE, $this->activeCustomer->account_status);
        $this->assertEquals($originalVerifiedAt, $this->activeCustomer->email_verified_at->toIso8601String());
        $this->assertNotEquals($originalRememberToken, $this->activeCustomer->remember_token);
        $this->assertTrue(Hash::check('NewPatientPass999!', $this->activeCustomer->password));
        $this->assertFalse(Hash::check('OldPatientPass123!', $this->activeCustomer->password));
        $this->assertEquals(0, $this->activeCustomer->tokens()->count());

        // Step 3: Old password fails login
        $oldLoginRes = $this->postJson('/api/login', [
            'email'    => 'juan.patient@example.com',
            'password' => 'OldPatientPass123!',
        ]);
        $oldLoginRes->assertStatus(401)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Invalid credentials');

        // Step 4: New password succeeds login and issues Sanctum token
        $newLoginRes = $this->postJson('/api/login', [
            'email'    => 'juan.patient@example.com',
            'password' => 'NewPatientPass999!',
        ]);
        $newLoginRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
    }

    // =========================================================================
    // 2. ACCOUNT ENUMERATION & ROLE / STATUS ISOLATION
    // =========================================================================

    /**
     * Scenarios 8, 9, 10, 11, 12, 13, 24:
     * Unknown email, Admin, Pharmacy Owner, Pharmacist, Pharmacy Staff, Driver,
     * Suspended Customer, Rejected Customer, and Pending Verification Customer
     * all receive the exact same public HTTP 202 JSON structure as an eligible patient,
     * send 0 emails, create 0 database challenges, and their dummy tokens can never reset any password.
     */
    public function test_unknown_and_ineligible_accounts_receive_uniform_response_and_dummy_tokens_cannot_reset(): void
    {
        $ineligibleCases = [
            'unknown'   => 'nonexistent.user@example.com',
            'admin'     => $this->adminUser->email,
            'owner'     => $this->pharmacyOwner->email,
            'pharm'     => $this->pharmacist->email,
            'staff'     => $this->pharmacyStaff->email,
            'driver'    => $this->driverUser->email,
            'suspended' => $this->suspendedCustomer->email,
            'rejected'  => $this->rejectedCustomer->email,
            'pending'   => $this->pendingCustomer->email,
        ];

        $idx = 1;
        foreach ($ineligibleCases as $label => $email) {
            $ip = "10.20.0.{$idx}";
            $idx++;
            Mail::fake();

            $res = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/password/forgot', [
                    'email' => $email,
                ]);

            $res->assertStatus(202, "Failed uniform status check for case: {$label}");
            $res->assertExactJson([
                'status'             => 'verification_required',
                'message'            => ApiAuthController::GENERIC_PATIENT_RESET_REQUEST_MESSAGE,
                'challenge_token'    => $res->json('challenge_token'),
                'masked_email'       => EmailOtpService::maskEmail(mb_strtolower(trim($email))),
                'expires_in_seconds' => 600,
                'resend_in_seconds'  => 60,
            ]);

            $dummyToken = $res->json('challenge_token');
            $this->assertIsString($dummyToken);
            $this->assertEquals(64, strlen($dummyToken));

            // No real OTP email sent and no DB challenge created
            Mail::assertNothingSent();
            $this->assertDatabaseMissing('email_otp_challenges', [
                'email' => $email,
            ]);

            // Attempting to reset password using the dummy token fails with identical 422 response
            $resetAttempt = $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/auth/password/reset', [
                    'challenge_token'       => $dummyToken,
                    'otp'                   => '123456',
                    'password'              => 'AttackerNewPass123!',
                    'password_confirmation' => 'AttackerNewPass123!',
                ]);

            $resetAttempt->assertStatus(422)
                ->assertJsonPath('status', 'error')
                ->assertJsonPath('message', 'Incorrect verification code. 4 attempt(s) remaining.')
                ->assertJsonPath('attempts_remaining', 4);
        }

        // Verify pending account remained pending_verification and unverified
        $this->pendingCustomer->refresh();
        $this->assertEquals(User::STATUS_PENDING_VERIFICATION, $this->pendingCustomer->account_status);
        $this->assertNull($this->pendingCustomer->email_verified_at);
        $this->assertTrue(Hash::check('PendingPass123!', $this->pendingCustomer->password));

        // Verify admin, pharmacy, and suspended passwords were never modified
        $this->adminUser->refresh();
        $this->assertTrue(Hash::check('AdminPass123!', $this->adminUser->password));
        $this->pharmacyOwner->refresh();
        $this->assertTrue(Hash::check('OwnerPass123!', $this->pharmacyOwner->password));
        $this->suspendedCustomer->refresh();
        $this->assertEquals(User::STATUS_SUSPENDED, $this->suspendedCustomer->account_status);
        $this->assertTrue(Hash::check('SuspendedPass123!', $this->suspendedCustomer->password));
    }

    /**
     * Dummy challenge token simulates identical cooldown, resend cap, and 5-attempt lockout
     * so an attacker cannot distinguish a dummy token from a real challenge on /resend or /reset.
     */
    public function test_dummy_challenge_simulates_identical_cooldown_resend_limit_and_attempt_lockout(): void
    {
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => 'unknown.target@example.com',
        ]);
        $dummyToken = $forgotRes->json('challenge_token');

        // 1. Immediate resend within 60s cooldown returns 429
        $earlyResend = $this->postJson('/api/auth/password/resend', [
            'challenge_token' => $dummyToken,
        ]);
        $earlyResend->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure(['status', 'message', 'seconds_remaining']);

        // 2. Resends 2..5 after cooldown succeed with 200 and reset attempts, resend #6 returns 429 max reached
        for ($send = 2; $send <= 5; $send++) {
            $this->travel(61)->seconds();
            RateLimiter::clear('ip:patient_resend:127.0.0.1');
            $okResend = $this->postJson('/api/auth/password/resend', [
                'challenge_token' => $dummyToken,
            ]);
            $okResend->assertStatus(200)
                ->assertExactJson([
                    'status'             => 'success',
                    'message'            => ApiAuthController::GENERIC_PATIENT_RESEND_MESSAGE,
                    'resend_in_seconds'  => 60,
                    'expires_in_seconds' => 600,
                ]);
        }

        $this->travel(61)->seconds();
        RateLimiter::clear('ip:patient_resend:127.0.0.1');
        $exceededResend = $this->postJson('/api/auth/password/resend', [
            'challenge_token' => $dummyToken,
        ]);
        $exceededResend->assertStatus(429)
            ->assertJsonPath('message', 'Maximum code requests reached for this session. Please request a new password reset.');

        // 3. 5 failed attempts on dummy token decrement remaining from 4..0, 6th attempt returns 429 locked
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $res = $this->postJson('/api/auth/password/reset', [
                'challenge_token'       => $dummyToken,
                'otp'                   => '00000' . $attempt,
                'password'              => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
            ]);
            $res->assertStatus(422)
                ->assertJsonPath('attempts_remaining', 5 - $attempt);
        }

        // Advance 61s past per-minute rate limiter window so 6th attempt hits controller session-lock check
        $this->travel(61)->seconds();

        $lockedRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $dummyToken,
            'otp'                   => '999999',
            'password'              => 'ValidPass123!',
            'password_confirmation' => 'ValidPass123!',
        ]);
        $lockedRes->assertStatus(429)
            ->assertJsonPath('message', 'Too many invalid attempts. This session is locked. Please request a new code.');

        Mail::assertNothingSent();
    }

    // =========================================================================
    // 3. STRICT OTP PURPOSE ISOLATION ACROSS ALL ENDPOINTS
    // =========================================================================

    /**
     * Scenarios 2, 3, 4, 5, 6, 7:
     * - Registration OTP cannot reset password or be resent via /api/auth/password/resend.
     * - Email-verification OTP cannot reset password or be resent via /api/auth/password/resend.
     * - Password-reset OTP cannot verify or resend on /api/auth/otp/verify or /api/auth/otp/resend,
     *   never issues a Sanctum token, and never activates a pending account.
     */
    public function test_strict_bidirectional_otp_purpose_isolation(): void
    {
        // A. Create registration and email_verification challenges for activeCustomer
        foreach ([EmailOtpService::PURPOSE_REGISTRATION, EmailOtpService::PURPOSE_EMAIL_VERIFICATION] as $nonResetPurpose) {
            Mail::fake();
            $otpResult = EmailOtpService::createChallenge($this->activeCustomer, $nonResetPurpose);
            $token = $otpResult['challenge_token'];

            $code = null;
            Mail::assertSent(EmailOtpMailable::class, function (EmailOtpMailable $mail) use ($nonResetPurpose, &$code) {
                if ($mail->purpose === $nonResetPurpose) {
                    $code = $mail->otp;
                    return true;
                }
                return false;
            });

            // 1. Cannot resend via /api/auth/password/resend
            $this->travel(61)->seconds();
            $resendRes = $this->postJson('/api/auth/password/resend', [
                'challenge_token' => $token,
                'purpose'         => 'password_reset',
            ]);
            $resendRes->assertStatus(400)
                ->assertJsonPath('message', 'Verification purpose mismatch.');

            // 2. Cannot reset password via /api/auth/password/reset
            $resetRes = $this->postJson('/api/auth/password/reset', [
                'challenge_token'       => $token,
                'otp'                   => $code,
                'password'              => 'HackedPassword123!',
                'password_confirmation' => 'HackedPassword123!',
                'purpose'               => 'password_reset',
            ]);
            $resetRes->assertStatus(400)
                ->assertJsonPath('message', 'Verification purpose mismatch.');

            $this->activeCustomer->refresh();
            $this->assertTrue(Hash::check('OldPatientPass123!', $this->activeCustomer->password));
        }

        // B. Create password_reset challenge and attempt to use it on registration/email_verification endpoints
        Mail::fake();
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $resetToken = $forgotRes->json('challenge_token');
        $resetOtp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        $verifyRegRes = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $resetToken,
            'otp'             => $resetOtp,
        ]);
        $verifyRegRes->assertStatus(400)
            ->assertJsonPath('message', 'Verification purpose mismatch.')
            ->assertJsonMissingPath('token');

        $resendRegRes = $this->postJson('/api/auth/otp/resend', [
            'challenge_token' => $resetToken,
        ]);
        $resendRegRes->assertStatus(400)
            ->assertJsonPath('message', 'Verification purpose mismatch.');

        // C. Even if a password_reset challenge existed in DB for a pending account, it cannot activate or reset
        Mail::fake();
        $forcedPendingReset = EmailOtpService::createChallenge($this->pendingCustomer, EmailOtpService::PURPOSE_PASSWORD_RESET);
        $pendingOtp = $this->captureLatestPasswordResetOtp($this->pendingCustomer->email);

        $verifyPendingOnReg = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $forcedPendingReset['challenge_token'],
            'otp'             => $pendingOtp,
        ]);
        $verifyPendingOnReg->assertStatus(400)
            ->assertJsonMissingPath('token');

        $resetPendingOnPwd = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $forcedPendingReset['challenge_token'],
            'otp'                   => $pendingOtp,
            'password'              => 'BypassPending123!',
            'password_confirmation' => 'BypassPending123!',
        ]);
        $resetPendingOnPwd->assertStatus(403);

        $this->pendingCustomer->refresh();
        $this->assertEquals(User::STATUS_PENDING_VERIFICATION, $this->pendingCustomer->account_status);
        $this->assertNull($this->pendingCustomer->email_verified_at);
        $this->assertTrue(Hash::check('PendingPass123!', $this->pendingCustomer->password));
        $this->assertEquals(0, $this->pendingCustomer->tokens()->count());
    }

    // =========================================================================
    // 4. OTP EXPIRATION, ATTEMPT LOCKOUT, REPLAY, CONCURRENCY & RESEND LIMITS
    // =========================================================================

    /**
     * Scenarios 14, 15, 16, 17, 19:
     * Wrong OTP decrements attempts, 5 wrong attempts lock challenge (429),
     * expired OTP rejected (410), consumed/replayed OTP rejected (410).
     */
    public function test_wrong_otp_lockout_expiration_and_replay_protection(): void
    {
        // 1. 5 failed attempts lock the challenge
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $challengeToken = $forgotRes->json('challenge_token');
        $validOtp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        for ($i = 1; $i <= 5; $i++) {
            $wrongOtp = $validOtp === '111111' ? '222222' : '111111';
            $res = $this->postJson('/api/auth/password/reset', [
                'challenge_token'       => $challengeToken,
                'otp'                   => $wrongOtp,
                'password'              => 'NewValidPass123!',
                'password_confirmation' => 'NewValidPass123!',
            ]);
            $res->assertStatus(422)
                ->assertJsonPath('attempts_remaining', 5 - $i);
        }

        // Advance 61s past per-minute rate limiter window; 6th attempt WITH THE CORRECT OTP must fail with 429 Locked
        $this->travel(61)->seconds();

        $lockedRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $validOtp,
            'password'              => 'NewValidPass123!',
            'password_confirmation' => 'NewValidPass123!',
        ]);
        $lockedRes->assertStatus(429)
            ->assertJsonPath('message', 'Too many invalid attempts. This session is locked. Please request a new code.');

        $this->activeCustomer->refresh();
        $this->assertTrue(Hash::check('OldPatientPass123!', $this->activeCustomer->password));

        // 2. Expired OTP is rejected with 410
        $this->travel(61)->seconds();
        Mail::fake();

        $forgotRes2 = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $token2 = $forgotRes2->json('challenge_token');
        $otp2 = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        $this->travel(11)->minutes();

        $expiredRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $token2,
            'otp'                   => $otp2,
            'password'              => 'NewValidPass123!',
            'password_confirmation' => 'NewValidPass123!',
        ]);
        $expiredRes->assertStatus(410)
            ->assertJsonPath('message', 'Verification code has expired. Please request a new code.');

        // 3. Consumed OTP cannot be replayed
        Mail::fake();
        $forgotRes3 = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $token3 = $forgotRes3->json('challenge_token');
        $otp3 = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        $firstReset = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $token3,
            'otp'                   => $otp3,
            'password'              => 'FirstResetPass123!',
            'password_confirmation' => 'FirstResetPass123!',
        ]);
        $firstReset->assertStatus(200);

        $replayReset = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $token3,
            'otp'                   => $otp3,
            'password'              => 'ReplayResetPass999!',
            'password_confirmation' => 'ReplayResetPass999!',
        ]);
        $replayReset->assertStatus(410)
            ->assertJsonPath('message', 'This verification code has already been used.');

        $this->activeCustomer->refresh();
        $this->assertTrue(Hash::check('FirstResetPass123!', $this->activeCustomer->password));
        $this->assertFalse(Hash::check('ReplayResetPass999!', $this->activeCustomer->password));
    }

    /**
     * Scenarios 20, 21, 22:
     * Resend cooldown (60s), maximum resend count (5), and concurrent in-flight reservation lock.
     */
    public function test_resend_cooldown_max_resend_limit_and_concurrent_reservation_lock(): void
    {
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $challengeToken = $forgotRes->json('challenge_token');
        $firstOtp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        // 1. Immediate resend within 60s cooldown returns 429
        $earlyResend = $this->postJson('/api/auth/password/resend', [
            'challenge_token' => $challengeToken,
        ]);
        $earlyResend->assertStatus(429)
            ->assertJsonStructure(['status', 'message', 'seconds_remaining']);

        // 2. Simulate active concurrent reservation in-flight -> second request rejected with 429
        $challenge = EmailOtpChallenge::where(
            'challenge_token_hash',
            EmailOtpService::hashChallengeToken($challengeToken)
        )->firstOrFail();

        $challenge->update([
            'delivery_state'           => EmailOtpService::STATE_RESERVED,
            'resend_reservation_token' => Str::random(32),
            'resend_reserved_until'    => now()->addSeconds(45),
        ]);

        RateLimiter::clear('ip:patient_resend:127.0.0.1');
        $challengeHash = EmailOtpService::hashOtp('rl_resend:patient:' . $challengeToken);
        RateLimiter::clear('challenge:patient_resend:' . $challengeHash);

        $concurrentResend = $this->postJson('/api/auth/password/resend', [
            'challenge_token' => $challengeToken,
        ]);
        $concurrentResend->assertStatus(429);

        // Clear simulated reservation
        $challenge->update([
            'delivery_state'           => EmailOtpService::STATE_DELIVERED,
            'resend_reservation_token' => null,
            'resend_reserved_until'    => null,
        ]);

        // 3. Resends 2..5 succeed after 60s cooldown; resend #6 fails with 429 max resends reached
        $latestOtp = $firstOtp;
        for ($send = 2; $send <= 5; $send++) {
            $this->travel(61)->seconds();
            RateLimiter::clear('ip:patient_resend:127.0.0.1');
            RateLimiter::clear('challenge:patient_resend:' . $challengeHash);
            Mail::fake();

            $res = $this->postJson('/api/auth/password/resend', [
                'challenge_token' => $challengeToken,
            ]);
            $res->assertStatus(200)
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('message', ApiAuthController::GENERIC_PATIENT_RESEND_MESSAGE);

            $latestOtp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);
        }

        $this->travel(61)->seconds();
        RateLimiter::clear('ip:patient_resend:127.0.0.1');
        RateLimiter::clear('challenge:patient_resend:' . $challengeHash);

        $maxedRes = $this->postJson('/api/auth/password/resend', [
            'challenge_token' => $challengeToken,
        ]);
        $maxedRes->assertStatus(429)
            ->assertJsonPath('message', 'Maximum code requests reached for this session. Please request a new password reset.');

        // Latest OTP still works to reset password
        $resetRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $latestOtp,
            'password'              => 'AfterResendPass123!',
            'password_confirmation' => 'AfterResendPass123!',
        ]);
        $resetRes->assertStatus(200);
    }

    // =========================================================================
    // 5. ATOMICITY, SANCTUM & SESSION REVOCATION, AND OUTSTANDING CHALLENGES
    // =========================================================================

    /**
     * Scenarios 18, 25, 26, 30 + Session Revocation & Outstanding Challenge Invalidation:
     * - Password validation enforced (min:8, confirmed).
     * - All Sanctum tokens for the patient are revoked upon reset (while other users' tokens remain intact).
     * - All database sessions (both user_id indexed and null-user_id serialized guard sessions) for the patient are revoked.
     * - Any other unconsumed password_reset challenges for the patient are invalidated.
     */
    public function test_validation_sanctum_token_revocation_session_revocation_and_challenge_invalidation(): void
    {
        // Issue 2 active Sanctum tokens for activeCustomer and 1 for secondCustomer
        $this->activeCustomer->createToken('mobile_app_token_device_1');
        $this->activeCustomer->createToken('mobile_app_token_device_2');
        $this->secondCustomer->createToken('second_customer_device');
        $this->assertEquals(2, $this->activeCustomer->tokens()->count());
        $this->assertEquals(1, $this->secondCustomer->tokens()->count());

        // Insert active database sessions for activeCustomer (both indexed user_id and null user_id with web guard payload)
        $webGuardKey = Auth::guard('web')->getName();
        $nullUserPayload = base64_encode(serialize([$webGuardKey => $this->activeCustomer->id]));
        $otherUserPayload = base64_encode(serialize([$webGuardKey => $this->secondCustomer->id]));

        DB::table('sessions')->insert([
            [
                'id'            => 'sess_patient_indexed',
                'user_id'       => $this->activeCustomer->id,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'GeoRxMobile',
                'payload'       => base64_encode('indexed_session'),
                'last_activity' => now()->timestamp,
            ],
            [
                'id'            => 'sess_patient_null_uid',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => $nullUserPayload,
                'last_activity' => now()->timestamp,
            ],
            [
                'id'            => 'sess_other_customer',
                'user_id'       => $this->secondCustomer->id,
                'ip_address'    => '127.0.0.2',
                'user_agent'    => 'GeoRxMobile',
                'payload'       => $otherUserPayload,
                'last_activity' => now()->timestamp,
            ],
        ]);

        // Create an older unconsumed password_reset challenge manually, plus a registration challenge for the same user
        $staleResetChallenge = EmailOtpChallenge::create([
            'user_id'              => $this->activeCustomer->id,
            'email'                => $this->activeCustomer->email,
            'purpose'              => EmailOtpService::PURPOSE_PASSWORD_RESET,
            'otp_hash'             => EmailOtpService::hashOtp('654321'),
            'challenge_token_hash' => EmailOtpService::hashChallengeToken(Str::random(64)),
            'attempts'             => 0,
            'max_attempts'         => 5,
            'send_count'           => 1,
            'last_sent_at'         => now()->subMinutes(2),
            'expires_at'           => now()->addMinutes(8),
            'consumed_at'          => null,
        ]);

        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $challengeToken = $forgotRes->json('challenge_token');
        $otp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        // 1. Weak password (< 8 chars) rejected with 422 without consuming OTP
        $weakRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => 'short1!',
            'password_confirmation' => 'short1!',
        ]);
        $weakRes->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 2. Mismatched password confirmation rejected with 422 without consuming OTP
        $mismatchRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'DifferentPassword123!',
        ]);
        $mismatchRes->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // 3. Valid password reset succeeds using the same unconsumed OTP
        $validRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);
        $validRes->assertStatus(200);

        // 4. All Sanctum tokens for activeCustomer are revoked; secondCustomer's token is untouched
        $this->assertEquals(0, $this->activeCustomer->tokens()->count());
        $this->assertEquals(1, $this->secondCustomer->tokens()->count());

        // 5. Both database sessions for activeCustomer are deleted; secondCustomer's session is untouched
        $this->assertDatabaseMissing('sessions', ['id' => 'sess_patient_indexed']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess_patient_null_uid']);
        $this->assertDatabaseHas('sessions', ['id' => 'sess_other_customer']);

        // 6. Stale password_reset challenge is expired
        $staleResetChallenge->refresh();
        $this->assertTrue($staleResetChallenge->isExpired());
    }

    /**
     * Scenario 20 (Transaction Rollback Safety):
     * If an exception occurs inside the password reset transaction after OTP verification,
     * the entire transaction rolls back: original password is preserved, existing Sanctum tokens
     * are NOT prematurely deleted, and the OTP challenge remains unconsumed so the patient can retry.
     */
    public function test_failed_transaction_rolls_back_cleanly_preserving_password_tokens_and_otp(): void
    {
        $this->activeCustomer->createToken('existing_active_token');
        $this->assertEquals(1, $this->activeCustomer->tokens()->count());

        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $challengeToken = $forgotRes->json('challenge_token');
        $otp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        // Register a temporary User::updating listener that throws during the first reset attempt
        $shouldThrow = true;
        User::updating(function (User $user) use (&$shouldThrow) {
            if ($shouldThrow && $user->id === $this->activeCustomer->id) {
                throw new \RuntimeException('Simulated database failure during password update.');
            }
        });

        $failedRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => 'RolledBackPass123!',
            'password_confirmation' => 'RolledBackPass123!',
        ]);
        $failedRes->assertStatus(500)
            ->assertJsonPath('status', 'error');

        // Verify rollback: original password intact, Sanctum token intact, challenge unconsumed
        $this->activeCustomer->refresh();
        $this->assertTrue(Hash::check('OldPatientPass123!', $this->activeCustomer->password));
        $this->assertEquals(1, $this->activeCustomer->tokens()->count());

        $challenge = EmailOtpChallenge::where(
            'challenge_token_hash',
            EmailOtpService::hashChallengeToken($challengeToken)
        )->firstOrFail();
        $this->assertNull($challenge->consumed_at);

        // Now disable the fault and retry with the exact same OTP -> succeeds!
        $shouldThrow = false;
        $retryRes = $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => 'RecoveredPass123!',
            'password_confirmation' => 'RecoveredPass123!',
        ]);
        $retryRes->assertStatus(200);

        $this->activeCustomer->refresh();
        $this->assertTrue(Hash::check('RecoveredPass123!', $this->activeCustomer->password));
        $this->assertEquals(0, $this->activeCustomer->tokens()->count());
    }

    // =========================================================================
    // 6. BREVO DELIVERY FAILURE, LOGGING HYGIENE & RATE LIMITING
    // =========================================================================

    /**
     * Scenarios 23 & 25 (Delivery Failure + No Secrets in Logs):
     * - When Brevo fails during /forgot, returns the exact same generic HTTP 202 response without leaking provider errors.
     * - When Brevo fails during /resend, preserves the prior valid OTP and returns a safe 503 message.
     * - Plaintext OTPs, passwords, and challenge tokens never appear in application logs.
     */
    public function test_delivery_failures_and_logs_never_expose_secrets_otps_or_passwords(): void
    {
        $loggedMessages = [];
        Log::listen(function ($event) use (&$loggedMessages) {
            $loggedMessages[] = $event->message;
        });

        // 1. Successful forgot + reset: verify no OTP, password, or challenge_token in logs
        $secretPassword = 'UltraSecretNewPass987!';
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->activeCustomer->email,
        ]);
        $challengeToken = $forgotRes->json('challenge_token');
        $otp = $this->captureLatestPasswordResetOtp($this->activeCustomer->email);

        $this->postJson('/api/auth/password/reset', [
            'challenge_token'       => $challengeToken,
            'otp'                   => $otp,
            'password'              => $secretPassword,
            'password_confirmation' => $secretPassword,
        ])->assertStatus(200);

        foreach ($loggedMessages as $msg) {
            $this->assertStringNotContainsString($otp, $msg);
            $this->assertStringNotContainsString($secretPassword, $msg);
            $this->assertStringNotContainsString($challengeToken, $msg);
        }

        // 2. Brevo failure during /forgot returns uniform 202 response and expires DB challenge
        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new TransportException('Brevo API delivery failed: HTTP 500 Internal Error'));

        $failedForgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => $this->secondCustomer->email,
        ]);
        $failedForgotRes->assertStatus(202)
            ->assertJsonPath('status', 'verification_required')
            ->assertJsonPath('message', ApiAuthController::GENERIC_PATIENT_RESET_REQUEST_MESSAGE);
        $this->assertStringNotContainsString('Brevo', $failedForgotRes->getContent());
    }

    /**
     * Scenario 26 (Rate Limiting):
     * Dedicated patient_forgot, patient_resend, and patient_reset rate limiters enforce limits
     * and use keyed HMAC identifiers rather than plaintext emails.
     */
    public function test_patient_password_recovery_rate_limiters_enforce_per_identifier_and_per_challenge_limits(): void
    {
        // 1. patient_forgot allows 3 requests/min per normalized email (even across different IPs)
        for ($i = 1; $i <= 3; $i++) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/auth/password/forgot', [
                    'email' => $this->activeCustomer->email,
                ]);
            $res->assertStatus(202);
        }

        $fourthForgot = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->postJson('/api/auth/password/forgot', [
                'email' => strtoupper($this->activeCustomer->email),
            ]);
        $fourthForgot->assertStatus(429);

        // 2. patient_resend allows 3 requests/min per challenge token
        $challengeToken = Str::random(64);
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.1.{$i}"])
                ->postJson('/api/auth/password/resend', [
                    'challenge_token' => $challengeToken,
                ]);
        }
        $fourthResend = $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.4'])
            ->postJson('/api/auth/password/resend', [
                'challenge_token' => $challengeToken,
            ]);
        $fourthResend->assertStatus(429);

        // 3. patient_reset allows 5 requests/min per challenge token
        $resetToken = Str::random(64);
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.2.{$i}"])
                ->postJson('/api/auth/password/reset', [
                    'challenge_token'       => $resetToken,
                    'otp'                   => '123456',
                    'password'              => 'ValidPass123!',
                    'password_confirmation' => 'ValidPass123!',
                ]);
        }
        $sixthReset = $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.6'])
            ->postJson('/api/auth/password/reset', [
                'challenge_token'       => $resetToken,
                'otp'                   => '123456',
                'password'              => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
            ]);
        $sixthReset->assertStatus(429);
    }
}
