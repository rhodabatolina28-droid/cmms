<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_number')->unique(); // REQ-2026-001, PM-2026-001
            $table->enum('type', ['ICT', 'Preventive Maintenance']); // Request type
            $table->string('requestor_name');
            $table->text('description')->nullable();
            $table->string('region')->nullable(); // For filtering by region
            $table->string('office')->nullable();
            $table->string('status')->default('Pending');
            $table->unsignedBigInteger('detail_id')->nullable(); // FK to repair_requests or preventive_maintenance
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('request_number');
            $table->index('type');
            $table->index('status');
            $table->index('region');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
