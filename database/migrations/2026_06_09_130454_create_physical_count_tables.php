<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->string('status')->default('Ongoing'); // Ongoing, Completed
            $table->string('scope_region');
            $table->string('scope_branch')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_physical_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('physical_count_sessions')->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained('inventory_assets', 'asset_id');
            $table->foreignId('counted_by')->constrained('users');
            $table->string('status'); // Present, Missing, Damaged
            $table->string('actual_location')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('counted_at')->useCurrent();

            $table->unique(['session_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_physical_counts');
        Schema::dropIfExists('physical_count_sessions');
    }
};
