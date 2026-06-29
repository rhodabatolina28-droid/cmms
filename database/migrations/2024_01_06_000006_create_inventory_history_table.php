<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->string('action'); // Asset Added, Asset Updated, etc.
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->unsignedBigInteger('previous_user_id')->nullable();
            $table->unsignedBigInteger('new_user_id')->nullable();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->foreign('asset_id')->references('asset_id')->on('inventory_assets')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('new_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_history');
    }
};
