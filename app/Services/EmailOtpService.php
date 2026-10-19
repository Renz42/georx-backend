<?php

namespace App\Services;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Mail\EmailOtpMailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailOtpService
{
    public const EXPIRATION_MINUTES = 10;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;
    public const MAX_RESENDS = 5;

    /**
     * Hash the 6-digit OTP using HMAC-SHA256 bound to APP_KEY.
     */
    public static function hashOtp(string $otp): string
    {
        $appKey = config('app.key') ?: 'GEORX_SECURE_FALLBACK_KEY_HMAC_2026';
        return hash_hmac('sha256', trim($otp), $appKey);
    }

    /**
     * Hash the client challenge token with SHA-256 for secure database lookup.
     */
    public static function hashChallengeToken(string $token): string
    {
        return hash('sha256', trim($token));
    }

    /**
     * Create a new OTP challenge, store securely, and dispatch the email.
     */
    public static function createChallenge(User $user, string $purpose = 'email_verification'): array
    {
        // 1. Invalidate any existing unconsumed challenges for this user and purpose
        EmailOtpChallenge::where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['expires_at' => now()]);

        // 2. Generate cryptographically secure 6-digit OTP
        $otp = (string) random_int(100000, 999999);
        $rawChallengeToken = Str::random(64);

        $now = now();
        $challenge = EmailOtpChallenge::create([
            'user_id'              => $user->id,
            'email'                => $user->email,
            'purpose'              => $purpose,
            'otp_hash'             => self::hashOtp($otp),
            'challenge_token_hash' => self::hashChallengeToken($rawChallengeToken),
            'attempts'             => 0,
            'max_attempts'         => self::MAX_ATTEMPTS,
            'send_count'           => 1,
            'last_sent_at'         => $now,
            'expires_at'           => $now->copy()->addMinutes(self::EXPIRATION_MINUTES),
            'consumed_at'          => null,
        ]);

        // 3. Dispatch Email (safe fallback if mail fails)
        try {
            Mail::to($user->email)->send(new EmailOtpMailable(
                otp: $otp,
                purpose: $purpose,
                recipientName: $user->name ?: 'Patient',
                expiresInMinutes: self::EXPIRATION_MINUTES
            ));
        } catch (\Throwable $e) {
            Log::error("EmailOtpService delivery error for user [{$user->id}]: " . $e->getMessage());
            // We do NOT leak the error or OTP to the user, but inform calling controller
            return [
                'success'         => false,
                'delivery_failed' => true,
                'error'           => 'Unable to send verification email. Please check your address or try again shortly.',
                'challenge_token' => $rawChallengeToken,
                'masked_email'    => self::maskEmail($user->email),
            ];
        }

