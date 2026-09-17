<?php

namespace Database\Seeders;

use App\Models\Medicine; // ✅ Import the model correctly
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MedicineSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Use the imported class directly to avoid namespace errors
        Medicine::query()->delete(); 
        
        $medicines = [
            // Pain Relief
            ['brand_name' => 'Biogesic', 'generic_name' => 'Paracetamol', 'strength' => '500mg', 'dosage_form' => 'tablet', 'drug_category' => 'OTC', 'prescription_required' => false],
            ['brand_name' => 'Dolfenal', 'generic_name' => 'Mefenamic Acid', 'strength' => '500mg', 'dosage_form' => 'capsule', 'drug_category' => 'OTC', 'prescription_required' => false],
            ['brand_name' => 'Advil', 'generic_name' => 'Ibuprofen', 'strength' => '200mg', 'dosage_form' => 'tablet', 'drug_category' => 'OTC', 'prescription_required' => false],
            
            // Cough & Cold
            ['brand_name' => 'Neozep', 'generic_name' => 'Phenylephrine + Chlorphenamine + Paracetamol', 'strength' => '10mg/2mg/500mg', 'dosage_form' => 'tablet', 'drug_category' => 'OTC', 'prescription_required' => false],
            ['brand_name' => 'Solmux', 'generic_name' => 'Carbocisteine', 'strength' => '500mg', 'dosage_form' => 'capsule', 'drug_category' => 'OTC', 'prescription_required' => false],
            
            // Antibiotics
            ['brand_name' => 'Amoxicillin', 'generic_name' => 'Amoxicillin', 'strength' => '500mg', 'dosage_form' => 'capsule', 'drug_category' => 'Prescription', 'prescription_required' => true],
            ['brand_name' => 'Augmentin', 'generic_name' => 'Amoxicillin + Clavulanic Acid', 'strength' => '625mg', 'dosage_form' => 'tablet', 'drug_category' => 'Prescription', 'prescription_required' => true],
            
            // Gastrointestinal
            ['brand_name' => 'Imodium', 'generic_name' => 'Loperamide', 'strength' => '2mg', 'dosage_form' => 'capsule', 'drug_category' => 'OTC', 'prescription_required' => false],
            ['brand_name' => 'Omeprazole', 'generic_name' => 'Omeprazole', 'strength' => '20mg', 'dosage_form' => 'capsule', 'drug_category' => 'OTC', 'prescription_required' => false],
            
            // Blood Pressure
            ['brand_name' => 'Losartan', 'generic_name' => 'Losartan Potassium', 'strength' => '50mg', 'dosage_form' => 'tablet', 'drug_category' => 'Prescription', 'prescription_required' => true],
        ];

        foreach ($medicines as $medicine) {
            Medicine::create([
                'brand_name' => $medicine['brand_name'],
                'generic_name' => $medicine['generic_name'],
                'strength' => $medicine['strength'],
                'dosage_form' => $medicine['dosage_form'],
                'drug_category' => $medicine['drug_category'],
                'prescription_required' => $medicine['prescription_required'],
                // ✅ Generate slug using the new field names
                'slug' => Str::slug($medicine['brand_name'] . '-' . $medicine['strength'] . '-' . Str::random(3)),
            ]);
        }
    }
}