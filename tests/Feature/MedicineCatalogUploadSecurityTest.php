<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicineCatalogUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Pharmacy $pharmacyA;
    private Pharmacy $pharmacyB;
    private User $ownerA;
    private Medicine $medicineA;
    private Medicine $medicineB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pharmacyA = Pharmacy::create([
            'name' => 'Pharmacy Alpha',
            'slug' => 'pharmacy-alpha',
            'address' => 'Alijis Road',
            'latitude' => 10.6385,
            'longitude' => 122.9520,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->pharmacyB = Pharmacy::create([
            'name' => 'Pharmacy Beta',
            'slug' => 'pharmacy-beta',
            'address' => 'Libertad Street',
            'latitude' => 10.6400,
            'longitude' => 122.9550,
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->ownerA = User::create([
            'name' => 'Owner Alpha',
            'email' => 'owner.alpha@georx.test',
            'phone' => '09180000001',
            'password' => bcrypt('password123'),
            'role' => User::ROLE_PHARMACY_OWNER,
            'pharmacy_id' => $this->pharmacyA->id,
            'account_status' => User::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->medicineA = Medicine::create([
            'slug' => 'paracetamol-alpha-500mg',
            'generic_name' => 'Paracetamol',
            'brand_name' => 'Biogesic',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'description' => 'Initial description A',
            'image' => 'medicine-images/old-a.png',
        ]);

        $this->medicineB = Medicine::create([
            'slug' => 'ibuprofen-beta-200mg',
            'generic_name' => 'Ibuprofen',
            'brand_name' => 'Advil',
            'dosage_form' => 'Capsule',
            'strength' => '200mg',
            'description' => 'Initial description B',
            'image' => 'medicine-images/old-b.png',
        ]);

        $this->pharmacyA->medicines()->attach($this->medicineA->id, [
            'unit' => 'Piece / Tablet',
            'selling_price' => 10.00,
            'quantity_on_hand' => 25,
            'is_available' => true,
        ]);

        $this->pharmacyB->medicines()->attach($this->medicineB->id, [
            'unit' => 'Piece / Tablet',
            'selling_price' => 15.00,
            'quantity_on_hand' => 30,
            'is_available' => true,
        ]);
    }

    public function test_pharmacy_admin_cannot_modify_another_pharmacys_catalog_medicine(): void
    {
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('tamper.png', $pngBytes);

        $response = $this->actingAs($this->ownerA, 'pharmacy')
            ->post(route('portal.catalog.update', $this->medicineB->id), [
                'description' => 'Tampered description',
                'image' => $file,
            ]);

        $response->assertForbidden();

        $this->medicineB->refresh();
        $this->assertSame('Initial description B', $this->medicineB->description);
        $this->assertSame('medicine-images/old-b.png', $this->medicineB->image);
    }

    public function test_unauthenticated_user_cannot_update_catalog(): void
    {
        $response = $this->post(route('portal.catalog.update', $this->medicineA->id), [
            'description' => 'Unauthorized update',
        ]);

        $response->assertRedirect();
        $this->medicineA->refresh();
        $this->assertSame('Initial description A', $this->medicineA->description);
    }

    public function test_owning_pharmacy_can_upload_catalog_photo_to_supabase(): void
    {
        Config::set('services.supabase', [
            'url' => 'https://mock.supabase.co',
            'secret_key' => 'sb_secret_feature_test_key',
            'service_role_key' => null,
            'service_key' => null,
            'key' => null,
            'anon_key' => null,
            'publishable_key' => null,
        ]);

        Http::fake([
            'https://mock.supabase.co/storage/v1/object/*' => Http::response(['Key' => 'ok'], 200),
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('valid.png', $pngBytes);

        $response = $this->actingAs($this->ownerA, 'pharmacy')
            ->post(route('portal.catalog.update', $this->medicineA->id), [
                'description' => 'Updated by owner',
                'image' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->medicineA->refresh();
        $this->assertSame('Updated by owner', $this->medicineA->description);
        $this->assertStringStartsWith(
            'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_MEDICINE_IMAGES . '/',
            (string) $this->medicineA->image
        );
    }

    public function test_pharmacy_cover_upload_failure_preserves_existing_cover_photo_and_returns_validation_error(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Storage::fake('public');

        Config::set('services.supabase', [
            'url' => 'https://mock.supabase.co',
            'secret_key' => 'sb_secret_feature_test_key',
            'service_role_key' => null,
            'service_key' => null,
            'key' => null,
            'anon_key' => null,
            'publishable_key' => null,
        ]);

        $existingCover = 'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_PHARMACY_COVERS . '/existing-cover.png';
        $this->pharmacyA->update(['cover_photo' => $existingCover]);

        Http::fake([
            'https://mock.supabase.co/storage/v1/object/*' => Http::response(['error' => 'upstream down'], 500),
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('new-cover.png', $pngBytes);

        $response = $this->actingAs($this->ownerA, 'pharmacy')
            ->from(route('pharmacy.settings'))
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('pharmacy.settings.update'), [
                '_token' => 'test-csrf-token',
                'theme_color' => '#2563eb',
                'is_active' => '1',
                'cover_photo' => $file,
            ]);

        $response->assertRedirect(route('pharmacy.settings'));
        $response->assertSessionHasErrors('cover_photo');

        $this->pharmacyA->refresh();
        $this->assertSame($existingCover, $this->pharmacyA->cover_photo);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        Http::assertSentCount(1);
    }

    public function test_pharmacy_cover_upload_success_deletes_old_cover_only_after_upload_succeeds(): void
    {
        Config::set('services.supabase', [
            'url' => 'https://mock.supabase.co',
            'secret_key' => 'sb_secret_feature_test_key',
            'service_role_key' => null,
            'service_key' => null,
            'key' => null,
            'anon_key' => null,
            'publishable_key' => null,
        ]);

        $oldCover = 'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_PHARMACY_COVERS . '/old-cover.png';
        $this->pharmacyA->update(['cover_photo' => $oldCover]);

        Http::fake([
            'https://mock.supabase.co/storage/v1/object/*' => Http::response(['Key' => 'ok'], 200),
        ]);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $file = UploadedFile::fake()->createWithContent('replacement-cover.png', $pngBytes);

        $response = $this->actingAs($this->ownerA, 'pharmacy')
            ->post(route('pharmacy.settings.update'), [
                'theme_color' => '#2563eb',
                'is_active' => '1',
                'cover_photo' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->pharmacyA->refresh();
        $this->assertNotSame($oldCover, $this->pharmacyA->cover_photo);
        $this->assertStringStartsWith(
            'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_PHARMACY_COVERS . '/',
            (string) $this->pharmacyA->cover_photo
        );

        Http::assertSentCount(2);
        $recorded = Http::recorded();
        $this->assertSame('POST', $recorded[0][0]->method());
        $this->assertSame('DELETE', $recorded[1][0]->method());
    }

    public function test_public_pages_render_full_supabase_urls_for_covers_and_medicines(): void
    {
        Config::set('services.supabase', [
            'url' => 'https://mock.supabase.co',
            'secret_key' => 'sb_secret_feature_test_key',
            'service_role_key' => null,
            'service_key' => null,
            'key' => null,
            'anon_key' => null,
            'publishable_key' => null,
        ]);

        $coverUrl = 'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_PHARMACY_COVERS . '/alpha-cover.png';
        $logoUrl = 'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_PHARMACY_LOGOS . '/alpha-logo.png';
        $medicineUrl = 'https://mock.supabase.co/storage/v1/object/public/' . SupabaseStorageService::BUCKET_MEDICINE_IMAGES . '/alpha-med.png';

        $this->pharmacyA->update([
            'cover_photo' => $coverUrl,
            'logo' => $logoUrl,
        ]);
        $this->medicineA->update([
            'image' => $medicineUrl,
        ]);

        $profileResponse = $this->get(route('public.pharmacy.profile', $this->pharmacyA->id));
        $profileResponse->assertOk();
        $profileResponse->assertSee($coverUrl, false);
        $profileResponse->assertSee($logoUrl, false);
        $profileResponse->assertDontSee('/storage/https://', false);

        $catalogResponse = $this->get(route('public.pharmacy.catalog', $this->pharmacyA->id));
        $catalogResponse->assertOk();
        $catalogResponse->assertSee($coverUrl, false);
        $catalogResponse->assertSee($medicineUrl, false);
        $catalogResponse->assertDontSee('/storage/https://', false);

        $detailsResponse = $this->get(route('medicine.details', [
            'pharmacy_id' => $this->pharmacyA->id,
            'medicine_id' => $this->medicineA->id,
        ]));
        $detailsResponse->assertOk();
        $detailsResponse->assertSee($coverUrl, false);
        $detailsResponse->assertSee($medicineUrl, false);
        $detailsResponse->assertDontSee('/storage/https://', false);
    }
}
