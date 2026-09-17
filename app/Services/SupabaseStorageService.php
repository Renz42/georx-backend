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
    //  GEORX — Supabase Storage Service
    //
    //  Manages file uploads, public URLs, and deletions across the 4 buckets:
    //    1. pharmacy-logos
    //    2. pharmacy-covers
    //    3. medicine-images
    //    4. profile-images
    //
    //  Supports both direct Supabase Storage REST API and local fallback.
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

    public function __construct()
    {
        $this->supabaseUrl = rtrim(config('services.supabase.url', env('SUPABASE_URL', '')), '/');
        $this->serviceKey = config('services.supabase.key', env('SUPABASE_SERVICE_ROLE_KEY', env('SUPABASE_ANON_KEY', '')));
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
        if (!in_array($bucket, self::BUCKETS)) {
            throw new \InvalidArgumentException("Invalid Supabase bucket: {$bucket}");
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = Str::uuid()->toString() . '.' . strtolower($extension);
        $path = $folder ? trim($folder, '/') . '/' . $filename : $filename;

        // If Supabase API credentials exist, upload directly to Supabase Storage
        if (!empty($this->supabaseUrl) && !empty($this->serviceKey)) {
            try {
                $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}/{$path}";

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->serviceKey}",
                    'apikey'        => $this->serviceKey,
                    'x-upsert'      => 'true',
                ])
                ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType())
                ->post($endpoint);

                if ($response->successful()) {
                    return $this->getPublicUrl($bucket, $path);
                }

                Log::warning("Supabase storage upload returned HTTP {$response->status()}: " . $response->body() . ". Falling back to local.");
            } catch (\Throwable $e) {
                Log::error("Supabase Storage Upload Exception: " . $e->getMessage());
            }
        }

        // Local fallback: store in storage/app/public/{bucket}/{path}
        $localPath = $file->storeAs("{$bucket}" . ($folder ? "/{$folder}" : ""), $filename, 'public');
        return $localPath;
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
     * Delete a file from a Supabase bucket.
     *
     * @param string $bucket Target bucket name
     * @param string|null $path File path or relative filename
     * @return bool True if deleted or skipped
     */
    public function delete(string $bucket, ?string $path): bool
    {
        if (empty($path)) {
            return true;
        }

        // If path is a full Supabase URL, extract the relative key
        if (Str::contains($path, "/storage/v1/object/public/{$bucket}/")) {
            $path = Str::after($path, "/storage/v1/object/public/{$bucket}/");
        }

        if (!empty($this->supabaseUrl) && !empty($this->serviceKey)) {
            try {
                $endpoint = "{$this->supabaseUrl}/storage/v1/object/{$bucket}";

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$this->serviceKey}",
                    'apikey'        => $this->serviceKey,
                ])->delete($endpoint, [
                    'prefixes' => [ltrim($path, '/')]
                ]);

                if ($response->successful()) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::error("Supabase Storage Delete Exception: " . $e->getMessage());
            }
        }

        // Local fallback delete
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->delete($path);
        }

        return true;
    }
}
