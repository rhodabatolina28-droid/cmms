<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Option C Implementation: Introduce pm_cycles as a distinct entity.
 * Each "run" of a PM schedule is its own cycle record.
 * pm_division_schedules now belongs to a specific cycle — not the schedule globally.
 * This means:
 *  - Completed divisions from Cycle 1 never bleed into Cycle 2.
 *  - Full audit history is preserved (all past cycles are queryable).
 *  - COA can see: when each cycle started, ended, and which divisions were done.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Drop the old flat pm_division_schedules table
        Schema::dropIfExists('pm_division_schedules');

        // 2. Create pm_cycles — each record = one full PM campaign/cycle
        Schema::create('pm_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->onDelete('cascade');
            $table->unsignedInteger('cycle_number')->default(1); // 1st cycle, 2nd cycle, etc.
            $table->timestamp('started_at')->useCurrent();       // When generate was first triggered
            $table->timestamp('completed_at')->nullable();       // When all divisions finished
            $table->timestamps();

            $table->index(['pm_schedule_id', 'cycle_number']);
        });

        // 3. Recreate pm_division_schedules — now scoped to a pm_cycle
        Schema::create('pm_division_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_cycle_id')->constrained('pm_cycles')->onDelete('cascade');
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->onDelete('cascade');
            $table->string('division_name', 150);
            $table->date('last_completed_at')->nullable();   // Date this division's PM was done
            $table->date('next_scheduled_at')->nullable();   // Computed: last_completed_at + frequency

            $table->timestamps();
            $table->unique(['pm_cycle_id', 'division_name']); // One record per division per cycle
        });

        // 4. Add cycle_number tracking to pm_schedules so we know what cycle we're on
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('current_cycle_id')->nullable()->after('current_focus_division');
            $table->unsignedInteger('cycle_count')->default(0)->after('current_cycle_id'); // total cycles ever run
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->dropColumnIfExists('current_cycle_id');
            $table->dropColumnIfExists('cycle_count');
        });

        Schema::dropIfExists('pm_division_schedules');
        Schema::dropIfExists('pm_cycles');

        // Restore old flat pm_division_schedules
        Schema::create('pm_division_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->onDelete('cascade');
            $table->string('division_name', 150);
            $table->date('last_completed_at')->nullable();
            $table->date('next_scheduled_at')->nullable();
            $table->timestamps();
            $table->unique(['pm_schedule_id', 'division_name']);
        });

        Schema::enableForeignKeyConstraints();
    }
};
