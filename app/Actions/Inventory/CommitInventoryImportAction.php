<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Http\Requests\CommitImportRequest;
use App\Services\InventoryCsvImportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommitInventoryImportAction
{
    /**
     * Commit a CSV import of inventory assets.
     *
     * @param  \App\Http\Requests\CommitImportRequest  $request
     * @param  \App\Services\InventoryCsvImportService  $importer
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(CommitImportRequest $request, InventoryCsvImportService $importer)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory import is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validated();

        $token = $validated['token'];
        $relativePath = session("inventory_import_{$token}");
        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Import preview expired. Upload the CSV again.',
            ], 422);
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $rows = $importer->importableRows($absolutePath, $user);

        $created = 0;
        $setRows = 0;
        // Maps shared PAR numbers to their parent asset_id so child components
        // (e.g. a split-out Monitor) can resolve their parent_asset_id as the
        // batch is committed.
        $parentByPar = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $rowWasSet = false;
                foreach ($row['records'] as $recordData) {
                    $isComponent = !empty($recordData['_is_component']);
                    unset($recordData['_is_component']);

                    if ($isComponent) {
                        $parKey = strtoupper(trim($recordData['par_number'] ?? ''));
                        if ($parKey && isset($parentByPar[$parKey])) {
                            $recordData['parent_asset_id'] = $parentByPar[$parKey];
                        }
                        $rowWasSet = true;
                    }

                    $asset = InventoryAsset::create($recordData);

                    // Remember the parent for any sibling components sharing this PAR.
                    if (!$isComponent) {
                        $parKey = strtoupper(trim($asset->par_number ?? ''));
                        if ($parKey) {
                            $parentByPar[$parKey] = $asset->asset_id;
                        }
                    }

                    InventoryHistory::create([
                        'asset_id' => $asset->asset_id,
                        'action' => $isComponent
                            ? ($asset->assigned_to_user ? 'Set Component Imported & Assigned' : 'Set Component Imported')
                            : ($asset->assigned_to_user ? 'Asset Imported & Assigned' : 'Asset Imported'),
                        'performed_by' => $user->id,
                        'new_user_id' => $asset->assigned_to_user,
                        'new_status' => $asset->status,
                        'remarks' => 'Imported from ICT CSV. Original responsible officer: ' . ($row['responsible_officer_raw'] ?: 'N/A'),
                    ]);

                    $created++;
                }

                if ($rowWasSet) {
                    $setRows++;
                }
            }

            AuditLog::log(
                'Imported Inventory CSV',
                'Inventory',
                "Imported {$created} ICT asset record(s)" . ($setRows ? " ({$setRows} split into set components)" : '') . " from CSV.",
                $user->office ?? $user->branch ?? 'Inventory'
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }

        Storage::disk('local')->delete($relativePath);
        session()->forget("inventory_import_{$token}");

        return response()->json([
            'success' => true,
            'message' => "Imported {$created} asset record(s)" . ($setRows ? " across {$setRows} set(s)" : '') . ".",
            'created' => $created,
        ]);
    }
}