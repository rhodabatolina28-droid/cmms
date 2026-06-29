<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('asset_disposals');
    }

    public function down(): void
    {
        // Restore the table if needed (basic structure only — data is gone)
        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->string('disposal_no')->nullable();
            $table->unsignedBigInteger('asset_id');
            $table->string('reason')->nullable();
            $table->string('disposal_method')->nullable();
            $table->text('condition_notes')->nullable();
            $table->date('inspection_date')->nullable();
            $table->text('it_findings')->nullable();
            $table->unsignedBigInteger('linked_request_id')->nullable();
            $table->decimal('book_value', 12, 2)->nullable();
            $table->string('head_of_agency')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('status')->default('IIRUP Created');
            $table->string('previous_asset_status')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('iirup_signed_at')->nullable();
            $table->date('actual_disposal_date')->nullable();
            $table->string('disposal_witnessed_by')->nullable();
            $table->timestamp('disposal_confirmed_at')->nullable();
            $table->string('wmr_no')->nullable();
            $table->timestamp('wmr_generated_at')->nullable();
            $table->timestamps();
        });
    }
};
