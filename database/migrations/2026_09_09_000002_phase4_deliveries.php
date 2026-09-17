<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Phase 4 — Customer Order to Delivery Booking
     */
    public function up(): void
    {
        // 1. Add supplementary delivery timestamps to orders table
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (!Schema::hasColumn('orders', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('confirmed_at');
                }
                if (!Schema::hasColumn('orders', 'picked_up_at')) {
                    $table->timestamp('picked_up_at')->nullable()->after('accepted_at');
                }
            });
        }

        // 2. Create dedicated deliveries table
        if (!Schema::hasTable('deliveries')) {
            Schema::create('deliveries', function (Blueprint $table) {
                $table->id();

                // 1-to-1 with orders (CASCADE: deleting order removes delivery)
                $table->foreignId('order_id')
                      ->unique()
                      ->constrained('orders')
                      ->cascadeOnDelete();

                // Customer who placed order (SET NULL on user deletion)
                $table->foreignId('customer_id')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();

                // Driver assigned to delivery (NULL initially, SET NULL on user deletion)
                $table->foreignId('driver_id')
                      ->nullable()
                      ->constrained('users')
                      ->nullOnDelete();

                // Official Pickup Pharmacy (RESTRICT: cannot delete pharmacy with active delivery)
                $table->foreignId('pickup_pharmacy_id')
                      ->constrained('pharmacies')
                      ->restrictOnDelete();

                // Pickup location coordinates snapshot (from selected pharmacy)
                $table->decimal('pickup_latitude', 10, 8);
                $table->decimal('pickup_longitude', 11, 8);

                // Customer delivery address & coordinates
                $table->text('delivery_address');
                $table->decimal('delivery_latitude', 10, 8)->nullable();
                $table->decimal('delivery_longitude', 11, 8)->nullable();

                // Delivery Status
                // waiting_for_driver | driver_assigned | driver_at_pharmacy | picked_up | out_for_delivery | delivered | cancelled | delivery_failed
                $table->string('status', 30)->default('waiting_for_driver');

                // Delivery Lifecycle Timestamps
                $table->timestamp('driver_accepted_at')->nullable();
                $table->timestamp('driver_at_pharmacy_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('out_for_delivery_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_by', 20)->nullable(); // customer | driver | system | pharmacy
                $table->text('cancellation_reason')->nullable();
                $table->text('failure_reason')->nullable();
                $table->text('driver_notes')->nullable();

                $table->timestamps();

                // Indexes
                $table->index('status');
                $table->index(['status', 'created_at']);
                $table->index(['driver_id', 'status']);
                $table->index(['customer_id', 'status']);
                $table->index(['pickup_pharmacy_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'accepted_at')) {
                    $table->dropColumn('accepted_at');
                }
                if (Schema::hasColumn('orders', 'picked_up_at')) {
                    $table->dropColumn('picked_up_at');
                }
            });
        }
    }
};
