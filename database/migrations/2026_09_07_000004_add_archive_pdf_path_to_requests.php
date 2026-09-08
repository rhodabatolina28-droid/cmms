<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D6: auto-archived final PDF copy per ticket (ICT/PM), generated on
     * completion. Path points to the private disk copy:
     *   ict-pdfs/{year}/{Month}/ARCH-ICT-{number}.pdf
     *   pm-pdfs/{year}/{Month}/ARCH-PM-{number}.pdf
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->string('archive_pdf_path')->nullable()->after('downtime_duration');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('archive_pdf_path');
        });
    }
};