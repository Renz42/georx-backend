<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Medicine;
use App\Services\SupabaseStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class MedicineImageUrlTest extends TestCase
{
    /**
     * Helper to generate mock JWTs with specified payload.
     */
    protected function makeJwt(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $b64Header = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $b64Payload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        return "{$b64Header}.{$b64Payload}.mock_sig_part";
    }

    /**
     * Test 1: Null image attribute returns null image_url.
     */
    public function test_image_url_returns_null_when_image_is_null(): void
    {
        $med = new Medicine(['image' => null]);
        $this->assertNull($med->image_url);
    }

    /**
     * Test 2: Empty or whitespace string returns null image_url.
     */
    public function test_image_url_returns_null_when_image_is_empty_or_whitespace(): void
    {
        $med1 = new Medicine(['image' => '']);
        $this->assertNull($med1->image_url);

        $med2 = new Medicine(['image' => '   ']);
        $this->assertNull($med2->image_url);
    }

    /**
     * Test 3: Trusted external HTTPS URL is returned untouched.
     */
    public function test_image_url_preserves_trusted_https_url(): void
    {
        $url = 'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/paracetamol-500mg.jpg';
        $med = new Medicine(['image' => $url]);
        $this->assertSame($url, $med->image_url);
    }

    /**
     * Test 4: Trusted HTTP URL is returned untouched.
     */
    public function test_image_url_preserves_http_url(): void
    {
        $url = 'http://example.com/images/biogesic.png';
        $med = new Medicine(['image' => $url]);
        $this->assertSame($url, $med->image_url);
    }

    /**
     * Test 5: Relative Supabase path with bucket prefix avoids double-prefixing.
     */
    public function test_image_url_resolves_bucket_path_without_duplicate_bucket(): void
    {
        $stored = 'medicine-images/da08fad9-422c-424e-a363-4162cb100595.jpg';
        $med = new Medicine(['image' => $stored]);
        
        $resolved = $med->image_url;
        $this->assertNotNull($resolved);
        $this->assertStringContainsString('medicine-images/da08fad9-422c-424e-a363-4162cb100595.jpg', $resolved);
        $this->assertStringNotContainsString('medicine-images/medicine-images/', $resolved);
    }

    /**
     * Test 6: Bare filename without bucket prefix resolves under default medicine-images bucket.
     */
    public function test_image_url_resolves_bare_filename(): void
    {
        $filename = '550e8400-e29b-41d4-a716-446655440000.png';
        $med = new Medicine(['image' => $filename]);

        $resolved = $med->image_url;
        $this->assertNotNull($resolved);
        $this->assertStringContainsString('medicine-images/' . $filename, $resolved);
    }

    /**
     * Test 7: Handles leading slash and optional storage/ prefix cleanly.
     */
    public function test_image_url_cleans_leading_slash_and_storage_prefix_variants(): void
    {
        $cases = [
            'medicine-images/sample-photo.png',
            'storage/medicine-images/sample-photo.png',
            '/storage/medicine-images/sample-photo.png',
            '/medicine-images/sample-photo.png',
        ];

        foreach ($cases as $path) {
            $med = new Medicine(['image' => $path]);
            $resolved = $med->image_url;
            $this->assertNotNull($resolved, "Failed for path: {$path}");
            $this->assertStringNotContainsString('storage/storage/', $resolved);
            $this->assertStringNotContainsString('medicine-images/medicine-images/', $resolved);
            $this->assertStringContainsString('medicine-images/sample-photo.png', $resolved);
        }
    }

    /**
     * Test 8: Rejects unsupported schemes and malformed URLs.
     */
    public function test_image_url_rejects_unsupported_schemes_and_malformed_urls(): void
    {
        $invalid = [
            'ftp://example.com/photo.jpg',
            'javascript:alert(1)',
            'data:image/png;base64,xxxx',
            'http:///invalid-url-domain',
        ];

        foreach ($invalid as $badUrl) {
            $med = new Medicine(['image' => $badUrl]);
            $this->assertNull($med->image_url, "Unsupported URL was not rejected: {$badUrl}");
        }
    }

    /**
     * Test 9: Legacy local path (medicines/...) resolves via local asset if file exists on disk.
     */
    public function test_image_url_resolves_legacy_local_path_when_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('medicines/sample.png', 'fake image bytes');

        $med = new Medicine(['image' => 'medicines/sample.png']);
        $resolved = $med->image_url;
        $this->assertNotNull($resolved);
        $this->assertStringContainsString('storage/medicines/sample.png', $resolved);
    }

    /**
     * Test 10: Legacy local path (medicines/...) returns null if file is missing (e.g. on Render container).
     */
    public function test_image_url_returns_null_when_legacy_local_file_is_missing(): void
    {
        Storage::fake('public'); // empty disk

        $med = new Medicine(['image' => 'medicines/nonexistent-file.png']);
        $this->assertNull($med->image_url, 'Missing legacy file must return null instead of broken 404 URL');
    }

    /**
     * Test 11: Serialization integrity: both raw image and image_url exist in array and JSON.
     */
    public function test_model_serialization_includes_image_url_and_raw_image(): void
    {
        $url = 'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/amoxicillin.jpg';
        $med = new Medicine([
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
            'image' => $url,
        ]);

        $arr = $med->toArray();
        $this->assertArrayHasKey('image', $arr);
        $this->assertSame($url, $arr['image']);
        $this->assertArrayHasKey('image_url', $arr);
        $this->assertSame($url, $arr['image_url']);

        $json = json_encode($med);
        $decoded = json_decode($json, true);
        $this->assertSame($url, $decoded['image']);
        $this->assertSame($url, $decoded['image_url']);
    }

    /**
     * Test 12: Successful Supabase upload returns public URL via mock.
     */
    public function test_supabase_upload_succeeds_and_returns_public_url(): void
    {
        Config::set('services.supabase.url', 'https://mock.supabase.co');
        Config::set('services.supabase.secret_key', 'sb_secret_test_mock_key_123');

        Http::fake([
            'https://mock.supabase.co/storage/v1/object/medicine-images/*' => Http::response(['Key' => 'medicine-images/test.png'], 200),
        ]);

        $file = UploadedFile::fake()->create('test.png', 10, 'image/png');
        $service = new SupabaseStorageService();
        $result = $service->upload($file, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);

        $this->assertNotNull($result);
        $this->assertStringStartsWith('https://mock.supabase.co/storage/v1/object/public/medicine-images/', $result);
    }

    /**
     * Test 13: Missing production credentials throw exception instead of silent local fallback.
     */
    public function test_missing_production_credentials_fail_safely(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('services.supabase.url', '');
        Config::set('services.supabase.secret_key', '');
        Config::set('services.supabase.service_role_key', '');
        Config::set('services.supabase.key', '');

        $file = UploadedFile::fake()->create('fail.png', 10, 'image/png');
        $service = new SupabaseStorageService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloud storage service is not configured with valid server credentials in production.');
        $service->upload($file, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);
    }

    /**
     * Test 14: Supabase HTTP error in production throws exception instead of silent local fallback.
     */
    public function test_supabase_http_error_in_production_fails_safely(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('services.supabase.url', 'https://mock.supabase.co');
        Config::set('services.supabase.secret_key', 'sb_secret_mock_500');

        Http::fake([
            'https://mock.supabase.co/storage/v1/object/medicine-images/*' => Http::response(['error' => 'Storage full'], 500),
        ]);

        $file = UploadedFile::fake()->create('error.png', 10, 'image/png');
        $service = new SupabaseStorageService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to upload image to cloud storage (HTTP 500).');
        $service->upload($file, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);
    }

    /**
     * Test 15: Configured secret key (sb_secret_...) is selected correctly.
     */
    public function test_configured_secret_key_is_selected_correctly(): void
    {
        Config::set('services.supabase.secret_key', 'sb_secret_production_key_001');
        Config::set('services.supabase.service_role_key', $this->makeJwt(['role' => 'service_role']));

        $resolved = SupabaseStorageService::resolveServerCredential();
        $this->assertSame('sb_secret_production_key_001', $resolved);

        $service = new SupabaseStorageService('https://mock.supabase.co');
        $this->assertSame('sb_secret_production_key_001', $service->getSelectedKey());
    }

    /**
     * Test 16: Valid legacy server-side service-role JWT remains supported.
     */
    public function test_valid_legacy_server_side_key_remains_supported(): void
    {
        $jwt = $this->makeJwt(['role' => 'service_role', 'sub' => '12345']);
        Config::set('services.supabase.secret_key', null);
        Config::set('services.supabase.service_role_key', $jwt);

        $resolved = SupabaseStorageService::resolveServerCredential();
        $this->assertSame($jwt, $resolved);

        $service = new SupabaseStorageService('https://mock.supabase.co');
        $this->assertSame($jwt, $service->getSelectedKey());
    }

    /**
     * Test 17: Anon or publishable-only configuration fails closed.
     */
    public function test_anon_and_publishable_only_configuration_fails_closed(): void
    {
        $anonJwt = $this->makeJwt(['role' => 'anon']);
        Config::set('services.supabase.secret_key', null);
        Config::set('services.supabase.service_role_key', null);
        Config::set('services.supabase.key', null);
        Config::set('services.supabase.anon_key', $anonJwt);
        Config::set('services.supabase.publishable_key', 'sb_publishable_client_123');

        $resolved = SupabaseStorageService::resolveServerCredential();
        $this->assertNull($resolved, 'Privileged operations must reject anon/publishable credentials');

        $this->assertFalse(SupabaseStorageService::isValidServerKey($anonJwt));
        $this->assertFalse(SupabaseStorageService::isValidServerKey('sb_publishable_client_123'));
        $this->assertFalse(SupabaseStorageService::isValidServerKey('sb_pub_test_999'));
    }

    /**
     * Test 18: Ambiguous generic key is not trusted without explicit validation.
     */
    public function test_ambiguous_generic_key_is_not_trusted_without_explicit_validation(): void
    {
        Config::set('services.supabase.secret_key', null);
        Config::set('services.supabase.service_role_key', null);

        // Case A: Unvalidated generic string
        Config::set('services.supabase.key', 'ambiguous_untrusted_token_12345');
        $this->assertNull(SupabaseStorageService::resolveServerCredential(), 'Ambiguous key must not be accepted without validation');

        // Case B: Generic key containing valid sb_secret
        Config::set('services.supabase.key', 'sb_secret_trusted_generic_001');
        $this->assertSame('sb_secret_trusted_generic_001', SupabaseStorageService::resolveServerCredential());

        // Case C: Generic key containing valid service_role JWT
        $jwt = $this->makeJwt(['role' => 'service_role']);
        Config::set('services.supabase.key', $jwt);
        $this->assertSame($jwt, SupabaseStorageService::resolveServerCredential());
    }

    /**
     * Test 19: Missing credentials do not trigger production local-disk fallback.
     */
    public function test_missing_credentials_do_not_trigger_production_local_disk_fallback(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Config::set('services.supabase.url', 'https://mock.supabase.co');
        Config::set('services.supabase.secret_key', null);
        Config::set('services.supabase.service_role_key', null);
        Config::set('services.supabase.key', null);

        Storage::fake('public');
        $file = UploadedFile::fake()->create('attempt.png', 10, 'image/png');
        $service = new SupabaseStorageService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cloud storage service is not configured with valid server credentials in production.');

        try {
            $service->upload($file, SupabaseStorageService::BUCKET_MEDICINE_IMAGES);
        } finally {
            // Confirm nothing was stored in local storage
            $this->assertEmpty(Storage::disk('public')->allFiles());
        }
    }

    /**
     * Test 20: Required request headers are correct for each supported key type.
     */
    public function test_required_request_headers_are_correct_for_each_supported_key_type(): void
    {
        $service = new SupabaseStorageService('https://mock.supabase.co');

        // Key Type 1: Modern sb_secret_...
        // MUST send apikey; MUST NOT send Authorization: Bearer
        $secretKey = 'sb_secret_test_headers_001';
        $headers1 = $service->buildAuthHeaders($secretKey, ['x-upsert' => 'true']);
        $this->assertArrayHasKey('apikey', $headers1);
        $this->assertSame($secretKey, $headers1['apikey']);
        $this->assertSame('true', $headers1['x-upsert']);
        $this->assertArrayNotHasKey('Authorization', $headers1, 'Modern sb_secret must not be treated as a JWT bearer token');

        // Key Type 2: Legacy service-role JWT
        // MUST send apikey AND Authorization: Bearer <jwt>
        $jwt = $this->makeJwt(['role' => 'service_role']);
        $headers2 = $service->buildAuthHeaders($jwt, ['x-upsert' => 'true']);
        $this->assertArrayHasKey('apikey', $headers2);
        $this->assertSame($jwt, $headers2['apikey']);
        $this->assertArrayHasKey('Authorization', $headers2);
        $this->assertSame("Bearer {$jwt}", $headers2['Authorization']);
    }
}
