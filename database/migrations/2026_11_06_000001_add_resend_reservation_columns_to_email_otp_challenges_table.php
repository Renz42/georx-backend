<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additive, non-destructive schema extension for two-phase concurrent OTP
     * resend reservation, version fencing, and ambiguous-timeout recovery.
     */
    public function up(): void
    {
        if (!Schema::hasTable('email_otp_challenges')) {
            return;
        }

        Schema::table('email_otp_challenges', function (Blueprint $table) {
            if (!Schema::hasColumn('email_otp_challenges', 'active_hashes')) {
                $table->json('active_hashes')->nullable()->after('otp_hash');
            }
            if (!Schema::hasColumn('email_otp_challenges', 'pending_otp_hash')) {
                $table->string('pending_otp_hash', 64)->nullable()->after('active_hashes');
            }
            if (!Schema::hasColumn('email_otp_challenges', 'previous_otp_hash')) {
                $table->string('previous_otp_hash', 64)->nullable()->after('pending_otp_hash');
            }
            if (!Schema::hasColumn('email_otp_challenges', 'resend_reservation_token')) {
                $table->string('resend_reservation_token', 64)->nullable()->after('send_count');
            }
            if (!Schema::hasColumn('email_otp_challenges', 'resend_reserved_until')) {
                $table->timestamp('resend_reserved_until')->nullable()->after('resend_reservation_token');
            }
            if (!Schema::hasColumn('email_otp_challenges', 'delivery_state')) {
                $table->string('delivery_state', 32)->default('delivered')->after('resend_reserved_until');
            }
            if (!Schema::hasColumn('email_otp_challenges', 'otp_version')) {
                $table->unsignedInteger('otp_version')->default(1)->after('delivery_state');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('email_otp_challenges')) {
            return;
        }

        Schema::table('email_otp_challenges', function (Blueprint $table) {
            $columns = array_filter([
                'active_hashes',
                'pending_otp_hash',
                'previous_otp_hash',
                'resend_reservation_token',
                'resend_reserved_until',
                'delivery_state',
                'otp_version',
            ], fn (string $col) => Schema::hasColumn('email_otp_challenges', $col));

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

