<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->timestamp('downtime_start')->nullable()->after('status');
            $table->timestamp('downtime_end')->nullable()->after('downtime_start');
            $table->integer('downtime_duration')->nullable()->after('downtime_end'); // in minutes
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn(['downtime_start', 'downtime_end', 'downtime_duration']);
        });
    }
};
