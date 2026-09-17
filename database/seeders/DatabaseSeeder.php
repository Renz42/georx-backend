<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Run your existing seeders for data
        // Make sure these seeders use the new column names we created!
        $this->call([
            PharmacySeeder::class,
            MedicineSeeder::class,
            InventorySeeder::class,
        ]);

        // 2. Create the Super Admin (The 3rd Party Controller)
        // This is the account you will use to Approve or Reject pharmacies
        User::create([
            'name' => 'Super Admin',
            'email' => 'admin@medicine.com',
            'password' => Hash::make('admin123'),
            'role' => User::ROLE_SUPER_ADMIN,
        ]);

        // 3. Create a Standard Test User (The Patient/Customer)
        User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => User::ROLE_USER,
        ]);
        
        // Note: If you want to create a Pharmacy Admin, it's best to do that 
        // through the registration form to ensure the pharmacy_id is linked correctly.
    }
}