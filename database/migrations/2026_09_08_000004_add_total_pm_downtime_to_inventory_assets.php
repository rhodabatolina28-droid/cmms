<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * X1 (Gov-Option-B): PM maintenance downtime gets its own bucket.
     * total_downtime    = ICT/repair breakdown downtime only (SIRA)
     * total_pm_downtime = Preventive Maintenance servicing duration only (Servicio)
     * PM is scheduled servicing — NOT failure downtime — so it is exposed
     * separately instead of inflating the failure total.
     */
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->integer('total_pm_downtime')->default(0)->after('total_downtime'); // minutes, PM-only
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn('total_pm_downtime');
        });
    }
};
