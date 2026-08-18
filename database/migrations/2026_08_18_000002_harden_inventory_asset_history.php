<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_history', function (Blueprint $table) {
            $table->index('previous_user_id');
            $table->foreign('previous_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_history', function (Blueprint $table) {
            $table->dropForeign(['previous_user_id']);
            $table->dropIndex(['previous_user_id']);
        });
    }
};
