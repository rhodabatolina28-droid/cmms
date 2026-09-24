<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // BUG-NOTIF-FROM-2: WHO PERFORMED the action that produced this
            // notification (admin who assigned IT, IT/SA who requested parts,
            // the user who submitted) — drives the bell "From" name shown
            // beside the icon. NULL = system-generated or legacy row (those
            // keep the old message-derivation fallback).
            $table->unsignedBigInteger('sender_id')->nullable()->after('request_id');
            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropColumn('sender_id');
        });
    }
};