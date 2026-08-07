<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_schedules', 'assigned_it_id')) {
                $table->unsignedBigInteger('assigned_it_id')->nullable()->after('next_scheduled_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('pm_schedules', 'assigned_it_id')) {
                $table->dropColumn('assigned_it_id');
            }
        });
    }
};