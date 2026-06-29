<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('schedule_name')->unique();
            $table->json('asset_categories');
            $table->string('division_filter', 50)->nullable()->comment('Null = all divisions');
            $table->string('frequency', 50); // Monthly, Quarterly, Semi-annual, Annual
            $table->date('last_generated_date')->nullable();
            $table->date('next_scheduled_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_schedules');
    }
};
