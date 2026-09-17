<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            // Check if the column exists before adding it to prevent crashes!
            if (!Schema::hasColumn('medicines', 'primary_use')) {
                $table->text('primary_use')->nullable()->after('drug_category');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            if (Schema::hasColumn('medicines', 'primary_use')) {
                $table->dropColumn('primary_use');
            }
        });
    }
};