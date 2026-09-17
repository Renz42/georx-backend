<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pharmacy_medicine', function (Blueprint $table) {
            $table->string('supplier')->nullable();
            $table->timestamp('last_restocked_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharmacy_medicine', function (Blueprint $table) {
            $table->dropColumn(['supplier', 'last_restocked_at']);
        });
    }
};
