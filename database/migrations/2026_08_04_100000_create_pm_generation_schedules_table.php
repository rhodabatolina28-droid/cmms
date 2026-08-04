<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_generation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->onDelete('cascade');
            $table->date('scheduled_date');
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('Pending');
            $table->text('remarks')->nullable();
            $table->unsignedInteger('estimated_asset_count')->nullable();
            $table->unsignedInteger('generated_count')->nullable();
            $table->string('division_filter_snapshot', 50)->nullable();
            $table->string('generated_division', 150)->nullable();
            $table->unsignedBigInteger('pm_cycle_id')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_date']);
            $table->index('pm_schedule_id');
            $table->index('generated_by');
            $table->unique(['pm_schedule_id', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_generation_schedules');
    }
};