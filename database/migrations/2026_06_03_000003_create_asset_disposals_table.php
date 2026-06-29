<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'For Disposal' to inventory_assets status enum
        \DB::statement("ALTER TABLE inventory_assets MODIFY COLUMN status ENUM('Active','Spare','Defective','For Repair','For Disposal','Scrapped') NOT NULL DEFAULT 'Spare'");

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->string('disposal_no')->unique();
            $table->unsignedBigInteger('asset_id');
            $table->string('reason');                   // Irreparable, End of Useful Life, Obsolete, Lost, etc.
            $table->string('disposal_method');          // Auction, Donation, Waste/Scrap, Turnover to COA
            $table->text('condition_notes')->nullable(); // IT's findings or supply admin notes
            $table->unsignedBigInteger('requested_by'); // Supply Admin
            $table->unsignedBigInteger('reviewed_by')->nullable(); // Super Admin
            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('rejection_reason')->nullable();
            $table->string('previous_asset_status')->nullable(); // to restore if rejected
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('asset_id')->references('asset_id')->on('inventory_assets')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['asset_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
        \DB::statement("ALTER TABLE inventory_assets MODIFY COLUMN status ENUM('Active','Spare','Defective','For Repair','Scrapped') NOT NULL DEFAULT 'Spare'");
    }
};
