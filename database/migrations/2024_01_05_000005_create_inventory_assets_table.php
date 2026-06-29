<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_assets', function (Blueprint $table) {
            $table->id('asset_id');
            $table->string('category'); // Desktop, Laptop, Printer, etc.
            $table->string('item_name');
            $table->string('serial_number')->unique()->nullable();
            $table->text('specifications')->nullable();
            $table->unsignedBigInteger('assigned_to_user')->nullable(); // FK to users
            $table->string('region'); // NCR, Region I, etc.
            $table->enum('status', ['Active', 'Spare', 'Defective', 'For Repair', 'Scrapped'])->default('Spare');
            $table->timestamp('date_added')->useCurrent();
            $table->timestamps();
            
            $table->foreign('assigned_to_user')->references('id')->on('users')->onDelete('set null');
            $table->index('serial_number');
            $table->index('region');
            $table->index('status');
            $table->index('assigned_to_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_assets');
    }
};
