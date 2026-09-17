<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            // Add the status column
            $table->string('status')->default('pending')->after('is_approved');
        });

        // Auto-update your existing approved pharmacies so they don't disappear!
        DB::table('pharmacies')->where('is_approved', true)->update(['status' => 'approved']);
    }

    public function down(): void
    {
        Schema::table('pharmacies', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};