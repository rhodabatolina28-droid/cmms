<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C (PR Receiving):
 * - delivered_at / delivered_by on purchase_requests → new 'delivered' status
 *   (distinct from legacy 'received' to avoid collision with old records).
 * - pr_attachments table → receipt / proof-of-purchase files.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('delivered_by')->nullable()->after('finalized_at');
            $table->timestamp('delivered_at')->nullable()->after('delivered_by');
        });

        Schema::create('pr_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_request_id');
            $table->string('filename');
            $table->string('filepath');
            $table->string('filetype', 127)->nullable();
            $table->string('label')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('purchase_request_id')
                ->references('id')->on('purchase_requests')
                ->cascadeOnDelete();
            $table->foreign('uploaded_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_attachments');
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['delivered_by', 'delivered_at']);
        });
    }
};
