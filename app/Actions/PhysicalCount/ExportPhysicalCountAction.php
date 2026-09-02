<?php

namespace App\Actions\PhysicalCount;

use App\Models\InventoryAsset;
use App\Models\Scopes\InventoryScope;
use App\Models\PhysicalCountSession;
use Illuminate\Support\Facades\Auth;

class ExportPhysicalCountAction
{
    /**
     * Export a physical count session as CSV.
     *
     * @param  int  $id
     * @return \Illuminate\Http\StreamedResponse
     */
    public function execute($id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $session = PhysicalCountSession::with(['startedBy', 'counts.asset.assignedUser', 'counts.countedBy'])
            ->findOrFail($id);

        if ($session->scope_region && $session->scope_region !== $user->region) {
            abort(403);
        }
        if ($user->branch && $session->scope_branch && $session->scope_branch !== $user->branch) {
            abort(403);
        }

        $allAssets = InventoryAsset::with('assignedUser');
        InventoryScope::scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')->get();

        $filename = 'physical-count-' . $session->id . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($session, $allAssets) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, ['PHYSICAL INVENTORY COUNT REPORT']);
            fputcsv($output, ['Session #', $session->id]);
            fputcsv($output, ['Region', $session->scope_region]);
            fputcsv($output, ['Branch', $session->scope_branch ?? 'All']);
            fputcsv($output, ['Started By', $session->startedBy->full_name ?? $session->startedBy->name ?? 'Unknown']);
            fputcsv($output, ['Date Started', $session->started_at->format('F j, Y g:i A')]);
            fputcsv($output, ['Status', $session->status]);
            if ($session->completed_at) {
                fputcsv($output, ['Date Completed', $session->completed_at->format('F j, Y g:i A')]);
            }
            fputcsv($output, []);

            $counted = $session->counts;
            fputcsv($output, ['Total Assets', $allAssets->count()]);
            fputcsv($output, ['Counted', $counted->count()]);
            fputcsv($output, ['Present', $counted->where('status', 'Present')->count()]);
            fputcsv($output, ['Missing', $counted->where('status', 'Missing')->count()]);
            fputcsv($output, ['Damaged', $counted->where('status', 'Damaged')->count()]);
            fputcsv($output, []);

            fputcsv($output, ['Asset ID', 'Item Name', 'Serial Number', 'PAR Number', 'Property Number', 'Category', 'Assigned To', 'Count Status', 'Counted By', 'Counted At', 'Remarks']);

            $countedByAsset = $session->counts->keyBy('asset_id');

            foreach ($allAssets as $asset) {
                $c = $countedByAsset->get($asset->asset_id);
                fputcsv($output, [
                    $asset->asset_id,
                    $asset->item_name,
                    $asset->serial_number ?? '',
                    $asset->par_number ?? '',
                    $asset->property_number ?? '',
                    $asset->category ?? '',
                    $asset->assignedUser ? ($asset->assignedUser->full_name ?? $asset->assignedUser->name ?? '') : '',
                    $c ? $c->status : 'Not Counted',
                    $c ? ($c->countedBy->full_name ?? $c->countedBy->name ?? '') : '',
                    $c ? $c->counted_at->format('Y-m-d H:i:s') : '',
                    $c ? ($c->remarks ?? '') : '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
