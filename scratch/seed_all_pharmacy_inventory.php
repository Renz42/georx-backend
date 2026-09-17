<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use App\Models\Pharmacy;
use App\Models\Medicine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== SEEDING PHARMACY INVENTORY FOR ALL PHARMACIES ===\n";

function getOrCreateMedicine($brand, $generic, $dosage, $use, $category, $class, $rx) {
    $med = Medicine::where('brand_name', $brand)->where('generic_name', $generic)->first();
    if (!$med) {
        $med = Medicine::create([
            'brand_name' => $brand,
            'generic_name' => $generic,
            'slug' => Str::slug($brand . '-' . $generic . '-' . Str::random(4)),
            'dosage' => $dosage,
            'primary_use' => $use,
            'drug_category' => $category,
            'therapeutic_class' => $class,
            'requires_prescription' => $rx,
        ]);
    }
    return $med;
}

$biogesic = getOrCreateMedicine('Biogesic', 'Paracetamol', '500mg', 'Fever & Pain Relief', 'Analgesics', 'Antipyretic', false);
$amoxil   = getOrCreateMedicine('Amoxil', 'Amoxicillin', '500mg', 'Bacterial Infections', 'Antibiotics', 'Penicillin', true);
$advil    = getOrCreateMedicine('Advil', 'Ibuprofen', '200mg', 'Pain & Inflammation Relief', 'NSAIDs', 'Analgesic', false);
$valium   = getOrCreateMedicine('Valium', 'Diazepam', '10mg', 'Anxiety & Muscle Spasms', 'Anxiolytics', 'Benzodiazepine', true);
$ponstan  = getOrCreateMedicine('Ponstan', 'Mefenamic Acid', '500mg', 'Severe Pain Relief', 'Analgesics', 'NSAID', false);

$pharmacies = Pharmacy::where('is_active', true)->get();

foreach ($pharmacies as $p) {
    echo "Seeding inventory for Pharmacy ID: {$p->id} ({$p->name})...\n";
    
    // Seed Biogesic / Paracetamol
    DB::table('pharmacy_medicine')->updateOrInsert(
        ['pharmacy_id' => $p->id, 'medicine_id' => $biogesic->id],
        ['quantity_on_hand' => 150, 'selling_price' => 5.00, 'reorder_level' => 10, 'is_available' => true, 'updated_at' => now()]
    );
    
    // Seed Amoxil / Amoxicillin
    DB::table('pharmacy_medicine')->updateOrInsert(
        ['pharmacy_id' => $p->id, 'medicine_id' => $amoxil->id],
        ['quantity_on_hand' => 45, 'selling_price' => 12.00, 'reorder_level' => 5, 'is_available' => true, 'updated_at' => now()]
    );
    
    // Seed Advil / Ibuprofen
    DB::table('pharmacy_medicine')->updateOrInsert(
        ['pharmacy_id' => $p->id, 'medicine_id' => $advil->id],
        ['quantity_on_hand' => 80, 'selling_price' => 8.50, 'reorder_level' => 10, 'is_available' => true, 'updated_at' => now()]
    );
    
    // Seed Valium / Diazepam
    DB::table('pharmacy_medicine')->updateOrInsert(
        ['pharmacy_id' => $p->id, 'medicine_id' => $valium->id],
        ['quantity_on_hand' => 21, 'selling_price' => 100.00, 'reorder_level' => 5, 'is_available' => true, 'updated_at' => now()]
    );

    // Seed Ponstan / Mefenamic Acid
    DB::table('pharmacy_medicine')->updateOrInsert(
        ['pharmacy_id' => $p->id, 'medicine_id' => $ponstan->id],
        ['quantity_on_hand' => 60, 'selling_price' => 9.50, 'reorder_level' => 10, 'is_available' => true, 'updated_at' => now()]
    );
}

echo "SUCCESSFULLY SEEDED REAL INVENTORY FOR ALL PHARMACIES!\n";
