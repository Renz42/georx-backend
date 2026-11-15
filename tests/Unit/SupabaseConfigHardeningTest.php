<?php

namespace Tests\Unit;

use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseConfigHardeningTest extends TestCase
{
    public function test_normalize_supabase_url_validates_scheme_and_host(): void
    {
        $this->assertSame(
            'https://example.supabase.co',
            SupabaseStorageService::normalizeSupabaseUrl(' https://example.supabase.co/// ')
        );
        $this->assertSame(
            'http://localhost:54321',
            SupabaseStorageService::normalizeSupabaseUrl('http://localhost:54321/')
        );

        $this->assertNull(SupabaseStorageService::normalizeSupabaseUrl(null));
        $this->assertNull(SupabaseStorageService::normalizeSupabaseUrl(''));
        $this->assertNull(SupabaseStorageService::normalizeSupabaseUrl('   '));
        $this->assertNull(SupabaseStorageService::normalizeSupabaseUrl('not-a-url'));
        $this->assertNull(SupabaseStorageService::normalizeSupabaseUrl('javascript:alert(1)'));
        $this->assertNull(SupabaseStorageService::normalizeSupabaseUrl('ftp://example.supabase.co'));
    }

    public function test_malformed_url_does_not_generate_broken_supabase_public_url(): void
    {
        $service = new SupabaseStorageService('not-a-valid-url', 'sb_secret_unit_test_key');

        $diagnostics = $service->diagnoseConfiguration();
        $this->assertFalse($diagnostics['has_valid_url']);
        $this->assertFalse($service->isConfigured());

        $publicUrl = $service->getPublicUrl(
            SupabaseStorageService::BUCKET_MEDICINE_IMAGES,
            'sample.png'
        );

        $this->assertStringNotContainsString('not-a-valid-url', $publicUrl);
        $this->assertStringEndsWith('/storage/sample.png', $publicUrl);
    }

    public function test_service_key_is_resolved_even_when_higher_priority_entries_are_empty_or_publishable(): void
    {
        Config::set('services.supabase', [
            'url' => 'https://mock.supabase.co/',
            'secret_key' => '   ',
            'service_role_key' => 'sb_publishable_not_privileged',
            'service_key' => 'sb_secret_unit_fallback_key',
            'key' => null,
            'anon_key' => 'sb_publishable_anon',
            'publishable_key' => 'sb_publishable_pub',
        ]);

        $service = new SupabaseStorageService();
        $diagnostics = $service->diagnoseConfiguration();

        $this->assertTrue($service->isConfigured());
        $this->assertSame('sb_secret_unit_fallback_key', $service->getSelectedKey());
        $this->assertSame('service_key', $diagnostics['key_source']);
        $this->assertSame('secret_key', $diagnostics['key_type']);
        $this->assertSame(['service_role_key'], $diagnostics['rejected_key_sources']);

        $serializedDiagnostics = json_encode($diagnostics);
        $this->assertIsString($serializedDiagnostics);
        $this->assertStringNotContainsString('sb_secret_unit_fallback_key', $serializedDiagnostics);
        $this->assertStringNotContainsString('sb_publishable_not_privileged', $serializedDiagnostics);
    }

    public function test_upload_rejects_unsupported_mime_types_and_folder_traversal(): void
    {
        $service = new SupabaseStorageService('https://mock.supabase.co', 'sb_secret_unit_test_key');

        $phpFile = UploadedFile::fake()->create('exploit.php', 5, 'text/x-php');

        $this->expectException(\InvalidArgumentException::class);
        $service->upload($phpFile, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);
    }

    public function test_upload_rejects_folder_traversal(): void
    {
        $service = new SupabaseStorageService('https://mock.supabase.co', 'sb_secret_unit_test_key');
        $imageFile = UploadedFile::fake()->create('med.png', 5, 'image/png');

        $this->expectException(\InvalidArgumentException::class);
        $service->upload($imageFile, SupabaseStorageService::BUCKET_MEDICINE_IMAGES, '../other-bucket');
    }

    public function test_valid_upload_uses_normalized_url_and_succeeds(): void
    {
        Http::fake([
            'https://mock.supabase.co/storage/v1/object/medicine-images/*' => Http::response(['Key' => 'ok'], 200),
        ]);

        $service = new SupabaseStorageService('https://mock.supabase.co/', 'sb_secret_unit_test_key');
        $imageFile = UploadedFile::fake()->create('med.png', 10, 'image/png');

        $url = $service->upload($imageFile, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);

        $this->assertStringStartsWith('https://mock.supabase.co/storage/v1/object/public/medicine-images/', $url);
        $this->assertStringEndsWith('.png', $url);
    }

    public function test_image_jpg_mime_alias_is_accepted_and_normalized_to_jpg_extension(): void
    {
        Http::fake([
            'https://mock.supabase.co/storage/v1/object/medicine-images/*' => Http::response(['Key' => 'ok'], 200),
        ]);

        $service = new SupabaseStorageService('https://mock.supabase.co/', 'sb_secret_unit_test_key');
        $imageFile = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpg');

        $url = $service->upload($imageFile, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);

        $this->assertStringStartsWith('https://mock.supabase.co/storage/v1/object/public/medicine-images/', $url);
        $this->assertStringEndsWith('.jpg', $url);
    }
}
