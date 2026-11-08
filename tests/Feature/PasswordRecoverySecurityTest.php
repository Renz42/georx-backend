<?php

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Mail\EmailOtpMailable;
use App\Models\DriverProfile;
use App\Models\EmailOtpChallenge;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\EmailOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordRecoverySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                DB::statement("ATTACH ':memory:' AS information_schema");
                DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {
            }
        }
    }

    protected Pharmacy $approvedPharmacy;
    protected Pharmacy $unapprovedPharmacy;
    protected User $adminUser;
    protected User $pharmacyOwner;
    protected User $pharmacist;
    protected User $pharmacyStaff;
    protected User $unapprovedPharmacyOwner;
    protected User $customerUser;
    protected User $driverUser;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

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

        $this->unapprovedPharmacy = Pharmacy::create([
            'name'        => 'Pending Alijis Pharmacy',
            'slug'        => 'pending-alijis-pharmacy',
            'address'     => 'Alijis Road, Bacolod City',
            'phone'       => '09170009999',
            'email'       => 'pending@alijispharmacy.com',
            'latitude'    => 10.6410,
            'longitude'   => 122.9510,
            'owner_name'  => 'Pending Owner',
            'is_approved' => false,
            'is_active'   => false,
        ]);

        $this->adminUser = User::create([
            'name'              => 'Super Administrator',
            'email'             => 'admin@georx.com',
            'phone'             => '09170002222',
            'password'          => Hash::make('OldAdminPass123!'),
            'role'              => User::ROLE_ADMINISTRATOR,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'remember_token'    => 'initial_admin_remember_token_12345',
        ]);

        $this->pharmacyOwner = User::create([
            'name'              => 'Pharmacy Owner',
            'email'             => 'owner@alijispharmacy.com',
            'phone'             => '09170003333',
            'password'          => Hash::make('OldOwnerPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
            'remember_token'    => 'initial_owner_remember_token_12345',
        ]);

        $this->pharmacist = User::create([
            'name'              => 'Registered Pharmacist',
            'email'             => 'pharmacist@alijispharmacy.com',
            'phone'             => '09170003334',
            'password'          => Hash::make('OldPharmacistPass123!'),
            'role'              => User::ROLE_PHARMACIST,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->pharmacyStaff = User::create([
            'name'              => 'Pharmacy Staff',
            'email'             => 'staff@alijispharmacy.com',
            'phone'             => '09170003335',
            'password'          => Hash::make('OldStaffPass123!'),
            'role'              => User::ROLE_PHARMACY_STAFF,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->unapprovedPharmacyOwner = User::create([
            'name'              => 'Unapproved Owner',
            'email'             => 'unapproved@alijispharmacy.com',
            'phone'             => '09170003336',
            'password'          => Hash::make('UnapprovedPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => $this->unapprovedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->customerUser = User::create([
            'name'              => 'Normal Customer',
            'email'             => 'customer@example.com',
            'phone'             => '09170004444',
            'password'          => Hash::make('CustomerPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->driverUser = User::create([
            'name'              => 'Approved Driver',
            'email'             => 'driver@georx.com',
            'phone'             => '09170005555',
            'password'          => Hash::make('DriverPass123!'),
            'role'              => User::ROLE_DRIVER,
            'account_status'    => User::STATUS_APPROVED,
            'email_verified_at' => now(),
        ]);

        DriverProfile::create([
            'user_id'        => $this->driverUser->id,
            'vehicle_type'   => 'motorcycle',
            'account_status' => DriverProfile::STATUS_APPROVED,
            'is_online'      => false,
            'is_available'   => true,
        ]);
    }

    protected function getLastSentOtpFor(string $email): ?string
    {
        $sent = Mail::sent(EmailOtpMailable::class, function (EmailOtpMailable $mail) use ($email) {
            return $mail->hasTo($email);
        });

        if ($sent->isEmpty()) {
            return null;
        }

        return $sent->last()->otp;
    }

    // =========================================================================
    // PORTAL RENDERING & CSRF PROTECTION (Tests 1–5)
    // =========================================================================

    /** 1. Admin Forgot Password page renders */
    public function test_admin_forgot_password_page_renders_correctly(): void
    {
        $response = $this->get(route('admin.password.request'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.admin-forgot-password');
        $response->assertSee(route('admin.password.email'));
        $response->assertSee('name="_token"', false);
        $response->assertSee('System Administration');
        $response->assertSee('Forgot Password');
    }

    /** 2. Pharmacy Forgot Password page renders */
    public function test_pharmacy_forgot_password_page_renders_correctly(): void
    {
        $response = $this->get(route('pharmacy.password.request'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.pharmacy-forgot-password');
        $response->assertSee(route('pharmacy.password.email'));
        $response->assertSee('name="_token"', false);
        $response->assertSee('Pharmacy Portal');
        $response->assertSee('Forgot Password');
    }

    /** 3. Admin reset page handles missing challenge safely */
    public function test_admin_reset_page_redirects_when_session_challenge_is_missing(): void
    {
        $response = $this->get(route('admin.password.reset'));

        $response->assertRedirect(route('admin.password.request'));
        $response->assertSessionHas('error');
    }

    /** 4. Pharmacy reset page handles missing challenge safely */
    public function test_pharmacy_reset_page_redirects_when_session_challenge_is_missing(): void
    {
        $response = $this->get(route('pharmacy.password.reset'));

        $response->assertRedirect(route('pharmacy.password.request'));
        $response->assertSessionHas('error');
    }

    /** 5. CSRF protection is preserved across all password recovery endpoints */
    public function test_csrf_protection_is_enforced_on_password_recovery_endpoints(): void
    {
        $csrfMiddleware = app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $excluded = $csrfMiddleware->getExcludedPaths();

        $this->assertNotContains('admin/forgot-password', $excluded);
        $this->assertNotContains('admin/reset-password', $excluded);
        $this->assertNotContains('admin/reset-password/resend', $excluded);
        $this->assertNotContains('pharmacy/forgot-password', $excluded);
        $this->assertNotContains('pharmacy/reset-password', $excluded);
        $this->assertNotContains('pharmacy/reset-password/resend', $excluded);

        // Verify reset forms render CSRF tokens and never expose challenge tokens in HTML
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $resetView = $this->get(route('admin.password.reset'));
        $resetView->assertStatus(200);
        $resetView->assertSee('name="_token"', false);
        $sessionContext = session(AuthController::ADMIN_RESET_SESSION_KEY);
        $this->assertNotEmpty($sessionContext['challenge_token']);
        $resetView->assertDontSee($sessionContext['challenge_token'], false);
    }

    // =========================================================================
    // OTP CREATION & ACCOUNT ENUMERATION PROTECTION (Tests 6–13)
    // =========================================================================

    /** 6. Eligible admin can request a password-reset OTP */
    public function test_eligible_admin_can_request_password_reset_otp(): void
    {
        $response = $this->post(route('admin.password.email'), [
            'email' => $this->adminUser->email,
        ]);

        $response->assertRedirect(route('admin.password.reset'));
        $response->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        Mail::assertSent(EmailOtpMailable::class, function (EmailOtpMailable $mail) {
            return $mail->hasTo($this->adminUser->email)
                && $mail->purpose === 'password_reset'
                && $mail->envelope()->subject === 'GEORX — Password Reset Verification Code'
                && strlen($mail->otp) === 6
                && ctype_digit($mail->otp);
        });

        $challenge = EmailOtpChallenge::where('user_id', $this->adminUser->id)
            ->where('purpose', 'password_reset')
            ->first();
        $this->assertNotNull($challenge);
        $this->assertNull($challenge->consumed_at);
    }

    /** 7. Eligible pharmacy owner can request a password-reset OTP */
    public function test_eligible_pharmacy_owner_can_request_password_reset_otp(): void
    {
        $response = $this->post(route('pharmacy.password.email'), [
            'email' => $this->pharmacyOwner->email,
        ]);

        $response->assertRedirect(route('pharmacy.password.reset'));
        $response->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        Mail::assertSent(EmailOtpMailable::class, function (EmailOtpMailable $mail) {
            return $mail->hasTo($this->pharmacyOwner->email) && $mail->purpose === 'password_reset';
        });

        $this->assertDatabaseHas('email_otp_challenges', [
            'user_id' => $this->pharmacyOwner->id,
            'purpose' => 'password_reset',
        ]);
    }

    /** 8. Eligible pharmacist can request a password-reset OTP */
    public function test_eligible_pharmacist_can_request_password_reset_otp(): void
    {
        $response = $this->post(route('pharmacy.password.email'), [
            'email' => $this->pharmacist->email,
        ]);

        $response->assertRedirect(route('pharmacy.password.reset'));
        $response->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        Mail::assertSent(EmailOtpMailable::class, function (EmailOtpMailable $mail) {
            return $mail->hasTo($this->pharmacist->email) && $mail->purpose === 'password_reset';
        });
    }

    /** 9. Eligible pharmacy staff can request a password-reset OTP */
    public function test_eligible_pharmacy_staff_can_request_password_reset_otp(): void
    {
        $response = $this->post(route('pharmacy.password.email'), [
            'email' => $this->pharmacyStaff->email,
        ]);

        $response->assertRedirect(route('pharmacy.password.reset'));
        $response->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        Mail::assertSent(EmailOtpMailable::class, function (EmailOtpMailable $mail) {
            return $mail->hasTo($this->pharmacyStaff->email) && $mail->purpose === 'password_reset';
        });
    }

    /** 10. Unknown email receives the same generic response without creating a challenge */
    public function test_unknown_email_receives_identical_generic_response_and_creates_no_challenge(): void
    {
        $adminRes = $this->post(route('admin.password.email'), [
            'email' => 'nonexistent_admin@georx.com',
        ]);
        $adminRes->assertRedirect(route('admin.password.reset'));
        $adminRes->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        $pharmacyRes = $this->post(route('pharmacy.password.email'), [
            'email' => 'nonexistent_pharmacy@georx.com',
        ]);
        $pharmacyRes->assertRedirect(route('pharmacy.password.reset'));
        $pharmacyRes->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        Mail::assertNothingSent();
        $this->assertEquals(0, EmailOtpChallenge::count());
    }

    /** 11. Wrong-portal email receives the same generic response and creates no challenge */
    public function test_wrong_portal_email_receives_identical_generic_response_and_creates_no_challenge(): void
    {
        // Pharmacy owner submitting on Admin Forgot Password
        $res1 = $this->post(route('admin.password.email'), [
            'email' => $this->pharmacyOwner->email,
        ]);
        $res1->assertRedirect(route('admin.password.reset'));
        $res1->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        // Admin submitting on Pharmacy Forgot Password
        $res2 = $this->post(route('pharmacy.password.email'), [
            'email' => $this->adminUser->email,
        ]);
        $res2->assertRedirect(route('pharmacy.password.reset'));
        $res2->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        Mail::assertNothingSent();
        $this->assertEquals(0, EmailOtpChallenge::count());
    }

    /** 12 & 13. Customer, driver, and unapproved pharmacy accounts cannot request portal password recovery and never generate real challenges */
    public function test_customer_driver_and_unapproved_pharmacy_accounts_never_generate_real_reset_challenges(): void
    {
        foreach ([$this->customerUser->email, $this->driverUser->email, $this->unapprovedPharmacyOwner->email] as $email) {
            $adminRes = $this->post(route('admin.password.email'), ['email' => $email]);
            $adminRes->assertRedirect(route('admin.password.reset'));
            $adminRes->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

            $pharmacyRes = $this->post(route('pharmacy.password.email'), ['email' => $email]);
            $pharmacyRes->assertRedirect(route('pharmacy.password.reset'));
            $pharmacyRes->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);
        }

        Mail::assertNothingSent();
        $this->assertEquals(0, EmailOtpChallenge::count());
    }

    // =========================================================================
    // OTP VERIFICATION & SECURITY BOUNDARIES (Tests 14–24)
    // =========================================================================

    /** 14. Correct OTP permits password reset for both Admin and Pharmacy */
    public function test_correct_otp_permits_password_reset_for_admin_and_pharmacy(): void
    {
        // Admin password reset
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $adminOtp = $this->getLastSentOtpFor($this->adminUser->email);
        $this->assertNotNull($adminOtp);

        $adminResetRes = $this->post(route('admin.password.update'), [
            'otp'                   => $adminOtp,
            'password'              => 'NewAdminStrongPass99!',
            'password_confirmation' => 'NewAdminStrongPass99!',
        ]);
        $adminResetRes->assertRedirect(route('admin.login'));
        $adminResetRes->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewAdminStrongPass99!', $this->adminUser->fresh()->password));

        // Pharmacy password reset
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $pharmacyOtp = $this->getLastSentOtpFor($this->pharmacyOwner->email);
        $this->assertNotNull($pharmacyOtp);

        $pharmacyResetRes = $this->post(route('pharmacy.password.update'), [
            'otp'                   => $pharmacyOtp,
            'password'              => 'NewOwnerStrongPass99!',
            'password_confirmation' => 'NewOwnerStrongPass99!',
        ]);
        $pharmacyResetRes->assertRedirect(route('pharmacy.login'));
        $pharmacyResetRes->assertSessionHas('success');
        $this->assertTrue(Hash::check('NewOwnerStrongPass99!', $this->pharmacyOwner->fresh()->password));
    }

    /** 15. Incorrect OTP is rejected */
    public function test_incorrect_otp_is_rejected_and_does_not_update_password(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);

        $res = $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
            'otp'                   => '000000',
            'password'              => 'AttackerPassword123!',
            'password_confirmation' => 'AttackerPassword123!',
        ]);

        $res->assertRedirect(route('admin.password.reset'));
        $res->assertSessionHasErrors(['otp']);
        $this->assertTrue(Hash::check('OldAdminPass123!', $this->adminUser->fresh()->password));
    }

    /** 16. Expired OTP is rejected */
    public function test_expired_otp_is_rejected(): void
    {
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $otp = $this->getLastSentOtpFor($this->pharmacyOwner->email);

        EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->update([
            'expires_at' => now()->subMinute(),
        ]);

        $res = $this->post(route('pharmacy.password.update'), [
            'otp'                   => $otp,
            'password'              => 'NewOwnerPass123!',
            'password_confirmation' => 'NewOwnerPass123!',
        ]);

        $res->assertRedirect(route('pharmacy.password.request'));
        $res->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldOwnerPass123!', $this->pharmacyOwner->fresh()->password));
    }

    /** 17. OTP cannot be reused */
    public function test_otp_cannot_be_reused_after_consumption(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $otp = $this->getLastSentOtpFor($this->adminUser->email);
        $savedSessionContext = session(AuthController::ADMIN_RESET_SESSION_KEY);

        // First use succeeds
        $firstRes = $this->post(route('admin.password.update'), [
            'otp'                   => $otp,
            'password'              => 'FirstResetPass123!',
            'password_confirmation' => 'FirstResetPass123!',
        ]);
        $firstRes->assertRedirect(route('admin.login'));

        // Replay attempt even if session context is restored
        $replayRes = $this->withSession([AuthController::ADMIN_RESET_SESSION_KEY => $savedSessionContext])
            ->post(route('admin.password.update'), [
                'otp'                   => $otp,
                'password'              => 'SecondReplayPass999!',
                'password_confirmation' => 'SecondReplayPass999!',
            ]);

        $replayRes->assertRedirect(route('admin.password.request'));
        $replayRes->assertSessionHas('error');
        $this->assertTrue(Hash::check('FirstResetPass123!', $this->adminUser->fresh()->password));
        $this->assertFalse(Hash::check('SecondReplayPass999!', $this->adminUser->fresh()->password));
    }

    /** 18. Five failed attempts enforce lockout */
    public function test_five_failed_attempts_enforce_lockout_even_if_correct_otp_is_submitted_later(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $correctOtp = $this->getLastSentOtpFor($this->adminUser->email);

        for ($i = 0; $i < 5; $i++) {
            $wrongOtp = $correctOtp === '999999' ? '888888' : '999999';
            $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
                'otp'                   => $wrongOtp,
                'password'              => 'LockoutTestPass123!',
                'password_confirmation' => 'LockoutTestPass123!',
            ]);
        }

        // Advance 65 seconds past route rate limiter window (still well within 10-minute OTP window)
        $this->travel(65)->seconds();

        // 6th attempt using the actual correct OTP must still be rejected due to 5-attempt challenge lockout
        $lockedRes = $this->post(route('admin.password.update'), [
            'otp'                   => $correctOtp,
            'password'              => 'LockoutTestPass123!',
            'password_confirmation' => 'LockoutTestPass123!',
        ]);

        $lockedRes->assertRedirect(route('admin.password.request'));
        $lockedRes->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldAdminPass123!', $this->adminUser->fresh()->password));
    }

    /** 19. Resend cooldown is respected */
    public function test_resend_cooldown_is_enforced_and_allows_resend_after_60_seconds(): void
    {
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $firstOtp = $this->getLastSentOtpFor($this->pharmacyOwner->email);

        // Immediate resend within 60-second cooldown is blocked
        $earlyResend = $this->from(route('pharmacy.password.reset'))->post(route('pharmacy.password.resend'));
        $earlyResend->assertRedirect(route('pharmacy.password.reset'));
        $earlyResend->assertSessionHas('error');

        // Advance time by 61 seconds
        $this->travel(61)->seconds();

        $validResend = $this->from(route('pharmacy.password.reset'))->post(route('pharmacy.password.resend'));
        $validResend->assertRedirect(route('pharmacy.password.reset'));
        $validResend->assertSessionHas('status', AuthController::GENERIC_RESEND_MESSAGE);

        $secondOtp = $this->getLastSentOtpFor($this->pharmacyOwner->email);
        $this->assertNotNull($secondOtp);
        $challenge = EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->first();
        $this->assertEquals(2, $challenge->send_count);
    }

    /** 20. Maximum resend limit is enforced */
    public function test_maximum_resend_limit_is_enforced(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);

        EmailOtpChallenge::where('user_id', $this->adminUser->id)->update([
            'send_count'   => EmailOtpService::MAX_RESENDS,
            'last_sent_at' => now()->subSeconds(65),
        ]);

        $context = session(AuthController::ADMIN_RESET_SESSION_KEY);
        $context['send_count'] = EmailOtpService::MAX_RESENDS;
        $context['last_sent_at'] = now()->subSeconds(65)->timestamp;

        $res = $this->withSession([AuthController::ADMIN_RESET_SESSION_KEY => $context])
            ->from(route('admin.password.reset'))
            ->post(route('admin.password.resend'));

        $res->assertRedirect(route('admin.password.reset'));
        $res->assertSessionHas('error', 'Maximum code requests reached for this session. Please request a new password reset.');
    }

    /** 21. Admin challenge cannot reset a Pharmacy account */
    public function test_admin_challenge_cannot_be_used_on_pharmacy_portal(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $adminOtp = $this->getLastSentOtpFor($this->adminUser->email);
        $adminContext = session(AuthController::ADMIN_RESET_SESSION_KEY);

        // Attempt to transplant admin challenge token into pharmacy session context
        $tamperedPharmacyContext = array_merge($adminContext, ['portal' => 'pharmacy']);

        $res = $this->withSession([AuthController::PHARMACY_RESET_SESSION_KEY => $tamperedPharmacyContext])
            ->post(route('pharmacy.password.update'), [
                'otp'                   => $adminOtp,
                'password'              => 'CrossPortalPass123!',
                'password_confirmation' => 'CrossPortalPass123!',
            ]);

        $res->assertRedirect(route('pharmacy.password.request'));
        $res->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldAdminPass123!', $this->adminUser->fresh()->password));
        $this->assertTrue(Hash::check('OldOwnerPass123!', $this->pharmacyOwner->fresh()->password));
    }

    /** 22. Pharmacy challenge cannot reset an Admin account */
    public function test_pharmacy_challenge_cannot_be_used_on_admin_portal(): void
    {
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $pharmacyOtp = $this->getLastSentOtpFor($this->pharmacyOwner->email);
        $pharmacyContext = session(AuthController::PHARMACY_RESET_SESSION_KEY);

        // Attempt to transplant pharmacy challenge token into admin session context
        $tamperedAdminContext = array_merge($pharmacyContext, ['portal' => 'admin']);

        $res = $this->withSession([AuthController::ADMIN_RESET_SESSION_KEY => $tamperedAdminContext])
            ->post(route('admin.password.update'), [
                'otp'                   => $pharmacyOtp,
                'password'              => 'CrossPortalPass123!',
                'password_confirmation' => 'CrossPortalPass123!',
            ]);

        $res->assertRedirect(route('admin.password.request'));
        $res->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldOwnerPass123!', $this->pharmacyOwner->fresh()->password));
        $this->assertTrue(Hash::check('OldAdminPass123!', $this->adminUser->fresh()->password));
    }

    /** 23. Missing or synthetic dummy challenge state cannot reset any password */
    public function test_missing_or_dummy_challenge_state_cannot_reset_any_password(): void
    {
        // Request with unknown/wrong-role email creates dummy session state
        $this->post(route('admin.password.email'), ['email' => $this->customerUser->email]);

        $res = $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
            'otp'                   => '123456',
            'password'              => 'NewCustomerPass123!',
            'password_confirmation' => 'NewCustomerPass123!',
        ]);

        $res->assertRedirect(route('admin.password.reset'));
        $res->assertSessionHasErrors(['otp']);
        $this->assertTrue(Hash::check('CustomerPass123!', $this->customerUser->fresh()->password));
    }

    /** 24. Another user's session cannot use a challenge (user_id binding verification) */
    public function test_mismatched_session_user_id_cannot_consume_challenge(): void
    {
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $otp = $this->getLastSentOtpFor($this->pharmacyOwner->email);
        $context = session(AuthController::PHARMACY_RESET_SESSION_KEY);

        // Tamper user_id in session context to point to pharmacist while holding owner's challenge token
        $context['user_id'] = $this->pharmacist->id;

        $res = $this->withSession([AuthController::PHARMACY_RESET_SESSION_KEY => $context])
            ->post(route('pharmacy.password.update'), [
                'otp'                   => $otp,
                'password'              => 'HijackedPass123!',
                'password_confirmation' => 'HijackedPass123!',
            ]);

        $res->assertRedirect(route('pharmacy.password.request'));
        $res->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldOwnerPass123!', $this->pharmacyOwner->fresh()->password));
        $this->assertTrue(Hash::check('OldPharmacistPass123!', $this->pharmacist->fresh()->password));
    }

    // =========================================================================
    // PASSWORD UPDATE, SESSION & TOKEN INVALIDATION (Tests 25–32)
    // =========================================================================

    /** 25, 27, 28, 29, 30, 31, 32. Complete lifecycle: hash storage, old password rejection, new login, remember_token rotation, session deletion, Sanctum token revocation, and attribute preservation */
    public function test_password_reset_rotates_remember_token_revokes_sessions_and_sanctum_tokens_and_preserves_user_attributes(): void
    {
        // Create active database session rows for pharmacyOwner and adminUser
        DB::table('sessions')->insert([
            [
                'id'            => 'owner_active_session_1',
                'user_id'       => $this->pharmacyOwner->id,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode('test_payload_1'),
                'last_activity' => now()->timestamp,
            ],
            [
                'id'            => 'admin_unrelated_session_1',
                'user_id'       => $this->adminUser->id,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode('test_payload_2'),
                'last_activity' => now()->timestamp,
            ],
        ]);

        // Create Sanctum personal access tokens for pharmacyOwner and customerUser
        $this->pharmacyOwner->createToken('owner_device_token');
        $this->customerUser->createToken('customer_device_token');
        $this->assertEquals(1, $this->pharmacyOwner->tokens()->count());
        $this->assertEquals(1, $this->customerUser->tokens()->count());

        $originalId = $this->pharmacyOwner->id;
        $originalRole = $this->pharmacyOwner->role;
        $originalPharmacyId = $this->pharmacyOwner->pharmacy_id;
        $originalRememberToken = $this->pharmacyOwner->remember_token;

        // Request and complete password reset for pharmacyOwner
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $otp = $this->getLastSentOtpFor($this->pharmacyOwner->email);

        $resetRes = $this->post(route('pharmacy.password.update'), [
            'otp'                   => $otp,
            'password'              => 'BrandNewPharmacyPass2026!',
            'password_confirmation' => 'BrandNewPharmacyPass2026!',
        ]);
        $resetRes->assertRedirect(route('pharmacy.login'));

        $updatedOwner = $this->pharmacyOwner->fresh();

        // 25. Stored as secure bcrypt hash (never plaintext)
        $this->assertNotEquals('BrandNewPharmacyPass2026!', $updatedOwner->password);
        $this->assertTrue(Hash::check('BrandNewPharmacyPass2026!', $updatedOwner->password));

        // 29. remember_token is rotated
        $this->assertNotEmpty($updatedOwner->remember_token);
        $this->assertNotEquals($originalRememberToken, $updatedOwner->remember_token);

        // 30. pharmacyOwner's database sessions are deleted, while adminUser's session is untouched
        $this->assertDatabaseMissing('sessions', ['id' => 'owner_active_session_1']);
        $this->assertDatabaseHas('sessions', ['id' => 'admin_unrelated_session_1']);

        // 31. pharmacyOwner's Sanctum tokens are revoked, while customerUser's token is untouched
        $this->assertEquals(0, $updatedOwner->tokens()->count());
        $this->assertEquals(1, $this->customerUser->tokens()->count());

        // 32. Existing user ID, role, and pharmacy association remain unchanged
        $this->assertEquals($originalId, $updatedOwner->id);
        $this->assertEquals($originalRole, $updatedOwner->role);
        $this->assertEquals($originalPharmacyId, $updatedOwner->pharmacy_id);

        // 27. Old password no longer works
        $oldLoginRes = $this->post(route('pharmacy.login'), [
            'email'    => $this->pharmacyOwner->email,
            'password' => 'OldOwnerPass123!',
        ]);
        $oldLoginRes->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $this->assertFalse(Auth::guard('pharmacy')->check());

        // 28. New password logs in through the Pharmacy portal
        $newLoginRes = $this->post(route('pharmacy.login'), [
            'email'    => $this->pharmacyOwner->email,
            'password' => 'BrandNewPharmacyPass2026!',
        ]);
        $newLoginRes->assertRedirect('/pharmacy/dashboard');
        $this->assertTrue(Auth::guard('pharmacy')->check());
    }

    /** 26. Password confirmation mismatch is rejected without consuming the OTP */
    public function test_password_confirmation_mismatch_is_rejected_and_preserves_unconsumed_otp(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $otp = $this->getLastSentOtpFor($this->adminUser->email);

        $mismatchRes = $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
            'otp'                   => $otp,
            'password'              => 'ValidNewPass123!',
            'password_confirmation' => 'MismatchedPass999!',
        ]);

        $mismatchRes->assertRedirect(route('admin.password.reset'));
        $mismatchRes->assertSessionHasErrors(['password']);
        $this->assertTrue(Hash::check('OldAdminPass123!', $this->adminUser->fresh()->password));

        // The same valid OTP can still be used once the confirmation matches
        $successRes = $this->post(route('admin.password.update'), [
            'otp'                   => $otp,
            'password'              => 'ValidNewPass123!',
            'password_confirmation' => 'ValidNewPass123!',
        ]);
        $successRes->assertRedirect(route('admin.login'));
        $this->assertTrue(Hash::check('ValidNewPass123!', $this->adminUser->fresh()->password));
    }

    // =========================================================================
    // REGRESSION VERIFICATION (Tests 33–37)
    // =========================================================================

    /** 33 & 34. Existing Admin and Pharmacy login flows work */
    public function test_existing_admin_and_pharmacy_login_flows_work(): void
    {
        $adminLogin = $this->post(route('admin.login'), [
            'email'    => $this->adminUser->email,
            'password' => 'OldAdminPass123!',
        ]);
        $adminLogin->assertRedirect('/admin/dashboard');
        $this->assertTrue(Auth::guard('admin')->check());

        $pharmacyLogin = $this->post(route('pharmacy.login'), [
            'email'    => $this->pharmacyOwner->email,
            'password' => 'OldOwnerPass123!',
        ]);
        $pharmacyLogin->assertRedirect('/pharmacy/dashboard');
        $this->assertTrue(Auth::guard('pharmacy')->check());
    }

    /** 35, 36, 37. Existing mobile/patient OTP registration, email verification, and unverified login flows remain intact */
    public function test_existing_patient_registration_and_email_verification_otp_flows_remain_intact(): void
    {
        // 35. Patient registration issues 'registration' OTP challenge
        $regRes = $this->postJson('/api/register', [
            'name'                  => 'New Patient',
            'email'                 => 'newpatient@georx.com',
            'phone'                 => '09178889999',
            'password'              => 'PatientPass123!',
            'password_confirmation' => 'PatientPass123!',
        ]);
        $regRes->assertStatus(202)
               ->assertJson(['status' => 'verification_required']);

        $challengeToken = $regRes->json('challenge_token');
        $otp = $this->getLastSentOtpFor('newpatient@georx.com');
        $this->assertNotNull($otp);

        // 37. Unverified login issues 'email_verification' OTP challenge
        $unverifiedLogin = $this->postJson('/api/login', [
            'email'    => 'newpatient@georx.com',
            'password' => 'PatientPass123!',
            'role'     => 'customer',
        ]);
        $unverifiedLogin->assertStatus(202)
                        ->assertJson(['status' => 'verification_required']);

        $latestToken = $unverifiedLogin->json('challenge_token');
        $latestOtp = $this->getLastSentOtpFor('newpatient@georx.com');

        // 36. Verify OTP activates patient account and returns Sanctum token
        $verifyRes = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $latestToken,
            'otp'             => $latestOtp,
        ]);
        $verifyRes->assertStatus(200)
                  ->assertJson(['status' => 'success'])
                  ->assertJsonStructure(['token', 'user']);
    }

    // =========================================================================
    // REMEDIATION AUDIT TESTS (Phases 1–4)
    // =========================================================================

    /** Phase 1: Orphaned, nonexistent-pharmacy, suspended/inactive/rejected-pharmacy, and pending-verification users cannot request or complete recovery */
    public function test_orphaned_suspended_inactive_rejected_pharmacy_and_pending_verification_users_are_rejected(): void
    {
        // 1. Orphaned pharmacy user (pharmacy_id = null)
        $orphanedOwner = User::create([
            'name'              => 'Orphaned Owner',
            'email'             => 'orphaned@alijispharmacy.com',
            'password'          => Hash::make('OrphanedPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => null,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 2. Pharmacy user pointing to nonexistent pharmacy record (simulated dangling FK)
        $danglingOwner = User::create([
            'name'              => 'Dangling Owner',
            'email'             => 'dangling@alijispharmacy.com',
            'password'          => Hash::make('DanglingPass123!'),
            'role'              => User::ROLE_PHARMACIST,
            'pharmacy_id'       => null,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
        User::retrieved(function (User $u) {
            if ($u->email === 'dangling@alijispharmacy.com') {
                $u->pharmacy_id = 999999;
                $u->setRelation('pharmacy', null);
            }
        });

        // 3. Suspended pharmacy (is_approved = true, status = 'suspended', is_active = false)
        $suspendedPharmacy = Pharmacy::create([
            'name'        => 'Suspended Alijis Pharmacy',
            'slug'        => 'suspended-alijis-pharmacy',
            'address'     => 'Alijis Road, Bacolod City',
            'latitude'    => 10.6420,
            'longitude'   => 122.9520,
            'is_approved' => true,
            'is_active'   => false,
            'status'      => 'suspended',
        ]);
        $suspendedPharmacyUser = User::create([
            'name'              => 'Suspended Pharmacy Owner',
            'email'             => 'suspended_pharm@alijispharmacy.com',
            'password'          => Hash::make('SuspendedPharmPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => $suspendedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 4. Inactive pharmacy (is_approved = true, is_active = false, status = 'approved')
        $inactivePharmacy = Pharmacy::create([
            'name'        => 'Inactive Alijis Pharmacy',
            'slug'        => 'inactive-alijis-pharmacy',
            'address'     => 'Alijis Road, Bacolod City',
            'latitude'    => 10.6425,
            'longitude'   => 122.9525,
            'is_approved' => true,
            'is_active'   => false,
            'status'      => 'approved',
        ]);
        $inactivePharmacyUser = User::create([
            'name'              => 'Inactive Pharmacy Owner',
            'email'             => 'inactive_pharm@alijispharmacy.com',
            'password'          => Hash::make('InactivePharmPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => $inactivePharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 5. Rejected pharmacy (status = 'rejected')
        $rejectedPharmacy = Pharmacy::create([
            'name'        => 'Rejected Alijis Pharmacy',
            'slug'        => 'rejected-alijis-pharmacy',
            'address'     => 'Alijis Road, Bacolod City',
            'latitude'    => 10.6430,
            'longitude'   => 122.9530,
            'is_approved' => false,
            'is_active'   => false,
            'status'      => 'rejected',
        ]);
        $rejectedPharmacyUser = User::create([
            'name'              => 'Rejected Pharmacy Owner',
            'email'             => 'rejected_pharm@alijispharmacy.com',
            'password'          => Hash::make('RejectedPharmPass123!'),
            'role'              => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id'       => $rejectedPharmacy->id,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // 6. Pending-verification user
        $pendingVerifPharmacyUser = User::create([
            'name'              => 'Pending Verification Staff',
            'email'             => 'pending_verif@alijispharmacy.com',
            'password'          => Hash::make('PendingVerifPass123!'),
            'role'              => User::ROLE_PHARMACY_STAFF,
            'pharmacy_id'       => $this->approvedPharmacy->id,
            'account_status'    => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $ineligibleEmails = [
            $orphanedOwner->email,
            $danglingOwner->email,
            $suspendedPharmacyUser->email,
            $inactivePharmacyUser->email,
            $rejectedPharmacyUser->email,
            $pendingVerifPharmacyUser->email,
        ];

        foreach ($ineligibleEmails as $index => $email) {
            if ($index > 0 && $index % 4 === 0) {
                $this->travel(65)->seconds();
            }
            $res = $this->post(route('pharmacy.password.email'), ['email' => $email]);
            $res->assertRedirect(route('pharmacy.password.reset'));
            $res->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);
            $context = session(AuthController::PHARMACY_RESET_SESSION_KEY);
            $this->assertTrue($context['is_dummy']);
            $this->assertNull($context['challenge_token']);
        }

        Mail::assertNothingSent();
        $this->assertEquals(0, EmailOtpChallenge::count());

        $this->travel(65)->seconds();

        // 7. Mid-flight pharmacy suspension after OTP was issued prevents password reset
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $otp = $this->getLastSentOtpFor($this->pharmacyOwner->email);
        $this->assertNotNull($otp);

        $this->approvedPharmacy->update(['status' => 'suspended', 'is_active' => false]);

        $resetRes = $this->post(route('pharmacy.password.update'), [
            'otp'                   => $otp,
            'password'              => 'BlockedAfterSuspension99!',
            'password_confirmation' => 'BlockedAfterSuspension99!',
        ]);
        $resetRes->assertRedirect(route('pharmacy.password.request'));
        $resetRes->assertSessionHas('error');
        $this->assertTrue(Hash::check('OldOwnerPass123!', $this->pharmacyOwner->fresh()->password));
        User::flushEventListeners();
    }

    /** Phase 2: Multi-guard login writes sessions.user_id and password reset invalidates both indexed and legacy null-user_id sessions */
    public function test_multi_guard_session_association_and_legacy_null_user_id_session_invalidation(): void
    {
        config(['session.driver' => 'database']);

        // Verify loginAdmin sets active guard so Auth::id() resolves to admin user ID
        $this->post(route('admin.login'), [
            'email'    => $this->adminUser->email,
            'password' => 'OldAdminPass123!',
        ])->assertRedirect('/admin/dashboard');
        $this->assertEquals($this->adminUser->id, Auth::id());
        Auth::guard('admin')->logout();

        // Verify loginPharmacy sets active guard so Auth::id() resolves to pharmacy user ID
        $this->post(route('pharmacy.login'), [
            'email'    => $this->pharmacyOwner->email,
            'password' => 'OldOwnerPass123!',
        ])->assertRedirect('/pharmacy/dashboard');
        $this->assertEquals($this->pharmacyOwner->id, Auth::id());
        Auth::guard('pharmacy')->logout();

        // Insert a legacy null-user_id session row for pharmacyOwner, one for pharmacist, and an anonymous guest session
        $pharmacyGuardKey = Auth::guard('pharmacy')->getName();
        $ownerLegacyPayload = base64_encode(serialize([
            '_token'          => 'legacy_csrf_1',
            $pharmacyGuardKey => $this->pharmacyOwner->id,
        ]));
        $otherPharmacyPayload = base64_encode(serialize([
            '_token'          => 'legacy_csrf_2',
            $pharmacyGuardKey => $this->pharmacist->id,
        ]));
        $guestPayload = base64_encode(serialize([
            '_token' => 'guest_csrf_3',
        ]));

        DB::table('sessions')->insert([
            [
                'id'            => 'legacy_owner_null_userid_session',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => $ownerLegacyPayload,
                'last_activity' => now()->timestamp,
            ],
            [
                'id'            => 'legacy_pharmacist_null_userid_session',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => $otherPharmacyPayload,
                'last_activity' => now()->timestamp,
            ],
            [
                'id'            => 'anonymous_guest_null_userid_session',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => $guestPayload,
                'last_activity' => now()->timestamp,
            ],
        ]);

        // Reset pharmacyOwner's password
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $otp = $this->getLastSentOtpFor($this->pharmacyOwner->email);

        $this->post(route('pharmacy.password.update'), [
            'otp'                   => $otp,
            'password'              => 'UpdatedOwnerPass2026!',
            'password_confirmation' => 'UpdatedOwnerPass2026!',
        ])->assertRedirect(route('pharmacy.login'));

        // pharmacyOwner's legacy null-user_id session is deleted, while pharmacist's and guest's sessions are preserved
        $this->assertDatabaseMissing('sessions', ['id' => 'legacy_owner_null_userid_session']);
        $this->assertDatabaseHas('sessions', ['id' => 'legacy_pharmacist_null_userid_session']);
        $this->assertDatabaseHas('sessions', ['id' => 'anonymous_guest_null_userid_session']);
    }

    /** Phase 3: Simulated password update failure rolls back OTP consumption so the same OTP succeeds on retry */
    public function test_atomic_transaction_rolls_back_otp_consumption_when_password_update_throws_exception(): void
    {
        $this->post(route('admin.password.email'), ['email' => $this->adminUser->email]);
        $otp = $this->getLastSentOtpFor($this->adminUser->email);
        $this->assertNotNull($otp);

        // Simulate a transient database/model exception during the password update
        $shouldFailOnce = true;
        User::updating(function (User $user) use (&$shouldFailOnce) {
            if ($shouldFailOnce && $user->id === $this->adminUser->id) {
                $shouldFailOnce = false;
                throw new \RuntimeException('Simulated database failure during password update.');
            }
        });

        try {
            $failedRes = $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
                'otp'                   => $otp,
                'password'              => 'AtomicRecoveredPass123!',
                'password_confirmation' => 'AtomicRecoveredPass123!',
            ]);

            $failedRes->assertRedirect(route('admin.password.reset'));
            $failedRes->assertSessionHas('error');

            // Verify OTP challenge rolled back and remains unconsumed, and password was NOT changed
            $challenge = EmailOtpChallenge::where('user_id', $this->adminUser->id)->first();
            $this->assertNotNull($challenge);
            $this->assertNull($challenge->consumed_at);
            $this->assertTrue(Hash::check('OldAdminPass123!', $this->adminUser->fresh()->password));

            // Retry with the exact same OTP now that the transient failure cleared
            $retryRes = $this->post(route('admin.password.update'), [
                'otp'                   => $otp,
                'password'              => 'AtomicRecoveredPass123!',
                'password_confirmation' => 'AtomicRecoveredPass123!',
            ]);

            $retryRes->assertRedirect(route('admin.login'));
            $retryRes->assertSessionHas('success');
            $this->assertNotNull($challenge->fresh()->consumed_at);
            $this->assertTrue(Hash::check('AtomicRecoveredPass123!', $this->adminUser->fresh()->password));
        } finally {
            User::flushEventListeners();
        }
    }

    /** Phase 4a: Initial Brevo delivery failure expires challenge immediately and stores dummy session state */
    public function test_initial_brevo_delivery_failure_expires_challenge_and_stores_dummy_session_state(): void
    {
        Mail::shouldReceive('to')
            ->once()
            ->with($this->adminUser->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Brevo API 503 Unavailable'));

        $res = $this->post(route('admin.password.email'), [
            'email' => $this->adminUser->email,
        ]);

        // Public response remains identical to prevent account-enumeration oracle
        $res->assertRedirect(route('admin.password.reset'));
        $res->assertSessionHas('status', AuthController::GENERIC_RESET_REQUEST_MESSAGE);

        // Session context must not hold an active challenge token
        $context = session(AuthController::ADMIN_RESET_SESSION_KEY);
        $this->assertNotNull($context);
        $this->assertTrue($context['is_dummy']);
        $this->assertNull($context['challenge_token']);

        // Database challenge must be immediately marked expired
        $challenge = EmailOtpChallenge::where('user_id', $this->adminUser->id)->first();
        $this->assertNotNull($challenge);
        $this->assertTrue($challenge->isExpired());
    }

    /** Phase 4b: Resend Brevo delivery failure preserves previous valid OTP, send_count, and cooldown */
    public function test_resend_brevo_delivery_failure_preserves_previous_valid_otp_and_send_count(): void
    {
        // 1. Initial OTP succeeds
        $this->post(route('pharmacy.password.email'), ['email' => $this->pharmacyOwner->email]);
        $initialOtp = $this->getLastSentOtpFor($this->pharmacyOwner->email);
        $this->assertNotNull($initialOtp);

        $challengeBefore = EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->first();
        $originalOtpHash = $challengeBefore->otp_hash;
        $this->assertEquals(1, $challengeBefore->send_count);

        // Advance past 60s cooldown
        $this->travel(61)->seconds();

        // 2. Simulate Brevo failure on resend
        Mail::shouldReceive('to')
            ->once()
            ->with($this->pharmacyOwner->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Brevo API timeout'));

        $resendRes = $this->from(route('pharmacy.password.reset'))->post(route('pharmacy.password.resend'));
        $resendRes->assertRedirect(route('pharmacy.password.reset'));
        $resendRes->assertSessionHas('status', AuthController::GENERIC_RESEND_MESSAGE);

        // 3. Verify original otp_hash and send_count were NOT overwritten or penalized
        $challengeAfter = $challengeBefore->fresh();
        $this->assertEquals($originalOtpHash, $challengeAfter->otp_hash);
        $this->assertEquals(1, $challengeAfter->send_count);
        $this->assertTrue($challengeAfter->canResend(EmailOtpService::RESEND_COOLDOWN_SECONDS));

        // 4. User can still reset their password using the originally delivered initial OTP
        $resetRes = $this->post(route('pharmacy.password.update'), [
            'otp'                   => $initialOtp,
            'password'              => 'OriginalOtpStillWorks99!',
            'password_confirmation' => 'OriginalOtpStillWorks99!',
        ]);
        $resetRes->assertRedirect(route('pharmacy.login'));
        $this->assertTrue(Hash::check('OriginalOtpStillWorks99!', $this->pharmacyOwner->fresh()->password));
    }

    // =========================================================================
    // PRIORITY 1–3 FINAL SECURITY HARDENING TESTS
    // =========================================================================

    /** Priority 1a: Two and three simultaneous resend attempts reserve only one slot and reject competing requests */
    public function test_concurrent_resend_attempts_reserve_single_slot_and_reject_competing_requests(): void
    {
        $created = EmailOtpService::createChallenge($this->pharmacyOwner, 'password_reset');
        $challengeToken = $created['challenge_token'];

        $this->travel(61)->seconds();

        $competingResults = [];
        $deliveredOtp = null;

        // Intercept Phase 2 Mail::to()->send() while Phase 1 reservation is committed in DB
        Mail::shouldReceive('to')
            ->once()
            ->with($this->pharmacyOwner->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andReturnUsing(function (EmailOtpMailable $mailable) use ($challengeToken, &$competingResults, &$deliveredOtp) {
                $deliveredOtp = $mailable->otp;

                // Verify DB state during Phase 2: reservation is active
                $inFlight = EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->first();
                $this->assertEquals(EmailOtpService::STATE_RESERVED, $inFlight->delivery_state);
                $this->assertTrue($inFlight->hasActiveResendReservation());

                // Fire 2 simultaneous competing resend requests while Request 1 is in-flight
                $competingResults[] = EmailOtpService::resendOtp($challengeToken);
                $competingResults[] = EmailOtpService::resendOtp($challengeToken);
            });

        $primaryResult = EmailOtpService::resendOtp($challengeToken);

        $this->assertTrue($primaryResult['success']);
        $this->assertEquals(200, $primaryResult['status']);
        $this->assertCount(2, $competingResults);

        // Both competing concurrent requests must be rejected with 429 without sending extra emails
        foreach ($competingResults as $competing) {
            $this->assertFalse($competing['success']);
            $this->assertEquals(429, $competing['status']);
        }

        $finalChallenge = EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->first();
        $this->assertEquals(2, $finalChallenge->send_count);
        $this->assertEquals(EmailOtpService::STATE_DELIVERED, $finalChallenge->delivery_state);
        $this->assertNull($finalChallenge->resend_reservation_token);
        $this->assertEquals(EmailOtpService::hashOtp($deliveredOtp), $finalChallenge->otp_hash);
    }

    /** Priority 1b: Maximum resend count and cooldown enforcement under concurrent requests */
    public function test_max_resends_and_cooldown_enforced_under_concurrent_requests(): void
    {
        $created = EmailOtpService::createChallenge($this->adminUser, 'password_reset');
        $challengeToken = $created['challenge_token'];

        // Set send_count to MAX_RESENDS - 1 (4) and advance past cooldown
        EmailOtpChallenge::where('user_id', $this->adminUser->id)->update([
            'send_count'   => EmailOtpService::MAX_RESENDS - 1,
            'last_sent_at' => now()->subSeconds(65),
        ]);

        $concurrentAtBoundary = null;
        Mail::shouldReceive('to')
            ->once()
            ->with($this->adminUser->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andReturnUsing(function () use ($challengeToken, &$concurrentAtBoundary) {
                // Competing request arrives while the 5th (final allowed) send is in flight
                $concurrentAtBoundary = EmailOtpService::resendOtp($challengeToken);
            });

        $fifthSend = EmailOtpService::resendOtp($challengeToken);
        $this->assertTrue($fifthSend['success']);
        $this->assertFalse($concurrentAtBoundary['success']);
        $this->assertEquals(429, $concurrentAtBoundary['status']);

        // Even after another 65 seconds, send_count is 5 (MAX_RESENDS), so further requests are blocked
        $this->travel(65)->seconds();
        $sixthSend = EmailOtpService::resendOtp($challengeToken);
        $this->assertFalse($sixthSend['success']);
        $this->assertEquals(429, $sixthSend['status']);
        $this->assertEquals(EmailOtpService::MAX_RESENDS, EmailOtpChallenge::where('user_id', $this->adminUser->id)->value('send_count'));
    }

    /** Priority 1c: Resend racing with successful OTP verification never overwrites a consumed challenge */
    public function test_resend_racing_with_successful_otp_verification_never_overwrites_consumed_challenge(): void
    {
        $created = EmailOtpService::createChallenge($this->adminUser, 'password_reset');
        $challengeToken = $created['challenge_token'];
        $initialOtp = $this->getLastSentOtpFor($this->adminUser->email);
        $this->assertNotNull($initialOtp);

        $this->travel(61)->seconds();

        $verificationDuringResend = null;

        Mail::shouldReceive('to')
            ->once()
            ->with($this->adminUser->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andReturnUsing(function () use ($challengeToken, $initialOtp, &$verificationDuringResend) {
                // While the resend email is in-flight, the user verifies using the initial OTP
                $verificationDuringResend = EmailOtpService::verifyAndExecute(
                    challengeToken: $challengeToken,
                    submittedOtp: $initialOtp,
                    expectedPurpose: 'password_reset',
                    onVerified: function (?User $user) {
                        $user->forceFill(['password' => Hash::make('VerifiedMidResendPass99!')])->save();
                        return true;
                    }
                );
            });

        $resendResult = EmailOtpService::resendOtp($challengeToken);

        // Verification succeeded and consumed the challenge
        $this->assertNotNull($verificationDuringResend);
        $this->assertTrue($verificationDuringResend['success']);
        $this->assertTrue(Hash::check('VerifiedMidResendPass99!', $this->adminUser->fresh()->password));

        // Phase 3 of resend detected the challenge was consumed and refused to overwrite it (410)
        $this->assertFalse($resendResult['success']);
        $this->assertEquals(410, $resendResult['status']);

        $finalChallenge = EmailOtpChallenge::where('user_id', $this->adminUser->id)->first();
        $this->assertNotNull($finalChallenge->consumed_at);
    }

    /** Priority 1d: Resend against expired and consumed challenges is rejected */
    public function test_resend_against_expired_and_consumed_challenges_is_rejected(): void
    {
        $created = EmailOtpService::createChallenge($this->pharmacyOwner, 'password_reset');
        $challengeToken = $created['challenge_token'];
        $challenge = EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->first();

        // 1. Expired challenge cannot be resent
        $challenge->update([
            'last_sent_at' => now()->subMinutes(12),
            'expires_at'   => now()->subMinute(),
        ]);

        $expiredRes = EmailOtpService::resendOtp($challengeToken);
        $this->assertFalse($expiredRes['success']);
        $this->assertEquals(410, $expiredRes['status']);

        // 2. Consumed challenge cannot be resent
        $challenge->update([
            'expires_at'  => now()->addMinutes(5),
            'consumed_at' => now(),
        ]);

        $consumedRes = EmailOtpService::resendOtp($challengeToken);
        $this->assertFalse($consumedRes['success']);
        $this->assertEquals(410, $consumedRes['status']);
    }

    /** Priority 1e: Definitive Brevo failure vs. Ambiguous timeout after possible provider acceptance */
    public function test_definitive_brevo_failure_vs_ambiguous_timeout_recovery(): void
    {
        // Part A: Definitive failure (HTTP 500 rejection) rolls back completely; failed OTP is NOT accepted
        $created = EmailOtpService::createChallenge($this->adminUser, 'password_reset');
        $challengeToken = $created['challenge_token'];
        $initialOtp = $this->getLastSentOtpFor($this->adminUser->email);

        $this->travel(61)->seconds();

        $failedResendOtp = null;
        Mail::shouldReceive('to')
            ->once()
            ->with($this->adminUser->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andReturnUsing(function (EmailOtpMailable $mailable) use (&$failedResendOtp) {
                $failedResendOtp = $mailable->otp;
                throw new \Symfony\Component\Mailer\Exception\TransportException('Brevo API delivery failed: HTTP 500');
            });

        $definitiveRes = EmailOtpService::resendOtp($challengeToken);
        $this->assertFalse($definitiveRes['success']);
        $this->assertEquals(503, $definitiveRes['status']);

        $challengeAfterDefinitive = EmailOtpChallenge::where('user_id', $this->adminUser->id)->first();
        $this->assertEquals(EmailOtpService::STATE_DELIVERED, $challengeAfterDefinitive->delivery_state);
        $this->assertNull($challengeAfterDefinitive->pending_otp_hash);

        // Undelivered OTP from definitive failure is rejected
        if ($failedResendOtp !== $initialOtp) {
            $badVerify = EmailOtpService::verifyChallenge($challengeToken, $failedResendOtp, 'password_reset');
            $this->assertFalse($badVerify['success']);
            $this->assertEquals(422, $badVerify['status']);
        }

        // Part B: Ambiguous timeout (ConnectionException cURL error 28) preserves BOTH codes for single-use recovery
        $timedOutOtp = null;
        Mail::shouldReceive('to')
            ->once()
            ->with($this->adminUser->email)
            ->andReturnSelf();
        Mail::shouldReceive('send')
            ->once()
            ->andReturnUsing(function (EmailOtpMailable $mailable) use (&$timedOutOtp) {
                $timedOutOtp = $mailable->otp;
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 15001 milliseconds');
            });

        $timeoutRes = EmailOtpService::resendOtp($challengeToken);
        $this->assertFalse($timeoutRes['success']);
        $this->assertEquals(503, $timeoutRes['status']);
        $this->assertTrue($timeoutRes['ambiguous_timeout'] ?? false);

        $challengeAfterTimeout = EmailOtpChallenge::where('user_id', $this->adminUser->id)->first();
        $this->assertEquals(EmailOtpService::STATE_AMBIGUOUS_TIMEOUT, $challengeAfterTimeout->delivery_state);
        $this->assertEquals(EmailOtpService::hashOtp($timedOutOtp), $challengeAfterTimeout->pending_otp_hash);
        $this->assertEquals(EmailOtpService::hashOtp($initialOtp), $challengeAfterTimeout->otp_hash);

        // Verify the timed-out OTP (if it arrived at the user's inbox) is accepted and consumes the challenge
        $verifyTimedOut = EmailOtpService::verifyChallenge($challengeToken, $timedOutOtp, 'password_reset');
        $this->assertTrue($verifyTimedOut['success']);
        $this->assertEquals(200, $verifyTimedOut['status']);

        // Once consumed, the initial OTP cannot be reused either
        $replayInitial = EmailOtpService::verifyChallenge($challengeToken, $initialOtp, 'password_reset');
        $this->assertFalse($replayInitial['success']);
        $this->assertEquals(410, $replayInitial['status']);
    }

    /** Priority 1f: Recovery from abandoned reservation and stale request version fencing */
    public function test_recovery_from_abandoned_reservation_and_stale_version_fencing(): void
    {
        $created = EmailOtpService::createChallenge($this->pharmacyOwner, 'password_reset');
        $challengeToken = $created['challenge_token'];
        $challenge = EmailOtpChallenge::where('user_id', $this->pharmacyOwner->id)->first();

        // Simulate an abandoned reservation whose TTL expired 5 seconds ago
        $challenge->update([
            'send_count'               => 2,
            'last_sent_at'             => now()->subSeconds(25),
            'pending_otp_hash'         => EmailOtpService::hashOtp('111222'),
            'resend_reservation_token' => 'stale_abandoned_token_abc',
            'resend_reserved_until'    => now()->subSeconds(5),
            'delivery_state'           => EmailOtpService::STATE_RESERVED,
            'otp_version'              => 2,
        ]);

        // Next resend request recovers from the abandoned reservation even within the 60s window of the crashed attempt
        $recoveredRes = EmailOtpService::resendOtp($challengeToken);
        $this->assertTrue($recoveredRes['success']);
        $this->assertEquals(200, $recoveredRes['status']);

        $freshChallenge = $challenge->fresh();
        $this->assertEquals(2, $freshChallenge->send_count);
        $this->assertEquals(EmailOtpService::STATE_DELIVERED, $freshChallenge->delivery_state);
        $newestOtpHash = $freshChallenge->otp_hash;

        // Now simulate the stale crashed worker waking up late and attempting Phase 3 finalization
        $staleFinalize = EmailOtpService::finalizeResendDelivery(
            challengeId: (int) $freshChallenge->id,
            reservationToken: 'stale_abandoned_token_abc',
            expectedVersion: 2,
            newOtpHash: EmailOtpService::hashOtp('999888'),
            priorState: [
                'otp_hash'          => $newestOtpHash,
                'previous_otp_hash' => null,
                'pending_otp_hash'  => null,
                'attempts'          => 0,
                'send_count'        => 1,
                'last_sent_at'      => now()->subMinute(),
                'expires_at'        => now()->addMinutes(10),
                'delivery_state'    => EmailOtpService::STATE_DELIVERED,
            ],
            outcome: 'delivered'
        );

        $this->assertFalse($staleFinalize['success']);
        $this->assertEquals(409, $staleFinalize['status']);
        $this->assertEquals($newestOtpHash, $freshChallenge->fresh()->otp_hash);
    }

    /** Priority 2: Bounded legacy session cleanup handles int/string IDs, dynamic guard key lengths, expired/encrypted exclusion, and chunked processing */
    public function test_bounded_legacy_session_cleanup_handles_int_and_string_ids_skips_expired_and_encrypted_and_chunks_large_tables(): void
    {
        $adminGuardKey = Auth::guard('admin')->getName();
        $pharmacyGuardKey = Auth::guard('pharmacy')->getName();

        // Verify dynamic guard key length calculation
        $this->assertEquals(strlen('login_admin_') + 40, strlen($adminGuardKey));
        $this->assertEquals(strlen('login_pharmacy_') + 40, strlen($pharmacyGuardKey));

        $otherAdmin = User::create([
            'name'              => 'Second Admin',
            'email'             => 'admin2@georx.com',
            'password'          => Hash::make('Admin2Pass123!'),
            'role'              => User::ROLE_ADMINISTRATOR,
            'account_status'    => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $nowTs = now()->timestamp;
        $expiredTs = now()->subMinutes(180)->timestamp; // Older than 120-minute session.lifetime

        $rows = [
            // Target pharmacyOwner legacy session with integer user ID
            [
                'id'            => 'chunk_001_target_owner_int',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode(serialize([$pharmacyGuardKey => (int) $this->pharmacyOwner->id])),
                'last_activity' => $nowTs,
            ],
            // Target pharmacyOwner legacy session with string user ID
            [
                'id'            => 'chunk_150_target_owner_str',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode(serialize([$pharmacyGuardKey => (string) $this->pharmacyOwner->id])),
                'last_activity' => $nowTs,
            ],
            // Target pharmacyOwner expired legacy session (excluded from active payload scan)
            [
                'id'            => 'expired_target_owner_session',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode(serialize([$pharmacyGuardKey => (int) $this->pharmacyOwner->id])),
                'last_activity' => $expiredTs,
            ],
            // Another pharmacy user's legacy session (must be preserved)
            [
                'id'            => 'other_pharmacy_user_session',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode(serialize([$pharmacyGuardKey => (int) $this->pharmacist->id])),
                'last_activity' => $nowTs,
            ],
            // Another admin's legacy session (must be preserved)
            [
                'id'            => 'other_admin_user_session',
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode(serialize([$adminGuardKey => (int) $otherAdmin->id])),
                'last_activity' => $nowTs,
            ],
        ];

        // Add 210 anonymous guest sessions so total active null-user_id rows exceed 2 chunks (chunk size = 100)
        for ($i = 1; $i <= 210; $i++) {
            $rows[] = [
                'id'            => sprintf('guest_session_%03d', $i),
                'user_id'       => null,
                'ip_address'    => '127.0.0.1',
                'user_agent'    => 'Mozilla/5.0',
                'payload'       => base64_encode(serialize(['_token' => "guest_token_{$i}"])),
                'last_activity' => $nowTs,
            ];
        }

        // Target pharmacyOwner session at the end of the 3rd chunk
        $rows[] = [
            'id'            => 'z_chunk_3_target_owner_end',
            'user_id'       => null,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'Mozilla/5.0',
            'payload'       => base64_encode(serialize([$pharmacyGuardKey => (int) $this->pharmacyOwner->id])),
            'last_activity' => $nowTs,
        ];

        DB::table('sessions')->insert($rows);

        // 1. Verify SESSION_ENCRYPT=true safely skips raw payload scanning without error
        config(['session.encrypt' => true]);
        $ref = new \ReflectionMethod(AuthController::class, 'revokeUserDatabaseSessions');
        $ref->setAccessible(true);
        $ref->invoke(app(AuthController::class), $this->pharmacyOwner);
        $this->assertDatabaseHas('sessions', ['id' => 'chunk_001_target_owner_int']);

        // 2. With SESSION_ENCRYPT=false, chunked cleanup removes all active target sessions across all 3 chunks
        config(['session.encrypt' => false]);
        $ref->invoke(app(AuthController::class), $this->pharmacyOwner);

        $this->assertDatabaseMissing('sessions', ['id' => 'chunk_001_target_owner_int']);
        $this->assertDatabaseMissing('sessions', ['id' => 'chunk_150_target_owner_str']);
        $this->assertDatabaseMissing('sessions', ['id' => 'z_chunk_3_target_owner_end']);

        // Expired row was excluded from active payload scan; other users and all 210 guests are preserved
        $this->assertDatabaseHas('sessions', ['id' => 'expired_target_owner_session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other_pharmacy_user_session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other_admin_user_session']);
        $this->assertEquals(210, DB::table('sessions')->where('id', 'like', 'guest_session_%')->count());
    }

    /** Priority 3: Trusted proxy spoofing protection, Render forwarded headers, HTTPS detection, and multi-dimensional rate limiting */
    public function test_trusted_proxy_spoofing_protection_https_detection_and_multidimensional_rate_limiting(): void
    {
        // 1. Direct connection from an untrusted public IP (REMOTE_ADDR = 198.51.100.77) trying to rotate spoofed X-Forwarded-For headers
        for ($i = 1; $i <= 5; $i++) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
                ->withHeaders(['X-Forwarded-For' => "1.1.1.{$i}"])
                ->post(route('admin.password.email'), [
                    'email' => "probe_{$i}@georx.com",
                ]);
            $res->assertRedirect(route('admin.password.reset'));
        }

        // 6th request from the same untrusted REMOTE_ADDR with a brand-new spoofed X-Forwarded-For is blocked (429)
        $spoofBlocked = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.77'])
            ->withHeaders(['X-Forwarded-For' => '9.9.9.9'])
            ->post(route('admin.password.email'), [
                'email' => 'probe_6@georx.com',
            ]);
        $spoofBlocked->assertStatus(429);

        // 2. Normal Render internal proxy hop (REMOTE_ADDR = 10.20.30.40) forwarding X-Forwarded-Proto: https
        // and X-Forwarded-For: <attacker-spoofed>, <real-client-ip>
        // Different source IPs targeting the SAME account identifier (existing admin email)
        $targetEmail = $this->adminUser->email;
        for ($i = 1; $i <= 3; $i++) {
            $proxyRes = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
                ->withHeaders([
                    'X-Forwarded-For'   => "8.8.8.{$i}, 203.0.113.{$i}",
                    'X-Forwarded-Proto' => 'https',
                ])
                ->post(route('admin.password.email'), [
                    'email' => $targetEmail,
                ]);
            $proxyRes->assertRedirect(route('admin.password.reset'));
        }

        // 4th request targeting the same email within 1 minute from a 4th distinct client IP (203.0.113.4)
        // is throttled by the HMAC-hashed identifier limiter (429)
        $idThrottledExisting = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders([
                'X-Forwarded-For'   => '203.0.113.4',
                'X-Forwarded-Proto' => 'https',
            ])
            ->post(route('admin.password.email'), [
                'email' => $targetEmail,
            ]);
        $idThrottledExisting->assertStatus(429);

        // Pharmacy portal rate limiter is independent for the same IP and email
        $independentPortalRes = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders([
                'X-Forwarded-For'   => '203.0.113.4',
                'X-Forwarded-Proto' => 'https',
            ])
            ->post(route('pharmacy.password.email'), [
                'email' => $targetEmail,
            ]);
        $independentPortalRes->assertRedirect(route('pharmacy.password.reset'));

        // 3. Unknown accounts obey the exact same 3-per-minute identifier rate limit across different IPs (no enumeration oracle)
        $unknownEmail = 'unknown_target@georx.com';
        for ($i = 10; $i <= 12; $i++) {
            $res = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
                ->withHeaders(['X-Forwarded-For' => "203.0.113.{$i}"])
                ->post(route('admin.password.email'), ['email' => $unknownEmail]);
            $res->assertRedirect(route('admin.password.reset'));
        }
        $idThrottledUnknown = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders(['X-Forwarded-For' => '203.0.113.13'])
            ->post(route('admin.password.email'), ['email' => $unknownEmail]);
        $idThrottledUnknown->assertStatus(429);

        // 4. Legitimate user is NOT permanently locked out: after 61 seconds the decay window clears
        $this->travel(61)->seconds();
        $recoveredRes = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeaders([
                'X-Forwarded-For'   => '203.0.113.50',
                'X-Forwarded-Proto' => 'https',
            ])
            ->post(route('admin.password.email'), [
                'email' => $targetEmail,
            ]);
        $recoveredRes->assertRedirect(route('admin.password.reset'));
    }

    public function test_two_consecutive_ambiguous_brevo_timeouts_preserve_all_hashes_and_allow_any_code_to_consume(): void
    {
        // 1. Initial request
        $postRes = $this->post(route('admin.password.email'), [
            'email' => $this->adminUser->email,
        ]);
        $postRes->assertRedirect(route('admin.password.reset'));

        $context = session(AuthController::ADMIN_RESET_SESSION_KEY);
        $this->assertNotEmpty($context['challenge_token']);
        $challengeToken = $context['challenge_token'];

        $tokenHash = EmailOtpService::hashChallengeToken($challengeToken);
        $challenge = EmailOtpChallenge::where('challenge_token_hash', $tokenHash)->firstOrFail();
        $code0Hash = $challenge->otp_hash;

        // 2. 1st Resend with simulated ambiguous timeout
        $this->travel(65)->seconds();
        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 15000 milliseconds'));

        $resend1Res = $this->from(route('admin.password.reset'))->post(route('admin.password.resend'));
        $resend1Res->assertRedirect(route('admin.password.reset'));
        $resend1Res->assertSessionHas('status', AuthController::GENERIC_RESEND_MESSAGE);

        $challengeAfterTo1 = EmailOtpChallenge::find($challenge->id);
        $to1ActiveHashes = $challengeAfterTo1->active_hashes;
        $code1Hash = $challengeAfterTo1->pending_otp_hash;

        $this->assertSame(EmailOtpService::STATE_AMBIGUOUS_TIMEOUT, $challengeAfterTo1->delivery_state);
        $this->assertCount(2, $to1ActiveHashes);
        $this->assertSame($code0Hash, $to1ActiveHashes[0]['hash']);
        $this->assertSame($code1Hash, $to1ActiveHashes[1]['hash']);

        // 3. 2nd Resend with simulated consecutive ambiguous timeout
        $this->travel(65)->seconds();
        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('Operation timed out after 15000 milliseconds'));

        $resend2Res = $this->from(route('admin.password.reset'))->post(route('admin.password.resend'));
        $resend2Res->assertRedirect(route('admin.password.reset'));
        $resend2Res->assertSessionHas('status', AuthController::GENERIC_RESEND_MESSAGE);

        $challengeAfterTo2 = EmailOtpChallenge::find($challenge->id);
        $to2ActiveHashes = $challengeAfterTo2->active_hashes;
        $code2Hash = $challengeAfterTo2->pending_otp_hash;

        $this->assertSame(EmailOtpService::STATE_AMBIGUOUS_TIMEOUT, $challengeAfterTo2->delivery_state);
        $this->assertCount(3, $to2ActiveHashes);
        $this->assertSame($code0Hash, $to2ActiveHashes[0]['hash']);
        $this->assertSame($code1Hash, $to2ActiveHashes[1]['hash']);
        $this->assertSame($code2Hash, $to2ActiveHashes[2]['hash']);

        // 4. Submitting code 1 (arriving late from 1st timeout) succeeds
        $matchedOtp1 = null;
        for ($cand = 100000; $cand <= 999999; $cand++) {
            if (hash_equals(EmailOtpService::hashOtp((string) $cand), $code1Hash)) {
                $matchedOtp1 = (string) $cand;
                break;
            }
        }
        $this->assertNotNull($matchedOtp1);

        $resetRes = $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
            'otp'                   => $matchedOtp1,
            'password'              => 'BrandNewPassword2026!#',
            'password_confirmation' => 'BrandNewPassword2026!#',
        ]);
        $resetRes->assertRedirect(route('admin.login'));
        $resetRes->assertSessionHas('success');

        $this->assertTrue(Hash::check('BrandNewPassword2026!#', $this->adminUser->fresh()->password));

        // 5. Subsequent attempts to use code 2, code 0, or code 1 are rejected (session cleared)
        $replayRes = $this->from(route('admin.password.reset'))->post(route('admin.password.update'), [
            'otp'                   => $matchedOtp1,
            'password'              => 'AnotherPassword2026!#',
            'password_confirmation' => 'AnotherPassword2026!#',
        ]);
        $replayRes->assertRedirect(route('admin.password.request'));
    }

    public function test_check_schema_readiness_accurately_detects_all_required_columns_and_prevents_unmigrated_production_resend(): void
    {
        $readiness = EmailOtpService::checkSchemaReadiness();
        $this->assertTrue($readiness['ready']);
        $this->assertTrue($readiness['table_exists']);
        $this->assertEmpty($readiness['missing_columns']);
    }

    public function test_is_ambiguous_timeout_exception_strictly_classifies_definitive_failures_and_read_timeouts(): void
    {
        $http400   = new \Exception("Brevo API delivery failed: HTTP 400 Bad Request");
        $http500   = new \Exception("Brevo API delivery failed: HTTP 500 Internal Server Error");
        $rawBrevo1 = new \Exception("Brevo API delivery failed: Invalid recipient address");
        $rawBrevo2 = new \Exception("Brevo API delivery failed: Gateway timeout");
        $dnsFail   = new \Exception("cURL error 6: Could not resolve host: api.brevo.com");
        $connRef   = new \Exception("cURL error 7: Failed to connect to api.brevo.com port 443: Connection refused");
        $tlsFail   = new \Exception("cURL error 35: SSL connect error");
        $connTo    = new \Exception("Connection timed out after 5000 milliseconds");
        $noKey     = new \Exception("Brevo API key is not configured");

        $readTo1 = new \Illuminate\Http\Client\ConnectionException("cURL error 28: Operation timed out after 15000 milliseconds with 0 bytes received");
        $readTo2 = new \Exception("cURL error 28: Operation timed out after 15002 milliseconds with 0 out of 0 bytes received");
        $readTo3 = new \Exception("Operation timed out after 15000 milliseconds");

        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($http400));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($http500));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($rawBrevo1));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($rawBrevo2));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($dnsFail));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($connRef));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($tlsFail));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($connTo));
        $this->assertFalse(EmailOtpService::isAmbiguousTimeoutException($noKey));

        $this->assertTrue(EmailOtpService::isAmbiguousTimeoutException($readTo1));
        $this->assertTrue(EmailOtpService::isAmbiguousTimeoutException($readTo2));
        $this->assertTrue(EmailOtpService::isAmbiguousTimeoutException($readTo3));
    }
}

