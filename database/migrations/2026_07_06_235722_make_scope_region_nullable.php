<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('physical_count_sessions', function (Blueprint $table) {
            $table->string('scope_region')->nullable()->change();
            $table->string('scope_branch')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('physical_count_sessions', function (Blueprint $table) {
            $table->string('scope_region')->nullable(false)->change();
            $table->string('scope_branch')->nullable(false)->change();
        });
    }
};
