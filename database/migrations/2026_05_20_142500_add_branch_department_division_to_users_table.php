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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'branch')) {
                $table->string('branch')->nullable()->after('region');
            }
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable()->after('office');
            }
            if (!Schema::hasColumn('users', 'division')) {
                $table->string('division')->nullable()->after('department');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['branch', 'department', 'division']);
        });
    }
};
