<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            // D7a: relative path (private disk 'local') of the archived final
            // Delivery Confirmation PDF. One archive per PR — set once when the
            // PR reaches the delivered status.
            $table->string('archive_pdf_path')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn('archive_pdf_path');
        });
    }
};
