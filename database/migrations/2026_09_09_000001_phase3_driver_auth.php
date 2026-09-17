<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // =========================================================================
    //  GEORX Medicine Hub Portal
    //  Phase 3 — Driver Authentication & RBAC
    //
    //  Changes:
    //    1. users         → ADD account_status (VARCHAR 20, DEFAULT 'active')
    //    2. driver_profiles → NEW TABLE (driver metadata, GPS, availability)
    //
    //  Safe to re-run: wrapped in hasTable() / hasColumn() guards.
    // =========================================================================

    public function up(): void
    {
        // =====================================================================
        // 1. ADD account_status to users
        //    Default 'active' preserves all existing rows — zero behavior change.
        //    Driver registrations will explicitly set 'pending_review'.
        // =====================================================================
        if (! Schema::hasColumn('users', 'account_status')) {
            Schema::table('users', function (Blueprint $table) {
                // Values: active | pending_review | approved | suspended | rejected
                $table->string('account_status', 20)->default('active')->after('role');
            });
        }

        // =====================================================================
        // 2. CREATE driver_profiles
        //    1-to-1 with users (role = 'driver')
        //    Stores vehicle info, live GPS, availability, and approval status.
        // =====================================================================
        if (! Schema::hasTable('driver_profiles')) {
            Schema::create('driver_profiles', function (Blueprint $table) {
                $table->id();

                // 1-to-1 with users (CASCADE: deleting driver user removes profile)
                $table->foreignId('user_id')
                      ->unique()
                      ->constrained('users')
                      ->cascadeOnDelete();

                // --- Vehicle Information ---
                // motorcycle | bicycle | e-bike | car
                $table->string('vehicle_type', 50)->nullable();
                $table->string('vehicle_make', 100)->nullable();   // Honda, Yamaha
                $table->string('vehicle_model', 100)->nullable();  // Click 125i, Mio
                $table->string('plate_number', 20)->nullable();    // ABC-1234

                // --- Availability (managed separately from account_status) ---
                // is_online:    driver self-toggle (Online = visible in booking feed)
                // is_available: system-managed (false = currently on active delivery)
                $table->boolean('is_online')->default(false);
                $table->boolean('is_available')->default(true);

                // Approval workflow: pending_review | approved | suspended | rejected
                // pending_review = freshly registered, waiting for admin action
                // approved       = can login and accept deliveries
                // suspended      = temporarily blocked (reversible by admin)
                // rejected       = permanently declined
                $table->string('account_status', 20)->default('pending_review');

                // --- Live GPS (updated by mobile app on each position change) ---
                // DECIMAL(10,8) = ±90.00000000  → sufficient for latitude
                // DECIMAL(11,8) = ±180.00000000 → sufficient for longitude
                $table->decimal('current_latitude', 10, 8)->nullable();
                $table->decimal('current_longitude', 11, 8)->nullable();
                $table->timestamp('last_location_at')->nullable(); // GPS recency check

                // --- Credentials ---
                $table->string('license_number', 50)->nullable();
                $table->string('profile_photo', 255)->nullable(); // Supabase Storage path

                $table->timestamps();

                // Indexes for driver-feed and admin queries
                $table->index('is_online');
                $table->index(['is_online', 'is_available']);    // Eligibility check
                $table->index(['current_latitude', 'current_longitude']); // Proximity sort
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');

        if (Schema::hasColumn('users', 'account_status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('account_status');
            });
        }
    }
};
