<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase A of the PR lifecycle completion plan (docs/PR_DOCUMENT_FLOW.md):
     * Invisible job order linkage. A PR raised from a parts requisition
     * silently inherits the requisition's job order ticket so the purchased
     * part stays traceable back to its asset + custodian. No UI anywhere.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('request_id')->nullable()->after('requisition_id');
            $table->foreign('request_id')->references('id')->on('requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};