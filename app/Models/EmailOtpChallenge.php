<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailOtpChallenge extends Model
{
    use HasFactory;

    protected $table = 'email_otp_challenges';

    protected $fillable = [
        'user_id',
        'email',
        'purpose',
        'otp_hash',
        'active_hashes',
        'pending_otp_hash',
        'previous_otp_hash',
        'challenge_token_hash',
        'attempts',
        'max_attempts',
        'send_count',
        'resend_reservation_token',
        'resend_reserved_until',
        'delivery_state',
        'otp_version',
        'last_sent_at',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'active_hashes' => 'array',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'send_count' => 'integer',
            'otp_version' => 'integer',
            'resend_reserved_until' => 'datetime',
            'last_sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function hasActiveResendReservation(): bool
    {
        return ($this->delivery_state ?? 'delivered') === 'reserved'
            && $this->resend_reserved_until !== null
            && $this->resend_reserved_until->isFuture();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }

    public function canResend(int $cooldownSeconds = 60): bool
    {
        if ($this->isConsumed()) {
            return false;
        }
        return $this->last_sent_at->addSeconds($cooldownSeconds)->isPast();
    }

    public function resendSecondsRemaining(int $cooldownSeconds = 60): int
    {
        if ($this->canResend($cooldownSeconds)) {
            return 0;
        }
        return max(0, (int) now()->diffInSeconds($this->last_sent_at->addSeconds($cooldownSeconds), false));
    }
}
