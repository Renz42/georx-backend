<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_logs') && !Schema::hasColumn('search_logs', 'search_type')) {
            Schema::table('search_logs', function (Blueprint $table) {
                $table->string('search_type')->default('medicine')->after('term');
            });
        }

        if (Schema::hasTable('inventory_batches') && !Schema::hasColumn('inventory_batches', 'status')) {
            Schema::table('inventory_batches', function (Blueprint $table) {
                $table->string('status')->default('active')->after('quantity');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'courier_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('courier_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('search_logs') && Schema::hasColumn('search_logs', 'search_type')) {
            Schema::table('search_logs', function (Blueprint $table) {
                $table->dropColumn('search_type');
            });
        }
    }
};
