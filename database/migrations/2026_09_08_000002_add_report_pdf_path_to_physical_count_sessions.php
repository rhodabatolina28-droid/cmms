<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('physical_count_sessions', function (Blueprint $table) {
            // D7b: relative path (private disk 'local') of the archived final
            // Physical Count Report PDF. One archive per session — set once
            // when the session is completed.
            $table->string('report_pdf_path')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('physical_count_sessions', function (Blueprint $table) {
            $table->dropColumn('report_pdf_path');
        });
    }
};
