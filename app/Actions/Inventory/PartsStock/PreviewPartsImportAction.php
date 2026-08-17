<?php

namespace App\Actions\Inventory\PartsStock;

use App\Services\PartsCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PreviewPartsImportAction
{
    /**
     * I-store ang CSV, i-preview, at ibalik ang token para sa commit.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json([
                'success' => false,
                'message' => 'Parts import is handled by the Administrative supply admin.',
            ], 403);
        }

        $file = $request->file('file');
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'No CSV file uploaded.'], 422);
        }

        $token = (string) Str::uuid();
        $relativePath = $file->storeAs('parts-imports', "{$token}.csv", 'local');
        $absolutePath = Storage::disk('local')->path($relativePath);

        $preview = (new PartsCsvImportService)->preview($absolutePath);
        session(["parts_import_{$token}" => $relativePath]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'summary' => $preview['summary'],
            'items' => array_slice($preview['items']->toArray(), 0, 25),
            'preview_limit' => 25,
        ]);
    }
}