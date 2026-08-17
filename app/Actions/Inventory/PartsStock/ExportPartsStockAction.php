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
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function execute(Request $request, User $user)
    {
        if (! $user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $query = (new ListPartsStockAction)->buildQuery($request, $user);
        $parts = $query->orderBy('item_name')->get();

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
                'Status', 'Region', 'Branch',
            ]);

            foreach ($parts as $part) {
                fputcsv($file, [
                    $part->item_name,
                    $part->unit,
                    $part->category ?? '',
                    $part->on_hand_qty,
                    $part->reorder_level,
                    strtoupper($part->statusLevel()),
                    $part->region ?? '',
                    $part->branch ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}