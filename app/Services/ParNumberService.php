<?php

namespace App\Services;

use App\Models\InventoryAsset;

class ParNumberService
{
    public static function generateNextParNumber(): string
    {
        $year = now()->year;
        $prefix = "PAR-{$year}-";

        // Use CAST to find the maximum numeric PAR number, regardless of creation order
        $result = InventoryAsset::withoutTrashed()
            ->selectRaw('CAST(RIGHT(par_number, 4) AS UNSIGNED) as last_number')
            ->where('par_number', 'LIKE', "{$prefix}%")
            ->orderByRaw('CAST(RIGHT(par_number, 4) AS UNSIGNED) DESC')
            ->first();

        if ($result && $result->last_number) {
            $nextNumber = $result->last_number + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
