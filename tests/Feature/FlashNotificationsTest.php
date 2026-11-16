<?php

namespace Tests\Feature;

use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FlashNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $pharmacyOwner;
    protected User $pendingOwner;
    protected Pharmacy $activePharmacy;
    protected Pharmacy $pendingPharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'flash.superadmin@georx.test',
            'phone' => '09170000100',
            'password' => Hash::make('AdminPass123!'),
            'role' => User::ROLE_ADMINISTRATOR,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->activePharmacy = Pharmacy::create([
            'name' => 'RH MEDHEALTH Alijis',
            'slug' => 'rh-medhealth-alijis-flash',
            'address' => 'Alijis Road, Bacolod City',
            'phone' => '09171112200',
            'email' => 'flash.rh@georx.test',
            'latitude' => 10.6407,
            'longitude' => 122.9463,
            'owner_name' => 'Gian Garcia',
            'license_number' => 'LTO-FLASH-001',
            'is_approved' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $this->pendingPharmacy = Pharmacy::create([
            'name' => 'Pending Care Pharmacy',
            'slug' => 'pending-care-flash',
            'address' => 'Alijis Road, Bacolod City',
            'phone' => '09171112201',
            'email' => 'flash.pending@georx.test',
            'latitude' => 10.6410,
            'longitude' => 122.9470,
            'owner_name' => 'Maria Reyes',
            'license_number' => 'LTO-FLASH-002',
            'is_approved' => false,
            'is_active' => false,
            'status' => 'pending',
        ]);

        $this->pharmacyOwner = User::create([
            'name' => 'Gian Garcia',
            'email' => 'flash.owner@georx.test',
            'phone' => '09170000101',
            'password' => Hash::make('OwnerPass123!'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->activePharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->pendingOwner = User::create([
            'name' => 'Maria Reyes',
            'email' => 'flash.pending.owner@georx.test',
            'phone' => '09170000102',
            'password' => Hash::make('OwnerPass123!'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->pendingPharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    public function test_super_admin_success_flash_renders_single_auto_dismiss_toast(): void
    {
        $message = 'Platform settings updated cleanly!';

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->withSession(['success' => $message])
            ->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('id="georxFlashNotificationContainer"', false)
            ->assertSee('top-[70px] lg:top-5', false)
            ->assertSee('z-[60]', false)
            ->assertSee('data-flash-type="success"', false)
            ->assertSee('data-auto-dismiss="1"', false)
            ->assertSee('data-duration-ms="4500"', false)
            ->assertSee('data-flash-dismiss', false)
            ->assertSee($message);

        $this->assertSame(1, substr_count($response->getContent(), $message));
    }

    public function test_pharmacy_inventory_and_catalog_success_flashes_render_without_duplicates(): void
    {
        $invMessage = 'New medicine created and added to your stock!';
        $invResponse = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession(['success' => $invMessage])
            ->get(route('portal.inventory'));

        $invResponse->assertOk()
            ->assertSee('id="georxFlashNotificationContainer"', false)
            ->assertSee('top-[84px]', false)
            ->assertSee('data-flash-type="success"', false)
            ->assertSee('data-auto-dismiss="1"', false)
            ->assertSee('data-duration-ms="4500"', false)
            ->assertSee($invMessage);

        $this->assertSame(1, substr_count($invResponse->getContent(), $invMessage));

        $catMessage = 'Co-Amoxiclav catalog details updated successfully!';
        $catResponse = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession(['success' => $catMessage])
            ->get(route('portal.catalog'));

        $catResponse->assertOk()
            ->assertSee('data-flash-type="success"', false)
            ->assertSee('data-auto-dismiss="1"', false)
            ->assertSee($catMessage);

        $this->assertSame(1, substr_count($catResponse->getContent(), $catMessage));
    }

    public function test_pharmacy_orders_renders_info_flash_with_auto_dismiss(): void
    {
        $infoMessage = 'Order #42 is already in transit.';

        $response = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession(['info' => $infoMessage])
            ->get(route('portal.orders'));

        $response->assertOk()
            ->assertSee('id="georxFlashNotificationContainer"', false)
            ->assertSee('data-flash-type="info"', false)
            ->assertSee('data-auto-dismiss="1"', false)
            ->assertSee('data-duration-ms="4500"', false)
            ->assertSee($infoMessage);

        $this->assertSame(1, substr_count($response->getContent(), $infoMessage));
    }

    public function test_pharmacy_profile_renders_success_flash_and_keeps_permanent_verified_notice(): void
    {
        $profileMessage = 'Profile updated!';

        $response = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession(['success' => $profileMessage])
            ->get(route('portal.profile'));

        $response->assertOk()
            ->assertSee('id="georxFlashNotificationContainer"', false)
            ->assertSee('data-flash-type="success"', false)
            ->assertSee('data-auto-dismiss="1"', false)
            ->assertSee($profileMessage)
            ->assertSee('Verified Account Information');

        $this->assertSame(1, substr_count($response->getContent(), $profileMessage));
    }

    public function test_pharmacy_dashboard_preserves_permanent_pending_approval_banner(): void
    {
        $dashMessage = 'Stock batch saved.';

        $response = $this->actingAs($this->pendingOwner, 'pharmacy')
            ->withSession(['success' => $dashMessage])
            ->get(route('portal.dashboard'));

        $response->assertOk()
            ->assertSee('Account Pending Verification')
            ->assertSee('data-flash-type="success"', false)
            ->assertSee('data-auto-dismiss="1"', false)
            ->assertSee($dashMessage);

        $this->assertSame(1, substr_count($response->getContent(), $dashMessage));
    }

    public function test_error_and_warning_flashes_remain_persistent_without_auto_dismiss(): void
    {
        $response = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession([
                'success' => 'Catalog saved.',
                'warning' => 'Previous photo cleanup failed.',
                'error' => 'Upload check failed.',
            ])
            ->get(route('portal.catalog'));

        $html = $response->getContent();
        $response->assertOk()
            ->assertSee('Catalog saved.')
            ->assertSee('Previous photo cleanup failed.')
            ->assertSee('Upload check failed.');

        $this->assertSame(1, preg_match_all('/data-flash-type="success"[^>]*data-auto-dismiss="1"/s', $html));
        $this->assertSame(1, preg_match_all('/data-flash-type="warning"[^>]*data-auto-dismiss="0"/s', $html));
        $this->assertSame(1, preg_match_all('/data-flash-type="error"[^>]*data-auto-dismiss="0"/s', $html));
    }

    public function test_validation_errors_remain_inline_on_both_portals_without_auto_dismiss(): void
    {
        $errorBag = (new ViewErrorBag())->put('default', new MessageBag(['image' => 'Image exceeds 2MB limit.']));

        $pharmacyRes = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession(['errors' => $errorBag])
            ->get(route('portal.catalog'));

        $pharmacyRes->assertOk()
            ->assertSee('Image exceeds 2MB limit.')
            ->assertDontSee('id="georxFlashNotificationContainer"', false);

        $adminErrorBag = (new ViewErrorBag())->put('default', new MessageBag(['reason' => 'Suspension reason is too short.']));
        $adminRes = $this->actingAs($this->superAdmin, 'admin')
            ->withSession(['errors' => $adminErrorBag])
            ->get(route('admin.pharmacies'));

        $adminRes->assertOk()
            ->assertSee('Suspension reason is too short.')
            ->assertDontSee('id="georxFlashNotificationContainer"', false);
    }

    public function test_flash_notifications_escape_html_safely(): void
    {
        $payload = '<script>alert("xss")</script>';

        $response = $this->actingAs($this->pharmacyOwner, 'pharmacy')
            ->withSession(['success' => $payload])
            ->get(route('portal.inventory'));

        $response->assertOk()
            ->assertDontSee($payload, false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }
}
