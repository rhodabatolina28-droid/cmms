<?php

namespace App\Actions\PhysicalCount\Concerns;

trait BuildsCustodianGroups
{
    /**
     * Group a set of assets by custodian (assigned user) for PAR-based
     * accountability views. Unassigned/spare assets form the last group.
     *
     * @param  iterable  $assets  InventoryAsset models (assignedUser eager-loaded)
     * @param  iterable|null  $counts  PhysicalCount models of the session
     * @return \Illuminate\Support\Collection<int, array{key:int,name:string,par:?string,total:int,counted:int,assets:\Illuminate\Support\Collection}>
     */
    protected function buildCustodianGroups($assets, $counts = null)
    {
        $countedByAsset = $counts ? $counts->keyBy('asset_id') : collect();

        return $assets
            ->groupBy(fn ($a) => $a->assigned_to_user ?? 0)
            ->map(function ($groupAssets) use ($countedByAsset) {
                $first = $groupAssets->first();
                $user = $first->assignedUser;
                $parAsset = $groupAssets->first(fn ($a) => !empty($a->par_number));

                return [
                    'key'     => $first->assigned_to_user ?? 0,
                    'name'    => $user ? ($user->full_name ?? $user->name) : 'Unassigned / Spare',
                    'par'     => $parAsset->par_number ?? null,
                    'total'   => $groupAssets->count(),
                    'counted' => $groupAssets->filter(fn ($a) => $countedByAsset->has($a->asset_id))->count(),
                    'assets'  => $groupAssets->sortBy('item_name')->values(),
                ];
            })
            ->sortBy(fn ($g) => [$g['key'] === 0 ? 1 : 0, $g['name']])
            ->values();
    }
}
