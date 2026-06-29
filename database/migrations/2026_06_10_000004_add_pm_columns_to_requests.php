<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false)->after('detail_id');
            $table->foreignId('pm_schedule_id')->nullable()->constrained('pm_schedules')->nullOnDelete()->after('is_auto_generated');
            $table->unsignedBigInteger('asset_id')->nullable()->after('pm_schedule_id');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['is_auto_generated', 'pm_schedule_id', 'asset_id']);
        });
    }
};
