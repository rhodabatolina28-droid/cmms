<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('requests', 'linked_asset_id')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->unsignedBigInteger('linked_asset_id')->nullable()->after('detail_id');
                $table->foreign('linked_asset_id')
                    ->references('asset_id')
                    ->on('inventory_assets')
                    ->nullOnDelete();
            });
        }

        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('submission_id', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->json('items')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('requests')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->unique(['request_id', 'requested_by', 'submission_id'], 'requisitions_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisitions');

        if (Schema::hasColumn('requests', 'linked_asset_id')) {
            Schema::table('requests', function (Blueprint $table) {
                $table->dropForeign(['linked_asset_id']);
                $table->dropColumn('linked_asset_id');
            });
        }
    }
};
