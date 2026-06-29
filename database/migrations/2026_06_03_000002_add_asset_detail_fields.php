<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->string('property_number')->nullable()->after('serial_number');
            $table->string('brand')->nullable()->after('property_number');
            $table->string('model')->nullable()->after('brand');
            $table->decimal('acquisition_cost', 12, 2)->nullable()->after('model')->comment('Purchase cost in PHP');
            $table->date('end_of_useful_life')->nullable()->after('acquisition_cost')->comment('Expected end of serviceable life');
            $table->text('asset_notes')->nullable()->after('end_of_useful_life')->comment('General notes about the asset');
        });

        // Asset attachments table
        Schema::create('asset_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->string('filename');          // original filename
            $table->string('filepath');          // stored path
            $table->string('filetype')->nullable(); // mime type
            $table->string('label')->nullable();    // e.g. "Purchase Order", "Inspection Report"
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('asset_id')->on('inventory_assets')->onDelete('cascade');
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('set null');
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_attachments');
        Schema::table('inventory_assets', function (Blueprint $table) {
            $table->dropColumn(['property_number', 'brand', 'model', 'acquisition_cost', 'end_of_useful_life', 'asset_notes']);
        });
    }
};
