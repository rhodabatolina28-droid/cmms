<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appendix 60 form fields for PR documents (2026-08-25):
     * fund_cluster, responsibility_center, office_unit.
     */
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->string('fund_cluster', 64)->nullable()->after('total_amount');
            $table->string('responsibility_center', 64)->nullable()->after('fund_cluster');
            $table->string('office_unit', 160)->nullable()->after('responsibility_center');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['fund_cluster', 'responsibility_center', 'office_unit']);
        });
    }
};
