<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot table: inventory linking pharmacies and medicines
        Schema::create('pharmacy_medicine', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('pharmacy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();

            // 1. Stock & Inventory (Category 2)
            $table->integer('quantity_on_hand')->default(0); 
            $table->integer('reorder_level')->nullable(); // Triggers low-stock alerts
            $table->boolean('is_available')->default(true); // Quick flag for map search

            // 2. Batch & Expiry (Category 3)
            $table->string('batch_number')->nullable();
            $table->date('expiration_date')->nullable();

            // 3. Pricing (Category 5)
            $table->decimal('purchase_price', 10, 2)->nullable(); // Cost to pharmacy
            $table->decimal('selling_price', 10, 2); // Price shown to users on the map

            // 4. Compliance (Category 6)
            $table->string('storage_condition')->nullable(); // e.g., 'Refrigerated', 'Room Temp'

            $table->timestamps();
            
            // Constraints & Indexes
            $table->unique(['pharmacy_id', 'medicine_id']); // One active record per med per pharmacy
            $table->index('is_available');
            $table->index('expiration_date'); // Speeds up queries checking for expired stock
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_medicine');
    }
};