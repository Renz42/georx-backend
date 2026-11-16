<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use App\Models\AuditLog;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Pharmacy;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminPortalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected Pharmacy $activePharmacy;
    protected Pharmacy $suspendedPharmacy;
    protected Pharmacy $pendingPharmacy;
    protected User $ownerUser;
    protected User $customerUser;
    protected User $patientUser;
    protected User $pharmacistUser;
    protected User $staffUser;
    protected User $legacyDriverUser;
    protected Medicine $medCatapres;
    protected Medicine $medBiogesic;
    protected Medicine $medVirlix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'name' => 'Platform Super Admin',
            'email' => 'superadmin@georx.com',
            'phone' => '09170000001',
            'password' => Hash::make('AdminPass123!'),
            'role' => User::ROLE_ADMINISTRATOR,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Inside Barangay Alijis (10.6407, 122.9463)
        $this->activePharmacy = Pharmacy::create([
            'name' => 'RH MEDHEALTH Alijis',
            'slug' => 'rh-medhealth-alijis',
            'address' => 'Alijis Road, Barangay Alijis, Bacolod City',
            'phone' => '09171112222',
            'email' => 'rhmedhealth@georx.com',
            'latitude' => 10.6407,
            'longitude' => 122.9463,
            'owner_name' => 'Gian Garcia',
            'license_number' => 'LTO-ALIJIS-001',
            'is_approved' => true,
            'is_active' => true,
            'status' => 'approved',
        ]);

        $this->suspendedPharmacy = Pharmacy::create([
            'name' => 'TGP Pharmacy Suspended',
            'slug' => 'tgp-pharmacy-suspended',
            'address' => 'Alijis Road, Barangay Alijis, Bacolod City',
            'phone' => '09173334444',
            'email' => 'tgp@georx.com',
            'latitude' => 10.6412,
            'longitude' => 122.9470,
            'owner_name' => 'Maria Santos',
            'license_number' => 'LTO-ALIJIS-002',
            'is_approved' => true,
            'is_active' => false,
            'status' => 'suspended',
        ]);

        $this->pendingPharmacy = Pharmacy::create([
            'name' => 'New Alijis Care Pharmacy',
            'slug' => 'new-alijis-care-pharmacy',
            'address' => 'Olympia Village, Barangay Alijis, Bacolod City',
            'phone' => '09175556666',
            'email' => 'newcare@georx.com',
            'latitude' => 10.6405,
            'longitude' => 122.9455,
            'owner_name' => 'Dr. Elena Cruz',
            'license_number' => 'LTO-ALIJIS-003',
            'is_approved' => false,
            'is_active' => false,
            'status' => 'pending',
        ]);

        $this->ownerUser = User::create([
            'name' => 'Gian Garcia',
            'email' => 'rhmedhealth@georx.com',
            'phone' => '09171112222',
            'password' => Hash::make('OwnerPass123!'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->activePharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->customerUser = User::create([
            'name' => 'Juan Customer',
            'email' => 'juan.customer@georx.com',
            'phone' => '09177778881',
            'password' => Hash::make('PatientPass123!'),
            'role' => User::ROLE_CUSTOMER,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->patientUser = User::create([
            'name' => 'Ana Patient',
            'email' => 'ana.patient@georx.com',
            'phone' => '09177778882',
            'password' => Hash::make('PatientPass123!'),
            'role' => 'patient',
            'account_status' => User::STATUS_PENDING_VERIFICATION,
            'email_verified_at' => null,
        ]);

        $this->pharmacistUser = User::create([
            'name' => 'RPh. Marco Reyes',
            'email' => 'pharmacist@georx.com',
            'phone' => '09177778883',
            'password' => Hash::make('PharmPass123!'),
            'role' => User::ROLE_PHARMACIST,
            'pharmacy_id' => $this->activePharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->staffUser = User::create([
            'name' => 'Staff Lorna',
            'email' => 'staff@georx.com',
            'phone' => '09177778884',
            'password' => Hash::make('StaffPass123!'),
            'role' => User::ROLE_PHARMACY_STAFF,
            'pharmacy_id' => $this->activePharmacy->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->legacyDriverUser = User::create([
            'name' => 'Legacy Test Driver',
            'email' => 'driver.legacy@test.georx',
            'phone' => '09179990000',
            'password' => Hash::make('DriverPass123!'),
            'role' => User::ROLE_DRIVER,
            'account_status' => User::STATUS_APPROVED,
            'email_verified_at' => now(),
        ]);

        $this->medCatapres = Medicine::create([
            'slug' => 'catapres-clonidine-75mcg',
            'brand_name' => 'Catapres',
            'generic_name' => 'Clonidine',
            'dosage_form' => 'Tablet',
            'strength' => '75mcg',
            'drug_category' => 'Cardiovascular',
            'prescription_required' => true,
            'description' => 'Antihypertensive agent.',
            'image' => 'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/4d7433a4-d24b-436d-b1d9-f9ad46d672e2.jpg',
        ]);

        $this->medBiogesic = Medicine::create([
            'slug' => 'biogesic-paracetamol-500mg',
            'brand_name' => 'Biogesic',
            'generic_name' => 'Paracetamol',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'drug_category' => 'Analgesic',
            'prescription_required' => false,
            'description' => 'For fever and mild pain.',
        ]);

        $this->medVirlix = Medicine::create([
            'slug' => 'virlix-cetirizine-10mg',
            'brand_name' => 'Virlix',
            'generic_name' => 'Cetirizine',
            'dosage_form' => 'Tablet',
            'strength' => '10mg',
            'drug_category' => 'Antihistamine',
            'prescription_required' => false,
            'description' => 'For allergy relief.',
        ]);

        // Attach inventory to active pharmacy (1 healthy stock, 1 low stock)
        $this->activePharmacy->medicines()->attach($this->medCatapres->id, [
            'quantity_on_hand' => 50,
            'reorder_level' => 10,
            'selling_price' => 50.00,
            'is_available' => true,
        ]);
        $this->activePharmacy->medicines()->attach($this->medBiogesic->id, [
            'quantity_on_hand' => 5,
            'reorder_level' => 10,
            'selling_price' => 8.50,
            'is_available' => true,
        ]);

        // Attach out-of-stock item to suspended pharmacy (must be excluded from active store stock alerts)
        $this->suspendedPharmacy->medicines()->attach($this->medVirlix->id, [
            'quantity_on_hand' => 0,
            'reorder_level' => 10,
            'selling_price' => 25.00,
            'is_available' => false,
        ]);
    }

    public function test_phase1_dashboard_metrics_reconcile_roles_orders_financials_searches_and_inventory(): void
    {
        // 1 Delivered order: total 150.00 (100.00 medicine + 50.00 delivery fee)
        $deliveredOrder = Order::create([
            'user_id' => $this->customerUser->id,
            'pharmacy_id' => $this->activePharmacy->id,
            'status' => Order::STATUS_DELIVERED,
            'total_amount' => 150.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis Road, Bacolod City',
        ]);
        OrderItem::create([
            'order_id' => $deliveredOrder->id,
            'medicine_id' => $this->medCatapres->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        // 1 Active order (pending_confirmation) for Biogesic (must NOT count in completed units sold)
        $activeOrder = Order::create([
            'user_id' => $this->customerUser->id,
            'pharmacy_id' => $this->activePharmacy->id,
            'status' => Order::STATUS_PENDING_CONFIRMATION,
            'total_amount' => 75.50,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis Road, Bacolod City',
        ]);
        OrderItem::create([
            'order_id' => $activeOrder->id,
            'medicine_id' => $this->medBiogesic->id,
            'quantity' => 3,
            'price' => 8.50,
        ]);

        // 1 Rejected order for Virlix (must NOT count in completed units sold)
        $rejectedOrder = Order::create([
            'user_id' => $this->customerUser->id,
            'pharmacy_id' => $this->activePharmacy->id,
            'status' => Order::STATUS_REJECTED,
            'total_amount' => 75.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis Road, Bacolod City',
        ]);
        OrderItem::create([
            'order_id' => $rejectedOrder->id,
            'medicine_id' => $this->medVirlix->id,
            'quantity' => 4,
            'price' => 25.00,
        ]);

        // Case-insensitive search logs + 1 short keystroke ('a')
        SearchLog::create(['term' => 'Paracetamol', 'results_count' => 1]);
        SearchLog::create(['term' => 'paracetamol', 'results_count' => 1]);
        SearchLog::create(['term' => '  PARACETAMOL  ', 'results_count' => 1]);
        SearchLog::create(['term' => 'a', 'results_count' => 0]);

        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.dashboard'));

        $response->assertOk();

        // Verify strict 3-role distribution combines customer + patient (2), pharmacy roles (3), admins, and excludes legacy driver (1)
        $roleCounts = $response->viewData('roleCounts');
        $stats = $response->viewData('stats');
        $expectedAdmins = User::whereIn('role', AdminController::ADMIN_ROLES)->count();

        $this->assertSame(['Customers', 'Pharmacy Admin', 'Administrator'], array_keys($roleCounts));
        $this->assertSame(2, $roleCounts['Customers']);
        $this->assertSame(3, $roleCounts['Pharmacy Admin']);
        $this->assertSame($expectedAdmins, $roleCounts['Administrator']);
        $this->assertArrayNotHasKey('Drivers', $roleCounts);
        $this->assertSame(5 + $expectedAdmins, $stats['users']);
        $this->assertSame($stats['users'], array_sum($roleCounts));
        $this->assertSame(1, $stats['legacy_drivers_excluded']);

        $response->assertSee('Total Platform Revenue', false)
            ->assertSee('Total Pharmacies', false)
            ->assertSee('Active Pharmacies', false)
            ->assertSee('Pending Review', false)
            ->assertSee('Platform Growth (6 Months)', false)
            ->assertSee('User Role Distribution', false)
            ->assertSee('Most Searched', false)
            ->assertSee('Most Ordered', false)
            ->assertSee('Recent Order Activity', false)
            ->assertDontSee('Inventory Status', false)
            ->assertDontSee('Inventory Health', false)
            ->assertDontSee('Quick Actions', false)
            ->assertDontSee('Drivers', false)
            ->assertDontSee('Archived Drivers', false);

        // Verify order status reconciliation: 1 delivered + 1 active + 1 rejected = 3 total
        $this->assertSame(3, $response->viewData('totalOrders'));
        $this->assertSame(1, $response->viewData('deliveredOrders'));
        $this->assertSame(1, $response->viewData('pendingOrders'));
        $this->assertSame(1, $response->viewData('rejectedOrCancelledOrders'));

        // Verify financial breakdown: 100.00 GMV + 50.00 Courier Fee = 150.00 Completed Order Value
        $this->assertSame(150.00, $response->viewData('completedOrderValue'));
        $this->assertSame(100.00, $response->viewData('medicineSalesGmv'));
        $this->assertSame(50.00, $response->viewData('estimatedDeliveryFees'));

        // Verify Most Ordered includes ONLY delivered order (Catapres: 2 units; excludes Biogesic and Virlix)
        $mostOrdered = $response->viewData('mostOrdered');
        $this->assertCount(1, $mostOrdered);
        $this->assertSame($this->medCatapres->id, (int) $mostOrdered->first()->medicine_id);
        $this->assertSame(2, (int) $mostOrdered->first()->total_qty);

        // Verify Most Searched merges case-insensitive 'Paracetamol' (3) and excludes 1-char 'a'
        $mostSearched = $response->viewData('mostSearched');
        $this->assertCount(1, $mostSearched);
        $this->assertSame('Paracetamol', $mostSearched->first()->term);
        $this->assertSame(3, (int) $mostSearched->first()->search_count);

        // Verify Inventory Status counts only active approved pharmacies (1 low stock, 0 out of stock)
        $this->assertSame(1, $response->viewData('lowStockCount'));
        $this->assertSame(0, $response->viewData('outOfStockCount'));

        // Verify Reports page reconciles with Dashboard
        $reportsRes = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.reports'));
        $reportsRes->assertOk();
        $this->assertSame(1, $reportsRes->viewData('lowStockCount'));
        $this->assertSame(0, $reportsRes->viewData('outOfStockCount'));
        $this->assertSame(100.00, $reportsRes->viewData('medicineSalesGmv'));
    }

    public function test_phase2_medicine_master_catalog_loads_without_crash_and_supports_search_and_filters(): void
    {
        $response = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.medicines'));

        $response->assertOk();
        $response->assertSee('Catapres');
        $response->assertSee('Clonidine');
        $response->assertSee('Biogesic');
        $response->assertSee('Virlix');
        $response->assertDontSee('admin.medicines.destroy');

        // Filter by category and search
        $filtered = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.medicines', [
                'search' => 'Clonidine',
                'category' => 'Cardiovascular',
                'availability' => 'in_stock',
            ]));

        $filtered->assertOk();
        $filtered->assertSee('Catapres');
        $filtered->assertDontSee('Biogesic');

        // Read-only enforcement: POST to /admin/medicines is rejected with 405
        $this->actingAs($this->superAdmin, 'admin')
            ->post('/admin/medicines', ['name' => 'Unauthorized Write'])
            ->assertStatus(405);
    }

    public function test_phase3_pharmacy_inventory_audit_loads_for_active_and_suspended_pharmacies(): void
    {
        $activeAudit = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.pharmacies.inventory', $this->activePharmacy));

        $activeAudit->assertOk();
        $activeAudit->assertSee('RH MEDHEALTH Alijis');
        $activeAudit->assertSee('Catapres');
        $activeAudit->assertSee('Biogesic');
        $activeAudit->assertSee('Low Stock');

        // Filter by low_stock
        $lowStockAudit = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.pharmacies.inventory', [
                'pharmacy' => $this->activePharmacy->id,
                'stock_status' => 'low_stock',
            ]));
        $lowStockAudit->assertOk();
        $lowStockAudit->assertSee('Biogesic');
        $lowStockAudit->assertDontSee('Catapres');

        // Suspended pharmacy historical inventory can still be audited safely
        $suspendedAudit = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.pharmacies.inventory', $this->suspendedPharmacy));
        $suspendedAudit->assertOk();
        $suspendedAudit->assertSee('TGP Pharmacy Suspended');
        $suspendedAudit->assertSee('Virlix');
    }

    public function test_phase4_pharmacy_governance_search_approval_suspension_restoration_and_non_destructive_rejection(): void
    {
        // 1. Directory search & status filter
        $dirRes = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.pharmacies', ['search' => 'RH MEDHEALTH', 'status' => 'approved']));
        $dirRes->assertOk();
        $dirRes->assertSee('RH MEDHEALTH Alijis');
        $dirRes->assertDontSee('TGP Pharmacy Suspended');

        // 2. Suspend active pharmacy requires reason (>= 5 chars)
        $this->actingAs($this->superAdmin, 'admin')
            ->from(route('admin.pharmacies'))
            ->patch(route('admin.pharmacies.suspend', $this->activePharmacy), ['reason' => 'no'])
            ->assertSessionHasErrors('reason');

        $suspendRes = $this->actingAs($this->superAdmin, 'admin')
            ->patch(route('admin.pharmacies.suspend', $this->activePharmacy), [
                'reason' => 'Scheduled LTO license verification audit.',
            ]);
        $suspendRes->assertRedirect(route('admin.pharmacies', ['status' => 'suspended']));
        $this->activePharmacy->refresh();
        $this->assertSame('suspended', $this->activePharmacy->status);
        $this->assertFalse($this->activePharmacy->is_active);
        $this->assertSame(2, $this->activePharmacy->medicines()->count(), 'Inventory must remain intact after suspension.');
        $this->assertTrue(AuditLog::where('pharmacy_id', $this->activePharmacy->id)->where('action', 'Pharmacy Suspended')->exists());

        // 3. Restore suspended approved pharmacy inside Barangay Alijis
        $restoreRes = $this->actingAs($this->superAdmin, 'admin')
            ->patch(route('admin.pharmacies.restore', $this->activePharmacy), [
                'reason' => 'LTO license verified and cleared.',
            ]);
        $restoreRes->assertRedirect(route('admin.pharmacies', ['status' => 'approved']));
        $this->activePharmacy->refresh();
        $this->assertSame('approved', $this->activePharmacy->status);
        $this->assertTrue($this->activePharmacy->is_active);
        $this->assertTrue(AuditLog::where('pharmacy_id', $this->activePharmacy->id)->where('action', 'Pharmacy Restored')->exists());

        // 4. Non-destructive rejection of pending application preserves Pharmacy record
        $rejectRes = $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.pharmacies.reject', $this->pendingPharmacy), [
                'reason' => 'Incomplete LTO documentation.',
            ]);
        $rejectRes->assertRedirect(route('admin.pharmacies', ['status' => 'rejected']));
        $this->pendingPharmacy->refresh();
        $this->assertSame('rejected', $this->pendingPharmacy->status);
        $this->assertFalse($this->pendingPharmacy->is_approved);

        // 5. Rejected pharmacy cannot be restored via restorePharmacy
        $this->actingAs($this->superAdmin, 'admin')
            ->from(route('admin.pharmacies'))
            ->patch(route('admin.pharmacies.restore', $this->pendingPharmacy))
            ->assertRedirect(route('admin.pharmacies'))
            ->assertSessionHas('error');
        $this->pendingPharmacy->refresh();
        $this->assertSame('rejected', $this->pendingPharmacy->status);
    }

    public function test_phase5_user_management_role_classification_and_destructive_action_safeguards(): void
    {
        // 1. Centralized role classification never misclassifies pharmacist, staff, driver, or unknown roles as Patient
        $this->assertSame('Pharmacist', AdminController::classifyUserRole('pharmacist')['label']);
        $this->assertSame('Pharmacy Staff', AdminController::classifyUserRole('pharmacy_staff')['label']);
        $this->assertSame('Patient / Customer', AdminController::classifyUserRole('customer')['label']);
        $this->assertSame('Patient / Customer', AdminController::classifyUserRole('patient')['label']);
        $this->assertSame('Historical Driver (Archived)', AdminController::classifyUserRole('driver')['label']);
        $this->assertStringStartsWith('Unclassified', AdminController::classifyUserRole('mystery_role')['label']);

        // 2. Users directory loads operational users by default and excludes archived drivers
        $usersRes = $this->actingAs($this->superAdmin, 'admin')
            ->get(route('admin.users'));
        $usersRes->assertOk();
        $usersRes->assertSee('RPh. Marco Reyes');
        $usersRes->assertSee('Pharmacist');
        $usersRes->assertSee('Staff Lorna');
        $usersRes->assertSee('Pharmacy Staff');
        $usersRes->assertDontSee('driver.legacy@test.georx');

        // 3. Super Admin self-deletion and self-suspension are blocked
        $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.users.destroy', $this->superAdmin->id))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');
        $this->assertNotNull(User::find($this->superAdmin->id));

        $this->actingAs($this->superAdmin, 'admin')
            ->patch(route('admin.users.status', $this->superAdmin->id), ['action' => 'suspend'])
            ->assertSessionHas('error');
        $this->assertSame(User::STATUS_ACTIVE, $this->superAdmin->fresh()->account_status);

        // 4. User linked to a pharmacy or historical order is protected from hard deletion
        Order::create([
            'user_id' => $this->customerUser->id,
            'pharmacy_id' => $this->activePharmacy->id,
            'status' => Order::STATUS_DELIVERED,
            'total_amount' => 100.00,
            'delivery_fee' => 50.00,
            'payment_method' => 'cod',
            'delivery_address' => 'Alijis Road',
        ]);

        $this->actingAs($this->superAdmin, 'admin')
            ->delete(route('admin.users.destroy', $this->customerUser->id))
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('error');
        $this->assertNotNull(User::find($this->customerUser->id));

        // 5. Reversible suspension & reactivation works safely for customer
        $this->actingAs($this->superAdmin, 'admin')
            ->patch(route('admin.users.status', $this->customerUser->id), ['action' => 'suspend'])
            ->assertSessionHas('success');
        $this->assertSame(User::STATUS_SUSPENDED, $this->customerUser->fresh()->account_status);

        $this->actingAs($this->superAdmin, 'admin')
            ->patch(route('admin.users.status', $this->customerUser->id), ['action' => 'activate'])
            ->assertSessionHas('success');
        $this->assertSame(User::STATUS_ACTIVE, $this->customerUser->fresh()->account_status);
    }

    public function test_phase6_and_7_settings_validation_and_authorization_enforcement(): void
    {
        // Minimum fee exceeding maximum fee cap is rejected
        $this->actingAs($this->superAdmin, 'admin')
            ->from(route('admin.settings'))
            ->post(route('admin.settings.update'), [
                'base_delivery_fee' => 50,
                'delivery_per_km_fee' => 10,
                'delivery_minimum_fee' => 200,
                'delivery_maximum_estimate' => 100,
            ])
            ->assertSessionHasErrors('delivery_minimum_fee');

        // Valid settings update succeeds
        $this->actingAs($this->superAdmin, 'admin')
            ->post(route('admin.settings.update'), [
                'base_delivery_fee' => 50,
                'delivery_per_km_fee' => 5,
                'delivery_minimum_fee' => 50,
                'delivery_maximum_estimate' => 150,
                'delivery_estimate_enabled' => '1',
                'enable_new_registrations' => '1',
            ])
            ->assertSessionHas('success');

        // Non-admin users cannot access Super Admin routes even if authenticated on admin/web/pharmacy guards
        $this->actingAs($this->customerUser, 'admin')
            ->get(route('admin.dashboard'))
            ->assertRedirect('/dashboard');

        $this->actingAs($this->ownerUser, 'admin')
            ->get(route('admin.medicines'))
            ->assertRedirect('/pharmacy/dashboard');
    }
}
