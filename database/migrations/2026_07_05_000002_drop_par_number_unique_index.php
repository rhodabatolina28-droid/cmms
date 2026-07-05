<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the unique constraint on par_number in inventory_assets.
 *
 * Rationale (COA compliance):
 *  A government PAR (Property Acknowledgement Receipt) may list several
 *  accountable articles on a single numbered form. When a "Complete Set"
 *  CSV row is split into a CPU parent and a Monitor child, both assets
 *  legitimately share the same PAR number — they appeared on the same form.
 *
 *  Uniqueness was enforced DB-side in the original migration but was always
 *  intended to be a PHP-layer check (see InventoryCsvImportService::preview).
 *  The DB unique index blocks set-splitting at INSERT time and must be removed.
 *
 *  The non-unique regular index (par_number) is retained for query performance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            // Drop the UNIQUE constraint; keep a plain index for performance.
            $table->dropUnique('inventory_assets_par_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_assets', function (Blueprint $table) {
            // Restore the unique constraint (only safe if no duplicate PARs exist).
            $table->unique('par_number');
        });
    }
};
