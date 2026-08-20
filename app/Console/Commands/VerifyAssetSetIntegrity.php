<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Asset Set Integrity & Verification Phase.
 *
 * Read-only audit: scans the inventory for set-integrity violations that the
 * AssetSetIntegrityService would have prevented at write time, but which may
 * exist in legacy data. Does NOT modify anything and does NOT enforce new
 * mandatory-field rules.
 */
class VerifyAssetSetIntegrity extends Command
{
    protected $signature = 'inventory:verify-asset-sets
                            {--json : Output violations as machine-readable JSON}
                            {--check= : Run only one check (orphan_components, nested_component_as_parent, parent_missing_par, component_par_mismatch, component_custodian_mismatch, component_org_mismatch, property_number_cross_set_reuse, status_custodian_inconsistency)}';

    protected $description = 'Scan inventory asset sets for integrity violations (read-only audit)';

    protected const ALL_CHECKS = [
        'orphan_components',
        'nested_component_as_parent',
        'parent_missing_par',
        'component_par_mismatch',
        'component_custodian_mismatch',
        'component_org_mismatch',
        'property_number_cross_set_reuse',
        'status_custodian_inconsistency',
    ];

    public function handle(): int
    {
        $only = $this->option('check');
        $checks = $only ? [str_replace('-', '_', $only)] : self::ALL_CHECKS;

        if ($only && ! in_array($checks[0], self::ALL_CHECKS, true)) {
            $this->error("Unknown check. Valid: " . implode(', ', self::ALL_CHECKS));
            return self::INVALID;
        }

        $results = [];
        foreach ($checks as $check) {
            $results[$check] = $this->{'check' . ucfirst(str_replace('_', '', $check))}();
        }

        if ($this->option('json')) {
            $this->line($this->asJson($results));
            return self::SUCCESS;
        }

        $this->renderText($results);
        return self::SUCCESS;
    }

    /** 1. Components whose parent_asset_id resolves to nothing (deleted or missing). */
    protected function checkOrphanComponents(): array
    {
        $rows = DB::table('inventory_assets as a')
            ->whereNotNull('a.parent_asset_id')
            ->whereNotIn('a.parent_asset_id', function ($q) {
                $q->select('asset_id')->from('inventory_assets')->whereNull('deleted_at');
            })
            ->orderBy('a.asset_id')
            ->get(['a.asset_id', 'a.serial_number', 'a.category', 'a.item_name', 'a.parent_asset_id']);

                return array_map(fn ($r) => $this->row('orphan_components', $r,
            'parent_asset_id ' . $r->parent_asset_id . ' no longer exists or was deleted'), $rows->all());
    }

    /** 2. An asset that is itself a component (has parent_asset_id) AND is used as a parent by others. */
    protected function checkNestedComponentAsParent(): array
    {
        $rows = DB::table('inventory_assets as a')
            ->whereNotNull('a.parent_asset_id')
            ->whereIn('a.asset_id', function ($q) {
                $q->select('parent_asset_id')->from('inventory_assets')
                    ->whereNotNull('parent_asset_id')->whereNull('deleted_at')->distinct();
            })
            ->orderBy('a.asset_id')
            ->get(['a.asset_id', 'a.serial_number', 'a.category', 'a.item_name', 'a.parent_asset_id']);

                return array_map(fn ($r) => $this->row('nested_component_as_parent', $r,
            'component is also a parent of other components'), $rows->all());
    }

    /** 3. A set parent that has components but no PAR number. */
    protected function checkParentMissingPar(): array
    {
        $rows = DB::table('inventory_assets as p')
            ->whereIn('p.asset_id', function ($q) {
                $q->select('parent_asset_id')->from('inventory_assets')
                    ->whereNotNull('parent_asset_id')->whereNull('deleted_at')->distinct();
            })
            ->whereNull('p.deleted_at')
            ->where(function ($q) {
                $q->whereNull('p.par_number')->orWhere('p.par_number', '');
            })
            ->orderBy('p.asset_id')
            ->get(['p.asset_id', 'p.serial_number', 'p.category', 'p.item_name']);

                return array_map(fn ($r) => $this->row('parent_missing_par', $r,
            'parent of a set has no PAR number'), $rows->all());
    }

    /** 4/5/6. Component PAR / custodian / org consistency against its parent — single joined pass. */
    protected function componentMismatchRows()
    {
        return DB::table('inventory_assets as c')
            ->join('inventory_assets as p', 'c.parent_asset_id', '=', 'p.asset_id')
            ->whereNull('c.deleted_at')->whereNull('p.deleted_at')
            ->orderBy('c.asset_id')
            ->get([
                'c.asset_id', 'c.serial_number', 'c.category', 'c.item_name',
                'c.par_number', 'c.assigned_to_user', 'c.region', 'c.branch', 'c.office', 'c.department',
                'p.asset_id as parent_id', 'p.serial_number as parent_sn', 'p.par_number as parent_par',
                'p.assigned_to_user as parent_user', 'p.region as parent_region', 'p.branch as parent_branch',
                'p.office as parent_office', 'p.department as parent_department',
            ]);
    }

    /** 4. Component PAR mismatch against parent. */
    protected function checkComponentParMismatch(): array
    {
        $out = [];
        foreach ($this->componentMismatchRows() as $r) {
            if ($this->norm($r->par_number) !== $this->norm($r->parent_par)) {
                $out[] = $this->row('component_par_mismatch', (array) $r,
                    "component PAR '{$this->norm($r->par_number)}' != parent '{$this->norm($r->parent_par)}'");
            }
        }
        return $out;
    }

