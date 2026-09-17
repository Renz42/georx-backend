<?php

namespace Database\Seeders;

use App\Models\Pharmacy;
use App\Models\Medicine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $pharmacies = Pharmacy::all();
        $medicines = Medicine::all();

        // Updated to match the 'therapeutic_class' seeded in your MedicineSeeder
        $priceRanges = [
            'Analgesic' => [5, 15],
            'Cough & Cold' => [8, 25],
            'Antibiotic' => [15, 80],
            'NSAID' => [10, 30],
            'Antacid' => [5, 20],
            'Antihistamine' => [5, 20],
            'Antihypertensive' => [10, 50],
            'Vitamins' => [8, 35],
        ];

        foreach ($pharmacies as $pharmacy) {
            // Each pharmacy stocks 70-100% of the Master List
            $stockCount = rand(ceil($medicines->count() * 0.7), $medicines->count());
            $stockedMedicines = $medicines->random($stockCount);

            foreach ($stockedMedicines as $medicine) {
                // Determine price based on therapeutic class
                $range = $priceRanges[$medicine->therapeutic_class] ?? [10, 50];
                $basePrice = rand($range[0] * 100, $range[1] * 100) / 100;
                
                // Small price variation per pharmacy (+/- 15%)
                $priceVariation = $basePrice * (rand(-15, 15) / 100);
                $sellingPrice = round($basePrice + $priceVariation, 2);
                
                // Generate a purchase price (cost) that is 20-30% lower than selling price
                $purchasePrice = round($sellingPrice * (rand(70, 80) / 100), 2);

                $pharmacy->medicines()->attach($medicine->id, [
                    // ✅ Updated Column Names
                    'selling_price' => max(1, $sellingPrice),
                    'purchase_price' => $purchasePrice,
                    'quantity_on_hand' => rand(0, 150),
                    'reorder_level' => 15,
                    
                    // ✅ Added Batch and Expiry Data
                    'batch_number' => 'BN-' . strtoupper(Str::random(6)),
                    'expiration_date' => now()->addMonths(rand(-2, 24)), // Some already expired, most good
                    'storage_condition' => 'Store at room temperature not exceeding 30°C',
                    
                    'is_available' => rand(0, 10) > 1, // 90% chance available
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}