<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patch migration: Adds columns that exist in the production database
 * but were never captured in a migration file.
 * Safe to run multiple times (all changes are wrapped in hasColumn checks).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── inventory_assets: missing columns ────────────────────────────────
        Schema::table('inventory_assets', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_assets', 'date_acquired')) {
                $table->date('date_acquired')->nullable()->after('date_added');
            }
            if (!Schema::hasColumn('inventory_assets', 'warranty_expiration')) {
                $table->date('warranty_expiration')->nullable()->after('date_acquired');
            }
            if (!Schema::hasColumn('inventory_assets', 'qr_code')) {
                $table->text('qr_code')->nullable();
            }
            // Make region nullable (it was required in the original migration but
            // is optional in practice — assets without a region are valid in test)
            // We do this by changing the column to nullable.
            // Note: on a fresh test DB, the column may already exist as NOT NULL.
        });

        // Make region nullable safely via raw SQL for MySQL
        if (Schema::hasColumn('inventory_assets', 'region')) {
            \Illuminate\Support\Facades\DB::statement(
                'ALTER TABLE inventory_assets MODIFY COLUMN region VARCHAR(255) NULL'
            );
        }

        // ── pm_schedules: missing cycle tracking columns ─────────────────────
        Schema::table('pm_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_schedules', 'current_cycle_id')) {
                $table->unsignedBigInteger('current_cycle_id')->nullable()->after('current_focus_division');
            }
            if (!Schema::hasColumn('pm_schedules', 'cycle_count')) {
                $table->unsignedInteger('cycle_count')->default(0)->after('current_cycle_id');
            }
            if (!Schema::hasColumn('pm_schedules', 'last_generated_date')) {
                $table->timestamp('last_generated_date')->nullable();
            }
            if (!Schema::hasColumn('pm_schedules', 'division_filter')) {
                $table->string('division_filter', 50)->nullable();
            }
            if (!Schema::hasColumn('pm_schedules', 'next_scheduled_date')) {
                $table->date('next_scheduled_date')->nullable();
            }
        });

        // ── pm_division_schedules: missing pm_cycle_id column ────────────────
        Schema::table('pm_division_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('pm_division_schedules', 'pm_cycle_id')) {
                $table->unsignedBigInteger('pm_cycle_id')->nullable()->after('pm_schedule_id');
            }
        });

        // Drop the unique constraint on (pm_schedule_id, division_name) if it exists,
        // since a division can appear in multiple cycles.
        try {
            Schema::table('pm_division_schedules', function (Blueprint $table) {
                $table->dropUnique(['pm_schedule_id', 'division_name']);
            });
        } catch (\Exception $e) {
            // Constraint may not exist — safe to ignore
        }

        // Add composite unique on (pm_cycle_id, division_name) instead
        if (Schema::hasColumn('pm_division_schedules', 'pm_cycle_id')) {
            try {
                Schema::table('pm_division_schedules', function (Blueprint $table) {
                    $table->unique(['pm_cycle_id', 'division_name']);
                });
            } catch (\Exception $e) {
                // Already exists — ignore
            }
        }
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $columns = ['date_acquired', 'warranty_expiration', 'qr_code'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('inventory_assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('pm_schedules', function (Blueprint $table) {
            $columns = ['current_cycle_id', 'cycle_count', 'last_generated_date', 'division_filter', 'next_scheduled_date'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('pm_schedules', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('pm_division_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('pm_division_schedules', 'pm_cycle_id')) {
                $table->dropColumn('pm_cycle_id');
            }
        });
    }
};
