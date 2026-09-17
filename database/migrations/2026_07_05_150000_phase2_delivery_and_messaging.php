<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =============================================
        // 1. CONVERSATIONS TABLE (Messaging)
        // =============================================
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'pharmacy_id', 'order_id']);
        });

        // =============================================
        // 2. MESSAGES TABLE
        // =============================================
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['conversation_id', 'is_read']);
        });

        // =============================================
        // 3. INVENTORY BATCHES TABLE (FIFO)
        // =============================================
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->string('storage_condition')->nullable();
            $table->string('supplier')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['pharmacy_id', 'medicine_id', 'expiration_date']);
        });

        // =============================================
        // 4. ALTER ORDERS TABLE (Maxim + Confirmation)
        // =============================================
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('pharmacy_confirmed')->default(false)->after('is_prepared');
            $table->timestamp('confirmed_at')->nullable()->after('pharmacy_confirmed');
            $table->timestamp('rejected_at')->nullable()->after('confirmed_at');
            $table->string('rejection_reason')->nullable()->after('rejected_at');

            // Maxim integration fields
            $table->string('maxim_booking_id')->nullable()->after('rejection_reason');
            $table->string('maxim_tracking_url')->nullable()->after('maxim_booking_id');
            $table->string('driver_name')->nullable()->after('maxim_tracking_url');
            $table->string('driver_phone')->nullable()->after('driver_name');
            $table->string('driver_vehicle')->nullable()->after('driver_phone');
            $table->timestamp('estimated_delivery_at')->nullable()->after('driver_vehicle');
            $table->timestamp('delivered_at')->nullable()->after('estimated_delivery_at');

            // FIFO deduction log (JSON array of batch deductions)
            $table->json('fifo_deductions')->nullable()->after('delivered_at');
        });

        // =============================================
        // 5. ALTER STOCK_ALERTS TABLE (global watch)
        // =============================================
        // Make pharmacy_id nullable so users can watch a medicine globally.
        // Driver-aware: PostgreSQL uses raw ALTER COLUMN, MySQL uses ->change().
        if (DB::getDriverName() === 'pgsql') {
            // Drop existing FK constraint first, then re-add as nullable
            DB::statement('ALTER TABLE stock_alerts ALTER COLUMN pharmacy_id DROP NOT NULL');
        } else {
            Schema::table('stock_alerts', function (Blueprint $table) {
                $table->unsignedBigInteger('pharmacy_id')->nullable()->change();
            });
        }

    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'pharmacy_confirmed', 'confirmed_at', 'rejected_at', 'rejection_reason',
                'maxim_booking_id', 'maxim_tracking_url',
                'driver_name', 'driver_phone', 'driver_vehicle',
                'estimated_delivery_at', 'delivered_at', 'fifo_deductions',
            ]);
        });

        Schema::dropIfExists('inventory_batches');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
