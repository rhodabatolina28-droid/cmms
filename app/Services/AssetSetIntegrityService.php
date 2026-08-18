<?php

namespace App\Services;

use App\Models\InventoryAsset;

/**
 * Guards the parent/component model used by government PAR asset sets.
 * A set keeps separate physical records, but its PAR and custodian belong
 * to one parent asset and must remain consistent across its components.
 */
class AssetSetIntegrityService
{
    /** @return array{parent:?InventoryAsset,error:?string} */
    public function validate(array $data, ?InventoryAsset $asset = null): array
    {
        $parentId = array_key_exists('parent_asset_id', $data)
            ? $data['parent_asset_id']
            : $asset?->parent_asset_id;

        if (! $parentId) {
            return [
                'parent' => null,
                'error' => $this->validatePropertyNumber($data, $asset, null),
            ];
        }

        if ($asset && (int) $parentId === (int) $asset->asset_id) {
            return ['parent' => null, 'error' => 'An asset cannot be its own set parent.'];
        }

        $parent = InventoryAsset::find($parentId);
        if (! $parent) {
            return ['parent' => null, 'error' => 'The selected parent asset no longer exists.'];
        }

        if ($parent->parent_asset_id) {
            return ['parent' => null, 'error' => 'A set component cannot be used as another component’s parent.'];
        }

        if (! $parent->par_number) {
            return ['parent' => null, 'error' => 'Assign a PAR number to the parent asset before adding a set component.'];
        }

        return [
            'parent' => $parent,
            'error' => $this->validatePropertyNumber($data, $asset, $parent->asset_id),
        ];
    }

    public function applyParentContext(array &$data, InventoryAsset $parent): void
    {
        $data['parent_asset_id'] = $parent->asset_id;
        $data['par_number'] = $parent->par_number;
        $data['assigned_to_user'] = $parent->assigned_to_user;
        $data['region'] = $parent->region;
        $data['branch'] = $parent->branch;
        $data['office'] = $parent->office;
        $data['department'] = $parent->department;
    }

    /**
     * Property numbers may repeat inside one parent/component set only.
     * They cannot be reused by unrelated parents or standalone assets.
     */
    private function validatePropertyNumber(array $data, ?InventoryAsset $asset, ?int $parentId): ?string
    {
        $property = trim((string) ($data['property_number'] ?? $asset?->property_number ?? ''));
        if ($property === '') {
            return null;
        }

        $rootId = $parentId ?? ($asset?->parent_asset_id ?: $asset?->asset_id);
        $matches = InventoryAsset::where('property_number', $property)
            ->when($asset, fn ($query) => $query->where('asset_id', '!=', $asset->asset_id))
            ->get(['asset_id', 'parent_asset_id']);

        foreach ($matches as $match) {
            $matchRootId = $match->parent_asset_id ?: $match->asset_id;
            if (! $rootId || (int) $matchRootId !== (int) $rootId) {
                return 'This property number already belongs to an unrelated asset or PAR set.';
            }
        }

        return null;
    }

    /** Only new records or a Spare → Active transition are subject to completeness rules. */
    public function activationError(array $data, ?InventoryAsset $asset = null): ?string
    {
        $isActivating = $asset
            ? $asset->status !== 'Active' && ($data['status'] ?? $asset->status) === 'Active'
            : ($data['status'] ?? null) === 'Active';

        if (! $isActivating) {
            return null;
        }

        $missing = [];
        if (empty($data['assigned_to_user'])) {
            $missing[] = 'custodian';
        }
        if (trim((string) ($data['property_number'] ?? $asset?->property_number ?? '')) === '') {
            $missing[] = 'property number';
        }
        if (($data['acquisition_cost'] ?? $asset?->acquisition_cost) === null
            || ($data['acquisition_cost'] ?? $asset?->acquisition_cost) === '') {
            $missing[] = 'acquisition cost';
        }

        return $missing
            ? 'Before an asset can become Active, provide its ' . implode(', ', $missing) . '.'
            : null;
    }
}
