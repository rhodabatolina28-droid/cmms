<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Disable FK checks so we can freely drop and recreate pm_schedules
        Schema::disableForeignKeyConstraints();

        // Step 1: Drop old pm_schedules and related division table if exists
        Schema::dropIfExists('pm_division_schedules');
        Schema::dropIfExists('pm_schedules');

        // Step 2: Recreate pm_schedules — clean, no deprecated columns
        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_name')->unique();
            $table->string('frequency', 50);             // Monthly, Quarterly, Semi-annual, Annual
            $table->json('asset_categories')->nullable(); // Filter by asset type (Desktop, Laptop, etc.)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_paused')->default(false);
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cycle_stopped_at')->nullable();
            $table->string('current_focus_division', 150)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });

        // Step 3: Create new pm_division_schedules table
        Schema::create('pm_division_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->onDelete('cascade');
            $table->string('division_name', 150);
            $table->date('last_completed_at')->nullable(); // Actual date division PM was completed
            $table->date('next_scheduled_at')->nullable(); // Auto-computed: last_completed_at + frequency
            $table->timestamps();

            $table->unique(['pm_schedule_id', 'division_name']);
        });

        // Re-enable FK checks
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_division_schedules');
        Schema::dropIfExists('pm_schedules');
    }
};
