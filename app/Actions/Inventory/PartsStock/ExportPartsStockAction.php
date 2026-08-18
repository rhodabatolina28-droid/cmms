<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\User;
use Illuminate\Http\Request;

class ExportPartsStockAction
{
    /**
     * CSV export ng parts & consumables — gaya ng ExportInventoryAction.
     * Iginagalang ang org scoping + filters (search/category/status).
     *
     * Serialized parts: isang CSV row bawat unit (serialized unit), para makita
     * ang bawat piraso na may serial / property / custodian. Para sa part na
     * walang unit records, may isang row pa rin (blangko ang unit columns) para
     * hindi mawala ang stock item sa masterlist.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function execute(Request $request, User $user)
    {
        if (! $user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $query = (new ListPartsStockAction)->buildQuery($request, $user);
        $parts = $query
            ->with([
                'units.issuedTo:id,full_name',
                'units.asset:id,item_name',
                'units.request:id,request_number',
            ])
            ->orderBy('item_name')
            ->get();

        $filename = 'parts_stock_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($parts) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF"); // BOM para sa Excel

            fputcsv($file, [
                'Item Name', 'Unit', 'Category', 'On-hand Qty', 'Reorder Level',
                'Part Status', 'Region', 'Branch',
                'Serial No', 'Property No', 'Unit Value', 'Unit Status',
                'Issued To', 'Asset', 'Request',
            ]);

            $emptyUnitColumns = ['', '', '', '', '', '', ''];

            foreach ($parts as $part) {
                $units = $part->units ?? collect();

                if ($units->isEmpty()) {
                    fputcsv($file, array_merge([
                        $part->item_name,
                        $part->unit,
                        $part->category ?? '',
                        $part->on_hand_qty,
                        $part->reorder_level,
                        strtoupper($part->statusLevel()),
                        $part->region ?? '',
                        $part->branch ?? '',
                    ], $emptyUnitColumns));
                    continue;
                }

                foreach ($units as $u) {
                    fputcsv($file, [
                        $part->item_name,
                        $part->unit,
                        $part->category ?? '',
                        $part->on_hand_qty,
                        $part->reorder_level,
                        strtoupper($part->statusLevel()),
                        $part->region ?? '',
                        $part->branch ?? '',
                        $u->serial_number,
                        $u->property_number,
                        $u->unit_value !== null ? number_format((float) $u->unit_value, 2) : '',
                        strtoupper($u->status),
                        $u->issuedTo?->full_name ?? ($u->issuedTo?->name ?? ''),
                        $u->asset?->item_name ?? ($u->asset_id ? (string) $u->asset_id : ''),
                        $u->request?->request_number ?? ($u->request_id ? (string) $u->request_id : ''),
                    ]);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}