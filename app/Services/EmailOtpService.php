<?php

namespace App\Services;

use App\Models\EmailOtpChallenge;
use App\Models\User;
use App\Mail\EmailOtpMailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmailOtpService
{
    public const EXPIRATION_MINUTES = 10;
    public const RESEND_COOLDOWN_SECONDS = 60;
    public const RESERVATION_TTL_SECONDS = 60;
    public const MAX_ATTEMPTS = 5;
    public const MAX_RESENDS = 5;

    public const STATE_DELIVERED = 'delivered';
    public const STATE_RESERVED = 'reserved';
    public const STATE_AMBIGUOUS_TIMEOUT = 'ambiguous_timeout';
    public const STATE_FAILED = 'failed';

    public const PURPOSE_REGISTRATION = 'registration';
    public const PURPOSE_EMAIL_VERIFICATION = 'email_verification';
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const ALLOWED_REGISTRATION_VERIFICATION_PURPOSES = [
        self::PURPOSE_REGISTRATION,
        self::PURPOSE_EMAIL_VERIFICATION,
    ];

    /**
     * Determine whether a challenge's purpose matches the expected purpose(s) in constant time.
     *
     * @param string $actualPurpose
     * @param string|array<int, string>|null $expectedPurpose
     */
    public static function purposeMatches(string $actualPurpose, string|array|null $expectedPurpose): bool
    {
        if ($expectedPurpose === null) {
            return true;
        }

        if (is_string($expectedPurpose)) {
            return $actualPurpose !== '' && hash_equals($expectedPurpose, $actualPurpose);
        }

        foreach ($expectedPurpose as $allowed) {
            if (is_string($allowed) && $allowed !== '' && hash_equals($allowed, $actualPurpose)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether the required two-phase concurrency and timeout-resilience
     * columns exist on the active database schema.
     */
    public static function checkSchemaReadiness(?string $connection = null): array
    {
        $requiredColumns = [
            'active_hashes',
            'pending_otp_hash',
            'previous_otp_hash',
            'resend_reservation_token',
            'resend_reserved_until',
            'delivery_state',
            'otp_version',
        ];

        $missing = [];
        $tableExists = Schema::connection($connection)->hasTable('email_otp_challenges');
        if ($tableExists) {
            foreach ($requiredColumns as $col) {
                if (!Schema::connection($connection)->hasColumn('email_otp_challenges', $col)) {
                    $missing[] = $col;
                }
            }
        } else {
            $missing = $requiredColumns;
        }

        return [
            'ready'           => $tableExists && empty($missing),
            'table_exists'    => $tableExists,
            'missing_columns' => $missing,
        ];
    }

    /**
     * Check whether the additive two-phase reservation columns exist on the active schema.
     */
    public static function supportsReservationColumns(?string $connection = null): bool
    {
        return self::checkSchemaReadiness($connection)['ready'];
    }

    /**
     * Determine whether an exception thrown during Mail::send() represents an ambiguous
     * post-transmission timeout (where Brevo may have already accepted the email)
     * rather than a definitive pre-delivery rejection.
     *
     * Pre-delivery rejections (DNS resolution failure, connection refused, TLS handshake failure,
     * connect timeout, missing API credentials, or HTTP 4xx/5xx responses) are strictly definitive failures.
     */
    public static function isAmbiguousTimeoutException(\Throwable $e): bool
    {
        $text = $e->getMessage() . ' ' . ($e->getPrevious() ? $e->getPrevious()->getMessage() : '');

        // 1. Missing credentials or configuration
        if (str_contains($text, 'Brevo API key is not configured')) {
            return false;
        }

        // 2. HTTP response from Brevo (4xx/5xx or API error payload)
        if (str_contains($text, 'Brevo API delivery failed:')) {
            return false;
        }

        // 3. DNS failure
        if (preg_match('/\b(Could not resolve host|cURL error 6\b)\b/i', $text)) {
            return false;
        }

        // 4. Connection refused / host unreachable
        if (preg_match('/\b(Connection refused|cURL error 7\b)\b/i', $text)) {
            return false;
        }

        // 5. TLS / SSL handshake failure
        if (preg_match('/\b(SSL connect error|cURL error 35\b|certificate|TLS)\b/i', $text)) {
            return false;
        }

        // 6. Connect timeout specifically (failed before TCP connection / bytes sent)
        if (preg_match('/(Failed to connect to|Connection timed out after|connect\(\) timed out)/i', $text)) {
            return false;
        }

        // 7. Post-transmission read / response timeout
        if ($e instanceof ConnectionException) {
            return true;
        }

        return (bool) preg_match('/\b(timed?\s*out|timeout|cURL error 28\b|Operation timed out)\b/i', $text);
    }

    /**
     * Constant-time verification of a submitted OTP hash against the challenge's
     * active OTP hash and all valid unexpired hashes in active_hashes or pending/previous hashes.
     *
     * Supports arbitrary consecutive ambiguous Brevo timeouts: any code delivered or potentially
     * delivered within its expiration period remains valid for single-use consumption.
     */
    protected static function matchesValidOtpHash(EmailOtpChallenge $challenge, string $submittedHash): bool
    {
        if (hash_equals((string) $challenge->otp_hash, $submittedHash)) {
            return true;
        }

        $nowTs = now()->timestamp;

        // Check durable multi-hash history (handles arbitrary repeated ambiguous timeouts)
        if (is_array($challenge->active_hashes)) {
            foreach ($challenge->active_hashes as $entry) {
                if (!is_array($entry) || empty($entry['hash'])) {
                    continue;
                }

                $state = $entry['state'] ?? self::STATE_DELIVERED;
                if (!in_array($state, [self::STATE_DELIVERED, self::STATE_AMBIGUOUS_TIMEOUT, self::STATE_RESERVED], true)) {
                    continue;
                }

                $expiresAt = (int) ($entry['expires_at'] ?? 0);
                if ($expiresAt > 0 && $expiresAt < $nowTs) {
                    continue;
                }

                if (hash_equals((string) $entry['hash'], $submittedHash)) {
                    return true;
                }
            }
        }

        // Backwards compatibility with scalar pending/previous columns
        $challengeState = $challenge->delivery_state ?? self::STATE_DELIVERED;
        if (in_array($challengeState, [self::STATE_AMBIGUOUS_TIMEOUT, self::STATE_RESERVED], true)) {
            if (!empty($challenge->pending_otp_hash) && hash_equals((string) $challenge->pending_otp_hash, $submittedHash)) {
                return true;
            }
            if (!empty($challenge->previous_otp_hash) && hash_equals((string) $challenge->previous_otp_hash, $submittedHash)) {
                return true;
            }
        }

        return false;
    }

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
        $hasColumns = self::supportsReservationColumns($user->getConnectionName());
        $challengeData = [
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
        ];

        if ($hasColumns) {
            $challengeData['delivery_state'] = self::STATE_DELIVERED;
            $challengeData['otp_version'] = 1;
            $challengeData['active_hashes'] = [
                [
                    'hash'       => self::hashOtp($otp),
                    'version'    => 1,
                    'state'      => self::STATE_DELIVERED,
                    'created_at' => $now->timestamp,
                    'expires_at' => $now->copy()->addMinutes(self::EXPIRATION_MINUTES)->timestamp,
                ],
            ];
        }

        $challenge = EmailOtpChallenge::create($challengeData);

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

            if ($purpose === 'password_reset') {
                $challenge->update(['expires_at' => now()]);
            }

            // We do NOT leak the error or OTP to the user, but inform calling controller
            return [
                'success'         => false,
                'delivery_failed' => true,
                'error'           => 'Unable to send verification email. Please check your address or try again shortly.',
                'challenge_token' => $purpose === 'password_reset' ? null : $rawChallengeToken,
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
     * Atomically verify a submitted OTP and execute a state-mutating callback
     * within the exact same database transaction.
     *
     * - Failed OTP attempts commit their `attempts` increment immediately.
     * - If `$onVerified` throws an exception, the transaction rolls back so
     *   `consumed_at` remains null and the valid OTP is not lost.
     */
    public static function verifyAndExecute(
        string $challengeToken,
        string $submittedOtp,
        string|array|null $expectedPurpose,
        callable $onVerified
    ): array {
        $tokenHash = self::hashChallengeToken($challengeToken);

        return DB::transaction(function () use ($tokenHash, $submittedOtp, $expectedPurpose, $onVerified) {
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

            if (!self::purposeMatches((string) $challenge->purpose, $expectedPurpose)) {
                return [
                    'success' => false,
                    'status'  => 400,
                    'error'   => 'Verification purpose mismatch.',
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

            // Constant-time hash verification (supports ambiguous-timeout recovery)
            $submittedHash = self::hashOtp($submittedOtp);
            if (!self::matchesValidOtpHash($challenge, $submittedHash)) {
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

            // Mark consumed inside the same transaction as the caller's state mutation
            $consumeUpdate = [
                'consumed_at' => now(),
            ];
            if (self::supportsReservationColumns($challenge->getConnectionName())) {
                $consumeUpdate['pending_otp_hash'] = null;
                $consumeUpdate['previous_otp_hash'] = null;
                $consumeUpdate['resend_reservation_token'] = null;
                $consumeUpdate['resend_reserved_until'] = null;
                $consumeUpdate['delivery_state'] = self::STATE_DELIVERED;
                $consumeUpdate['otp_version'] = ((int) ($challenge->otp_version ?? 1)) + 1;
                $consumeUpdate['active_hashes'] = null;
            }
            $challenge->update($consumeUpdate);

            $callbackPassed = $onVerified($challenge->user, $challenge);
            if ($callbackPassed === false) {
                $challenge->update([
                    'consumed_at' => null,
                    'expires_at'  => now(),
                ]);

                return [
                    'success' => false,
                    'status'  => 403,
                    'error'   => 'Invalid or expired verification session.',
                ];
            }

            return [
                'success'   => true,
                'status'    => 200,
                'challenge' => $challenge,
                'user'      => $challenge->user,
            ];
        });
    }

    /**
     * Atomically verify a submitted OTP against the challenge token.
     */
    public static function verifyChallenge(
        string $challengeToken,
        string $submittedOtp,
        string|array|null $expectedPurpose = null
    ): array {
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

            if (!self::purposeMatches((string) $challenge->purpose, $expectedPurpose)) {
                return [
                    'success' => false,
                    'status'  => 400,
                    'error'   => 'Verification purpose mismatch.',
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

            // Constant-time hash verification (supports ambiguous-timeout recovery)
            $submittedHash = self::hashOtp($submittedOtp);
            if (!self::matchesValidOtpHash($challenge, $submittedHash)) {
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

            // Successful verification: atomically mark consumed and invalidate any pending reservation
            $consumeUpdate = [
                'consumed_at' => now(),
            ];
            if (self::supportsReservationColumns($challenge->getConnectionName())) {
                $consumeUpdate['pending_otp_hash'] = null;
                $consumeUpdate['previous_otp_hash'] = null;
                $consumeUpdate['resend_reservation_token'] = null;
                $consumeUpdate['resend_reserved_until'] = null;
                $consumeUpdate['delivery_state'] = self::STATE_DELIVERED;
                $consumeUpdate['otp_version'] = ((int) ($challenge->otp_version ?? 1)) + 1;
                $consumeUpdate['active_hashes'] = null;
            }
            $challenge->update($consumeUpdate);

            return [
                'success'   => true,
                'status'    => 200,
                'challenge' => $challenge,
                'user'      => $challenge->user,
            ];
        });
    }

    /**
     * Atomically verify a submitted OTP against the challenge token without consuming it.
     * Enforces expiry, attempt limits, purpose matching, and constant-time hash verification.
     * Leaves the challenge unconsumed so that downstream actions (e.g., password reset)
     * can atomically consume it during the final state update.
     */
    public static function verifyOtpWithoutConsuming(
        string $challengeToken,
        string $submittedOtp,
        string|array|null $expectedPurpose = null
    ): array {
        $tokenHash = self::hashChallengeToken($challengeToken);

        return DB::transaction(function () use ($tokenHash, $submittedOtp, $expectedPurpose) {
            /** @var EmailOtpChallenge|null $challenge */
            $challenge = EmailOtpChallenge::with('user')
                ->where('challenge_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$challenge) {
                return [
                    'success' => false,
                    'status'  => 404,
                    'error'   => 'Invalid or expired verification session.',
                ];
            }

            if (!self::purposeMatches((string) $challenge->purpose, $expectedPurpose)) {
                return [
                    'success' => false,
                    'status'  => 400,
                    'error'   => 'Verification purpose mismatch.',
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
                    'attempts_remaining' => 0,
                ];
            }

            // Constant-time hash verification (supports ambiguous-timeout recovery)
            $submittedHash = self::hashOtp($submittedOtp);
            if (!self::matchesValidOtpHash($challenge, $submittedHash)) {
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

            return [
                'success'   => true,
                'status'    => 200,
                'challenge' => $challenge,
                'user'      => $challenge->user,
            ];
        });
    }

    /**
     * Resend an OTP code using a two-phase atomic reservation strategy:
     *
     * Phase 1 (short DB transaction + SELECT ... FOR UPDATE):
     *   - Rejects consumed or expired challenges.
     *   - Rejects concurrent resends while an active reservation exists.
     *   - Recovers cleanly if a prior reservation expired (abandoned worker).
     *   - Enforces cooldown and MAX_RESENDS atomically.
     *   - Reserves the resend slot (`delivery_state = 'reserved'`, `resend_reservation_token`,
     *     `otp_version`, `pending_otp_hash`, `send_count`, `last_sent_at`) and commits
     *     immediately so no PostgreSQL row lock is held across external Brevo HTTPS I/O.
     *
     * Phase 2 (unlocked external Brevo HTTPS call):
     *   - Dispatches the email via Mail::to()->send().
     *
     * Phase 3 (short DB transaction + SELECT ... FOR UPDATE):
     *   - Verifies the challenge was not consumed/expired or superseded by a newer `otp_version`.
     *   - On HTTP 2xx success: promotes `otp_hash = $newOtpHash`, resets `attempts = 0`,
     *     marks `delivery_state = 'delivered'`.
     *   - On ambiguous timeout: transitions to `delivery_state = 'ambiguous_timeout'`,
     *     retaining both the prior `otp_hash` and `pending_otp_hash` while restoring
     *     `send_count` and `last_sent_at` so the user is not locked out by a timeout.
     *   - On definitive failure: rolls back reservation and restores prior state.
     */
    public static function resendOtp(string $challengeToken, string|array|null $expectedPurpose = null): array
    {
        $tokenHash = self::hashChallengeToken($challengeToken);

        // Phase 1: Atomic reservation under short row lock
        $reservation = DB::transaction(function () use ($tokenHash, $expectedPurpose) {
            /** @var EmailOtpChallenge|null $challenge */
            $challenge = EmailOtpChallenge::with('user')
                ->where('challenge_token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$challenge) {
                return [
                    'reserved' => false,
                    'response' => [
                        'success' => false,
                        'status'  => 404,
                        'error'   => 'Invalid verification session.',
                    ],
                ];
            }

            if (!self::purposeMatches((string) $challenge->purpose, $expectedPurpose)) {
                return [
                    'reserved' => false,
                    'response' => [
                        'success' => false,
                        'status'  => 400,
                        'error'   => 'Verification purpose mismatch.',
                    ],
                ];
            }

            if ($challenge->isConsumed()) {
                return [
                    'reserved' => false,
                    'response' => [
                        'success' => false,
                        'status'  => 410,
                        'error'   => 'This verification session has already been completed.',
                    ],
                ];
            }

            if ($challenge->isExpired()) {
                return [
                    'reserved' => false,
                    'response' => [
                        'success' => false,
                        'status'  => 410,
                        'error'   => 'Verification code has expired. Please request a new code.',
                    ],
                ];
            }

            $hasColumns = self::supportsReservationColumns($challenge->getConnectionName());

            // Check if another concurrent resend is actively in-flight
            if ($hasColumns && $challenge->hasActiveResendReservation()) {
                $waitSeconds = max(1, (int) now()->diffInSeconds($challenge->resend_reserved_until, false));
                return [
                    'reserved' => false,
                    'response' => [
                        'success'           => false,
                        'status'            => 429,
                        'error'             => "Please wait {$waitSeconds} second(s) before requesting another code.",
                        'seconds_remaining' => $waitSeconds,
                    ],
                ];
            }

            // Recover if a prior reservation was abandoned (worker crashed before Phase 3)
            $abandonedRecovered = false;
            if ($hasColumns && ($challenge->delivery_state ?? '') === self::STATE_RESERVED && !$challenge->hasActiveResendReservation()) {
                $abandonedRecovered = true;
                $challenge->send_count = max(1, (int) $challenge->send_count - 1);
                $challenge->delivery_state = self::STATE_AMBIGUOUS_TIMEOUT;
                $challenge->previous_otp_hash = $challenge->pending_otp_hash ?: $challenge->previous_otp_hash;
                $challenge->resend_reservation_token = null;
                $challenge->resend_reserved_until = null;
            }

            if (!$abandonedRecovered && !$challenge->canResend(self::RESEND_COOLDOWN_SECONDS)) {
                $secondsRemaining = $challenge->resendSecondsRemaining(self::RESEND_COOLDOWN_SECONDS);
                return [
                    'reserved' => false,
                    'response' => [
                        'success'           => false,
                        'status'            => 429,
                        'error'             => "Please wait {$secondsRemaining} second(s) before requesting another code.",
                        'seconds_remaining' => $secondsRemaining,
                    ],
                ];
            }

            if ($challenge->send_count >= self::MAX_RESENDS) {
                $actionPrompt = $challenge->purpose === 'password_reset'
                    ? 'Please request a new password reset.'
                    : 'Please start registration again.';

                return [
                    'reserved' => false,
                    'response' => [
                        'success' => false,
                        'status'  => 429,
                        'error'   => "Maximum code requests reached for this session. {$actionPrompt}",
                    ],
                ];
            }

            $newOtp = (string) random_int(100000, 999999);
            $newOtpHash = self::hashOtp($newOtp);
            $reservationToken = Str::random(32);
            $nextVersion = ((int) ($challenge->otp_version ?? 1)) + 1;
            $now = now();

            $priorActiveHashes = is_array($challenge->active_hashes) ? $challenge->active_hashes : [];
            if (empty($priorActiveHashes) && !empty($challenge->otp_hash)) {
                $priorActiveHashes[] = [
                    'hash'       => $challenge->otp_hash,
                    'version'    => (int) ($challenge->otp_version ?? 1),
                    'state'      => self::STATE_DELIVERED,
                    'created_at' => $challenge->created_at ? $challenge->created_at->timestamp : $now->timestamp,
                    'expires_at' => $challenge->expires_at ? $challenge->expires_at->timestamp : $now->copy()->addMinutes(self::EXPIRATION_MINUTES)->timestamp,
                ];
            }

            // Prune expired hashes from active_hashes
            $nowTs = $now->timestamp;
            $prunedHashes = [];
            foreach ($priorActiveHashes as $entry) {
                if (!is_array($entry)) continue;
                $exp = (int) ($entry['expires_at'] ?? 0);
                if ($exp === 0 || $exp >= $nowTs) {
                    $prunedHashes[] = $entry;
                }
            }

            $priorState = [
                'otp_hash'          => $challenge->otp_hash,
                'previous_otp_hash' => $hasColumns ? $challenge->previous_otp_hash : null,
                'pending_otp_hash'  => $hasColumns ? $challenge->pending_otp_hash : null,
                'attempts'          => (int) $challenge->attempts,
                'send_count'        => (int) $challenge->send_count,
                'last_sent_at'      => $challenge->last_sent_at,
                'expires_at'        => $challenge->expires_at,
                'delivery_state'    => $hasColumns ? ($challenge->delivery_state ?? self::STATE_DELIVERED) : self::STATE_DELIVERED,
                'active_hashes'     => $prunedHashes,
            ];

            $reserveUpdate = [
                'send_count'   => $priorState['send_count'] + 1,
                'last_sent_at' => $now,
            ];

            if ($hasColumns) {
                $reserveUpdate['pending_otp_hash'] = $newOtpHash;
                $reserveUpdate['resend_reservation_token'] = $reservationToken;
                $reserveUpdate['resend_reserved_until'] = $now->copy()->addSeconds(self::RESERVATION_TTL_SECONDS);
                $reserveUpdate['delivery_state'] = self::STATE_RESERVED;
                $reserveUpdate['otp_version'] = $nextVersion;

                $reservedHashes = $prunedHashes;
                $reservedHashes[] = [
                    'hash'              => $newOtpHash,
                    'version'           => $nextVersion,
                    'state'             => self::STATE_RESERVED,
                    'reservation_token' => $reservationToken,
                    'created_at'        => $nowTs,
                    'expires_at'        => $now->copy()->addMinutes(self::EXPIRATION_MINUTES)->timestamp,
                ];
                $reserveUpdate['active_hashes'] = $reservedHashes;
            }

            $challenge->update($reserveUpdate);

            $user = $challenge->user;

            return [
                'reserved'          => true,
                'challenge_id'      => (int) $challenge->id,
                'purpose'           => (string) $challenge->purpose,
                'recipient_email'   => $user ? $user->email : $challenge->email,
                'recipient_name'    => $user ? ($user->name ?: 'Patient') : 'Patient',
                'new_otp'           => $newOtp,
                'new_otp_hash'      => $newOtpHash,
                'reservation_token' => $reservationToken,
                'expected_version'  => $nextVersion,
                'prior_state'       => $priorState,
            ];
        });

        if (!$reservation['reserved']) {
            return $reservation['response'];
        }

        // Phase 2: Unlocked external email transport call
        $outcome = 'delivered';
        try {
            Mail::to($reservation['recipient_email'])->send(new EmailOtpMailable(
                otp: $reservation['new_otp'],
                purpose: $reservation['purpose'],
                recipientName: $reservation['recipient_name'],
                expiresInMinutes: self::EXPIRATION_MINUTES
            ));
        } catch (\Throwable $e) {
            Log::error("EmailOtpService resend delivery error for [{$reservation['recipient_email']}]: " . $e->getMessage());
            $outcome = self::isAmbiguousTimeoutException($e) ? 'ambiguous_timeout' : 'failed';
        }

        // Phase 3: Finalize or recover under short row lock with version fencing
        return self::finalizeResendDelivery(
            challengeId: $reservation['challenge_id'],
            reservationToken: $reservation['reservation_token'],
            expectedVersion: $reservation['expected_version'],
            newOtpHash: $reservation['new_otp_hash'],
            priorState: $reservation['prior_state'],
            outcome: $outcome
        );
    }

    /**
     * Phase 3 of two-phase resend: atomically finalize delivery or recover from failure/timeout
     * while preventing stale writers or post-consumption overwrites.
     */
    public static function finalizeResendDelivery(
        int $challengeId,
        string $reservationToken,
        int $expectedVersion,
        string $newOtpHash,
        array $priorState,
        string $outcome
    ): array {
        return DB::transaction(function () use (
            $challengeId,
            $reservationToken,
            $expectedVersion,
            $newOtpHash,
            $priorState,
            $outcome
        ) {
            /** @var EmailOtpChallenge|null $locked */
            $locked = EmailOtpChallenge::where('id', $challengeId)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                return [
                    'success' => false,
                    'status'  => 404,
                    'error'   => 'Invalid verification session.',
                ];
            }

            $hasColumns = self::supportsReservationColumns($locked->getConnectionName());

            // Never overwrite a challenge that was consumed or expired while resend was in flight
            if ($locked->isConsumed()) {
                return [
                    'success' => false,
                    'status'  => 410,
                    'error'   => 'This verification session has already been completed.',
                ];
            }

            if ($locked->isExpired()) {
                return [
                    'success' => false,
                    'status'  => 410,
                    'error'   => 'Verification code has expired. Please request a new code.',
                ];
            }

            // Fence out stale operations that lost their reservation or version
            if (
                $hasColumns
                && (
                    $locked->resend_reservation_token !== $reservationToken
                    || (int) ($locked->otp_version ?? 0) !== $expectedVersion
                )
            ) {
                return [
                    'success' => false,
                    'status'  => 409,
                    'error'   => 'Verification session was updated by a newer request.',
                ];
            }

            if ($outcome === 'delivered') {
                $update = [
                    'otp_hash'     => $newOtpHash,
                    'attempts'     => 0,
                    'send_count'   => $priorState['send_count'] + 1,
                    'last_sent_at' => now(),
                    'expires_at'   => now()->addMinutes(self::EXPIRATION_MINUTES),
                ];

                if ($hasColumns) {
                    $update['pending_otp_hash'] = null;
                    $update['previous_otp_hash'] = null;
                    $update['resend_reservation_token'] = null;
                    $update['resend_reserved_until'] = null;
                    $update['delivery_state'] = self::STATE_DELIVERED;
                    $update['otp_version'] = $expectedVersion + 1;

                    $currentHashes = is_array($locked->active_hashes) ? $locked->active_hashes : ($priorState['active_hashes'] ?? []);
                    $updatedHashes = [];
                    foreach ($currentHashes as $entry) {
                        if (!is_array($entry)) continue;
                        if (($entry['version'] ?? 0) === $expectedVersion || hash_equals((string) ($entry['hash'] ?? ''), $newOtpHash)) {
                            $entry['state'] = self::STATE_DELIVERED;
                            unset($entry['reservation_token']);
                        }
                        $updatedHashes[] = $entry;
                    }
                    $update['active_hashes'] = $updatedHashes;
                }

                $locked->update($update);

                return [
                    'success'            => true,
                    'status'             => 200,
                    'message'            => 'A new six-digit verification code has been sent to your email.',
                    'resend_in_seconds'  => self::RESEND_COOLDOWN_SECONDS,
                    'expires_in_seconds' => self::EXPIRATION_MINUTES * 60,
                ];
            }

            if ($outcome === 'ambiguous_timeout') {
                // Brevo may have accepted the request before the HTTP read timed out.
                // Retain the prior otp_hash AND preserve the newly generated hash in
                // pending_otp_hash under STATE_AMBIGUOUS_TIMEOUT so either code can
                // verify, while restoring send_count and last_sent_at so the user is
                // not penalized by a cooldown if the timed-out email never arrives.
                $update = [
                    'otp_hash'     => $priorState['otp_hash'],
                    'attempts'     => $priorState['attempts'],
                    'send_count'   => $priorState['send_count'],
                    'last_sent_at' => $priorState['last_sent_at'],
                    'expires_at'   => now()->addMinutes(self::EXPIRATION_MINUTES),
                ];

                if ($hasColumns) {
                    $update['pending_otp_hash'] = $newOtpHash;
                    $update['previous_otp_hash'] = $priorState['otp_hash'];
                    $update['resend_reservation_token'] = null;
                    $update['resend_reserved_until'] = null;
                    $update['delivery_state'] = self::STATE_AMBIGUOUS_TIMEOUT;
                    $update['otp_version'] = $expectedVersion + 1;

                    $currentHashes = is_array($locked->active_hashes) ? $locked->active_hashes : ($priorState['active_hashes'] ?? []);
                    $updatedHashes = [];
                    foreach ($currentHashes as $entry) {
                        if (!is_array($entry)) continue;
                        if (($entry['version'] ?? 0) === $expectedVersion || hash_equals((string) ($entry['hash'] ?? ''), $newOtpHash)) {
                            $entry['state'] = self::STATE_AMBIGUOUS_TIMEOUT;
                            unset($entry['reservation_token']);
                        }
                        $updatedHashes[] = $entry;
                    }
                    $update['active_hashes'] = $updatedHashes;
                }

                $locked->update($update);

                return [
                    'success'            => false,
                    'status'             => 503,
                    'ambiguous_timeout'  => true,
                    'error'              => 'Email delivery timed out. If a code arrives you may use it, or request another code now.',
                ];
            }

            // Definitive failure: restore prior challenge state completely
            $rollbackUpdate = [
                'otp_hash'     => $priorState['otp_hash'],
                'attempts'     => $priorState['attempts'],
                'send_count'   => $priorState['send_count'],
                'last_sent_at' => $priorState['last_sent_at'],
                'expires_at'   => $priorState['expires_at'],
            ];

            if ($hasColumns) {
                $rollbackUpdate['pending_otp_hash'] = null;
                $rollbackUpdate['previous_otp_hash'] = $priorState['previous_otp_hash'];
                $rollbackUpdate['resend_reservation_token'] = null;
                $rollbackUpdate['resend_reserved_until'] = null;
                $rollbackUpdate['delivery_state'] = $priorState['delivery_state'];
                $rollbackUpdate['otp_version'] = $expectedVersion + 1;

                $currentHashes = is_array($locked->active_hashes) ? $locked->active_hashes : ($priorState['active_hashes'] ?? []);
                $rollbackUpdate['active_hashes'] = array_values(array_filter($currentHashes, function ($entry) use ($expectedVersion, $newOtpHash) {
                    if (!is_array($entry)) return false;
                    return ($entry['version'] ?? 0) !== $expectedVersion && !hash_equals((string) ($entry['hash'] ?? ''), $newOtpHash);
                }));
            }

            $locked->update($rollbackUpdate);

            return [
                'success' => false,
                'status'  => 503,
                'error'   => 'Email delivery failed. Please verify your connection or try again shortly.',
            ];
        });
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
