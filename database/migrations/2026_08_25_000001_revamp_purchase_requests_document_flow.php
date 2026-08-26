<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PR Document flow revamp (2026-08-25):
     * The CMMS is a PR document creator + printer + history tracker.
     * Actual procurement happens outside the system (BAC/Procurement Office).
     *
     * New statuses: draft -> submitted -> finalized.
     * Legacy statuses (pending/approved/received/cancelled) remain readable.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('requested_by');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedBigInteger('finalized_by')->nullable()->after('created_by');
            $table->foreign('finalized_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('finalized_by');

            $table->text('purpose')->nullable()->after('items');
            $table->decimal('total_amount', 12, 2)->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['created_by', 'finalized_by', 'finalized_at', 'purpose', 'total_amount']);
        });
    }
};
