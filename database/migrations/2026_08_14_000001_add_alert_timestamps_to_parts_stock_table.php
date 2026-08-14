<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Event-based low-stock alert flags (para hindi ma-spam araw-araw).
     *   low_notified_at      — naipadala na ang alerto kapag LOW
     *   critical_notified_at — naipadala na ang alerto kapag CRITICAL
     * Ni-reset ito kapag healthy na ulit ang item para makapag-alert ulit kung bababa muli.
     */
    public function up(): void
    {
        Schema::table('parts_stock', function (Blueprint $table) {
            $table->timestamp('low_notified_at')->nullable()->after('reorder_level');
            $table->timestamp('critical_notified_at')->nullable()->after('low_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('parts_stock', function (Blueprint $table) {
            $table->dropColumn(['low_notified_at', 'critical_notified_at']);
        });
    }
};