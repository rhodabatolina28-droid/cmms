<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy workflow cleanup (2026-08-25):
     * The old internal approval/receive/cancel workflow was removed. Before
     * dropping its columns, archive any recorded legacy data into `remarks`
     * so historical information (e.g. PR-2026-0001) is preserved.
     */
    public function up(): void
    {
        $rows = DB::table('purchase_requests')->get();

        foreach ($rows as $row) {
            $bits = [];

            if (!empty($row->approved_at) || !empty($row->approved_by)) {
                $approver = $row->approved_by ? DB::table('users')->find($row->approved_by)?->full_name : null;
                $bits[] = '[Legacy: approved' . ($approver ? ' by ' . $approver : '') . ($row->approved_at ? ' on ' . $row->approved_at : '') . ']';
            }
            if (!empty($row->received_at) || !empty($row->received_by)) {
                $receiver = $row->received_by ? DB::table('users')->find($row->received_by)?->full_name : null;
                $bits[] = '[Legacy: received' . ($receiver ? ' by ' . $receiver : '') . ($row->received_at ? ' on ' . $row->received_at : '') . ']';
            }
            if (!empty($row->cancelled_at) || !empty($row->cancelled_by)) {
                $canceller = $row->cancelled_by ? DB::table('users')->find($row->cancelled_by)?->full_name : null;
                $bits[] = '[Legacy: cancelled' . ($canceller ? ' by ' . $canceller : '') . ($row->cancelled_at ? ' on ' . $row->cancelled_at : '') . ']';
            }

            if ($bits !== []) {
                $prefix = implode(' ', $bits);
                $existing = trim((string) $row->remarks);
                DB::table('purchase_requests')
                    ->where('id', $row->id)
                    ->update(['remarks' => $existing !== '' ? $prefix . ' ' . $existing : $prefix]);
            }
        }

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['received_by']);
            $table->dropForeign(['cancelled_by']);
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropColumn([
                'approved_by',
                'approved_at',
                'received_by',
                'received_at',
                'cancelled_by',
                'cancelled_at',
            ]);
        });
    }

    public function down(): void
    {
        // Columns are not restored — the legacy workflow is retired.
    }
};
