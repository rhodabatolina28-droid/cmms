<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            // D7c: relative path (private disk 'local') of the archived FINAL
            // PR form PDF (the document itself), separate from the Delivery
            // Confirmation archive (archive_pdf_path). A PR flows finalized → delivered,
            // and both documents deserve their own permanently stored copy.
            $table->string('pr_form_pdf_path')->nullable()->after('archive_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('pr_form_pdf_path');
        });
    }
};