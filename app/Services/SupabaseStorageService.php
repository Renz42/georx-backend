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

    /** Allowed MIME types for managed image buckets */
    public const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** Allowed canonical image extensions */
    public const ALLOWED_IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
    ];

    /** Default HTTP timeouts (seconds) for Supabase Storage API requests */
    private const CONNECT_TIMEOUT_SECONDS = 10;
    private const REQUEST_TIMEOUT_SECONDS = 30;

    private ?string $supabaseUrl;
    private ?string $serviceKey;
    private string $keySource = 'none';

    /** @var array<int, string> */
    private array $rejectedKeySources = [];

    public function __construct(?string $url = null, ?string $key = null)
    {
        $rawUrl = $url ?? config('services.supabase.url');
        $this->supabaseUrl = self::normalizeSupabaseUrl(is_string($rawUrl) ? $rawUrl : null);

        if (is_string($rawUrl) && trim($rawUrl) !== '' && $this->supabaseUrl === null) {
            Log::warning('Supabase storage configuration contains a malformed SUPABASE_URL; cloud storage base URL disabled.');
        }

        if ($key !== null) {
            $trimmedKey = trim($key);
            if ($trimmedKey !== '' && self::isValidServerKey($trimmedKey)) {
                $this->serviceKey = $trimmedKey;
                $this->keySource = 'explicit';
            } else {
                $this->serviceKey = null;
                $this->keySource = 'none';
                if ($trimmedKey !== '') {
                    $this->rejectedKeySources = ['explicit'];
                }
            }
        } else {
            $resolved = self::resolveServerCredentialWithDiagnostics();
            $this->serviceKey = $resolved['key'];
            $this->keySource = $resolved['source'];
            $this->rejectedKeySources = $resolved['rejected_sources'];
        }
    }

    /**
     * Validate and normalize a Supabase base URL (must be http/https with a valid host).
     */
    public static function normalizeSupabaseUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = rtrim(trim($url), '/');
        if ($trimmed === '' || filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($trimmed);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        return $trimmed;
    }

    /**
     * Determine whether cloud storage URL and privileged server credential are both ready.
     */
    public function isConfigured(): bool
    {
        return !empty($this->supabaseUrl) && !empty($this->serviceKey);
    }

    /**
     * Return safe, non-secret configuration diagnostics for logging and verification.
     *
     * @return array<string, mixed>
     */
    public function diagnoseConfiguration(): array
    {
        return [
            'environment' => app()->environment(),
            'has_valid_url' => !empty($this->supabaseUrl),
            'url_scheme' => $this->supabaseUrl ? parse_url($this->supabaseUrl, PHP_URL_SCHEME) : null,
            'has_server_key' => !empty($this->serviceKey),
            'key_source' => $this->keySource,
            'key_type' => $this->serviceKey
                ? (self::isJwt($this->serviceKey) ? 'service_role_jwt' : 'secret_key')
                : 'none',
            'rejected_key_sources' => $this->rejectedKeySources,
            'is_configured' => $this->isConfigured(),
        ];
    }

    /**
     * Resolve privileged server-side credential.
     *
     * Supported order:
     * 1. config('services.supabase.secret_key')
     * 2. config('services.supabase.service_role_key')
     * 3. config('services.supabase.service_key')
     * 4. config('services.supabase.key') - ONLY if explicitly validated as a server-side secret
     *
     * Publishable and anon keys are NEVER selected.
     */
    public static function resolveServerCredential(): ?string
    {
        return self::resolveServerCredentialWithDiagnostics()['key'];
    }

    /**
     * Resolve privileged server credential together with safe key-source diagnostics.
     *
     * @return array{key: ?string, source: string, rejected_sources: array<int, string>}
     */
    private static function resolveServerCredentialWithDiagnostics(): array
    {
        $candidates = [
            'secret_key' => config('services.supabase.secret_key'),
            'service_role_key' => config('services.supabase.service_role_key'),
            'service_key' => config('services.supabase.service_key'),
            'key' => config('services.supabase.key'),
        ];

        $rejectedSources = [];

        foreach ($candidates as $source => $value) {
            if (!is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }

            if (self::isValidServerKey($trimmed)) {
                return [
                    'key' => $trimmed,
                    'source' => $source,
                    'rejected_sources' => $rejectedSources,
                ];
            }

            $rejectedSources[] = $source;
        }

        return [
            'key' => null,
            'source' => 'none',
            'rejected_sources' => $rejectedSources,
        ];
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

        $imageMeta = $this->resolveValidatedImageMeta($file);

        $cleanFolder = null;
        if ($folder !== null) {
            if (str_contains($folder, '..')) {
                throw new \InvalidArgumentException('Invalid storage folder path.');
            }
            $trimmedFolder = trim($folder, '/');
            $cleanFolder = $trimmedFolder !== '' ? $trimmedFolder : null;
        }

        // Generate unique canonical filename
        $filename = Str::uuid()->toString() . '.' . $imageMeta['extension'];
        $path = $cleanFolder !== null ? "{$cleanFolder}/{$filename}" : $filename;

        $isProduction = app()->environment('production');

        // Fail safely in production if credentials are missing or unprivileged
        if (empty($this->supabaseUrl) || empty($this->serviceKey)) {
            if ($isProduction) {
                Log::error(
                    "Supabase Storage configuration missing or unprivileged in production for bucket {$bucket}.",
                    $this->diagnoseConfiguration()
                );
                throw new \RuntimeException("Cloud storage service is not configured with valid server credentials in production.");
            }

            // Local fallback permitted ONLY in non-production environments when Supabase is unconfigured
            Log::info("Supabase credentials not configured in local environment; saving to local public disk.");
            return $file->storeAs("{$bucket}" . ($cleanFolder !== null ? "/{$cleanFolder}" : ""), $filename, 'public');
        }

        // Verify temp file readability before transmitting to Supabase Storage
        $realPath = $file->getRealPath();
        if (empty($realPath) || !is_file($realPath)) {
            throw new \RuntimeException('Uploaded image file could not be located on disk.');
        }

        $contents = @file_get_contents($realPath);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read uploaded image file contents.');
        }

        // Upload directly to Supabase Storage REST API
        try {
            $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$path}";

            $headers = $this->buildAuthHeaders($this->serviceKey, [
                'x-upsert' => 'true',
            ]);

            $response = Http::withHeaders($headers)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->withBody($contents, $imageMeta['mime'])
                ->post($endpoint);

            if ($response->successful()) {
                return $this->getPublicUrl($bucket, $path);
            }

            $status = $response->status();
            $bodySummary = Str::limit(trim((string) $response->body()), 300);
            Log::error("Supabase storage upload failed with HTTP {$status} for bucket {$bucket}: {$bodySummary}");

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
        return $file->storeAs("{$bucket}" . ($cleanFolder !== null ? "/{$cleanFolder}" : ""), $filename, 'public');
    }

    /**
     * Validate an uploaded image's integrity, MIME type, and canonical extension.
     *
     * @return array{mime: string, extension: string}
     */
    private function resolveValidatedImageMeta(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('The uploaded image file is invalid or incomplete.');
        }

        $realPath = $file->getRealPath();
        if (empty($realPath) || !is_file($realPath)) {
            throw new \RuntimeException('Uploaded image file could not be located on disk.');
        }

        $detectedMime = '';
        try {
            $detectedMime = strtolower((string) ($file->getMimeType() ?: ''));
        } catch (\Throwable) {
            // Fall back to byte-level finfo when OS/ACL checks prevent Symfony FileinfoMimeTypeGuesser from reading tmp paths.
            if (function_exists('finfo_open')) {
                $probeBytes = @file_get_contents($realPath, false, null, 0, 8192);
                if ($probeBytes !== false && $probeBytes !== '') {
                    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
                    if ($finfo !== false) {
                        $finfoMime = @finfo_buffer($finfo, $probeBytes);
                        @finfo_close($finfo);
                        if (is_string($finfoMime)) {
                            $detectedMime = strtolower($finfoMime);
                        }
                    }
                }
            }
        }

        $clientMime = strtolower((string) ($file->getClientMimeType() ?: ''));
        $clientExt = strtolower((string) $file->getClientOriginalExtension());

        if ($detectedMime !== '' && !in_array($detectedMime, ['application/octet-stream', 'application/x-empty'], true)) {
            if (!in_array($detectedMime, self::ALLOWED_IMAGE_MIMES, true)) {
                throw new \InvalidArgumentException("Unsupported image MIME type: {$detectedMime}");
            }
            $resolvedMime = $detectedMime;
        } else {
            if (!in_array($clientMime, self::ALLOWED_IMAGE_MIMES, true)) {
                throw new \InvalidArgumentException('Uploaded file must have a supported image MIME type.');
            }
            if ($clientExt !== '' && !in_array($clientExt, self::ALLOWED_IMAGE_EXTENSIONS, true)) {
                throw new \InvalidArgumentException("Unsupported image file extension: {$clientExt}");
            }
            $resolvedMime = $clientMime;
        }

        $canonicalExt = match ($resolvedMime) {
            'image/jpeg', 'image/pjpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };

        $extension = in_array($clientExt, self::ALLOWED_IMAGE_EXTENSIONS, true) ? $clientExt : $canonicalExt;

        return [
            'mime' => $resolvedMime,
            'extension' => $extension,
        ];
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

                $response = Http::withHeaders($headers)
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                    ->delete($endpoint, [
                        'prefixes' => [$canonicalKey],
                    ]);

                if ($response->successful()) {
                    return true;
                }

                $bodySummary = Str::limit(trim((string) $response->body()), 300);
                Log::error("Supabase Storage Delete failed with HTTP {$response->status()} for bucket {$bucket}: {$bodySummary}");
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
