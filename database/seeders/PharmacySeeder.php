<?php

namespace Database\Seeders;

use App\Models\Pharmacy; // ✅ Ensure the model is imported
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Clear existing pharmacies first to prevent duplicates
        Pharmacy::query()->delete();

        $pharmacies = [
            [
                'name' => 'Mercury Drug Lacson',
                'address' => 'Lacson Street, Bacolod City, Negros Occidental',
                'phone' => '034-433-1234',
                'latitude' => 10.6804,
                'longitude' => 122.9550,
                'owner_name' => 'Mercury Drug Corp',
                'description' => '24-hour pharmacy with complete medicine stocks',
                'operating_hours' => ['open' => '00:00', 'close' => '23:59', 'is_24hr' => true],
            ],
            [
                'name' => 'Rose Pharmacy SM City',
                'address' => 'SM City Bacolod, Reclamation Area, Bacolod City',
                'phone' => '034-435-5678',
                'latitude' => 10.6970,
                'longitude' => 122.9680,
                'owner_name' => 'Rose Pharmacy Inc',
                'description' => 'Located inside SM City Bacolod',
                'operating_hours' => ['open' => '10:00', 'close' => '21:00', 'is_24hr' => false],
            ],
            [
                'name' => 'Generika Drugstore Libertad',
                'address' => 'Libertad Street, Bacolod City, Negros Occidental',
                'phone' => '034-432-9012',
                'latitude' => 10.6765,
                'longitude' => 122.9509,
                'owner_name' => 'Generika Drugstore',
                'description' => 'Affordable generic medicines',
                'operating_hours' => ['open' => '08:00', 'close' => '20:00', 'is_24hr' => false],
            ],
            [
                'name' => 'Southstar Drug Robinsons',
                'address' => 'Robinsons Place Bacolod, Lacson Street',
                'phone' => '034-434-3456',
                'latitude' => 10.6832,
                'longitude' => 122.9575,
                'owner_name' => 'Southstar Drug Inc',
                'description' => 'Full-service pharmacy at Robinsons',
                'operating_hours' => ['open' => '10:00', 'close' => '21:00', 'is_24hr' => false],
            ],
            [
                'name' => 'TGP Araneta Street',
                'address' => 'Araneta Street, Bacolod City, Negros Occidental',
                'phone' => '034-431-7890',
                'latitude' => 10.6720,
                'longitude' => 122.9480,
                'owner_name' => 'The Generics Pharmacy',
                'description' => 'Quality generics at affordable prices',
                'operating_hours' => ['open' => '08:00', 'close' => '21:00', 'is_24hr' => false],
            ],
            [
                'name' => 'Watsons Ayala Malls Capitol Central',
                'address' => 'Ayala Malls Capitol Central, Bacolod City',
                'phone' => '034-436-2345',
                'latitude' => 10.6790,
                'longitude' => 122.9620,
                'owner_name' => 'Watsons Philippines',
                'description' => 'Health and beauty store with pharmacy',
                'operating_hours' => ['open' => '10:00', 'close' => '21:00', 'is_24hr' => false],
            ],
        ];

        foreach ($pharmacies as $pharmacy) {
            Pharmacy::create([
                ...$pharmacy,
                'slug' => Str::slug($pharmacy['name']) . '-' . Str::random(5),
                'is_active' => true,
                'is_approved' => true, // ✅ CRITICAL: Seeded pharmacies are pre-approved
            ]);
        }
    }
}