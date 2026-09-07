<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D5a: CSM survey archival PDF path (private storage).
     * Nullable — the survey is saved first; the PDF copy is generated
     * after the DB transaction commits (non-blocking).
     */
    public function up(): void
    {
        Schema::table('csm_surveys', function (Blueprint $table) {
            $table->string('pdf_path')->nullable()->after('suggestions');
        });
    }

    public function down(): void
    {
        Schema::table('csm_surveys', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });
    }
};