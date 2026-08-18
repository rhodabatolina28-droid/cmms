<?php

namespace App\Actions\Inventory\PartsStock;

use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PartUnit;
use App\Services\PartsCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CommitPartsImportAction
{
    /**
     * I-commit ang dating na-preview na CSV (token-based).
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

        $token = (string) $request->input('token');
        $relativePath = session("parts_import_{$token}");
        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            return response()->json(['success' => false, 'message' => 'Import preview expired. Upload the CSV again.'], 422);
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $rows = (new PartsCsvImportService)->importableRows($absolutePath);

        $incompleteRow = collect($rows)->first(fn (array $row) => $row['serial'] === ''
            || $row['property'] === ''
            || $row['unit_value'] === null);
        if ($incompleteRow !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Every imported unit needs a serial number, property number, and unit cost.',
            ], 422);
        }

        $createdParts = 0;
        $createdUnits = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $user, &$createdParts, &$createdUnits, &$skipped) {
            $region = $user->region;

            foreach ($rows as $row) {
                $match = [
                    'item_name' => $row['item_name'],
                    'category' => $row['category'],
                    'region' => $region,
                ];

                $part = Part::firstOrCreate(
                    $match,
                    array_merge($match, [
                        'unit' => $row['unit'] ?: 'pcs',
                        'on_hand_qty' => 0,
                        'reorder_level' => 0,
                        'branch' => null,
                        'is_active' => true,
                        'requires_unit_tracking' => true,
                    ])
                );

                $wasCreated = $part->wasRecentlyCreated;

                $locked = Part::whereKey($part->id)->lockForUpdate()->first();
                if (! $locked) {
                    continue;
                }

                if (! $locked->requires_unit_tracking) {
                    $locked->update(['requires_unit_tracking' => true]);
                }

                if ($row['serial'] !== '') {
                    $exists = PartUnit::where('part_id', $part->id)->where('serial_number', $row['serial'])->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                }

                PartUnit::create([
                    'part_id' => $part->id,
                    'serial_number' => $row['serial'] !== '' ? $row['serial'] : null,
                    'property_number' => $row['property'] !== '' ? $row['property'] : null,
                    'unit_value' => $row['unit_value'],
                    'status' => 'in_stock',
                ]);

                $locked->increment('on_hand_qty', 1);
                $createdUnits++;

                if ($wasCreated) {
                    $createdParts++;
                }
            }
        });

        Storage::disk('local')->delete($relativePath);
        session()->forget("parts_import_{$token}");

        AuditLog::log(
            'Imported Parts CSV',
            'Parts & Consumables',
            "Imported {$createdUnits} unit(s) ({$createdParts} new part(s)) mula sa CSV.",
            $user->office ?? $user->branch ?? 'Parts & Consumables'
        );

        return response()->json([
            'success' => true,
            'message' => "Imported {$createdUnits} unit(s) across {$createdParts} new part(s), {$skipped} duplicate serial(s) skipped.",
            'created_parts' => $createdParts,
            'created_units' => $createdUnits,
            'skipped_duplicates' => $skipped,
        ]);
    }
}
