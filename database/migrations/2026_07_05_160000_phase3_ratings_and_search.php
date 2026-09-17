<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand reviews table with granular ratings
        Schema::table('reviews', function (Blueprint $table) {
            // Delivery ratings (1-5 stars)
            $table->unsignedTinyInteger('delivery_speed')->nullable()->after('comment');
            $table->unsignedTinyInteger('driver_professionalism')->nullable()->after('delivery_speed');
            $table->unsignedTinyInteger('medicine_condition')->nullable()->after('driver_professionalism');
            $table->unsignedTinyInteger('delivery_overall')->nullable()->after('medicine_condition');

            // Pharmacy ratings (1-5 stars)
            $table->unsignedTinyInteger('medicine_availability')->nullable()->after('delivery_overall');
            $table->unsignedTinyInteger('price_rating')->nullable()->after('medicine_availability');
            $table->unsignedTinyInteger('customer_service')->nullable()->after('price_rating');
            $table->unsignedTinyInteger('accuracy')->nullable()->after('customer_service');
            $table->unsignedTinyInteger('pharmacy_overall')->nullable()->after('accuracy');
        });

        // 2. Create search_logs table for analytics
        Schema::create('search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('term');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('results_count')->default(0);
            $table->timestamps();

            $table->index('term');
            $table->index('created_at');
        });

        // 3. Add cover_photo to pharmacies if not exists
        if (!Schema::hasColumn('pharmacies', 'cover_photo')) {
            Schema::table('pharmacies', function (Blueprint $table) {
                $table->string('cover_photo')->nullable()->after('logo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_speed', 'driver_professionalism', 'medicine_condition', 'delivery_overall',
                'medicine_availability', 'price_rating', 'customer_service', 'accuracy', 'pharmacy_overall',
            ]);
        });

        Schema::dropIfExists('search_logs');

        if (Schema::hasColumn('pharmacies', 'cover_photo')) {
            Schema::table('pharmacies', function (Blueprint $table) {
                $table->dropColumn('cover_photo');
            });
        }
    }
};
