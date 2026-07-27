<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;

class ExportInventoryAction
{
    /**
     * Export inventory assets to CSV.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function execute(\Illuminate\Http\Request $request)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $query = InventoryAsset::with('assignedUser');

        if ($user->canProcessSupply()) {
            InventoryScope::scopeAssetsToActor($query, $user);
        } else {
            $query->where('region', $user->region);
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(item_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $assets = $query->orderBy('created_at', 'desc')->get();

        $filename = 'inventory_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($assets) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'PAR No', 'Property No', 'Item Name', 'Serial No', 'Brand', 'Model',
                'Category', 'Status', 'Assigned To', 'Region', 'Branch', 'Office',
                'Department', 'Date Acquired', 'Warranty Expiration', 'Acquisition Cost',
                'End of Useful Life', 'Asset Notes',
            ]);

            foreach ($assets as $asset) {
                fputcsv($file, [
                    $asset->par_number,
                    $asset->property_number,
                    $asset->item_name,
                    $asset->serial_number,
                    $asset->brand,
                    $asset->model,
                    $asset->category,
                    $asset->status,
                    $asset->assignedUser?->full_name ?? 'Unassigned',
                    $asset->region,
                    $asset->branch,
                    $asset->office,
                    $asset->department,
                    $asset->date_acquired?->format('Y-m-d'),
                    $asset->warranty_expiration?->format('Y-m-d'),
                    $asset->acquisition_cost,
                    $asset->end_of_useful_life?->format('Y-m-d'),
                    $asset->asset_notes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}