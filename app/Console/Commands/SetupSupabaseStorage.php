<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Services\SupabaseStorageService;

class SetupSupabaseStorage extends Command
{
    // =========================================================================
    //  GEORX — Supabase Storage Bucket Initializer
    //
    //  Creates the 4 required public storage buckets in Supabase:
    //    • pharmacy-logos
    //    • pharmacy-covers
    //    • medicine-images
    //    • profile-images
    //
    //  Usage:
    //    php artisan supabase:setup-storage
    // =========================================================================

    protected $signature = 'supabase:setup-storage';
    protected $description = 'Create required storage buckets in Supabase and output security policies SQL';

    public function handle()
    {
        $this->info("=================================================");
        $this->info("   GEORX — Supabase Storage Setup & Policy Tool   ");
        $this->info("=================================================\n");

        $url = rtrim(config('services.supabase.url', env('SUPABASE_URL', '')), '/');
        $key = config('services.supabase.key', env('SUPABASE_SERVICE_ROLE_KEY', env('SUPABASE_ANON_KEY', '')));

        if (empty($url) || empty($key)) {
            $this->warn("⚠️  SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY is missing in .env.");
            $this->warn("   Buckets can also be created manually via Supabase Dashboard -> Storage.");
        } else {
            $this->info("Attempting to create 4 storage buckets via Supabase REST API...\n");

            foreach (SupabaseStorageService::BUCKETS as $bucket) {
                $endpoint = "{$url}/storage/v1/bucket";

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$key}",
                    'apikey'        => $key,
                    'Content-Type'  => 'application/json',
                ])->post($endpoint, [
                    'id'          => $bucket,
                    'name'        => $bucket,
                    'public'      => true,
                    'file_size_limit' => 10485760, // 10MB limit
                    'allowed_mime_types' => ['image/png', 'image/jpeg', 'image/webp', 'image/jpg', 'image/gif'],
                ]);

                if ($response->successful()) {
                    $this->line("  ✓ Bucket [{$bucket}] created successfully.");
                } elseif ($response->status() === 409 || str_contains($response->body(), 'already exists')) {
                    $this->line("  ℹ Bucket [{$bucket}] already exists.");
                } else {
                    $this->error("  ✗ Failed to create [{$bucket}]: HTTP " . $response->status() . " — " . $response->body());
                }
            }
        }

        $this->newLine();
        $this->info("=================================================");
        $this->info("   SQL SECURITY POLICIES FOR SUPABASE STORAGE   ");
        $this->info("   Run in: Supabase Dashboard -> SQL Editor     ");
        $this->info("=================================================\n");

        $sql = $this->getSecurityPoliciesSql();
        $this->line($sql);

        return Command::SUCCESS;
    }

    private function getSecurityPoliciesSql(): string
    {
        return <<<SQL
-- ============================================================
-- GEORX — Supabase Storage RLS Security Policies
-- ============================================================

-- 1. Ensure all 4 buckets exist and are marked public
INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES 
  ('pharmacy-logos', 'pharmacy-logos', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/jpg']),
  ('pharmacy-covers', 'pharmacy-covers', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/jpg']),
  ('medicine-images', 'medicine-images', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/jpg']),
  ('profile-images', 'profile-images', true, 10485760, ARRAY['image/png', 'image/jpeg', 'image/webp', 'image/jpg'])
ON CONFLICT (id) DO UPDATE SET public = true;

-- 2. Enable Row Level Security on storage.objects
ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;

-- 3. Policy: Public READ access for all GEORX image buckets
CREATE POLICY "Public Read Access for GEORX Images" 
ON storage.objects FOR SELECT 
USING (bucket_id IN ('pharmacy-logos', 'pharmacy-covers', 'medicine-images', 'profile-images'));

-- 4. Policy: Authenticated users / Service Role INSERT access
CREATE POLICY "Authenticated Upload Access for GEORX Images" 
ON storage.objects FOR INSERT 
WITH CHECK (bucket_id IN ('pharmacy-logos', 'pharmacy-covers', 'medicine-images', 'profile-images'));

-- 5. Policy: Authenticated users / Service Role UPDATE access
CREATE POLICY "Authenticated Update Access for GEORX Images" 
ON storage.objects FOR UPDATE 
USING (bucket_id IN ('pharmacy-logos', 'pharmacy-covers', 'medicine-images', 'profile-images'));

-- 6. Policy: Authenticated users / Service Role DELETE access
CREATE POLICY "Authenticated Delete Access for GEORX Images" 
ON storage.objects FOR DELETE 
USING (bucket_id IN ('pharmacy-logos', 'pharmacy-covers', 'medicine-images', 'profile-images'));
SQL;
    }
}
