<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('email');
            $table->unsignedBigInteger('pharmacy_id')->nullable()->after('role');
            $table->string('phone', 20)->nullable()->after('name');
            
            $table->foreign('pharmacy_id')->references('id')->on('pharmacies')->onDelete('set null');
        });

        // Create default super admin
        DB::table('users')->insert([
            'name' => 'Super Admin',
            'email' => 'admin@medicinelocator.com',
            'phone' => '09171234567',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
            'pharmacy_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'admin@medicinelocator.com')->delete();
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['pharmacy_id']);
            $table->dropColumn(['role', 'pharmacy_id', 'phone']);
        });
    }
};