        /** 5. Component custodian differs from parent set custodian. */
    protected function checkComponentCustodianMismatch(): array
    {
        $out = [];
        foreach ($this->componentMismatchRows() as $r) {
            if (! is_null($r->parent_user)
                && $r->assigned_to_user != $r->parent_user
                && ! is_null($r->assigned_to_user)) {
                $out[] = $this->row('component_custodian_mismatch', (array) $r,
                    'component custodian differs from parent set custodian');
            }
        }
        return $out;
    }

    /** 6. Component org fields (region/branch/office/department) differ from parent. */
    protected function checkComponentOrgMismatch(): array
    {
        $out = [];
        foreach ($this->componentMismatchRows() as $r) {
            $diffs = [];
            foreach (['region' => 'parent_region', 'branch' => 'parent_branch', 'office' => 'parent_office', 'department' => 'parent_department'] as $field => $parentField) {
                if ($r->{$field} != $r->{$parentField}) {
                    $diffs[] = $field . " ({$this->norm($r->{$field})} vs {$this->norm($r->{$parentField})})";
                }
            }
            if ($diffs) {
                $out[] = $this->row('component_org_mismatch', (array) $r, implode(', ', $diffs));
            }
        }
        return $out;
    }

    /** 7. Same property_number shared by assets under different roots (sets). */
    protected function checkPropertyNumberCrossSetReuse(): array
    {
        $dups = DB::table('inventory_assets')
            ->whereNull('deleted_at')
            ->whereNotNull('property_number')->where('property_number', '!=', '')
            ->selectRaw('property_number, COUNT(DISTINCT COALESCE(NULLIF(parent_asset_id, 0), asset_id)) AS roots')
            ->groupBy('property_number')
            ->having('roots', '>', 1)
            ->pluck('property_number')->toArray();

        if (empty($dups)) {
            return [];
        }

        $rows = DB::table('inventory_assets as a')
            ->whereIn('a.property_number', $dups)
            ->whereNull('a.deleted_at')
            ->orderBy('a.property_number')->orderBy('a.asset_id')
            ->get(['a.asset_id', 'a.serial_number', 'a.category', 'a.item_name', 'a.parent_asset_id', 'a.property_number']);

        $out = [];
        foreach ($rows as $r) {
            $root = $r->parent_asset_id ?: $r->asset_id;
            $out[] = $this->row('property_number_cross_set_reuse', $r,
                "property_number '{$r->property_number}' shared by multiple roots (this asset root={$root})");
        }
        return $out;
    }

    /** 8. Status/custodian invariant (model boot only auto-fixes on save — legacy rows may be stale). */
    protected function checkStatusCustodianInconsistency(): array
    {
        $out = [];
        $noUser = DB::table('inventory_assets')
            ->where('status', 'Active')
            ->where(function ($q) { $q->whereNull('assigned_to_user')->orWhere('assigned_to_user', 0); })
            ->whereNull('deleted_at')->orderBy('asset_id')
            ->get(['asset_id', 'serial_number', 'category', 'item_name']);
        foreach ($noUser as $r) {
            $out[] = $this->row('status_custodian_inconsistency', $r, 'Active asset has no custodian (should be Spare)');
        }

        $withUser = DB::table('inventory_assets')
            ->where('status', 'Spare')
            ->whereNotNull('assigned_to_user')->where('assigned_to_user', '!=', 0)
            ->whereNull('deleted_at')->orderBy('asset_id')
            ->get(['asset_id', 'serial_number', 'category', 'item_name']);
        foreach ($withUser as $r) {
            $out[] = $this->row('status_custodian_inconsistency', $r, 'Spare asset has a custodian (should be Active)');
        }
        return $out;
    }

        /** Build a normalized violation row. */
    protected function row(string $type, $r, string $details): array
    {
        return [
            'type'            => $type,
            'asset_id'        => $r->asset_id ?? ($r['asset_id'] ?? null),
            'serial_number'   => $r->serial_number ?? ($r['serial_number'] ?? ''),
            'category'        => $r->category ?? ($r['category'] ?? ''),
            'item_name'       => $r->item_name ?? ($r['item_name'] ?? ''),
            'details'         => $details,
        ];
    }

    protected function norm($v): string
    {
        return ($v === null || $v === '') ? '' : (string) $v;
    }

    protected function asJson(array $results): string
    {
        $summary = [];
        $all = [];
        foreach ($results as $check => $rows) {
            $summary[$check] = count($rows);
            foreach ($rows as $row) {
                $all[] = $row;
            }
        }
        return json_encode([
            'generated_at' => now()->toIso8601String(),
            'summary'      => $summary,
            'total'        => count($all),
            'violations'   => $all,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function renderText(array $results): void
    {
        $this->info('Asset Set Integrity - verification report');
        $this->line('Generated: ' . now()->toDateTimeString() . ' (read-only)');

        $totalAll = 0;
        foreach ($results as $check => $rows) {
            $count = count($rows);
            $totalAll += $count;
            $this->newLine();
            $this->warn(str_replace('_', ' ', ucfirst($check)) . " - {$count} violation(s)");

            if ($count > 0) {
                $tableRows = array_map(fn ($r) => [
                    $r['asset_id'],
                    $r['serial_number'],
                    $r['category'],
                    $r['item_name'],
                    $r['details'],
                ], $rows);
                $this->table(['asset_id', 'serial_number', 'category', 'item_name', 'details'], $tableRows);
            }
        }

        $this->newLine();
        $this->info("Total violations: {$totalAll}");
        if ($totalAll > 0) {
            $this->comment('These are data issues the write-time integrity service prevents. Resolve before enforcing stricter rules.');
        }
    }
}
