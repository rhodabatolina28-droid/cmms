<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->string('par_number')
                  ->unique()
                  ->nullable()
                  ->after('property_number')
                  ->comment('Property Acquisition Receipt No (PAR-YYYY-####)');
            $table->index('par_number');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropIndex(['par_number']);
            $table->dropColumn('par_number');
        });
    }
};
