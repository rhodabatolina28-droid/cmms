<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (!Schema::hasColumn('requests', 'division_admin_review_status')) {
                $table->string('division_admin_review_status', 50)->nullable()->after('status');
                $table->text('division_admin_notes')->nullable()->after('division_admin_review_status');
                $table->unsignedBigInteger('reviewed_by_admin_id')->nullable()->after('division_admin_notes');
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_admin_id');

                $table->foreign('reviewed_by_admin_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            if (Schema::hasColumn('requests', 'reviewed_by_admin_id')) {
                $table->dropForeign(['reviewed_by_admin_id']);
            }
            $table->dropColumn([
                'division_admin_review_status',
                'division_admin_notes',
                'reviewed_by_admin_id',
                'reviewed_at'
            ]);
        });
    }
};
