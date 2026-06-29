<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_schedule_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_schedule_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50); // generated, modified, deactivated
            $table->integer('generated_count')->default(0);
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_schedule_history');
    }
};
