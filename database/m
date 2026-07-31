<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->integer('total_downtime')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn('total_downtime');
        });
    }
};
