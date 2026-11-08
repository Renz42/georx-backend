<?php

namespace App\Providers;

use App\Http\Controllers\AuthController;
use App\Services\EmailOtpService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Administrator Gate
        Gate::define('manage-system', function ($user) {
            return $user->role === 'administrator';
        });

        // Pharmacy Gate
        Gate::define('manage-pharmacy', function ($user) {
            return $user->role === 'pharmacy_owner' || $user->role === 'administrator';
        });

        // Customer Gate
        Gate::define('access-customer-features', function ($user) {
            return $user->role === 'customer';
        });

        // Multi-dimensional password recovery rate limiters (Admin & Pharmacy portals)
        foreach (['admin', 'pharmacy'] as $portal) {
            $sessionKey = $portal === 'admin'
                ? AuthController::ADMIN_RESET_SESSION_KEY
                : AuthController::PHARMACY_RESET_SESSION_KEY;

            RateLimiter::for("{$portal}_forgot", function (Request $request) use ($portal) {
                $ipKey = 'ip:' . $portal . ':' . $request->ip();
                $limits = [
                    Limit::perMinute(5)->by($ipKey),
                    Limit::perMinute(60)->by('global_forgot:' . $portal),
                ];

                $rawEmail = $request->input('email');
                if (is_string($rawEmail) && trim($rawEmail) !== '') {
                    $normalized = mb_strtolower(trim($rawEmail));
                    // Privacy-preserving HMAC key: never stores plaintext email in cache/rate-limiter keys
                    $idHash = EmailOtpService::hashOtp("rl_forgot:{$portal}:{$normalized}");
                    $limits[] = Limit::perMinute(3)->by('id:' . $portal . ':' . $idHash);
                }

                return $limits;
            });

            RateLimiter::for("{$portal}_reset", function (Request $request) use ($portal, $sessionKey) {
                $ipKey = 'ip:' . $portal . ':' . $request->ip();
                $limits = [
                    Limit::perMinute(5)->by($ipKey),
                ];

                if ($request->hasSession()) {
                    $context = $request->session()->get($sessionKey);
                    $tokenOrSession = is_array($context) && !empty($context['challenge_token'])
                        ? (string) $context['challenge_token']
                        : (string) $request->session()->getId();

                    if ($tokenOrSession !== '') {
                        $challengeHash = EmailOtpService::hashChallengeToken("rl_reset:{$portal}:{$tokenOrSession}");
                        $limits[] = Limit::perMinute(5)->by('challenge:' . $portal . ':' . $challengeHash);
                    }
                }

                return $limits;
            });

            RateLimiter::for("{$portal}_resend", function (Request $request) use ($portal, $sessionKey) {
                $ipKey = 'ip:' . $portal . ':' . $request->ip();
                $limits = [
                    Limit::perMinute(3)->by($ipKey),
                ];

                if ($request->hasSession()) {
                    $context = $request->session()->get($sessionKey);
                    $tokenOrSession = is_array($context) && !empty($context['challenge_token'])
                        ? (string) $context['challenge_token']
                        : (string) $request->session()->getId();

                    if ($tokenOrSession !== '') {
                        $challengeHash = EmailOtpService::hashChallengeToken("rl_resend:{$portal}:{$tokenOrSession}");
                        $limits[] = Limit::perMinute(3)->by('challenge:' . $portal . ':' . $challengeHash);
                    }
                }

                return $limits;
            });
        }

        // Multi-dimensional rate limiters for Patient Mobile API password recovery
        RateLimiter::for('patient_forgot', function (Request $request) {
            $ipKey = 'ip:patient_forgot:' . $request->ip();
            $limits = [
                Limit::perMinute(5)->by($ipKey),
                Limit::perMinute(60)->by('global_forgot:patient'),
            ];

            $rawEmail = $request->input('email');
            if (is_string($rawEmail) && trim($rawEmail) !== '') {
                $normalized = mb_strtolower(trim($rawEmail));
                // Privacy-preserving keyed HMAC identifier: never stores plaintext email in cache keys
                $idHash = EmailOtpService::hashOtp("rl_forgot:patient:{$normalized}");
                $limits[] = Limit::perMinute(3)->by('id:patient_forgot:' . $idHash);
            }

            return $limits;
        });

        RateLimiter::for('patient_resend', function (Request $request) {
            $ipKey = 'ip:patient_resend:' . $request->ip();
            $limits = [
                Limit::perMinute(3)->by($ipKey),
            ];

            $rawToken = $request->input('challenge_token');
            if (is_string($rawToken) && trim($rawToken) !== '') {
                $challengeHash = EmailOtpService::hashOtp('rl_resend:patient:' . trim($rawToken));
                $limits[] = Limit::perMinute(3)->by('challenge:patient_resend:' . $challengeHash);
            }

            return $limits;
        });

        RateLimiter::for('patient_reset', function (Request $request) {
            $ipKey = 'ip:patient_reset:' . $request->ip();
            $limits = [
                Limit::perMinute(5)->by($ipKey),
            ];

            $rawToken = $request->input('challenge_token');
            if (is_string($rawToken) && trim($rawToken) !== '') {
                $challengeHash = EmailOtpService::hashOtp('rl_reset:patient:' . trim($rawToken));
                $limits[] = Limit::perMinute(5)->by('challenge:patient_reset:' . $challengeHash);
            }

            return $limits;
        });

        // Brevo HTTP API Mail Transport
        \Illuminate\Support\Facades\Mail::extend('brevo', function (array $config = []) {
            $key = $config['key'] ?? config('services.brevo.key') ?? env('BREVO_API_KEY', '');
            return new \App\Mail\Transport\BrevoApiTransport($key);
        });
    }
}
