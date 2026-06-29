<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->date('last_pm_date')->nullable()->after('total_maintenance_cost');
            $table->date('next_pm_due_date')->nullable()->after('last_pm_date');
            $table->foreignId('pm_schedule_id')->nullable()->constrained('pm_schedules')->nullOnDelete()->after('next_pm_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn(['last_pm_date', 'next_pm_due_date', 'pm_schedule_id']);
        });
    }
};
