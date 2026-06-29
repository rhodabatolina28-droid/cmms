<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Note: supply_officer role no longer exists. The ENUM is managed by the canonical
        // users migration and 2026_06_01_100000. This migration only adds assigned_to to requests.

        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'assigned_to')) {
                $table->unsignedBigInteger('assigned_to')->nullable()->after('user_id');
                $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
                $table->index('assigned_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'assigned_to')) {
                $table->dropForeign(['assigned_to']);
                $table->dropColumn('assigned_to');
            }
        });
    }
};
