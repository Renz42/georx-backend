<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SupabaseAuthService
{
    /**
     * Sync or create a user in Supabase Auth (auth.users) with auto email confirmation.
     */
    public static function createOrSyncUser(string $email, string $password, string $name = '', ?string $phone = ''): bool
    {
        try {
            $supabaseUrl = config('services.supabase.url') ?: env('SUPABASE_URL', 'https://aqzeibjljgvzvgpobbkx.supabase.co');
            $serviceKey = config('services.supabase.service_key') ?: env('SUPABASE_SERVICE_KEY');

            if (!$supabaseUrl || !$serviceKey) {
                Log::warning('SupabaseAuthService: Missing SUPABASE_URL or SUPABASE_SERVICE_KEY');
                return false;
            }

            $url = rtrim($supabaseUrl, '/') . '/auth/v1/admin/users';

            $payload = [
                'email' => $email,
                'password' => $password,
                'email_confirm' => true,
                'user_metadata' => [
                    'name' => $name,
                    'phone' => $phone ?? '',
                    'email_verified' => true
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer ' . $serviceKey
            ])->post($url, $payload);

            if ($response->successful()) {
                Log::info("SupabaseAuthService: User {$email} successfully created/synced in Supabase Auth");
                return true;
            } else {
                Log::info("SupabaseAuthService: Note on creating {$email}: " . $response->body());
                return false;
            }
        } catch (\Throwable $e) {
            Log::error("SupabaseAuthService Error: " . $e->getMessage());
            return false;
        }
    }
}
