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

    public function test_pharmacy_admin_catalog_renders_object_contain_retry_and_fallback_attributes(): void
    {
        Config::set('services.supabase.url', 'https://aqzeibjljgvzvgpobbkx.supabase.co');

        $clonidineUrl = 'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/4d7433a4-d24b-436d-b1d9-f9ad46d672e2.jpg';
        $this->medicineA->update([
            'brand_name' => 'Catapres',
            'generic_name' => 'Clonidine',
            'image' => $clonidineUrl,
        ]);

        $response = $this->actingAs($this->ownerA, 'pharmacy')
            ->get(route('portal.catalog'));

        $response->assertOk();
        $response->assertSee($clonidineUrl, false);
        $response->assertSee('data-original-src="' . $clonidineUrl . '"', false);
        $response->assertSee('data-fallback-src="/medicine-images/4d7433a4-d24b-436d-b1d9-f9ad46d672e2.jpg"', false);
        $response->assertSee('object-contain', false);
        $response->assertSee('handleCatalogImageError(this)', false);
        $response->assertSee("parsedUrl.searchParams.set('retry', '1')", false);
        $response->assertSee('data-image-placeholder', false);
    }

    /**
     * Build a controlled PSR-7 StreamInterface that generates bytes lazily on read(),
     * forbids full-buffer casts (__toString / getContents), and tracks read metrics and close state.
     */
    private function createControlledStream(string $prefix, int $totalLength, ?int $throwAfterBytes = null): \Psr\Http\Message\StreamInterface
    {
        return new class($prefix, $totalLength, $throwAfterBytes) implements \Psr\Http\Message\StreamInterface {
            private int $position = 0;
            public int $bytesRead = 0;
            public int $readCalls = 0;
            public int $maxRequestedLength = 0;
            public bool $closed = false;

            public function __construct(
                private string $prefix,
                private int $totalLength,
                private ?int $throwAfterBytes = null
            ) {
            }

            public function __toString(): string
            {
                throw new \LogicException('Unbounded __toString() buffering is forbidden on proxy stream.');
            }

            public function getContents(): string
            {
                throw new \LogicException('Unbounded getContents() buffering is forbidden on proxy stream.');
            }

            public function close(): void
            {
                $this->closed = true;
            }

            public function detach()
            {
                $this->closed = true;
                return null;
            }

            public function getSize(): ?int
            {
                return null;
            }

            public function tell(): int
            {
                return $this->position;
            }

            public function eof(): bool
            {
                return $this->closed || $this->position >= $this->totalLength;
            }

            public function isSeekable(): bool
            {
                return false;
            }

            public function seek(int $offset, int $whence = SEEK_SET): void
            {
                throw new \RuntimeException('Stream is not seekable.');
            }

            public function rewind(): void
            {
                throw new \RuntimeException('Stream is not seekable.');
            }

            public function isWritable(): bool
            {
                return false;
            }

            public function write(string $string): int
            {
                throw new \RuntimeException('Stream is not writable.');
            }

            public function isReadable(): bool
            {
                return !$this->closed;
            }

            public function read(int $length): string
            {
                if ($this->closed) {
                    throw new \RuntimeException('Cannot read from closed stream.');
                }

                $this->readCalls++;
                if ($length > $this->maxRequestedLength) {
                    $this->maxRequestedLength = $length;
                }

                if ($this->throwAfterBytes !== null && $this->bytesRead >= $this->throwAfterBytes) {
                    throw new \RuntimeException('Simulated mid-stream read exception.');
                }

                $remaining = $this->totalLength - $this->position;
                if ($remaining <= 0 || $length <= 0) {
                    return '';
                }

                $toRead = min($length, $remaining);
                $start = $this->position;
                $end = $start + $toRead;
                $prefixLen = strlen($this->prefix);

                $out = '';
                if ($start < $prefixLen) {
                    $prefixTake = min($toRead, $prefixLen - $start);
                    $out .= substr($this->prefix, $start, $prefixTake);
                }

                $padNeeded = $toRead - strlen($out);
                if ($padNeeded > 0) {
                    $out .= str_repeat("\x00", $padNeeded);
                }

                $this->position = $end;
                $this->bytesRead += strlen($out);

                return $out;
            }

            public function getMetadata(?string $key = null)
            {
                $meta = ['timed_out' => false, 'seekable' => false, 'eof' => $this->eof()];
                return $key === null ? $meta : ($meta[$key] ?? null);
            }
        };
    }

    public function test_same_origin_medicine_image_proxy_serves_valid_supabase_image_without_exposing_credentials(): void
    {
        Config::set('services.supabase', [
            'url' => 'https://aqzeibjljgvzvgpobbkx.supabase.co',
            'secret_key' => 'sb_secret_do_not_send_on_public_proxy',
            'service_role_key' => null,
            'service_key' => null,
            'key' => null,
            'anon_key' => null,
            'publishable_key' => null,
        ]);

        $jpegBytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        $stream = $this->createControlledStream($jpegBytes, strlen($jpegBytes));
        $capturedOptions = [];

        Http::fake(function ($request, $options) use ($stream, $jpegBytes, &$capturedOptions) {
            $capturedOptions = $options;
            return \GuzzleHttp\Promise\Create::promiseFor(new \GuzzleHttp\Psr7\Response(200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => (string) strlen($jpegBytes),
            ], $stream));
        });

        $response = $this->get('/medicine-images/4d7433a4-d24b-436d-b1d9-f9ad46d672e2.jpg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($jpegBytes, $response->getContent());
        $this->assertTrue($stream->closed, 'Upstream stream must be closed after successful response.');
        $this->assertSame(strlen($jpegBytes), $stream->bytesRead);
        $this->assertLessThanOrEqual(8192, $stream->maxRequestedLength);
        $this->assertTrue((bool) ($capturedOptions['stream'] ?? false));
        $this->assertFalse((bool) ($capturedOptions['allow_redirects'] ?? true));
        $this->assertSame(15, $capturedOptions['read_timeout'] ?? null);

        Http::assertSent(function ($request) {
            return !$request->hasHeader('apikey') && !$request->hasHeader('Authorization');
        });
    }

    public function test_same_origin_medicine_image_proxy_enforces_streaming_size_limits_and_closes_streams(): void
    {
        Config::set('services.supabase.url', 'https://aqzeibjljgvzvgpobbkx.supabase.co');

        $jpegPrefix = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        $maxBytes = 5 * 1024 * 1024; // 5,242,880 bytes

        $exactLimitStream = $this->createControlledStream($jpegPrefix, $maxBytes);
        $overByOneStream = $this->createControlledStream($jpegPrefix, $maxBytes + 1);
        $chunkedTenMbStream = $this->createControlledStream($jpegPrefix, 10 * 1024 * 1024);
        $misleadingSmallLengthStream = $this->createControlledStream($jpegPrefix, 6 * 1024 * 1024);
        $oversizedHeaderStream = $this->createControlledStream($jpegPrefix, 1024);
        $emptyStream = $this->createControlledStream('', 0);
        $failedUpstreamStream = $this->createControlledStream('{"error":"not_found"}', 21);
        $exceptionMidReadStream = $this->createControlledStream($jpegPrefix, 65536, 8192);

        Http::fake([
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/exact-5mb.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg', 'Content-Length' => (string) $maxBytes], $exactLimitStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/over-by-1.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg'], $overByOneStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/chunked-10mb.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg'], $chunkedTenMbStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/misleading-length.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg', 'Content-Length' => '100'], $misleadingSmallLengthStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/oversized-header.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg', 'Content-Length' => (string) ($maxBytes + 1)], $oversizedHeaderStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/empty.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg'], $emptyStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/failed-upstream.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(404, ['Content-Type' => 'application/json'], $failedUpstreamStream)
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/mid-stream-error.jpg' => fn () => \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, ['Content-Type' => 'image/jpeg'], $exceptionMidReadStream)
            ),
        ]);

        // 1. Exact 5 MB limit succeeds and closes stream
        $exactRes = $this->get('/medicine-images/exact-5mb.jpg');
        $exactRes->assertOk();
        $exactRes->assertHeader('Content-Length', (string) $maxBytes);
        $this->assertSame($maxBytes, strlen($exactRes->getContent()));
        $this->assertSame($maxBytes, $exactLimitStream->bytesRead);
        $this->assertLessThanOrEqual(8192, $exactLimitStream->maxRequestedLength);
        $this->assertTrue($exactLimitStream->closed);

        // 2. 5 MB + 1 byte is rejected after reading at most 5 MB + 1 byte and closes stream
        $this->get('/medicine-images/over-by-1.jpg')->assertNotFound();
        $this->assertSame($maxBytes + 1, $overByOneStream->bytesRead);
        $this->assertLessThanOrEqual(8192, $overByOneStream->maxRequestedLength);
        $this->assertTrue($overByOneStream->closed);

        // 3. 10 MB response without Content-Length stops immediately at 5 MB + 1 byte (never buffers 10 MB)
        $this->get('/medicine-images/chunked-10mb.jpg')->assertNotFound();
        $this->assertSame($maxBytes + 1, $chunkedTenMbStream->bytesRead);
        $this->assertLessThanOrEqual(8192, $chunkedTenMbStream->maxRequestedLength);
        $this->assertTrue($chunkedTenMbStream->closed);

        // 4. Misleading small Content-Length (100 bytes) on a 6 MB stream stops at 5 MB + 1 byte and is rejected
        $this->get('/medicine-images/misleading-length.jpg')->assertNotFound();
        $this->assertSame($maxBytes + 1, $misleadingSmallLengthStream->bytesRead);
        $this->assertTrue($misleadingSmallLengthStream->closed);

        // 5. Oversized Content-Length header is rejected before reading 1 byte from the stream
        $this->get('/medicine-images/oversized-header.jpg')->assertNotFound();
        $this->assertSame(0, $oversizedHeaderStream->bytesRead);
        $this->assertSame(0, $oversizedHeaderStream->readCalls);
        $this->assertTrue($oversizedHeaderStream->closed);

        // 6. Empty body is rejected and stream is closed
        $this->get('/medicine-images/empty.jpg')->assertNotFound();
        $this->assertSame(0, $emptyStream->bytesRead);
        $this->assertTrue($emptyStream->closed);

        // 7. Failed upstream response is rejected before reading the body and stream is closed
        $this->get('/medicine-images/failed-upstream.jpg')->assertNotFound();
        $this->assertSame(0, $failedUpstreamStream->bytesRead);
        $this->assertSame(0, $failedUpstreamStream->readCalls);
        $this->assertTrue($failedUpstreamStream->closed);

        // 8. Mid-stream exception is handled safely (returns 404) and closes the stream
        $this->get('/medicine-images/mid-stream-error.jpg')->assertNotFound();
        $this->assertSame(8192, $exceptionMidReadStream->bytesRead);
        $this->assertTrue($exceptionMidReadStream->closed);
    }

    public function test_same_origin_medicine_image_proxy_validates_binary_magic_bytes_and_rejects_spoofed_or_mismatched_mimes(): void
    {
        Config::set('services.supabase.url', 'https://aqzeibjljgvzvgpobbkx.supabase.co');

        $jpegBytes = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        $pngBytes = "\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1F\x15\xC4\x89\x00\x00\x00\nIDATx\x9Cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xB4\x00\x00\x00\x00IEND\xAEB`\x82";
        $webpBytes = "RIFF\x1A\x00\x00\x00WEBPVP8 \x0E\x00\x00\x000\x01\x00\x9D\x01*\x01\x00\x01\x00\x02\x004%\xA4\x00\x03p\x00\xFE\xFB\x94\x00\x00";
        $gifBytes = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF!\xF9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
        $htmlBytes = "<!DOCTYPE html><html><body><script>alert(1)</script></body></html>";
        $svgBytes = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>';
        $unknownBytes = "\x00\x01\x02\x03\x04\x05\x06\x07";

        Http::fake([
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/valid-jpg-alias.jpg' => Http::response(
                $jpegBytes,
                200,
                ['Content-Type' => 'image/jpg']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/valid.png' => Http::response(
                $pngBytes,
                200,
                ['Content-Type' => 'image/png']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/valid.webp' => Http::response(
                $webpBytes,
                200,
                ['Content-Type' => 'image/webp']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/valid.gif' => Http::response(
                $gifBytes,
                200,
                ['Content-Type' => 'image/gif']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/html-spoof.jpg' => Http::response(
                $htmlBytes,
                200,
                ['Content-Type' => 'image/jpeg']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/svg-spoof.jpg' => Http::response(
                $svgBytes,
                200,
                ['Content-Type' => 'image/jpeg']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/unknown-bin.jpg' => Http::response(
                $unknownBytes,
                200,
                ['Content-Type' => 'image/jpeg']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/mismatch-png-as-jpg.jpg' => Http::response(
                $pngBytes,
                200,
                ['Content-Type' => 'image/jpeg']
            ),
            'https://aqzeibjljgvzvgpobbkx.supabase.co/storage/v1/object/public/medicine-images/fake-json.jpg' => Http::response(
                '{"not":"an image"}',
                200,
                ['Content-Type' => 'application/json']
            ),
        ]);

        // Supported image formats pass and return canonical Content-Type
        $this->get('/medicine-images/valid-jpg-alias.jpg')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->get('/medicine-images/valid.png')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get('/medicine-images/valid.webp')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
        $this->get('/medicine-images/valid.gif')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        // Spoofed Content-Type headers, non-images, mismatches, and invalid filenames are rejected
        $this->get('/medicine-images/html-spoof.jpg')->assertNotFound();
        $this->get('/medicine-images/svg-spoof.jpg')->assertNotFound();
        $this->get('/medicine-images/unknown-bin.jpg')->assertNotFound();
        $this->get('/medicine-images/mismatch-png-as-jpg.jpg')->assertNotFound();
        $this->get('/medicine-images/fake-json.jpg')->assertNotFound();
        $this->get('/medicine-images/exploit.php')->assertNotFound();
        $this->get('/medicine-images/vector.svg')->assertNotFound();
        $this->get('/medicine-images/..secret.jpg')->assertNotFound();
        $this->post('/medicine-images/valid.png')->assertStatus(405);
    }
}
