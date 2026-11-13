<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') === 'sqlite') {
            try {
                \Illuminate\Support\Facades\DB::statement("ATTACH ':memory:' AS information_schema");
                \Illuminate\Support\Facades\DB::statement("CREATE TABLE IF NOT EXISTS information_schema.table_constraints (table_name TEXT, constraint_name TEXT, constraint_type TEXT)");
            } catch (\Throwable $e) {}
        }
    }

    protected User $adminUser;
    protected User $pharmacyUser;
    protected User $customerUser;
    protected Pharmacy $approvedPharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approvedPharmacy = Pharmacy::create([
            'name' => 'Alijis Care Pharmacy',
            'slug' => 'alijis-care-pharmacy',
            'address' => 'Alijis Road, Bacolod City',
            'phone' => '09170001111',
            'email' => 'care@alijispharmacy.com',
            'latitude' => 10.6400,
            'longitude' => 122.9500,
            'owner_name' => 'Dr. Alijis',
            'is_approved' => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@georx.com',
            'phone' => '09170002222',
            'password' => bcrypt('AdminSecurePass123!'),
            'role' => User::ROLE_ADMINISTRATOR,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->pharmacyUser = User::create([
            'name' => 'Pharmacy Partner',
            'email' => 'partner@alijispharmacy.com',
            'phone' => '09170003333',
            'password' => bcrypt('PartnerSecurePass123!'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->approvedPharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->customerUser = User::create([
            'name' => 'Normal Customer',
            'email' => 'customer@example.com',
            'phone' => '09170004444',
            'password' => bcrypt('CustomerPass123!'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    /** 1. Correct pharmacy login route and form action */
    public function test_pharmacy_login_page_renders_with_correct_form_action_and_csrf(): void
    {
        $response = $this->get(route('pharmacy.login'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.pharmacy-login');
        $response->assertSee(route('pharmacy.login'));
        $response->assertSee('name="_token"', false);
        $response->assertSee('Pharmacy Portal', false);
        $response->assertSee('Manage your pharmacy and orders.');
    }

    /** 2. Correct admin login route and form action */
    public function test_admin_login_page_renders_with_correct_form_action_and_csrf(): void
    {
        $response = $this->get(route('admin.login'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.admin-login');
        $response->assertSee(route('admin.login'));
        $response->assertSee('name="_token"', false);
        $response->assertSee('System Administration', false);
        $response->assertSee('Restricted Portal');
    }

    /** 3. CSRF token validation and presence */
    public function test_csrf_tokens_are_rendered_in_both_login_forms(): void
    {
        $pharmacyView = $this->get('/pharmacy/login');
        $pharmacyView->assertSee('name="_token"', false);

        $adminView = $this->get('/admin/login');
        $adminView->assertSee('name="_token"', false);
    }

    /** 3b. Priority 3: CSRF middleware actively enforces token validation and rejects missing tokens */
    public function test_csrf_middleware_actively_rejects_requests_without_valid_token(): void
    {
        // 1. Verify login routes are not in the CSRF exclusion list
        $csrfMiddleware = app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $excluded = $csrfMiddleware->getExcludedPaths();
        $this->assertNotContains('admin/login', $excluded);
        $this->assertNotContains('pharmacy/login', $excluded);

        // 2. Instantiate non-testing CSRF verifier to simulate real non-test production HTTP traffic
        $enforcingMiddleware = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests() {
                return false;
            }
        };

        // Create a simulated POST request with active session
        $session = app('session.store');
        $session->start();

        // 2a. Request to /admin/login without token throws TokenMismatchException
        $requestNoToken = \Illuminate\Http\Request::create(route('admin.login'), 'POST', [
            'email' => 'admin@georx.com',
            'password' => 'AdminSecurePass123!',
        ]);
        $requestNoToken->setLaravelSession($session);

        $this->expectException(\Illuminate\Session\TokenMismatchException::class);
        $enforcingMiddleware->handle($requestNoToken, function () {
            return response('OK');
        });
    }

    /** 3c. Priority 3: CSRF middleware allows requests when valid token is supplied */
    public function test_csrf_middleware_accepts_valid_matching_token(): void
    {
        $enforcingMiddleware = new class(app(), app('encrypter')) extends \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken {
            protected function runningUnitTests() {
                return false;
            }
        };

        $session = app('session.store');
        $session->start();
        $sessionToken = $session->token();

        $requestValidToken = \Illuminate\Http\Request::create(route('admin.login'), 'POST', [
            '_token' => $sessionToken,
            'email' => 'admin@georx.com',
            'password' => 'AdminSecurePass123!',
        ]);
        $requestValidToken->setLaravelSession($session);

        $response = $enforcingMiddleware->handle($requestValidToken, function () {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /** 4. Invalid credentials fail safely without leaking sensitive information */
    public function test_invalid_credentials_fail_safely_on_both_portals(): void
    {
        $pharmacyRes = $this->post(route('pharmacy.login'), [
            'email' => 'partner@alijispharmacy.com',
            'password' => 'WrongPassword999!',
        ]);
        $pharmacyRes->assertStatus(302);
        $pharmacyRes->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $pharmacyRes->assertSessionMissing('_old_input.password');

        $adminRes = $this->post(route('admin.login'), [
            'email' => 'admin@georx.com',
            'password' => 'WrongPassword999!',
        ]);
        $adminRes->assertStatus(302);
        $adminRes->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $adminRes->assertSessionMissing('_old_input.password');
    }

    /** 5. Valid credentials follow the existing successful login flow */
    public function test_valid_credentials_authenticate_and_redirect_correctly(): void
    {
        $pharmacyRes = $this->post(route('pharmacy.login'), [
            'email' => 'partner@alijispharmacy.com',
            'password' => 'PartnerSecurePass123!',
        ]);
        $pharmacyRes->assertRedirect('/pharmacy/dashboard');
        $this->assertTrue(Auth::guard('pharmacy')->check());
        $this->assertEquals($this->pharmacyUser->id, Auth::guard('pharmacy')->id());

        $adminRes = $this->post(route('admin.login'), [
            'email' => 'admin@georx.com',
            'password' => 'AdminSecurePass123!',
        ]);
        $adminRes->assertRedirect('/admin/dashboard');
        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertEquals($this->adminUser->id, Auth::guard('admin')->id());
    }

    /** 6. Session regeneration and logout behavior */
    public function test_session_and_logout_behavior(): void
    {
        $this->post(route('admin.login'), [
            'email' => 'admin@georx.com',
            'password' => 'AdminSecurePass123!',
        ]);
        $this->assertTrue(Auth::guard('admin')->check());

        $logoutRes = $this->post(route('logout'));
        $logoutRes->assertRedirect('/admin/login');
        $this->assertFalse(Auth::guard('admin')->check());
        $this->assertFalse(Auth::guard('pharmacy')->check());
    }

    /** 7. Pharmacy and admin authorization boundaries remain enforced */
    public function test_pharmacy_and_admin_guards_are_isolated(): void
    {
        $this->post(route('pharmacy.login'), [
            'email' => 'partner@alijispharmacy.com',
            'password' => 'PartnerSecurePass123!',
        ]);
        $this->assertTrue(Auth::guard('pharmacy')->check());
        $this->assertFalse(Auth::guard('admin')->check());
    }

    /** 8. Priority 1: Cross-role login attempts produce identical generic failure and leave guards unauthenticated */
    public function test_cross_role_login_rejection_is_indistinguishable_and_leaves_guards_unauthenticated(): void
    {
        // 8a. Pharmacy user attempts admin login: should return generic "Invalid credentials."
        $adminAttempt = $this->post(route('admin.login'), [
            'email' => 'partner@alijispharmacy.com',
            'password' => 'PartnerSecurePass123!',
        ]);
        $adminAttempt->assertStatus(302);
        $adminAttempt->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $adminAttempt->assertSessionMissing('error'); // No "Access Denied. Administrator only."
        $adminAttempt->assertSessionMissing('_old_input.password');
        $this->assertFalse(Auth::guard('admin')->check());
        $this->assertFalse(Auth::guard('pharmacy')->check());

        // 8b. Admin user attempts pharmacy login: should return generic "Invalid credentials."
        $pharmacyAttempt = $this->post(route('pharmacy.login'), [
            'email' => 'admin@georx.com',
            'password' => 'AdminSecurePass123!',
        ]);
        $pharmacyAttempt->assertStatus(302);
        $pharmacyAttempt->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $pharmacyAttempt->assertSessionMissing('error'); // No "Access Denied. Pharmacy staff only."
        $pharmacyAttempt->assertSessionMissing('_old_input.password');
        $this->assertFalse(Auth::guard('pharmacy')->check());
        $this->assertFalse(Auth::guard('admin')->check());

        // 8c. Customer attempts admin login: should return generic "Invalid credentials."
        $customerAttempt = $this->post(route('admin.login'), [
            'email' => 'customer@example.com',
            'password' => 'CustomerPass123!',
        ]);
        $customerAttempt->assertStatus(302);
        $customerAttempt->assertSessionHasErrors(['email' => 'Invalid credentials.']);
        $this->assertFalse(Auth::guard('admin')->check());
    }

    /** 9. An unauthenticated user cannot access protected routes */
    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $adminRes = $this->get('/admin/dashboard');
        $adminRes->assertRedirect();

        $pharmacyRes = $this->get('/pharmacy/dashboard');
        $pharmacyRes->assertRedirect();
    }

    /** 10. No admin registration link or endpoint is introduced */
    public function test_no_admin_registration_link_or_endpoint_exists(): void
    {
        $response = $this->get(route('admin.login'));
        $response->assertDontSee('/admin/register');
        $response->assertDontSee('register', false);
        $this->assertFalse(Route::has('admin.register'));
    }

    /** 11. Password fields do not retain submitted password values */
    public function test_password_field_is_never_retained_in_old_input(): void
    {
        $response = $this->post(route('pharmacy.login'), [
            'email' => 'test@example.com',
            'password' => 'supersecretpass',
        ]);
        $response->assertSessionMissing('_old_input.password');
        $response->assertSessionHas('_old_input.email');
    }

    /** 12. Validation and session alerts render without unsafe HTML */
    public function test_validation_and_alerts_render_escaped_html(): void
    {
        $payload = '<script>alert("xss")</script>';
        $response = $this->withSession(['error' => $payload])
                         ->get(route('pharmacy.login'));

        $response->assertStatus(200);
        $response->assertDontSee('<script>alert("xss")</script>', false);
        $response->assertSee(e($payload), false);
    }

    /** 13. Existing password-reset and account-verification links are intact */
    public function test_password_recovery_and_registration_links_are_intact(): void
    {
        $pharmacyView = $this->get(route('pharmacy.login'));
        $pharmacyView->assertSee(route('pharmacy.register'));
        $pharmacyView->assertSee(route('pharmacy.password.request'));

        $adminView = $this->get(route('admin.login'));
        $adminView->assertSee(route('admin.password.request'));
    }

    /** 14. Existing rate-limiting behavior remains enabled and effective */
    public function test_rate_limiting_triggers_too_many_requests_on_excessive_attempts(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('admin.login'), [
                'email' => 'attacker@example.com',
                'password' => 'badpass' . $i,
            ]);
        }

        $response = $this->post(route('admin.login'), [
            'email' => 'attacker@example.com',
            'password' => 'badpass11',
        ]);
        $response->assertStatus(429);
    }

    /** 15. Both Blade templates compile successfully */
    public function test_both_blade_templates_compile_cleanly(): void
    {
        $errors = new \Illuminate\Support\ViewErrorBag();
        $pharmacyCompiled = view('auth.pharmacy-login', ['errors' => $errors])->render();
        $this->assertNotEmpty($pharmacyCompiled);
        $this->assertStringContainsString('Pharmacy Portal', $pharmacyCompiled);

        $adminCompiled = view('auth.admin-login', ['errors' => $errors])->render();
        $this->assertNotEmpty($adminCompiled);
        $this->assertStringContainsString('System Administration', $adminCompiled);
    }

    /** 16. Phase 1.A: Legitimate registration and email_verification OTP challenges succeed on mobile OTP endpoints */
    public function test_mobile_registration_and_email_verification_otp_succeed_and_issue_token(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        // 16a. Registration flow (purpose = 'registration')
        $regRes = $this->postJson('/api/register', [
            'name'                  => 'New Patient',
            'email'                 => 'newpatient@example.com',
            'phone'                 => '09171234567',
            'password'              => 'PatientPass123!',
            'password_confirmation' => 'PatientPass123!',
        ]);
        $regRes->assertStatus(202)
               ->assertJsonPath('status', 'verification_required');

        $challengeToken = $regRes->json('challenge_token');
        $this->assertNotEmpty($challengeToken);

        $sentOtp = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use (&$sentOtp) {
            $sentOtp = $mail->otp;
            return $mail->purpose === 'registration';
        });
        $this->assertNotNull($sentOtp);

        // Resend after cooldown on legitimate registration challenge succeeds
        $this->travel(61)->seconds();
        $resendRes = $this->postJson('/api/auth/otp/resend', [
            'challenge_token' => $challengeToken,
        ]);
        $resendRes->assertStatus(200)
                  ->assertJsonPath('status', 'success');

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use (&$sentOtp) {
            $sentOtp = $mail->otp;
            return $mail->purpose === 'registration';
        });

        // Verify legitimate registration OTP activates account and issues Sanctum token
        $verifyRes = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $challengeToken,
            'otp'             => $sentOtp,
        ]);
        $verifyRes->assertStatus(200)
                  ->assertJsonPath('status', 'success')
                  ->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);

        $newUser = User::where('email', 'newpatient@example.com')->firstOrFail();
        $this->assertEquals(User::STATUS_ACTIVE, $newUser->account_status);
        $this->assertNotNull($newUser->email_verified_at);
        $this->assertEquals(1, $newUser->tokens()->count());

        // 16b. Unverified login flow (purpose = 'email_verification')
        $unverifiedCustomer = User::create([
            'name'              => 'Unverified Customer',
            'email'             => 'unverified@example.com',
            'password'          => bcrypt('CustomerPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $loginRes = $this->postJson('/api/login', [
            'email'    => 'unverified@example.com',
            'password' => 'CustomerPass123!',
        ]);
        $loginRes->assertStatus(202)
                 ->assertJsonPath('status', 'verification_required');

        $loginChallengeToken = $loginRes->json('challenge_token');
        $loginOtp = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use (&$loginOtp) {
            if ($mail->purpose === 'email_verification') {
                $loginOtp = $mail->otp;
                return true;
            }
            return false;
        });

        $verifyLoginOtpRes = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $loginChallengeToken,
            'otp'             => $loginOtp,
        ]);
        $verifyLoginOtpRes->assertStatus(200)
                          ->assertJsonPath('status', 'success');

        $unverifiedCustomer->refresh();
        $this->assertEquals(User::STATUS_ACTIVE, $unverifiedCustomer->account_status);
        $this->assertNotNull($unverifiedCustomer->email_verified_at);
        $this->assertEquals(1, $unverifiedCustomer->tokens()->count());
    }

    /** 17. Phase 1.A: Password-reset OTP challenge is strictly rejected by /api/auth/otp/verify and never issues a Sanctum token or activates pending accounts */
    public function test_password_reset_otp_cannot_verify_or_issue_sanctum_token_on_mobile_otp_endpoint(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $pendingCustomer = User::create([
            'name'              => 'Pending Customer',
            'email'             => 'pending_reset@example.com',
            'password'          => bcrypt('OriginalPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $otpResult = \App\Services\EmailOtpService::createChallenge($pendingCustomer, 'password_reset');
        $resetChallengeToken = $otpResult['challenge_token'];

        $resetOtp = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use (&$resetOtp) {
            $resetOtp = $mail->otp;
            return $mail->purpose === 'password_reset';
        });
        $this->assertNotNull($resetOtp);

        // Attempt to submit password_reset challenge + valid OTP (even with forged client 'purpose' parameter) to /api/auth/otp/verify
        $verifyRes = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $resetChallengeToken,
            'otp'             => $resetOtp,
            'purpose'         => 'registration',
        ]);

        $verifyRes->assertStatus(400)
                  ->assertJsonPath('status', 'error')
                  ->assertJsonPath('message', 'Verification purpose mismatch.')
                  ->assertJsonMissingPath('token');

        // Verify pending account was NOT activated and NO Sanctum token was minted
        $pendingCustomer->refresh();
        $this->assertEquals(User::STATUS_PENDING_VERIFICATION, $pendingCustomer->account_status);
        $this->assertNull($pendingCustomer->email_verified_at);
        $this->assertEquals(0, $pendingCustomer->tokens()->count());

        // Verify password_reset challenge was NOT consumed or incremented by the rejected cross-purpose call
        $challenge = \App\Models\EmailOtpChallenge::where(
            'challenge_token_hash',
            \App\Services\EmailOtpService::hashChallengeToken($resetChallengeToken)
        )->firstOrFail();
        $this->assertNull($challenge->consumed_at);
        $this->assertEquals(0, $challenge->attempts);
    }

    /** 18. Phase 1.A: Password-reset OTP challenge cannot be resent or mutated through /api/auth/otp/resend */
    public function test_password_reset_otp_cannot_be_resent_or_mutated_via_registration_resend_endpoint(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $otpResult = \App\Services\EmailOtpService::createChallenge($this->customerUser, 'password_reset');
        $resetChallengeToken = $otpResult['challenge_token'];

        // Clear initial creation mail so we can assert no resend mail is dispatched
        \Illuminate\Support\Facades\Mail::fake();
        $this->travel(65)->seconds();

        $resendRes = $this->postJson('/api/auth/otp/resend', [
            'challenge_token' => $resetChallengeToken,
            'purpose'         => 'registration',
        ]);

        $resendRes->assertStatus(400)
                  ->assertJsonPath('status', 'error')
                  ->assertJsonPath('message', 'Verification purpose mismatch.');

        \Illuminate\Support\Facades\Mail::assertNothingSent();

        $challenge = \App\Models\EmailOtpChallenge::where(
            'challenge_token_hash',
            \App\Services\EmailOtpService::hashChallengeToken($resetChallengeToken)
        )->firstOrFail();
        $this->assertNull($challenge->consumed_at);
        $this->assertEquals(1, $challenge->send_count);
    }

    /** 19. Phase 1.A: Registration and email_verification challenges are rejected when password_reset purpose is required */
    public function test_registration_and_email_verification_otps_rejected_when_password_reset_purpose_required(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        foreach (['registration', 'email_verification'] as $purpose) {
            $otpResult = \App\Services\EmailOtpService::createChallenge($this->customerUser, $purpose);
            $challengeToken = $otpResult['challenge_token'];

            $code = null;
            \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use ($purpose, &$code) {
                if ($mail->purpose === $purpose) {
                    $code = $mail->otp;
                    return true;
                }
                return false;
            });

            $executed = false;
            $verifyExecResult = \App\Services\EmailOtpService::verifyAndExecute(
                challengeToken: $challengeToken,
                submittedOtp: $code,
                expectedPurpose: \App\Services\EmailOtpService::PURPOSE_PASSWORD_RESET,
                onVerified: function () use (&$executed): bool {
                    $executed = true;
                    return true;
                }
            );

            $this->assertFalse($verifyExecResult['success']);
            $this->assertEquals(400, $verifyExecResult['status']);
            $this->assertEquals('Verification purpose mismatch.', $verifyExecResult['error']);
            $this->assertFalse($executed);

            $resendResult = \App\Services\EmailOtpService::resendOtp(
                challengeToken: $challengeToken,
                expectedPurpose: \App\Services\EmailOtpService::PURPOSE_PASSWORD_RESET
            );
            $this->assertFalse($resendResult['success']);
            $this->assertEquals(400, $resendResult['status']);
            $this->assertEquals('Verification purpose mismatch.', $resendResult['error']);
        }
    }

    /** 20. Phase 1.A: Non-customer or suspended/rejected accounts cannot obtain a Sanctum token via /api/auth/otp/verify */
    public function test_non_customer_or_suspended_user_challenge_cannot_issue_mobile_token_on_verify_otp(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $suspendedCustomer = User::create([
            'name'              => 'Suspended Customer',
            'email'             => 'suspended_cust@example.com',
            'password'          => bcrypt('CustomerPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_SUSPENDED,
            'email_verified_at' => null,
        ]);

        foreach ([$this->adminUser, $this->pharmacyUser, $suspendedCustomer] as $ineligibleUser) {
            $otpResult = \App\Services\EmailOtpService::createChallenge($ineligibleUser, 'registration');
            $token = $otpResult['challenge_token'];

            $code = null;
            \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use ($ineligibleUser, &$code) {
                if ($mail->hasTo($ineligibleUser->email)) {
                    $code = $mail->otp;
                    return true;
                }
                return false;
            });

            $res = $this->postJson('/api/auth/otp/verify', [
                'challenge_token' => $token,
                'otp'             => $code,
            ]);

            $res->assertStatus(403)
                ->assertJsonMissingPath('token');

            $ineligibleUser->refresh();
            $this->assertEquals(0, $ineligibleUser->tokens()->count());
            if ($ineligibleUser->id === $suspendedCustomer->id) {
                $this->assertEquals(User::STATUS_SUSPENDED, $ineligibleUser->account_status);
            }
        }
    }

    /** 21. BrevoApiTransport omits null recipient/sender names and enforces key/HTTP checks */
    public function test_brevo_api_transport_omits_null_names_and_handles_errors(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'https://api.brevo.com/v3/smtp/email' => \Illuminate\Support\Facades\Http::response(['messageId' => '<test-123@georx.ph>'], 201),
        ]);

        config([
            'mail.from.address' => 'noreply@georx.ph',
            'mail.from.name'    => 'GEORX Medicine Hub',
        ]);

        $transport = new \App\Mail\Transport\BrevoApiTransport('test-brevo-key');
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address('noreply@georx.ph', 'GEORX Medicine Hub'))
            ->to('newpatient@gmail.com')
            ->subject('GEORX Verification')
            ->html('<p>Code: 123456</p>');

        $transport->send($email);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            $payload = $request->data();
            $toEntry = $payload['to'][0] ?? [];

            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->hasHeader('api-key', 'test-brevo-key')
                && ($payload['sender']['email'] ?? null) === 'noreply@georx.ph'
                && ($payload['sender']['name'] ?? null) === 'GEORX Medicine Hub'
                && ($toEntry['email'] ?? null) === 'newpatient@gmail.com'
                && !array_key_exists('name', $toEntry);
        });

        // Missing API key throws TransportException before external HTTP call
        $emptyKeyTransport = new \App\Mail\Transport\BrevoApiTransport('');
        $this->expectException(\Symfony\Component\Mailer\Exception\TransportException::class);
        $emptyKeyTransport->send($email);
    }

    /** 22. Registration never returns fake HTTP 202 on email failure, cleans up on definitive failure, and preserves ambiguous timeout */
    public function test_mobile_registration_handles_definitive_delivery_failure_and_ambiguous_timeout(): void
    {
        // A. Definitive Brevo failure rolls back newly created unverified user and returns 503
        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->once()
            ->with('failcase@gmail.com')
            ->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Brevo API delivery failed: HTTP 400 Bad Request'));

        $definitiveFailRes = $this->postJson('/api/register', [
            'name'                  => 'Fail Case',
            'email'                 => '  FailCase@Gmail.com  ',
            'phone'                 => '09171230001',
            'password'              => 'PatientPass123!',
            'password_confirmation' => 'PatientPass123!',
        ]);

        $definitiveFailRes->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonMissingPath('challenge_token');
        $this->assertStringNotContainsString('Brevo', $definitiveFailRes->getContent());
        $this->assertNull(User::where('email', 'failcase@gmail.com')->first());

        // B. Ambiguous timeout preserves challenge in ambiguous_timeout state and returns 503 with challenge_token
        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->once()
            ->with('timeoutcase@gmail.com')
            ->andThrow(new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 15001 milliseconds'));

        $timeoutRes = $this->postJson('/api/register', [
            'name'                  => 'Timeout Case',
            'email'                 => 'TimeoutCase@Gmail.com',
            'password'              => 'PatientPass123!',
            'password_confirmation' => 'PatientPass123!',
        ]);

        $timeoutRes->assertStatus(503)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('ambiguous_timeout', true)
            ->assertJsonStructure(['challenge_token', 'masked_email']);

        $timeoutChallenge = \App\Models\EmailOtpChallenge::where('email', 'timeoutcase@gmail.com')->first();
        $this->assertNotNull($timeoutChallenge);
        $this->assertEquals(\App\Services\EmailOtpService::STATE_AMBIGUOUS_TIMEOUT, $timeoutChallenge->delivery_state);
    }

    /** 23. Case-insensitive email registration, duplicate prevention, unverified login recovery, and post-verify Forgot Password eligibility */
    public function test_case_insensitive_registration_login_and_pending_re_registration_guidance(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        // Register with mixed-case email
        $regRes = $this->postJson('/api/register', [
            'name'                  => 'Case Patient',
            'email'                 => '  New.Patient@Gmail.COM ',
            'phone'                 => '09179998888',
            'password'              => 'PatientPass123!',
            'password_confirmation' => 'PatientPass123!',
        ]);
        $regRes->assertStatus(202)
            ->assertJsonPath('status', 'verification_required');

        $storedUser = User::where('email', 'new.patient@gmail.com')->first();
        $this->assertNotNull($storedUser);

        $challenge = \App\Models\EmailOtpChallenge::where('user_id', $storedUser->id)->firstOrFail();
        $this->assertEquals(\App\Services\EmailOtpService::STATE_DELIVERED, $challenge->delivery_state);

        // Duplicate registration attempt with different casing detects pending verification cleanly
        $dupRes = $this->postJson('/api/register', [
            'name'                  => 'Case Patient',
            'email'                 => 'new.patient@GMAIL.com',
            'password'              => 'PatientPass123!',
            'password_confirmation' => 'PatientPass123!',
        ]);
        $dupRes->assertStatus(422)
            ->assertJsonPath('verification_pending', true);

        // Unverified login succeeds with uppercase email and issues email_verification OTP
        $loginPendingRes = $this->postJson('/api/login', [
            'email'    => 'NEW.PATIENT@gmail.com',
            'password' => 'PatientPass123!',
            'role'     => 'customer',
        ]);
        $loginPendingRes->assertStatus(202)
            ->assertJsonPath('verification_required', true);

        $loginChallengeToken = $loginPendingRes->json('challenge_token');
        $loginOtp = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) use (&$loginOtp) {
            if ($mail->purpose === 'email_verification') {
                $loginOtp = $mail->otp;
                return true;
            }
            return false;
        });
        $this->assertNotNull($loginOtp);

        // Complete OTP verification -> activates account & sets email_verified_at
        $verifyRes = $this->postJson('/api/auth/otp/verify', [
            'challenge_token' => $loginChallengeToken,
            'otp'             => $loginOtp,
        ]);
        $verifyRes->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        $storedUser->refresh();
        $this->assertEquals(User::STATUS_ACTIVE, $storedUser->account_status);
        $this->assertNotNull($storedUser->email_verified_at);

        // Verified new user is immediately eligible for Forgot Password (real DB challenge, not dummy)
        \Illuminate\Support\Facades\Mail::fake();
        $forgotRes = $this->postJson('/api/auth/password/forgot', [
            'email' => 'New.Patient@Gmail.com',
        ]);
        $forgotRes->assertStatus(202);
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\EmailOtpMailable::class, function ($mail) {
            return $mail->purpose === 'password_reset';
        });

        // Unverified login also returns 503 instead of fake 202 when Brevo fails
        $unverified2 = User::create([
            'name'              => 'Unverified Two',
            'email'             => 'unverified2@gmail.com',
            'password'          => bcrypt('CustomerPass123!'),
            'role'              => User::ROLE_CUSTOMER,
            'account_status'    => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        \Illuminate\Support\Facades\Mail::shouldReceive('to')
            ->once()
            ->with('unverified2@gmail.com')
            ->andThrow(new \Symfony\Component\Mailer\Exception\TransportException('Brevo API delivery failed: HTTP 500'));

        $failedLoginRes = $this->postJson('/api/login', [
            'email'    => 'UNVERIFIED2@gmail.com',
            'password' => 'CustomerPass123!',
            'role'     => 'customer',
        ]);
        $failedLoginRes->assertStatus(503)
            ->assertJsonPath('status', 'error');
        $this->assertStringNotContainsString('Brevo', $failedLoginRes->getContent());
    }
}
