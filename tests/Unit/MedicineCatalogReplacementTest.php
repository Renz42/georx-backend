<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\PortalController;
use App\Models\Medicine;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;

class MedicineCatalogReplacementTest extends TestCase
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

    protected function tearDown(): void
    {
        Medicine::flushEventListeners();
        try {
            Mockery::close();
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Create a mock Medicine model saved in DB.
     */
    protected function createMedicine(string $oldImage = 'medicine-images/old-photo.png'): Medicine
    {
        return Medicine::create([
            'slug' => 'test-med-' . uniqid(),
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
            'image' => $oldImage,
            'description' => 'Original description',
        ]);
    }

    /**
     * Helper to prepare an initialized request with session and referer header.
     */
    protected function createCatalogRequest(int $medicineId, array $parameters = [], array $files = []): Request
    {
        $request = Request::create("/catalog/{$medicineId}", 'POST', $parameters, [], $files);
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->headers->set('referer', "/catalog/{$medicineId}");
        return $request;
    }

    /**
     * Test 1: Upload failure preserves old image reference and old object, returning user-facing error.
     */
    public function test_upload_failure_preserves_old_image_and_returns_error(): void
    {
        $medicine = $this->createMedicine('medicine-images/old-photo.png');

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andThrow(new \RuntimeException('Connection to storage timed out'));

        $mockStorage->shouldNotReceive('delete');

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        $file = UploadedFile::fake()->create('replacement.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [
            'description' => 'New Description',
        ], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('errors'), 'Session must contain validation errors');
        $errors = $session->get('errors')->getBag('default')->get('image');
        $this->assertNotEmpty($errors);
        $this->assertSame('The medicine photo could not be uploaded. Please try again.', $errors[0]);

        $fresh = Medicine::find($medicine->id);
        $this->assertSame('medicine-images/old-photo.png', $fresh->image, 'Old database image path must be preserved');
        $this->assertSame('Original description', $fresh->description, 'Description must not be modified if upload failed');
    }

    /**
     * Test 2: Database save failure attempts cleanup of newly uploaded object and preserves old reference and old object.
     */
    public function test_database_save_failure_attempts_new_object_cleanup_and_preserves_old_image(): void
    {
        $oldPath = 'medicine-images/preserved-old-object.png';
        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/new-uuid-file.png';
        $medicine = $this->createMedicine($oldPath);

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Expect compensating deletion of the newly uploaded object
        $mockStorage->shouldReceive('delete')
            ->once()
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $newPath)
            ->andReturn(true);

        // Ensure old object is NEVER deleted
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $oldPath);

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        // Force database save to fail
        Medicine::saving(function ($model) {
            if ($model->exists) {
                throw new \RuntimeException('Simulated PostgreSQL database constraint error');
            }
        });

        $file = UploadedFile::fake()->create('new-med.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [
            'description' => 'Attempted description',
        ], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('errors'));
        $errors = $session->get('errors')->getBag('default')->get('error');
        $this->assertNotEmpty($errors);
        $this->assertSame('The catalog could not be updated. Your previous image has been preserved.', $errors[0]);

        // Unregister listener to fetch clean record
        Medicine::flushEventListeners();
        $fresh = Medicine::find($medicine->id);
        $this->assertSame($oldPath, $fresh->image, 'Database must retain old image path');
    }

    /**
     * Test 3: Old-object deletion failure does not crash successful update.
     */
    public function test_old_object_deletion_failure_does_not_crash_successful_update(): void
    {
        $oldPath = 'medicine-images/old-to-delete.png';
        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/successful-new-file.png';
        $medicine = $this->createMedicine($oldPath);

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Old object deletion throws an error (e.g. Supabase network hiccup)
        $mockStorage->shouldReceive('delete')
            ->once()
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $oldPath)
            ->andThrow(new \RuntimeException('Supabase Storage 503 Service Unavailable'));

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        $file = UploadedFile::fake()->create('new.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [
            'description' => 'Updated successfully',
        ], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('success'), 'User must receive success message because DB save succeeded');
        $this->assertTrue($session->has('warning'), 'User must be notified that old image removal failed');

        $fresh = Medicine::find($medicine->id);
        $this->assertSame($newPath, $fresh->image, 'Database must be updated with the new image');
        $this->assertSame('Updated successfully', $fresh->description);
    }

    /**
     * Test 4: New-object cleanup failure after DB failure logs safely without masking original error.
     */
    public function test_new_object_cleanup_failure_after_db_failure_logs_safely_without_masking_original_error(): void
    {
        $oldPath = 'medicine-images/old-preserved.png';
        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/orphaned-candidate.png';
        $medicine = $this->createMedicine($oldPath);

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Cleanup deletion ALSO fails with an exception
        $mockStorage->shouldReceive('delete')
            ->once()
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $newPath)
            ->andThrow(new \RuntimeException('Network timeout during compensating deletion'));

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        // Force database save to fail
        Medicine::saving(function ($model) {
            if ($model->exists) {
                throw new \RuntimeException('Simulated Disk Full DB error');
            }
        });

        $file = UploadedFile::fake()->create('photo.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        // Original database error must still be returned to the session; cleanup failure must NOT crash or mask it
        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('errors'));
        $errors = $session->get('errors')->getBag('default')->get('error');
        $this->assertNotEmpty($errors);
        $this->assertSame('The catalog could not be updated. Your previous image has been preserved.', $errors[0]);

        Medicine::flushEventListeners();
        $fresh = Medicine::find($medicine->id);
        $this->assertSame($oldPath, $fresh->image, 'Old image path must remain in DB');
    }

    /**
     * Test 5: Model save returning false is treated as a database failure:
     * - Upload is attempted
     * - Save failure is detected
     * - Old database image reference remains unchanged
     * - Old Storage object is never deleted
     * - Cleanup of newly uploaded object is attempted
     * - User receives an error response rather than success
     * - Method does not continue into old-object deletion branch
     */
    public function test_save_returning_false_is_treated_as_database_failure_with_safe_cleanup(): void
    {
        $oldPath = 'medicine-images/old-preserved-on-false.png';
        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/new-attempted-file.png';
        $medicine = $this->createMedicine($oldPath);

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Expect compensating deletion of the newly uploaded object
        $mockStorage->shouldReceive('delete')
            ->once()
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $newPath)
            ->andReturn(true);

        // Ensure old object is NEVER deleted
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $oldPath);

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        // Eloquent returns false without throwing when 'saving' listener returns false
        Medicine::saving(function ($model) {
            if ($model->exists) {
                return false;
            }
        });

        $file = UploadedFile::fake()->create('replacement.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [
            'description' => 'Attempted description when save returns false',
        ], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertFalse($session->has('success'), 'Must NOT return success response when save returns false');
        $this->assertTrue($session->has('errors'), 'Session must contain error response');

        $errors = $session->get('errors')->getBag('default')->get('error');
        $this->assertNotEmpty($errors);
        $this->assertSame('The catalog could not be updated. Your previous image has been preserved.', $errors[0]);

        // Unregister listener to fetch clean record
        Medicine::flushEventListeners();
        $fresh = Medicine::find($medicine->id);
        $this->assertSame($oldPath, $fresh->image, 'Database must retain old image path');
        $this->assertSame('Original description', $fresh->description, 'Description must not be updated in DB');
    }

    /**
     * Test 6: Shared old image is not deleted when referenced by another medicine record.
     */
    public function test_shared_old_image_is_not_deleted_when_referenced_by_another_medicine(): void
    {
        $sharedImage = 'medicine-images/shared-brand-pack.png';
        $medicineA = $this->createMedicine($sharedImage);
        $medicineB = $this->createMedicine($sharedImage);

        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/brand-pack-a-v2.png';

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Old shared object must NOT be deleted because medicineB still references it
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $sharedImage);

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        $file = UploadedFile::fake()->create('new-pack.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicineA->id, [
            'description' => 'Updated pack A',
        ], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicineA->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('success'));

        $freshA = Medicine::find($medicineA->id);
        $this->assertSame($newPath, $freshA->image, 'Medicine A must have new image');

        $freshB = Medicine::find($medicineB->id);
        $this->assertSame($sharedImage, $freshB->image, 'Medicine B must still have shared image reference');
    }

    /**
     * Test 7: Shared references stored in different but equivalent path formats
     * (e.g. bare, storage/ prefixed, and full Supabase URL) are detected via canonical identity
     * and preserved against deletion.
     */
    public function test_shared_reference_in_different_path_formats_is_detected_and_preserved(): void
    {
        $canonicalKey = 'brand-shared-asset.png';
        // Medicine A uses standard relative bucket path
        $medicineA = $this->createMedicine("medicine-images/{$canonicalKey}");
        // Medicine B uses legacy storage/ prefix format
        $medicineB = $this->createMedicine("storage/medicine-images/{$canonicalKey}");
        // Medicine C uses full Supabase public HTTPS URL
        $medicineC = $this->createMedicine("https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/{$canonicalKey}");

        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/replacement-for-a.png';

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Crucial invariant: delete must NEVER be called for the shared asset under ANY representation
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, $canonicalKey);
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, "medicine-images/{$canonicalKey}");
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, "storage/medicine-images/{$canonicalKey}");

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        $file = UploadedFile::fake()->create('replacement.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicineA->id, [
            'description' => 'Updated pack A only',
        ], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicineA->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('success'));

        // Medicine A is updated
        $this->assertSame($newPath, Medicine::find($medicineA->id)->image);
        // Medicines B and C retain their shared references
        $this->assertSame("storage/medicine-images/{$canonicalKey}", Medicine::find($medicineB->id)->image);
        $this->assertSame("https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/{$canonicalKey}", Medicine::find($medicineC->id)->image);
    }

    /**
     * Test 8: External HTTPS image URL is never interpreted as permission to delete a Supabase Storage object.
     */
    public function test_external_https_url_is_never_treated_as_deletable_supabase_storage_object(): void
    {
        $externalUrl = 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=500';
        $medicine = $this->createMedicine($externalUrl);

        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/new-upload.png';

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Delete must NEVER be called on external URLs
        $mockStorage->shouldNotReceive('delete');

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        $file = UploadedFile::fake()->create('new.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('success'));

        $this->assertSame($newPath, Medicine::find($medicine->id)->image);
    }

    /**
     * Test 9: Legacy local storage path (medicines/...) is never sent to Supabase Storage delete.
     */
    public function test_legacy_medicines_path_is_never_sent_to_supabase_storage_delete(): void
    {
        $legacyLocalPath = 'medicines/old-legacy-product.png';
        $medicine = $this->createMedicine($legacyLocalPath);

        $newPath = 'https://mock.supabase.co/storage/v1/object/public/medicine-images/new-upload.png';

        $mockStorage = Mockery::mock(SupabaseStorageService::class);
        $mockStorage->shouldReceive('upload')
            ->once()
            ->andReturn($newPath);

        // Supabase delete must NEVER be called for medicines/... path
        $mockStorage->shouldNotReceive('delete')
            ->with(SupabaseStorageService::BUCKET_MEDICINE_IMAGES, Mockery::any());

        $this->app->instance(SupabaseStorageService::class, $mockStorage);

        $file = UploadedFile::fake()->create('new.png', 10, 'image/png');
        $request = $this->createCatalogRequest($medicine->id, [], ['image' => $file]);

        $controller = new PortalController();
        $response = $controller->updateCatalog($request, $medicine->id);

        $this->assertTrue($response->isRedirection());
        $session = $request->session();
        $this->assertTrue($session->has('success'));

        $this->assertSame($newPath, Medicine::find($medicine->id)->image);
    }
}
