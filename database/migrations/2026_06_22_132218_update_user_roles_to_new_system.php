<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')->where('role', 'user')->update(['role' => 'customer']);
        DB::table('users')->where('role', 'pharmacy_admin')->update(['role' => 'pharmacy_owner']);
        DB::table('users')->where('role', 'super_admin')->update(['role' => 'administrator']);
        DB::table('users')->where('role', 'courier_admin')->update(['role' => 'delivery_partner']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'customer')->update(['role' => 'user']);
        DB::table('users')->where('role', 'pharmacy_owner')->update(['role' => 'pharmacy_admin']);
        DB::table('users')->where('role', 'administrator')->update(['role' => 'super_admin']);
        DB::table('users')->where('role', 'delivery_partner')->update(['role' => 'courier_admin']);
    }
};
