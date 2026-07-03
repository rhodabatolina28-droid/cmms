<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preventive_maintenance', function (Blueprint $table) {
            if (!Schema::hasColumn('preventive_maintenance', 'earphone_pno')) {
                $table->string('earphone_pno')->nullable()->after('earphone_model');
            }
        });
    }

    public function down(): void
    {
        Schema::table('preventive_maintenance', function (Blueprint $table) {
            if (Schema::hasColumn('preventive_maintenance', 'earphone_pno')) {
                $table->dropColumn('earphone_pno');
            }
        });
    }
};
