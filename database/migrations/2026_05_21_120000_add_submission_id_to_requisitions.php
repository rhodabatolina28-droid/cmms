<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('requisitions')) {
            return;
        }

        Schema::table('requisitions', function (Blueprint $table) {
            if (!Schema::hasColumn('requisitions', 'submission_id')) {
                $table->string('submission_id', 64)->nullable()->after('requested_by');
                $table->unique(['request_id', 'requested_by', 'submission_id'], 'requisitions_idempotency_unique');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('requisitions')) {
            return;
        }

        Schema::table('requisitions', function (Blueprint $table) {
            if (Schema::hasColumn('requisitions', 'submission_id')) {
                $table->dropUnique('requisitions_idempotency_unique');
                $table->dropColumn('submission_id');
            }
        });
    }
};
