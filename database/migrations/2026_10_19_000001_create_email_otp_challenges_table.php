<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('email_otp_challenges')) {
            Schema::create('email_otp_challenges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('email', 255)->index();
                $table->string('purpose', 50)->default('email_verification'); // registration, email_verification, login_2fa, password_reset
                $table->string('otp_hash', 64); // HMAC-SHA256 hash of 6-digit OTP
                $table->string('challenge_token_hash', 64)->unique(); // SHA256 hash of client challenge token
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->unsignedSmallInteger('max_attempts')->default(5);
                $table->unsignedSmallInteger('send_count')->default(1);
                $table->timestamp('last_sent_at')->useCurrent();
                $table->timestamp('expires_at');
                $table->timestamp('consumed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'purpose']);
                $table->index(['email', 'purpose']);
                $table->index('expires_at');
            });
        }

        // PostgreSQL / Supabase RLS Enforcement & Privilege Hardening (Always enforced idempotently)
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE public.email_otp_challenges ENABLE ROW LEVEL SECURITY;');
            \Illuminate\Support\Facades\DB::statement('REVOKE ALL ON public.email_otp_challenges FROM anon, authenticated, public;');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_otp_challenges');
    }
};
