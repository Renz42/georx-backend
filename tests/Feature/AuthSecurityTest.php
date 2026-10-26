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
        $pharmacyView->assertSee('mailto:support@georx.com?subject=GEORX%20Pharmacy%20Portal%20Password%20Reset%20Request');

        $adminView = $this->get(route('admin.login'));
        $adminView->assertSee('mailto:support@georx.com?subject=GEORX%20System%20Admin%20Password%20Reset%20Request');
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
}
