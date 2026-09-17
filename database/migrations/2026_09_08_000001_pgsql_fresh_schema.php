<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // =========================================================================
    //  GEORX Medicine Hub Portal
    //  Supabase PostgreSQL — Consolidated Fresh Schema
    //  Phase 3 — Single-file authoritative PostgreSQL schema
    //
    //  This file consolidates all 31 incremental MySQL migrations into one
    //  PostgreSQL-native migration. It is the ONLY migration you need to
    //  run on a fresh Supabase project.
    //
    //  Usage:
    //    php artisan migrate \
    //      --path=database/migrations/2026_09_08_000001_pgsql_fresh_schema.php
    //
    //  Guards:
    //    - Every table is wrapped in hasTable() — safe to re-run without error.
    //    - Designed for PostgreSQL. All ->after() calls removed.
    //    - Uses unsignedSmallInteger (SMALLINT) for rating columns (no TINYINT in PG).
    //    - Uses JSONB natively via Laravel's json() for operating_hours, fifo_deductions.
    //    - Haversine queries must use ILIKE and LEAST() — see Phase 3 app patches.
    // =========================================================================

    public function up(): void
    {
        // =====================================================================
        // LAYER 0 — ROOT TABLES (no foreign key dependencies)
        // =====================================================================

        // [1] USERS — auth, RBAC roles, pharmacy staff link
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone', 20)->nullable();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                // Roles: customer | pharmacy_owner | pharmacist | pharmacy_staff
                //        delivery_partner | administrator
                $table->string('role', 20)->default('customer');
                // pharmacy_id FK is added in LAYER 1 after pharmacies exists
                $table->unsignedBigInteger('pharmacy_id')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // [2] PHARMACIES — core entity, geolocation, status, branding
        if (! Schema::hasTable('pharmacies')) {
            Schema::create('pharmacies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('address');
                $table->string('phone', 20)->nullable();
                $table->string('email')->nullable();

                // Geospatial — Barangay Alijis pilot scope
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);

                // Profile
                $table->string('owner_name')->nullable();
                $table->string('theme_color')->default('#2563eb');
                $table->text('description')->nullable();
                $table->string('license_number')->nullable();
                $table->string('lto_number')->nullable();
                $table->string('logo')->nullable();
                $table->string('cover_photo')->nullable();
                // JSON → PostgreSQL stores as JSONB (queryable, indexed)
                $table->json('operating_hours')->nullable();
                $table->string('business_permit')->nullable();

                // Approval workflow
                $table->boolean('is_active')->default(true);
                $table->boolean('is_approved')->default(false);
                // status: pending | approved | rejected | suspended
                $table->string('status')->default('pending');

                $table->timestamps();

                // Composite index for proximity radius queries (Haversine)
                $table->index(['latitude', 'longitude']);
            });
        }

        // [3] MEDICINES — master catalog (shared across all pharmacies)
        if (! Schema::hasTable('medicines')) {
            Schema::create('medicines', function (Blueprint $table) {
                $table->id();
                $table->string('slug')->unique();

                // Identification
                $table->string('barcode')->nullable()->unique();
                $table->string('medicine_code')->nullable()->unique();

                // Nomenclature
                $table->string('brand_name')->nullable();
                $table->string('generic_name'); // required — every drug has one

                // Formulation & Packaging
                $table->string('dosage_form')->nullable();     // Tablet, Capsule, Syrup
                $table->string('strength')->nullable();        // 500mg, 250mg/5mL
                $table->string('unit_of_measure')->nullable(); // box, bottle, piece
                $table->integer('pack_size')->nullable();      // 100 (box of 100)

                // Classification
                $table->string('therapeutic_class')->nullable(); // Antibiotic, Analgesic
                $table->string('drug_category')->nullable();     // OTC, Prescription
                $table->text('primary_use')->nullable();         // What it treats
                $table->text('description')->nullable();
                $table->string('image')->nullable();

                // Regulatory
                $table->boolean('prescription_required')->default(false);
                $table->boolean('controlled_substance_flag')->default(false);
                $table->string('fda_registration_no')->nullable();

                $table->timestamps();

                // Full-text search performance indexes
                $table->index('generic_name');
                $table->index('brand_name');
                $table->index('therapeutic_class');
                $table->index('drug_category');
            });
        }

        // [4] LARAVEL SYSTEM — password reset tokens
        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        // [5] LARAVEL SYSTEM — sessions (database driver)
        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // [6] LARAVEL SYSTEM — cache (database driver)
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration')->index();
            });
        }

        // [7] LARAVEL SYSTEM — queues
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts'); // → SMALLINT on PG
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('job_batches')) {
            Schema::create('job_batches', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->integer('total_jobs');
                $table->integer('pending_jobs');
                $table->integer('failed_jobs');
                $table->longText('failed_job_ids');
                $table->mediumText('options')->nullable();
                $table->integer('cancelled_at')->nullable();
                $table->integer('created_at');
                $table->integer('finished_at')->nullable();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        // [8] GLOBAL SETTINGS — platform-wide admin config
        if (! Schema::hasTable('global_settings')) {
            Schema::create('global_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        // =====================================================================
        // LAYER 1 — Add users → pharmacies FK (both tables now exist)
        // =====================================================================
        if (! $this->foreignKeyExists('users', 'users_pharmacy_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('pharmacy_id')
                      ->references('id')->on('pharmacies')
                      ->onDelete('set null');
            });
        }

        // =====================================================================
        // LAYER 2 — INVENTORY (depends on pharmacies + medicines)
        // =====================================================================

        // [9] PHARMACY_MEDICINE — inventory pivot, aggregate totals
        //     This is the "current stock" snapshot for the map/search UI.
        if (! Schema::hasTable('pharmacy_medicine')) {
            Schema::create('pharmacy_medicine', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();

                // Stock quantities
                $table->integer('quantity_on_hand')->default(0);
                $table->string('unit')->default('Piece / Tablet');
                $table->integer('reorder_level')->nullable();
                $table->boolean('is_available')->default(true);

                // Batch snapshot (mirrors earliest active batch from inventory_batches)
                $table->string('batch_number')->nullable();
                $table->date('expiration_date')->nullable();

                // Pricing
                $table->decimal('purchase_price', 10, 2)->nullable();
                $table->decimal('selling_price', 10, 2); // required — shown on map

                // Compliance & Logistics
                $table->string('storage_condition')->nullable();
                $table->string('supplier')->nullable();
                $table->timestamp('last_restocked_at')->nullable();

                $table->timestamps();

                // One active pivot record per medicine per pharmacy
                $table->unique(['pharmacy_id', 'medicine_id']);
                $table->index('is_available');
                $table->index('expiration_date');
            });
        }

        // [10] INVENTORY_BATCHES — FIFO batch tracking (source of truth for stock)
        //      Multiple batches per (pharmacy × medicine). Deducted earliest-first.
        if (! Schema::hasTable('inventory_batches')) {
            Schema::create('inventory_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();

                $table->string('batch_number')->nullable();
                $table->date('expiration_date')->nullable();
                $table->integer('quantity')->default(0);
                // status: active | quarantined | expired | depleted
                $table->string('status')->default('active');

                $table->decimal('purchase_price', 10, 2)->nullable();
                $table->string('storage_condition')->nullable();
                $table->string('supplier')->nullable();
                $table->timestamp('received_at')->nullable();

                $table->timestamps();

                // Index supports FIFO ORDER BY expiration_date ASC queries
                $table->index(['pharmacy_id', 'medicine_id', 'expiration_date']);
                $table->index(['pharmacy_id', 'medicine_id', 'status']);
            });
        }

        // =====================================================================
        // LAYER 3 — USER-LINKED TABLES (depends on users, pharmacies, medicines)
        // =====================================================================

        // [11] STOCK_ALERTS — notify user when medicine is restocked
        //      pharmacy_id is NULLABLE → allows global (all-pharmacy) watches
        if (! Schema::hasTable('stock_alerts')) {
            Schema::create('stock_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                // NOTE: In PostgreSQL, NULL != NULL in unique constraints.
                // Multiple rows with (user_id, pharmacy_id=NULL, medicine_id) are allowed.
                // This matches original MySQL behavior.
                $table->unique(['user_id', 'pharmacy_id', 'medicine_id']);
                $table->index(['pharmacy_id', 'medicine_id', 'is_active']);
            });
        }

        // [12] USER_FAVORITES — saved pharmacies (mapped from "saved_medicines" concept)
        if (! Schema::hasTable('user_favorites')) {
            Schema::create('user_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['user_id', 'pharmacy_id']);
            });
        }

        // [13] PERSONAL_ACCESS_TOKENS — Laravel Sanctum API tokens
        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->morphs('tokenable'); // tokenable_type + tokenable_id
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }

        // [14] NOTIFICATIONS — Laravel DB notification channel (UUID primary key)
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable'); // notifiable_type + notifiable_id
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // =====================================================================
        // LAYER 4 — COMMERCE (cart → orders → order_items)
        // =====================================================================

        // [15] CART_ITEMS — pre-order staging basket
        if (! Schema::hasTable('cart_items')) {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity')->default(1);
                $table->timestamps();
            });
        }

        // [16] ORDERS — full order lifecycle + Maxim rider integration
        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                // Self-referencing FK: delivery_partner is also a User
                $table->foreignId('delivery_partner_id')
                      ->nullable()->constrained('users')->nullOnDelete();

                // --- Order Status Flow ---
                // pending_confirmation → confirmed → pending → accepted →
                // at_pharmacy → picked_up → delivered | cancelled | rejected
                $table->string('status')->default('pending_confirmation');
                $table->boolean('is_prepared')->default(false);
                $table->boolean('pharmacy_confirmed')->default(false);
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->string('rejection_reason')->nullable();

                // --- Financials ---
                $table->decimal('total_amount', 10, 2);
                $table->decimal('delivery_fee', 10, 2)->default(50.00);
                $table->string('payment_method')->default('cod');

                // --- Delivery Location (Barangay Alijis scope) ---
                $table->text('delivery_address');
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();

                // --- Maxim Rider Integration ---
                $table->string('maxim_booking_id')->nullable();
                $table->string('maxim_tracking_url')->nullable();
                $table->string('driver_name')->nullable();
                $table->string('driver_phone')->nullable();
                $table->string('driver_vehicle')->nullable();
                $table->timestamp('estimated_delivery_at')->nullable();
                $table->timestamp('delivered_at')->nullable();

                // FIFO deduction log stored as JSONB
                // Structure: [{"batch_id":1,"batch_number":"B001","quantity":2}, ...]
                $table->json('fifo_deductions')->nullable();

                $table->timestamps();

                $table->index(['pharmacy_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        // [17] ORDER_ITEMS — line items per order (snapshot pricing)
        if (! Schema::hasTable('order_items')) {
            Schema::create('order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity');
                $table->decimal('price', 10, 2); // price at time of purchase
                $table->timestamps();
            });
        }

        // =====================================================================
        // LAYER 5 — POST-ORDER (reviews, audit, messaging, search)
        // =====================================================================

        // [18] REVIEWS — granular ratings for pharmacy & delivery
        //      Maps to user's "ratings" table requirement.
        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();

                // Legacy overall rating (fallback if granular not filled)
                $table->integer('rating')->default(5);
                $table->text('comment')->nullable();

                // Delivery Ratings (1–5 stars)
                // NOTE: PostgreSQL has no TINYINT → SMALLINT is used (SMALLINT = 2 bytes)
                $table->unsignedSmallInteger('delivery_speed')->nullable();
                $table->unsignedSmallInteger('driver_professionalism')->nullable();
                $table->unsignedSmallInteger('medicine_condition')->nullable();
                $table->unsignedSmallInteger('delivery_overall')->nullable();

                // Pharmacy Ratings (1–5 stars)
                $table->unsignedSmallInteger('medicine_availability')->nullable();
                $table->unsignedSmallInteger('price_rating')->nullable();
                $table->unsignedSmallInteger('customer_service')->nullable();
                $table->unsignedSmallInteger('accuracy')->nullable();
                $table->unsignedSmallInteger('pharmacy_overall')->nullable();

                $table->timestamps();

                $table->index(['pharmacy_id', 'created_at']);
            });
        }

        // [19] AUDIT_LOGS — pharmacy staff action trail
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('action');     // e.g. 'Restocked Item', 'Changed Price'
                $table->text('details');      // full change specifics
                $table->timestamps();

                $table->index(['pharmacy_id', 'created_at']);
            });
        }

        // [20] CONVERSATIONS — messaging threads (customer ↔ pharmacy)
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                // One thread per (customer, pharmacy, order) combination.
                // NULL order_id = general inquiry thread.
                $table->unique(['user_id', 'pharmacy_id', 'order_id']);
            });
        }

        // [21] MESSAGES — individual chat messages (maps to user's "messages" requirement)
        if (! Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                // sender_id is a FK to users (not a dedicated sender table)
                $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
                $table->text('body')->nullable();
                $table->string('image_path')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                // Composite indexes for efficient chat loading
                $table->index(['conversation_id', 'created_at']);
                $table->index(['conversation_id', 'is_read']);
            });
        }

        // [22] SEARCH_LOGS — analytics (maps to user's "medicine_searches" requirement)
        if (! Schema::hasTable('search_logs')) {
            Schema::create('search_logs', function (Blueprint $table) {
                $table->id();
                $table->string('term');
                // search_type: medicine | pharmacy | all
                $table->string('search_type')->default('medicine');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->unsignedInteger('results_count')->default(0);
                $table->timestamps();

                $table->index('term');
                $table->index('created_at');
                $table->index(['term', 'search_type']);
            });
        }

        // =====================================================================
        // SEED — Default Super Administrator
        // =====================================================================
        if (! DB::table('users')->where('email', 'admin@medicinelocator.com')->exists()) {
            DB::table('users')->insert([
                'name'        => 'Super Admin',
                'email'       => 'admin@medicinelocator.com',
                'phone'       => '09171234567',
                'password'    => Hash::make('password123'),
                'role'        => 'administrator',
                'pharmacy_id' => null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // =====================================================================
        // SEED — Default Global Settings
        // =====================================================================
        $defaults = [
            ['key' => 'enable_new_registrations', 'value' => 'true',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'base_delivery_fee',        'value' => '50.00', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($defaults as $setting) {
            if (! DB::table('global_settings')->where('key', $setting['key'])->exists()) {
                DB::table('global_settings')->insert($setting);
            }
        }
    }

    // =========================================================================
    // DOWN — Drop all tables in reverse FK dependency order
    // =========================================================================
    public function down(): void
    {
        // Layer 5
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reviews');
        // Layer 4
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('cart_items');
        // Layer 3
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_favorites');
        Schema::dropIfExists('stock_alerts');
        // Layer 2
        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('pharmacy_medicine');
        // Layer 1 — drop FK before dropping parents
        if ($this->foreignKeyExists('users', 'users_pharmacy_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['pharmacy_id']);
            });
        }
        // Layer 0
        Schema::dropIfExists('global_settings');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('pharmacies');
        Schema::dropIfExists('users');
    }

    // =========================================================================
    // HELPER — Check if a named FK constraint already exists (idempotency)
    // =========================================================================
    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $result = DB::select(
            "SELECT 1 FROM information_schema.table_constraints
             WHERE table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'",
            [$table, $constraintName]
        );

        return ! empty($result);
    }
};
