<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * D5a follow-up: the CSM form collects an optional email address —
     * store it so it can appear on the archived PDF copy.
     */
    public function up(): void
    {
        Schema::table('csm_surveys', function (Blueprint $table) {
            $table->string('email')->nullable()->after('sex');
        });
    }

    public function down(): void
    {
        Schema::table('csm_surveys', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};