        return [
            'success'            => true,
            'delivery_failed'    => false,
            'challenge_token'    => $rawChallengeToken,
            'masked_email'       => self::maskEmail($user->email),
            'expires_in_seconds' => self::EXPIRATION_MINUTES * 60,
            'resend_in_seconds'  => self::RESEND_COOLDOWN_SECONDS,
        ];
    }

    /**
     * Atomically verify a submitted OTP against the challenge token.
     */
    public static function verifyChallenge(string $challengeToken, string $submittedOtp, ?string $expectedPurpose = null): array
    {
        $tokenHash = self::hashChallengeToken($challengeToken);

        return DB::transaction(function () use ($tokenHash, $submittedOtp, $expectedPurpose) {
            /** @var EmailOtpChallenge|null $challenge */
            $challenge = EmailOtpChallenge::where('challenge_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$challenge) {
                return [
                    'success' => false,
                    'status'  => 404,
                    'error'   => 'Invalid or expired verification session.',
                ];
            }

            if ($challenge->isConsumed()) {
                return [
                    'success' => false,
                    'status'  => 410,
                    'error'   => 'This verification code has already been used.',
                ];
            }

            if ($challenge->isExpired()) {
                return [
                    'success' => false,
                    'status'  => 410,
                    'error'   => 'Verification code has expired. Please request a new code.',
                ];
            }

            if ($challenge->hasExceededAttempts()) {
                return [
                    'success' => false,
                    'status'  => 429,
                    'error'   => 'Too many invalid attempts. This session is locked. Please request a new code.',
                ];
            }

            if ($expectedPurpose && $challenge->purpose !== $expectedPurpose) {
                return [
                    'success' => false,
                    'status'  => 400,
                    'error'   => 'Verification purpose mismatch.',
                ];
            }

            // Constant-time hash verification
            $submittedHash = self::hashOtp($submittedOtp);
            if (!hash_equals($challenge->otp_hash, $submittedHash)) {
                $challenge->increment('attempts');
                $remaining = max(0, $challenge->max_attempts - $challenge->attempts);

                return [
                    'success'            => false,
                    'status'             => 422,
                    'error'              => $remaining > 0
                        ? "Incorrect verification code. {$remaining} attempt(s) remaining."
                        : "Incorrect verification code. Maximum attempts exceeded. Please request a new code.",
                    'attempts_remaining' => $remaining,
                ];
            }

            // Successful verification: atomically mark consumed
            $challenge->update([
                'consumed_at' => now(),
            ]);

            return [
                'success'   => true,
                'status'    => 200,
                'challenge' => $challenge,
                'user'      => $challenge->user,
            ];
        });
    }

    /**
     * Resend an OTP code with strict cooldown and attempt protections.
     */
    public static function resendOtp(string $challengeToken): array
    {
        $tokenHash = self::hashChallengeToken($challengeToken);

        /** @var EmailOtpChallenge|null $challenge */
        $challenge = EmailOtpChallenge::where('challenge_token_hash', $tokenHash)->first();

        if (!$challenge) {
            return [
                'success' => false,
                'status'  => 404,
                'error'   => 'Invalid verification session.',
            ];
        }

        if ($challenge->isConsumed()) {
            return [
                'success' => false,
                'status'  => 410,
                'error'   => 'This verification session has already been completed.',
            ];
        }

        if (!$challenge->canResend(self::RESEND_COOLDOWN_SECONDS)) {
            $secondsRemaining = $challenge->resendSecondsRemaining(self::RESEND_COOLDOWN_SECONDS);
            return [
                'success'           => false,
                'status'            => 429,
                'error'             => "Please wait {$secondsRemaining} second(s) before requesting another code.",
                'seconds_remaining' => $secondsRemaining,
            ];
        }

        if ($challenge->send_count >= self::MAX_RESENDS) {
            return [
                'success' => false,
                'status'  => 429,
                'error'   => 'Maximum code requests reached for this session. Please start registration again.',
            ];
        }

        // Generate new OTP
        $newOtp = (string) random_int(100000, 999999);
        $user = $challenge->user;
        $recipientEmail = $user ? $user->email : $challenge->email;
        $recipientName = $user ? $user->name : 'Patient';

        $challenge->update([
            'otp_hash'     => self::hashOtp($newOtp),
            'attempts'     => 0, // Reset attempts for new code
            'send_count'   => $challenge->send_count + 1,
            'last_sent_at' => now(),
            'expires_at'   => now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        // Dispatch new code
        try {
            Mail::to($recipientEmail)->send(new EmailOtpMailable(
                otp: $newOtp,
                purpose: $challenge->purpose,
                recipientName: $recipientName,
                expiresInMinutes: self::EXPIRATION_MINUTES
            ));
        } catch (\Throwable $e) {
            Log::error("EmailOtpService resend delivery error for [{$recipientEmail}]: " . $e->getMessage());
            return [
                'success' => false,
                'status'  => 503,
                'error'   => 'Email delivery failed. Please verify your connection or try again shortly.',
            ];
        }

        return [
            'success'            => true,
            'status'             => 200,
            'message'            => 'A new six-digit verification code has been sent to your email.',
            'resend_in_seconds'  => self::RESEND_COOLDOWN_SECONDS,
            'expires_in_seconds' => self::EXPIRATION_MINUTES * 60,
        ];
    }

    /**
     * Safely mask an email address for privacy (e.g. j***n@domain.com).
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***.***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $len = strlen($name);
        if ($len <= 2) {
            $maskedName = substr($name, 0, 1) . '***';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $len - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }
}
