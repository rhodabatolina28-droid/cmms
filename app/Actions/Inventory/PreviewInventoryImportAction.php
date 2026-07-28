<?php

namespace App\Actions\Inventory;

use App\Services\InventoryCsvImportService;
use App\Http\Requests\PreviewImportRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreviewInventoryImportAction
{
    /**
     * Preview CSV import before committing.
     *
     * @param  \App\Http\Requests\PreviewImportRequest  $request
     * @param  \App\Services\InventoryCsvImportService  $importer
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(PreviewImportRequest $request, InventoryCsvImportService $importer)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory import is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validated();

        $token = (string) Str::uuid();
        $relativePath = $validated['file']->storeAs('inventory-imports', "{$token}.csv", 'local');
        $absolutePath = Storage::disk('local')->path($relativePath);

        $preview = $importer->preview($absolutePath, $user);
        session(["inventory_import_{$token}" => $relativePath]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'summary' => $preview['summary'],
            'items' => array_slice($preview['items'], 0, 25),
            'preview_limit' => 25,
        ]);
    }
}
