<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicines', function (Blueprint $table) {
            $table->id(); // This acts as your unique medicine_id
            $table->string('slug')->unique(); // Great for clean URLs

            // 1. Identification
            $table->string('barcode')->nullable()->unique();
            $table->string('medicine_code')->nullable()->unique(); // Internal SKU

            // 2. Nomenclature
            $table->string('brand_name')->nullable();
            $table->string('generic_name'); // Kept required, as every drug has a generic name

            // 3. Formulation & Packaging
            $table->string('dosage_form')->nullable(); // e.g., Tablet, Capsule, Syrup
            $table->string('strength')->nullable(); // e.g., 500 mg, 250 mg/5mL
            $table->string('unit_of_measure')->nullable(); // e.g., box, bottle, piece
            $table->integer('pack_size')->nullable(); // e.g., 100 (for a box of 100)

            // 4. Classification
            $table->string('therapeutic_class')->nullable(); // e.g., Antibiotic, Analgesic
            $table->string('drug_category')->nullable(); // e.g., OTC, Prescription
            $table->text('description')->nullable();

            // 5. Regulatory / Compliance (Master Level)
            $table->boolean('prescription_required')->default(false);
            $table->boolean('controlled_substance_flag')->default(false);
            $table->string('fda_registration_no')->nullable();

            $table->timestamps();
            
            // Indexes make your search bar lightning fast
            $table->index('generic_name');
            $table->index('brand_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};