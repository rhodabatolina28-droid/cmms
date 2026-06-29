<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->boolean('is_paused')->default(false)->after('is_active');
            $table->timestamp('paused_at')->nullable()->after('is_paused');
            $table->timestamp('cycle_stopped_at')->nullable()->after('paused_at');
            $table->string('current_focus_division', 100)->nullable()->after('cycle_stopped_at');
        });
    }

    public function down(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->dropColumn(['is_paused', 'paused_at', 'cycle_stopped_at', 'current_focus_division']);
        });
    }
};