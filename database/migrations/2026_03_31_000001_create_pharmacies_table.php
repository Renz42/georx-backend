<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('address');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            
            // Geospatial columns
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            
            // Profile data
            $table->string('owner_name')->nullable();
            $table->text('description')->nullable();
            $table->string('license_number')->nullable();
            $table->string('logo')->nullable();
            $table->json('operating_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_approved')->default(false); // Add this line!
            $table->timestamps();
            
            // Index for proximity searches
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacies');
    }
};
