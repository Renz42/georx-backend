<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupabaseStorageService
{
    // =========================================================================
    //  Supabase Storage Service
    //
    //  Buckets:
    //    1. pharmacy-logos
    //    2. pharmacy-covers
    //    3. medicine-images
    //    4. profile-images
    //
    //  Supports direct Supabase Storage REST API with explicit server key validation.
    // =========================================================================

    public const BUCKET_PHARMACY_LOGOS = 'pharmacy-logos';
    public const BUCKET_PHARMACY_COVERS = 'pharmacy-covers';
    public const BUCKET_MEDICINE_IMAGES = 'medicine-images';
    public const BUCKET_PROFILE_IMAGES  = 'profile-images';

    /** Valid bucket names */
    public const BUCKETS = [
        self::BUCKET_PHARMACY_LOGOS,
        self::BUCKET_PHARMACY_COVERS,
        self::BUCKET_MEDICINE_IMAGES,
        self::BUCKET_PROFILE_IMAGES,
    ];

    private ?string $supabaseUrl;
    private ?string $serviceKey;

    public function __construct(?string $url = null, ?string $key = null)
    {
        $this->supabaseUrl = rtrim((string) ($url ?? config('services.supabase.url', '')), '/');

        if ($key !== null) {
            $this->serviceKey = self::isValidServerKey($key) ? trim($key) : null;
        } else {
            $this->serviceKey = self::resolveServerCredential();
        }
    }

    /**
     * Resolve privileged server-side credential.
     *
     * Supported order:
     * 1. config('services.supabase.secret_key')
     * 2. config('services.supabase.service_role_key')
     * 3. config('services.supabase.key') - ONLY if explicitly validated as a server-side secret
     *
     * Publishable and anon keys are NEVER selected.
     */
    public static function resolveServerCredential(): ?string
    {
        // 1. SUPABASE_SECRET_KEY
        $secretKey = config('services.supabase.secret_key');
        if (!empty($secretKey) && self::isValidServerKey($secretKey)) {
            return trim($secretKey);
        }

        // 2. Legacy server-side service-role keys
        $serviceRoleKey = config('services.supabase.service_role_key');
        if (!empty($serviceRoleKey) && self::isValidServerKey($serviceRoleKey)) {
            return trim($serviceRoleKey);
        }

        // 3. Ambiguous SUPABASE_KEY - only trusted if explicitly validated
        $genericKey = config('services.supabase.key');
        if (!empty($genericKey) && self::isValidServerKey($genericKey)) {
            return trim($genericKey);
        }

        // Fails closed if no acceptable server-side credential is found
        return null;
    }

    /**
     * Validate whether a given key is a privileged server-side credential.
     * Rejects publishable, anon, empty, or unvalidated keys.
     */
    public static function isValidServerKey(?string $key): bool
    {
        if (empty($key)) {
            return false;
        }

        $key = trim($key);

        // Explicitly reject publishable or anon keys
        if (str_starts_with($key, 'sb_publishable_') || str_starts_with($key, 'sb_pub_') || str_starts_with($key, 'sb_anon_')) {
            return false;
        }

        // Modern Supabase secret key (sb_secret_...)
        if (str_starts_with($key, 'sb_secret_')) {
            return true;
        }

        // JWT token validation: must have role == 'service_role'
        $parts = explode('.', $key);
        if (count($parts) === 3 && str_starts_with($key, 'eyJ')) {
            $payloadJson = base64_decode(strtr($parts[1], '-_', '+/'), true);
            if ($payloadJson !== false) {
                $payload = json_decode($payloadJson, true);
                if (is_array($payload) && isset($payload['role'])) {
                    return $payload['role'] === 'service_role';
                }
            }
        }

        // Reject arbitrary untrusted or ambiguous strings
        return false;
    }

    /**
     * Check if a key is a JWT token.
     */
    public static function isJwt(?string $key): bool
    {
        if (empty($key)) {
            return false;
        }
        $parts = explode('.', trim($key));
        return count($parts) === 3 && str_starts_with(trim($key), 'eyJ');
    }

    /**
     * Build appropriate authentication headers according to Supabase Storage API specs:
     * - Modern secret keys (sb_secret_...): send 'apikey' header, DO NOT send 'Authorization: Bearer'
     * - Legacy service-role JWTs: send 'apikey' and 'Authorization: Bearer <jwt>'
     */
    public function buildAuthHeaders(string $key, array $extraHeaders = []): array
    {
        $headers = [
            'apikey' => $key,
        ];

        if (self::isJwt($key)) {
            $headers['Authorization'] = "Bearer {$key}";
        }

        return array_merge($headers, $extraHeaders);
    }

    /**
     * Get the selected privileged service key (or null if none/invalid).
     */
    public function getSelectedKey(): ?string
    {
        return $this->serviceKey;
    }

    /**
     * Upload a file to a specific Supabase bucket.
     *
     * @param UploadedFile $file The file from $request->file()
     * @param string $bucket Name of the target bucket
     * @param string|null $folder Optional subfolder inside the bucket
     * @return string Public URL or relative path stored in DB
     */
    public function upload(UploadedFile $file, string $bucket, ?string $folder = null): string
    {
        // Sanitize bucket
        if (!in_array($bucket, self::BUCKETS, true)) {
            throw new \InvalidArgumentException("Invalid Supabase bucket: {$bucket}");
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = Str::uuid()->toString() . '.' . strtolower($extension);
        $path = $folder ? trim($folder, '/') . '/' . $filename : $filename;

        $isProduction = app()->environment('production');

        // Fail safely in production if credentials are missing or unprivileged
        if (empty($this->supabaseUrl) || empty($this->serviceKey)) {
            if ($isProduction) {
                Log::error("Supabase Storage configuration missing or unprivileged in production for bucket {$bucket}.");
                throw new \RuntimeException("Cloud storage service is not configured with valid server credentials in production.");
            }

            // Local fallback permitted ONLY in non-production environments when Supabase is unconfigured
            Log::info("Supabase credentials not configured in local environment; saving to local public disk.");
            return $file->storeAs("{$bucket}" . ($folder ? "/{$folder}" : ""), $filename, 'public');
        }

        // Upload directly to Supabase Storage REST API
        try {
            $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$path}";

            $headers = $this->buildAuthHeaders($this->serviceKey, [
                'x-upsert' => 'true',
            ]);

            $response = Http::withHeaders($headers)
                ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
                ->post($endpoint);

            if ($response->successful()) {
                return $this->getPublicUrl($bucket, $path);
            }

            $status = $response->status();
            $body = $response->body();
            Log::error("Supabase storage upload failed with HTTP {$status} for bucket {$bucket}: {$body}");

            if ($isProduction) {
                throw new \RuntimeException("Failed to upload image to cloud storage (HTTP {$status}).");
            }
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }
            Log::error("Supabase Storage Upload Exception for bucket {$bucket}: " . $e->getMessage());

            if ($isProduction) {
                throw new \RuntimeException("Cloud storage upload connection error: " . $e->getMessage(), 0, $e);
            }
        }

        // Non-production fallback to local disk
        Log::warning("Supabase storage upload failed in non-production; falling back to local public disk.");
        return $file->storeAs("{$bucket}" . ($folder ? "/{$folder}" : ""), $filename, 'public');
    }

    /**
     * Get the full public URL for a file in a Supabase bucket.
     *
     * @param string $bucket Target bucket name
     * @param string $path File path or relative filename
     * @return string Full accessible HTTPS URL
     */
    public function getPublicUrl(string $bucket, string $path): string
    {
        // If path is already a full URL, return it directly
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (!empty($this->supabaseUrl)) {
            $cleanPath = ltrim($path, '/');
            return "{$this->supabaseUrl}/storage/v1/object/public/{$bucket}/{$cleanPath}";
        }

        // Fallback to local storage URL
        return asset('storage/' . ltrim($path, '/'));
    }

    /**
     * Resolve the canonical object key within a given Supabase Storage bucket.
     * Returns null if the reference does not belong to the specified bucket
     * (e.g. external HTTPS URLs, local disk paths, unsupported schemes, or different buckets).
     *
     * Supported formats:
     * - "medicine-images/photo.jpg" -> "photo.jpg"
     * - "storage/medicine-images/photo.jpg" -> "photo.jpg"
     * - "/storage/medicine-images/photo.jpg" -> "photo.jpg"
     * - "/medicine-images/photo.jpg" -> "photo.jpg"
     * - "https://<supabase-host>/storage/v1/object/public/medicine-images/photo.jpg" -> "photo.jpg"
     * - "photo.jpg" -> "photo.jpg"
     *
     * Rejected (returns null):
     * - "https://images.unsplash.com/photo.jpg" (external host)
     * - "medicines/photo.jpg" (legacy local storage path)
     * - "javascript:alert(1)" (unsupported scheme)
     * - "data:image/png;base64,..." (data URI)
     * - "pharmacy-logos/photo.jpg" (different bucket)
     */
    public static function extractObjectKey(string $bucket, ?string $reference, ?string $supabaseUrl = null): ?string
    {
        if (empty($reference) || trim($reference) === '') {
            return null;
        }

        $reference = trim($reference);

        // 1. Explicit URI schemes (URLs, data, javascript, ftp, etc.)
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $reference)) {
            if (!str_starts_with($reference, 'http://') && !str_starts_with($reference, 'https://')) {
                return null;
            }

            // Must contain the expected Supabase storage public object path segment for this bucket
            $bucketSegment = "/storage/v1/object/public/{$bucket}/";
            if (!str_contains($reference, $bucketSegment)) {
                return null; // External URL or different bucket
            }

            // Validate host matches configured Supabase URL or official Supabase domain
            $parsedHost = parse_url($reference, PHP_URL_HOST);
            if (!empty($supabaseUrl)) {
                $expectedHost = parse_url($supabaseUrl, PHP_URL_HOST);
                if ($parsedHost !== $expectedHost && !str_ends_with((string)$parsedHost, '.supabase.co')) {
                    return null;
                }
            } elseif (!str_ends_with((string)$parsedHost, '.supabase.co')) {
                return null;
            }

            $keyPart = Str::after($reference, $bucketSegment);
            $cleanKey = ltrim($keyPart, '/');
            return !empty($cleanKey) ? $cleanKey : null;
        }

        // 2. Relative string: strip leading slash and optional storage/ prefix
        $clean = preg_replace('/^\/?(storage\/)?/', '', $reference);

        // Reject legacy local storage path (medicines/...)
        if (str_starts_with($clean, 'medicines/')) {
            return null;
        }

        // Check if prefixed with another bucket name
        foreach (self::BUCKETS as $otherBucket) {
            if ($otherBucket !== $bucket && str_starts_with($clean, $otherBucket . '/')) {
                return null;
            }
        }

        // Strip bucket name prefix if present
        if (str_starts_with($clean, $bucket . '/')) {
            $key = substr($clean, strlen($bucket) + 1);
            return !empty($key) ? $key : null;
        }

        // Bare filename or subpath within bucket
        if (!empty($clean) && !str_contains($clean, ':')) {
            return $clean;
        }

        return null;
    }

    /**
     * Delete a file from a Supabase bucket.
     *
     * @param string $bucket Target bucket name
     * @param string|null $path File path or relative filename
     * @return bool True if deleted or skipped, false if deletion failed
     */
    public function delete(string $bucket, ?string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        $canonicalKey = self::extractObjectKey($bucket, $path, $this->supabaseUrl);

        // Never interpret an arbitrary external URL or unsupported path as permission to delete a Storage object
        if ($canonicalKey === null) {
            // Check if it is explicitly a local disk file
            $cleanPath = preg_replace('/^\/?(storage\/)?/', '', trim($path));
            if (Storage::disk('public')->exists($cleanPath)) {
                return Storage::disk('public')->delete($cleanPath);
            }

            Log::warning("Storage deletion skipped: Path does not resolve to a managed object in bucket '{$bucket}' [{$path}].");
            return true;
        }

        if (!empty($this->supabaseUrl) && !empty($this->serviceKey)) {
            try {
                $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}";

                $headers = $this->buildAuthHeaders($this->serviceKey);

                $response = Http::withHeaders($headers)->delete($endpoint, [
                    'prefixes' => [$canonicalKey]
                ]);

                if ($response->successful()) {
                    return true;
                }

                Log::error("Supabase Storage Delete failed with HTTP {$response->status()} for bucket {$bucket}: " . $response->body());
                return false;
            } catch (\Throwable $e) {
                Log::error("Supabase Storage Delete Exception: " . $e->getMessage());
                return false;
            }
        }

        // Local fallback delete
        $cleanPath = "{$bucket}/{$canonicalKey}";
        if (Storage::disk('public')->exists($cleanPath)) {
            return Storage::disk('public')->delete($cleanPath);
        }

        return true;
    }
}
