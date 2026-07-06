<?php

namespace App\Services;

use App\Models\InventoryAsset;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * ICT CSV importer for the inventory.
 *
 * Government (COA) compliance notes:
 *  - One PAR may list several articles; all share the same accountable officer.
 *    We keep the shared PAR number across set components.
 *  - Each accountable article (CPU, Monitor) becomes its own inventory asset
 *    so physical inventory, depreciation, and PM scheduling stay honest.
 *  - Serial numbers are NEVER fabricated. If absent in the CSV the field is
 *    left null and flagged for verification during physical inventory.
 *  - Unmatched custodians (resigned, misspelled, or "TRANSFERRED") yield a
 *    Spare asset with the raw officer text preserved in asset_notes for review.
 */
class InventoryCsvImportService
{
    public function preview(string $path, User $actor): array
    {
        $rows = $this->dataRows($path);
        $users = $this->candidateUsers($actor);

        // Detect PMS format from first row
        $isPms = !empty($rows) && !empty($rows[0]['_pms_type']);

        $seenPar = [];        // singletons + set parents (strict uniqueness)
        $seenParSets = [];    // PARs that head a set — their children may reuse the PAR
        $seenProperty = [];
        $seenSerial = [];

        $items = [];
        $summary = [
            'total_rows' => count($rows),
            'valid_rows' => 0,
            'duplicate_rows' => 0,
            'needs_review_rows' => 0,
            'matched_custodians' => 0,
            'unmatched_custodians' => 0,
            'set_rows' => 0,
            'component_rows' => 0,
        ];

        foreach ($rows as $index => $row) {
            $mapped = $isPms
                ? $this->mapPmsRow($row, $actor, $users)
                : $this->mapRow($row, $actor, $users);
            $warnings = $mapped['warnings'];
            $records = $mapped['records'];
            $errors = [];

            // Skip PMS rows that had no assets (e.g. blank Laptop/Scanner rows)
            if (empty($records) && $isPms) {
                continue;
            }

            foreach ($records as $record) {
                $isComponent = !empty($record['_is_component']);
                $parKey = $this->norm($record['par_number'] ?? '');
                $propKey = $this->norm($record['property_number'] ?? '');
                $serialKey = $this->norm($record['serial_number'] ?? '');

                // PAR: components inherit parent's PAR (allowed); singletons/parents must be unique.
                if (!$parKey) {
                    $errors[] = 'Missing PAR number.';
                } elseif (!$isComponent) {
                    if (isset($seenPar[$parKey]) || InventoryAsset::where('par_number', $record['par_number'])->exists()) {
                        $errors[] = 'Duplicate PAR number.';
                    }
                }

                // Property number: validate uniqueness when present (PMS imports may lack prop# for some accessories).
                if ($propKey && (isset($seenProperty[$propKey]) || InventoryAsset::where('property_number', $record['property_number'])->exists())) {
                    $errors[] = 'Duplicate property number.';
                }

                // Serial: enforced when present; null allowed.
                if ($serialKey) {
                    if (isset($seenSerial[$serialKey]) || InventoryAsset::where('serial_number', $record['serial_number'])->exists()) {
                        $errors[] = 'Duplicate serial number.';
                    }
                }

                if (empty($record['item_name'])) {
                    $errors[] = 'Missing item description.';
                }
            }

            // Bookkeeping for dedupe maps (after validation so further dupes still surface).
            foreach ($records as $record) {
                $isComponent = !empty($record['_is_component']);
                $parKey = $this->norm($record['par_number'] ?? '');
                $propKey = $this->norm($record['property_number'] ?? '');
                $serialKey = $this->norm($record['serial_number'] ?? '');

                if (!$isComponent && $parKey) {
                    $seenPar[$parKey] = true;
                    if (count($records) > 1) {
                        $seenParSets[$parKey] = true;
                    }
                }
                if ($propKey) {
                    $seenProperty[$propKey] = true;
                }
                if ($serialKey) {
                    $seenSerial[$serialKey] = true;
                }
            }

            if ($mapped['custodian_matched']) {
                $summary['matched_custodians']++;
            } else {
                $summary['unmatched_custodians']++;
            }

            $componentCount = count(array_filter($records, fn ($r) => !empty($r['_is_component'])));
            if ($componentCount > 0) {
                $summary['set_rows']++;
                $summary['component_rows'] += $componentCount;
            }

            $status = empty($errors) ? (empty($warnings) ? 'valid' : 'needs_review') : 'blocked';
            if ($status === 'blocked') {
                $summary['duplicate_rows']++;
            } elseif ($status === 'needs_review') {
                $summary['needs_review_rows']++;
                $summary['valid_rows']++;
            } else {
                $summary['valid_rows']++;
            }

            $items[] = [
                'row_number' => $index + 1,
                'status' => $status,
                'errors' => $errors,
                'warnings' => $warnings,
                'records' => $records,
                'is_set' => $componentCount > 0,
                'raw' => $mapped['raw'],
                'responsible_officer_raw' => $mapped['responsible_officer_raw'],
                'location_raw' => $mapped['location_raw'],
            ];
        }

        return [
            'summary' => $summary,
            'items' => $items,
        ];
    }

    public function importableRows(string $path, User $actor): array
    {
        $preview = $this->preview($path, $actor);
        return array_values(array_filter($preview['items'], function ($item) {
            return in_array($item['status'], ['valid', 'needs_review'], true);
        }));
    }

    private function dataRows(string $path): array
    {
        $rows = [];

        $handle = fopen($path, 'r');
        if (!$handle) return [];
        while (($row = fgetcsv($handle)) !== false) {
            // Normalize row length to prevent index errors
            $rows[] = $row;
        }
        fclose($handle);

        // Detect format
        if (!empty($rows) && isset($rows[0][0]) && str_starts_with(trim((string) $rows[0][0]), 'ICT-')) {
            // ICT PAR format: each row starting with ICT- is a record
            return array_values(array_filter($rows, fn ($r) => str_starts_with(trim((string) ($r[0] ?? '')), 'ICT-')));
        }

        // PMS format: detect type from headers (line 2, 0-indexed: index 1)
        // Row 0: DATE header, Row 1: column group headers, Row 2: sub-headers
        if (count($rows) >= 4) {
            $headerRow = $rows[1] ?? [];
            $headerText = strtoupper(implode(' ', array_slice($headerRow, 0, 8)));

            $pmsType = match (true) {
                str_contains($headerText, 'DESKTOP') => 'pms_desktop',
                str_contains($headerText, 'LAPTOP')  => 'pms_laptop',
                str_contains($headerText, 'INKJET') || str_contains($headerText, 'LASERJET') => 'pms_printer',
                str_contains($headerText, 'SCANNER') => 'pms_scanner',
                default => 'pms_others',
            };

            // Data starts at row index 3 (0-based)
            for ($i = 3; $i < count($rows); $i++) {
                $row = $rows[$i];
                $col0 = trim((string) ($row[0] ?? ''));
                // Skip completely empty rows or rows without a row number
                if ($col0 === '' && count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) continue;
                // Mark the row with PMS type
                $row['_pms_type'] = $pmsType;
                $rows[$i] = $row;
            }

            return array_values(array_filter(
                array_slice($rows, 3),
                fn ($r) => isset($r['_pms_type'])
            ));
        }

        return [];
    }

    /**
     * Map a single CSV row into 1 (singleton) or 2 (split Complete Set) asset
     * record arrays. Each record array carries everything InventoryAsset::create
     * expects, plus an internal `_is_component` flag consumed by commitImport.
     */
    private function mapRow(array $row, User $actor, Collection $users): array
    {
        $raw = [
            'par_number' => $this->clean($row[0] ?? ''),
            'article' => $this->clean($row[1] ?? ''),
            'description' => $this->clean($row[2] ?? ''),
            'date_of_acquisition' => $this->clean($row[3] ?? ''),
            // Col 4 (old_property_prefix in the original layout) actually holds
            // the structured S/N block in newer CSVs, often multi-line + labelled.
            'serial_block' => $this->clean($row[4] ?? ''),
            'old_property_or_serial' => $this->clean($row[5] ?? ''),
            'new_property_number' => $this->clean($row[6] ?? ''),
            'unit_measure' => $this->clean($row[7] ?? ''),
            'unit_value' => $this->clean($row[8] ?? ''),
            'qty_property_card' => $this->clean($row[9] ?? ''),
            'qty_physical_count' => $this->clean($row[10] ?? ''),
            'shortage_overage_qty' => $this->clean($row[11] ?? ''),
            'shortage_overage_value' => $this->clean($row[12] ?? ''),
            'responsible_officer' => $this->clean($row[13] ?? ''),
            'location' => $this->clean($row[14] ?? ''),
        ];

        $description = $raw['description'];
        $parsed = $this->parseDescription($description, $raw['article']);
        $serials = $this->extractSerials($raw['serial_block'], $description);
        $category = $this->normalizeCategory($raw['article'], $description);

        $isTransferred = $this->isTransferred($raw['responsible_officer']);
        $custodian = $isTransferred ? null : $this->matchUser($raw['responsible_officer'], $users);

        $warnings = [];
        if ($isTransferred) {
            $warnings[] = 'Responsible officer marked TRANSFERRED — asset will be created as Spare.';
        } elseif (!$custodian && $raw['responsible_officer']) {
            $warnings[] = 'Responsible officer was kept as raw text; no matching active user found.';
        }
        if ($category === 'Others') {
            $warnings[] = 'Category mapped to Others; review asset class.';
        }

        $office = $this->normalizeLocation($raw['location']);
        $descUpper = strtoupper($description);
        $unitUpper = strtoupper($raw['unit_measure']);
        $isSet = $unitUpper === 'SET' || str_contains($descUpper, 'COMPLETE SET') || !empty($parsed['set_type']);

        $baseSpecs = array_filter([
            'cpu' => $parsed['processor'] ?? null,
            'ram' => $parsed['ram'] ?? null,
            'hd1' => $parsed['storage'] ?? null,
            'os' => $parsed['operating_system'] ?? null,
            'gpu' => $parsed['gpu'] ?? null,
            'office' => $parsed['office'] ?? null,
            'set_type' => $parsed['set_type'] ?? null,
            'speed' => $parsed['speed'] ?? null,
            'capacity' => $parsed['capacity'] ?? null,
            'network_role' => $parsed['network_role'] ?? null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $itemName = $this->itemName($description, $raw['article']);

        // Build the parent (main unit) record always.
        $parentCpuSerial = $serials['cpu'] ?? ($parsed['serial_number'] ?? null);
        $parentNotes = $this->buildNotes($raw, $isTransferred);

        $parent = [
            'category' => $category,
            'item_name' => $itemName,
            'serial_number' => $parentCpuSerial,
            'property_number' => $raw['new_property_number'],
            'par_number' => $raw['par_number'],
            'brand' => $parsed['brand'] ?? null,
            'model' => $parsed['model'] ?? null,
            'specifications' => $baseSpecs,
            'assigned_to_user' => $custodian?->id,
            'region' => $actor->region,
            'branch' => $actor->branch,
            'office' => $custodian?->office ?: $office,
            'department' => $custodian?->department,
            'status' => $custodian ? 'Active' : 'Spare',
            'date_acquired' => $this->parseDate($raw['date_of_acquisition']),
            'acquisition_cost' => $this->toMoney($raw['unit_value']),
            'asset_notes' => $parentNotes,
            '_is_component' => false,
        ];

        $records = [$parent];

        // Detect accessories from description and create separate child records
        if ($isSet && in_array($category, ['Desktop', 'Laptop'], true)) {
            $accessories = $this->extractAccessories($description);

            foreach ($accessories as $acc) {
                $typeKey = strtolower($acc['type']);
                $accSerial = $serials[$typeKey] ?? null;
                $suffix = $this->componentSuffix($acc['type']);
                $accCategory = $acc['type'] === 'Monitor' ? 'Monitor' : 'Peripherals';

                $records[] = [
                    'category' => $accCategory,
                    'item_name' => $acc['type'] . ' - ' . ($acc['brand'] ?? 'Unknown'),
                    'serial_number' => $accSerial,
                    'property_number' => $raw['new_property_number'] . '-' . $suffix,
                    'par_number' => $raw['par_number'],
                    'brand' => $acc['brand'],
                    'model' => $acc['model'],
                    'specifications' => null,
                    'assigned_to_user' => $custodian?->id,
                    'region' => $actor->region,
                    'branch' => $actor->branch,
                    'office' => $custodian?->office ?: $office,
                    'department' => $custodian?->department,
                    'status' => $custodian ? 'Active' : 'Spare',
                    'date_acquired' => $this->parseDate($raw['date_of_acquisition']),
                    'acquisition_cost' => null,
                    'asset_notes' => ($accSerial ? '' : $acc['type'] . ' S/N to verify during physical inventory. ') . $parentNotes,
                    '_is_component' => true,
                ];

                if (empty($accSerial)) {
                    $warnings[] = $acc['type'] . ' S/N not found in CSV — created with null serial for verification.';
                }
            }
        }

        return [
            'records' => $records,
            'raw' => $raw,
            'warnings' => $warnings,
            'custodian_matched' => (bool) $custodian,
            'responsible_officer_raw' => $raw['responsible_officer'],
            'location_raw' => $raw['location'],
        ];
    }

    /**
     * Extract serial numbers from column 4 (structured S/N block). Supports
     * labeled serials: (CPU), (MONITOR), (UPS), (SPEAKER), (WEBCAM), etc.
     * Unlabeled serials are assigned to CPU. Older CSVs fall back to the
     * description for a single S/N.
     *
     * Returns a map of lowercase labels → S/N values (e.g. ['cpu' => ..., 'monitor' => ...]).
     */
    private function extractSerials(string $serialBlock, string $description): array
    {
        $serials = [];

        // Prefer the structured col-4 block when present.
        if ($serialBlock !== '' && stripos($serialBlock, 'S/N') !== false) {
            preg_match_all('/S\/N\s*[:;#\-]?\s*([A-Z0-9][A-Z0-9\-]*)\s*(?:\(([^)]+)\))?/i', $serialBlock, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $value = trim($m[1], " .;,)");
                $label = strtoupper(trim($m[2] ?? ''));
                if ($label === '') {
                    // Unlabeled — assign to CPU if not already set
                    if (!isset($serials['cpu'])) {
                        $serials['cpu'] = $value;
                    }
                } else {
                    $serials[strtolower($label)] = $value;
                }
            }
        }

        // Fall back to the description for the (single) S/N when col 4 was empty.
        if (!isset($serials['cpu']) && preg_match('/(?:S\/N|S\.N\.|SN|SERIAL(?:\s+NO\.?)?)\s*[:;#\-]?\s*([A-Z0-9][A-Z0-9\-]+)/i', $description, $m)) {
            $serials['cpu'] = trim($m[1], " .;,)");
        }

        return $serials;
    }

    private function buildNotes(array $raw, bool $isTransferred): string
    {
        $parts = ["CSV import. Officer: {$raw['responsible_officer']}; Location: {$raw['location']}"];
        if ($isTransferred) {
            $parts[] = 'TRANSFERRED per CSV — awaiting reassignment';
        }
        return trim(implode('; ', $parts));
    }

    /**
     * PMSF The dispatcher for all PMS CSV format rows.
     */
    private function mapPmsRow(array $row, User $actor, Collection $users): array
    {
        $pmsType = $row['_pms_type'];
        unset($row['_pms_type']);

        return match ($pmsType) {
            'pms_desktop' => $this->mapPmsDesktop($row, $actor, $users),
            'pms_laptop'  => $this->mapPmsLaptop($row, $actor, $users),
            'pms_printer' => $this->mapPmsPrinter($row, $actor, $users),
            'pms_scanner' => $this->mapPmsScanner($row, $actor, $users),
            'pms_others'  => $this->mapPmsOthers($row, $actor, $users),
            default       => $this->mapPmsOthers($row, $actor, $users),
        };
    }

    /**
     * PMS Desktop CSV. Columns:
     *   0:No. 1:End-user 2:FLR 3:DIV 4:Brand/Model 5:PropertyNo 6:ComputerName
     *   7:Year 8:CPU 9:RAM 10:GPU 11:HD-1 12:HD-2 13:OS
     *   14:Mon1Brand 15:Mon1Model 16:Mon1Prop 17:Mon2Brand 18:Mon2Model 19:Mon2Prop 20:MSOffice
     */
    private function mapPmsDesktop(array $row, User $actor, Collection $users): array
    {
        $row = array_pad($row, 21, '');
        [$no, $officer, $flr, $div, $brandModel, $propNo, $compName, $year, $cpu, $ram, $gpu, $hd1, $hd2, $os, $m1b, $m1m, $m1p, $m2b, $m2m, $m2p, $office] = $row;

        $officer = $this->clean($officer);
        $brand = null;
        $model = null;
        $upperBm = strtoupper($brandModel);
        foreach (['LENOVO', 'DELL', 'HP', 'HPE', 'ACER', 'ASUS', 'SAMSUNG', 'AOC', 'LG', 'BENQ', 'PHILIPS'] as $kb) {
            if (str_contains($upperBm, $kb)) { $brand = $kb; break; }
        }
        $model = $brand ? trim(str_ireplace($brand, '', $brandModel)) : $brandModel;

        $isTransferred = $this->isTransferred($officer);
        $custodian = $isTransferred ? null : $this->matchUser($officer, $users);
        $warnings = [];
        if ($isTransferred) $warnings[] = 'Responsible officer marked TRANSFERRED';
        elseif (!$custodian && $officer) $warnings[] = 'No matching user found for: ' . $officer;

        $parNumber = $propNo ?: ('PMS-DT-' . $no);
        $specs = array_filter(compact('cpu', 'ram', 'gpu', 'hd1', 'hd2', 'os', 'office'), fn ($v) => $v !== null && $v !== '' && $v !== false);
        $baseNotes = "PMS import. Officer: {$officer}; Location: {$div}/{$flr}";

        $parent = [
            'category' => 'Desktop', 'item_name' => $brandModel, 'serial_number' => null,
            'property_number' => $propNo, 'par_number' => $parNumber,
            'brand' => $brand, 'model' => $model, 'specifications' => $specs,
            'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
            'office' => $div, 'department' => $div,
            'status' => $custodian ? 'Active' : 'Spare',
            'date_acquired' => $this->parseDate($year), 'acquisition_cost' => null,
            'asset_notes' => $baseNotes, '_is_component' => false,
        ];
        $records = [$parent];

        // Monitor 1
        if ($this->clean($m1b) || $this->clean($m1m)) {
            $records[] = [
                'category' => 'Monitor', 'item_name' => 'Monitor - ' . ($this->clean($m1b) ?: 'Unknown'),
                'serial_number' => null, 'property_number' => $this->clean($m1p) ?: ($propNo ? $propNo . '-M1' : null),
                'par_number' => $parNumber, 'brand' => $this->clean($m1b) ?: null, 'model' => $this->clean($m1m) ?: null,
                'specifications' => null, 'assigned_to_user' => $custodian?->id,
                'region' => $actor->region, 'branch' => $actor->branch, 'office' => $div, 'department' => $div,
                'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => $this->parseDate($year), 'acquisition_cost' => null,
                'asset_notes' => $baseNotes, '_is_component' => true,
            ];
        }
        // Monitor 2
        if ($this->clean($m2b) || $this->clean($m2m)) {
            $records[] = [
                'category' => 'Monitor', 'item_name' => 'Monitor - ' . ($this->clean($m2b) ?: 'Unknown'),
                'serial_number' => null, 'property_number' => $this->clean($m2p) ?: ($propNo ? $propNo . '-M2' : null),
                'par_number' => $parNumber, 'brand' => $this->clean($m2b) ?: null, 'model' => $this->clean($m2m) ?: null,
                'specifications' => null, 'assigned_to_user' => $custodian?->id,
                'region' => $actor->region, 'branch' => $actor->branch, 'office' => $div, 'department' => $div,
                'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => $this->parseDate($year), 'acquisition_cost' => null,
                'asset_notes' => $baseNotes, '_is_component' => true,
            ];
        }

        return [
            'records' => $records, 'raw' => ['par_number' => $parNumber, 'article' => 'Desktop Computer', 'description' => $brandModel, 'responsible_officer' => $officer, 'location' => $div],
            'warnings' => $warnings, 'custodian_matched' => (bool) $custodian,
            'responsible_officer_raw' => $officer, 'location_raw' => $div,
        ];
    }

    /**
     * PMS Laptop CSV.
     *   0:No. 1:End-user 2:DIV 3:(-) 4:Brand 5:Model 6:PropertyNo 7:ComputerName
     *   8:Year 9:CPU 10:RAM 11:GPU 12:HD-1 13:HD-2 14:OS 15:MSOffice
     */
    private function mapPmsLaptop(array $row, User $actor, Collection $users): array
    {
        $row = array_pad($row, 16, '');
        [$no, $officer, $div,,,, $propNo, $compName, $year, $cpu, $ram, $gpu, $hd1, $hd2, $os, $office] = $row;
        $officer = $this->clean($officer);
        $brand = $this->clean($row[4] ?? '');
        $model = $this->clean($row[5] ?? '');

        // Skip empty rows (no laptop data)
        if (!$brand && !$model && !$propNo) {
            return [
                'records' => [], 'raw' => [], 'warnings' => [],
                'custodian_matched' => false, 'responsible_officer_raw' => $officer, 'location_raw' => $div,
            ];
        }

        $isTransferred = $this->isTransferred($officer);
        $custodian = $isTransferred ? null : $this->matchUser($officer, $users);
        $warnings = [];
        if ($isTransferred) $warnings[] = 'Responsible officer marked TRANSFERRED';
        elseif (!$custodian && $officer) $warnings[] = 'No matching user found for: ' . $officer;

        $parNumber = $propNo ?: ('PMS-LT-' . $no);
        $specs = array_filter(compact('cpu', 'ram', 'gpu', 'hd1', 'hd2', 'os', 'office'), fn ($v) => $v !== null && $v !== '' && $v !== false);

        return [
            'records' => [[
                'category' => 'Laptop', 'item_name' => trim($brand . ' ' . $model) ?: 'Laptop',
                'serial_number' => null, 'property_number' => $propNo, 'par_number' => $parNumber,
                'brand' => $brand ?: null, 'model' => $model ?: null, 'specifications' => $specs,
                'assigned_to_user' => $custodian?->id,
                'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div,
                'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => $this->parseDate($year), 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ]],
            'raw' => ['par_number' => $parNumber, 'article' => 'Laptop Computer', 'description' => $brand . ' ' . $model, 'responsible_officer' => $officer, 'location' => $div],
            'warnings' => $warnings, 'custodian_matched' => (bool) $custodian,
            'responsible_officer_raw' => $officer, 'location_raw' => $div,
        ];
    }

    /**
     * PMS Printer CSV. Up to 3 printers per row (Inkjet + LaserJet x 2).
     *   0:No. 1:End-user 2:DIV
     *   3:IJBrand 4:IJModel 5:IJProp 6:IJYear 7:LJ1Brand 8:LJ1Model 9:LJ1Prop 10:LJ1Year
     *   11:LJ2Brand 12:LJ2Model 13:LJ2Prop 14:LJ2Year
     */
    private function mapPmsPrinter(array $row, User $actor, Collection $users): array
    {
        $row = array_pad($row, 15, '');
        [$no, $officer, $div, $ijB, $ijM, $ijP, $ijY, $lj1B, $lj1M, $lj1P, $lj1Y, $lj2B, $lj2M, $lj2P, $lj2Y] = $row;
        $officer = $this->clean($officer);
        $warnings = [];

        $isTransferred = $this->isTransferred($officer);
        $custodian = $isTransferred ? null : $this->matchUser($officer, $users);
        if ($isTransferred) $warnings[] = 'Responsible officer marked TRANSFERRED';
        elseif (!$custodian && $officer) $warnings[] = 'No matching user found for: ' . $officer;

        $records = [];

        $printerSlots = [
            ['b' => $ijB, 'm' => $ijM, 'p' => $ijP, 'y' => $ijY, 's' => 'IJ'],
            ['b' => $lj1B, 'm' => $lj1M, 'p' => $lj1P, 'y' => $lj1Y, 's' => 'LJ1'],
            ['b' => $lj2B, 'm' => $lj2M, 'p' => $lj2P, 'y' => $lj2Y, 's' => 'LJ2'],
        ];

        foreach ($printerSlots as $slot) {
            $brand = $this->clean($slot['b']);
            $model = $this->clean($slot['m']);
            if (!$brand && !$model) continue;
            $prop = $this->clean($slot['p']);
            $year = $this->clean($slot['y']);
            $parNumber = $prop ?: ($brand . '-' . $model . '-' . $no);

            $records[] = [
                'category' => 'Printer/Scanner', 'item_name' => ($brand ?: 'Printer') . ($model ? ' ' . $model : ''),
                'serial_number' => null, 'property_number' => $prop, 'par_number' => $parNumber,
                'brand' => $brand ?: null, 'model' => $model ?: null, 'specifications' => null,
                'assigned_to_user' => $custodian?->id,
                'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div,
                'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => $this->parseDate($year), 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ];
        }

        return [
            'records' => $records,
            'raw' => ['par_number' => '', 'article' => 'Printer', 'description' => '', 'responsible_officer' => $officer, 'location' => $div],
            'warnings' => $warnings, 'custodian_matched' => (bool) $custodian,
            'responsible_officer_raw' => $officer, 'location_raw' => $div,
        ];
    }

    /**
     * PMS Scanner CSV.
     *   0:No. 1:End-user 2:DIV 3:Brand 4:Model 5:PropertyNo 6:Year
     */
    private function mapPmsScanner(array $row, User $actor, Collection $users): array
    {
        $row = array_pad($row, 7, '');
        [$no, $officer, $div, $brand, $model, $prop, $year] = $row;
        $officer = $this->clean($officer);
        $brand = $this->clean($brand);
        $model = $this->clean($model);

        if (!$brand && !$model) {
            return [
                'records' => [], 'raw' => [], 'warnings' => [],
                'custodian_matched' => false, 'responsible_officer_raw' => $officer, 'location_raw' => $div,
            ];
        }

        $isTransferred = $this->isTransferred($officer);
        $custodian = $isTransferred ? null : $this->matchUser($officer, $users);
        $warnings = [];
        if ($isTransferred) $warnings[] = 'Responsible officer marked TRANSFERRED';
        elseif (!$custodian && $officer) $warnings[] = 'No matching user found for: ' . $officer;

        $prop = $this->clean($prop);
        $year = $this->clean($year);
        $parNumber = $prop ?: ('PMS-SC-' . $no);

        return [
            'records' => [[
                'category' => 'Printer/Scanner', 'item_name' => ($brand ?: 'Scanner') . ($model ? ' ' . $model : ''),
                'serial_number' => null, 'property_number' => $prop, 'par_number' => $parNumber,
                'brand' => $brand ?: null, 'model' => $model ?: null, 'specifications' => null,
                'assigned_to_user' => $custodian?->id,
                'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div,
                'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => $this->parseDate($year), 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ]],
            'raw' => ['par_number' => $parNumber, 'article' => 'Scanner', 'description' => $brand . ' ' . $model, 'responsible_officer' => $officer, 'location' => $div],
            'warnings' => $warnings, 'custodian_matched' => (bool) $custodian,
            'responsible_officer_raw' => $officer, 'location_raw' => $div,
        ];
    }

    /**
     * PMS Others CSV (UPS, IP Phone, Webcam, Speaker, Headphones, Others).
     *   0:No. 1:End-user 2:DIV
     *   3:UPS Brand 4:UPS Model 5:UPS Prop 6:IP Phone 7:Webcam 8:WebcamProp
     *   9:SpkBrand 10:SpkModel 11:SpkProp 12:HpBrand 13:HpModel
     *   14:Oth1 15:Oth1Prop 16:Oth2 17:Oth2Prop 18:MSOffice
     */
    private function mapPmsOthers(array $row, User $actor, Collection $users): array
    {
        $row = array_pad($row, 19, '');
        [$no, $officer, $div, $upB, $upM, $upP, $ip, $webB, $webP, $spkB, $spkM, $spkP, $hpB, $hpM, $oth1, $oth1P, $oth2, $oth2P, $office] = $row;
        $officer = $this->clean($officer);
        $warnings = [];

        $isTransferred = $this->isTransferred($officer);
        $custodian = $isTransferred ? null : $this->matchUser($officer, $users);
        if ($isTransferred) $warnings[] = 'Responsible officer marked TRANSFERRED';
        elseif (!$custodian && $officer) $warnings[] = 'No matching user found for: ' . $officer;

        $records = [];

        // UPS
        $b = $this->clean($upB); $m = $this->clean($upM); $p = $this->clean($upP);
        if ($b || $m) {
            $records[] = [
                'category' => 'Peripherals', 'item_name' => 'UPS - ' . ($b ?: 'Unknown'),
                'serial_number' => null, 'property_number' => $p, 'par_number' => $p ?: ('PMS-UPS-' . $no),
                'brand' => $b ?: null, 'model' => $m ?: null, 'specifications' => null,
                'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div, 'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => null, 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ];
        }

        // IP Phone
        $v = $this->clean($ip);
        if ($v) {
            $records[] = [
                'category' => 'Network/Server', 'item_name' => 'IP Phone - ' . $v,
                'serial_number' => null, 'property_number' => null, 'par_number' => 'PMS-IP-' . $no,
                'brand' => null, 'model' => $v, 'specifications' => null,
                'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div, 'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => null, 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ];
        }

        // Webcam
        $b = $this->clean($webB); $p = $this->clean($webP);
        if ($b) {
            $records[] = [
                'category' => 'Peripherals', 'item_name' => 'Webcam - ' . $b,
                'serial_number' => null, 'property_number' => $p, 'par_number' => $p ?: ('PMS-WEB-' . $no),
                'brand' => $b, 'model' => null, 'specifications' => null,
                'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div, 'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => null, 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ];
        }

        // Speaker
        $b = $this->clean($spkB); $m = $this->clean($spkM); $p = $this->clean($spkP);
        if ($b || $m) {
            $records[] = [
                'category' => 'Peripherals', 'item_name' => 'Speaker - ' . ($b ?: 'Unknown'),
                'serial_number' => null, 'property_number' => $p, 'par_number' => $p ?: ('PMS-SPK-' . $no),
                'brand' => $b ?: null, 'model' => $m ?: null, 'specifications' => null,
                'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div, 'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => null, 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ];
        }

        // Headphones
        $b = $this->clean($hpB); $m = $this->clean($hpM);
        if ($b || $m) {
            $records[] = [
                'category' => 'Peripherals', 'item_name' => 'Headphones - ' . ($b ?: 'Unknown'),
                'serial_number' => null, 'property_number' => null, 'par_number' => 'PMS-HP-' . $no,
                'brand' => $b ?: null, 'model' => $m ?: null, 'specifications' => null,
                'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
                'office' => $div, 'department' => $div, 'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => null, 'acquisition_cost' => null,
                'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
            ];
        }

        // Others 1 & 2
        foreach ([['v' => $oth1, 'p' => $oth1P, 's' => 'O1'], ['v' => $oth2, 'p' => $oth2P, 's' => 'O2']] as $o) {
            $v = $this->clean($o['v']);
            $oP = $this->clean($o['p']);
            if ($v) {
                $records[] = [
                    'category' => 'Others', 'item_name' => $v,
                    'serial_number' => null, 'property_number' => $oP, 'par_number' => $oP ?: ('PMS-OTH-' . $no . '-' . $o['s']),
                    'brand' => null, 'model' => $v, 'specifications' => null,
                    'assigned_to_user' => $custodian?->id, 'region' => $actor->region, 'branch' => $actor->branch,
                    'office' => $div, 'department' => $div, 'status' => $custodian ? 'Active' : 'Spare',
                    'date_acquired' => null, 'acquisition_cost' => null,
                    'asset_notes' => "PMS import. Officer: {$officer}; Location: {$div}", '_is_component' => false,
                ];
            }
        }

        // MS Office — store in first record's specs if any record exists
        $officeVer = $this->clean($office);
        if ($officeVer && !empty($records)) {
            $records[0]['specifications'] = ['office' => 'MS OFFICE ' . $officeVer];
        }

        return [
            'records' => $records,
            'raw' => ['par_number' => '', 'article' => 'Peripherals', 'description' => 'Others', 'responsible_officer' => $officer, 'location' => $div],
            'warnings' => $warnings, 'custodian_matched' => (bool) $custodian,
            'responsible_officer_raw' => $officer, 'location_raw' => $div,
        ];
    }

    private function parseDescription(string $description, string $article): array
    {
        $text = trim($description);
        $upper = strtoupper($text);
        $parsed = [];

        // Note: serials are now extracted by extractSerials(); this is kept only
        // as a fallback signal for legacy callers.
        if (preg_match('/(?:S\/N|S\.N\.|SN|SERIAL(?:\s+NO\.?)?)\s*[:;#-]?\s*([A-Z0-9][A-Z0-9\-]+)/i', $text, $m)) {
            $parsed['serial_number'] = trim($m[1], " .;,)");
        }

        $brands = ['LENOVO', 'DELL', 'HP', 'HPE', 'ACER', 'ASUS', 'EPSON', 'CANON', 'BROTHER', 'SAMSUNG', 'CISCO', 'FORTINET', 'APC', 'EATON', 'FUJITSU', 'PANASONIC', 'RICOH', 'LEXMARK'];
        foreach ($brands as $brand) {
            if (str_contains($upper, $brand)) {
                $parsed['brand'] = $brand;
                $parsed['model'] = $this->extractModelAfterBrand($text, $brand);
                break;
            }
        }

        // CPU: handle modern formats like "CORE 7 240H", "CORE i5 14TH GEN", "INTEL CORE I7-13700H"
        if (preg_match('/((?:INTEL\s+)?CORE\s+(?:\d+\s+\d{3,5}[A-Z]*|I[3579]\s+\d+TH\s+GEN|(?:I[3579])?[-\s]?\d{3,5}[A-Z]*|2\s+DUO)|XEON\s+[A-Z0-9\-]+|RYZEN\s+\d\s*[A-Z0-9\-]*)/i', $text, $m)) {
            $parsed['processor'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match('/(\d+\s*GB)\s*(DDR[0-9])?\s*(?:RAM|MEMORY)?/i', $text, $m)) {
            $ram = strtoupper(str_replace(' ', '', $m[1]));
            if (!empty($m[2])) {
                $ram .= ' ' . strtoupper(trim($m[2]));
            }
            $parsed['ram'] = $ram;
        }

        if (preg_match('/((?:NVIDIA\s+)?(?:GeForce|GEFORCE|RTX|GTX|QUADRO|TITAN)\s+[A-Z0-9\s]+(?:\d+GB\s*(?:GDDR[0-9])?)?|INTEL\s+(?:HD\s+|UHD\s+|IRIS\s+)?GRAPHICS|AMD\s+(?:RADEON|FIREPRO)\s+[A-Z0-9\s]+)/i', $text, $m)) {
            $parsed['gpu'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match('/(?:MS\s+)?OFFICE\s+(?:PRO(?:FESSIONAL)?\s+)?(?:\d{4}|20\d{2})?(?:\s+(?:PRO|PLUS|HOME|STUDENT|BUSINESS|ENTERPRISE|STANDARD))?/i', $text, $m)) {
            $parsed['office'] = trim(preg_replace('/\s+/', ' ', $m[0]));
        }

        if (preg_match('/(\d+(?:\.\d+)?\s*(?:TB|GB)\s*(?:HDD|SSD|DRIVE|STORAGE)?)/i', $text, $m)) {
            $parsed['storage'] = trim($m[1]);
        }

        if (preg_match('/WINDOWS\s+(?:SERVER\s+)?[A-Z0-9\s]+/i', $text, $m)) {
            $parsed['operating_system'] = trim($m[0]);
        }

        if (preg_match('/\(([^)]*(?:COMPLETE SET|CPU ONLY|LOT|BUNDLE)[^)]*)\)/i', $text, $m)) {
            $parsed['set_type'] = trim($m[1]);
        } elseif (str_contains($upper, 'COMPLETE SET')) {
            $parsed['set_type'] = 'Complete Set';
        }

        if (preg_match('/(\d+\s*-\s*\d+\s*LPM|\d+\s*PPM|\d+\s*IPM)/i', $text, $m)) {
            $parsed['speed'] = strtoupper(str_replace(' ', '', $m[1]));
        }

        if (preg_match('/(\d+\s*(?:VA|WATTS?|TB|GB|PORTS?))/i', $text, $m)) {
            $parsed['capacity'] = strtoupper(trim($m[1]));
        }

        if (preg_match('/(\d+(?:\.\d+)?)["\']?\s*(?:INCH|"|IN)\s*(?:MONITOR|DISPLAY|SCREEN)/i', $text, $m)) {
            $parsed['monitor_size'] = trim($m[1]) . '"';
        }

        $articleUpper = strtoupper($article);
        if (str_contains($articleUpper, 'FIREWALL')) {
            $parsed['network_role'] = 'Firewall';
        } elseif (str_contains($articleUpper, 'SWITCH')) {
            $parsed['network_role'] = 'Network Switch';
        } elseif (str_contains($articleUpper, 'NAS') || str_contains($articleUpper, 'NETWORK ATTACHED STORAGE')) {
            $parsed['network_role'] = 'Network Attached Storage';
        } elseif (str_contains($articleUpper, 'SERVER')) {
            $parsed['network_role'] = 'Server';
        }

        return $parsed;
    }

    private function normalizeCategory(string $article, string $description): string
    {
        $text = strtoupper($article . ' ' . $description);
        return match (true) {
            str_contains($text, 'LAPTOP') => 'Laptop',
            str_contains($text, 'DESKTOP') || str_contains($text, 'COMPUTER') => 'Desktop',
            str_contains($text, 'MONITOR') => 'Monitor',
            str_contains($text, 'PRINTER') || str_contains($text, 'SCANNER') => 'Printer/Scanner',
            str_contains($text, 'SERVER') || str_contains($text, 'NETWORK') || str_contains($text, 'FIREWALL') || str_contains($text, 'SWITCH') || str_contains($text, 'NVR') || str_contains($text, 'VOIP') => 'Network/Server',
            str_contains($text, 'UPS') || str_contains($text, 'UNINTERRUPTED POWER') => 'Peripherals',
            default => 'Others',
        };
    }

    private function itemName(string $description, string $article): string
    {
        $first = trim(explode(',', $description)[0] ?? '');
        $first = trim(explode(' - ', $first)[0] ?? $first);
        return $first ?: trim($article);
    }

    private function extractModelAfterBrand(string $description, string $brand): ?string
    {
        $pattern = '/' . preg_quote($brand, '/') . '\s+([^,;(]+)/i';
        if (preg_match($pattern, $description, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function isTransferred(string $responsibleOfficer): bool
    {
        return strtoupper(trim($responsibleOfficer)) === 'TRANSFERRED';
    }

    /**
     * Match a CSV responsible officer string against active users.
     * Handles three real-world formats:
     *   1. "Last, First M."       (older CSV rows)
     *   2. "First Middle Last"    (newer CSV rows)
     *   3. "Last, First" inverted to "First Last"
     * Comparison is done on a normalized, punctuation-stripped uppercase form.
     */
    private function matchUser(string $responsibleOfficer, Collection $users): ?User
    {
        if ($this->isTransferred($responsibleOfficer)) {
            return null;
        }
        $candidates = array_filter(array_map(
            fn ($s) => $this->normName($s),
            $this->namePermutations($responsibleOfficer)
        ));

        if (empty($candidates)) {
            return null;
        }

        return $users->first(function (User $user) use ($candidates) {
            $userName = $this->normName($user->full_name ?? '');
            if ($userName === '') {
                return false;
            }
            if (in_array($userName, $candidates, true)) {
                return true;
            }
            // Allow matching when the user's normalized name is contained in
            // any candidate (handles extra middle names / suffixes).
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && (str_contains($candidate, $userName) || str_contains($userName, $candidate))) {
                    return true;
                }
            }
            return false;
        });
    }

    /**
     * Produce plausible permutations of an officer name so we can fuzzy-match
     * against DB users stored in either "Last, First" or "First Middle Last".
     */
    private function namePermutations(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return [];
        }
        $perms = [$name];

        // "Last, First M." -> "First M. Last"
        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));
            $perms[] = trim($first . ' ' . $last);
        } else {
            // "First Middle Last" -> "Last, First Middle"
            $tokens = preg_split('/\s+/', $name);
            if (count($tokens) >= 2) {
                $last = array_pop($tokens);
                $first = implode(' ', $tokens);
                $perms[] = $last . ', ' . $first;
                $perms[] = $first . ' ' . $last;
                $perms[] = $last . ' ' . $first;
            }
        }

        return array_unique($perms);
    }

    private function candidateUsers(User $actor): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->when($actor->region, fn ($q) => $q->where('region', $actor->region))
            ->when($actor->branch, fn ($q) => $q->where('branch', $actor->branch))
            ->get();
    }

    private function normalizeLocation(string $location): ?string
    {
        $location = trim(preg_replace('/\s*\([^)]*\)/', '', $location));
        $first = trim(explode('-', $location)[0] ?? $location);
        $first = trim(explode(' ', $first)[0] ?? $first);
        return $first ?: null;
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') return null;
        if (preg_match('/^\d{4}$/', $value)) return $value . '-01-01';
        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function toMoney(string $value): ?float
    {
        $value = trim(str_replace([',', ' '], '', $value));
        if ($value === '' || $value === '-') return null;
        return is_numeric($value) ? (float) $value : null;
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function norm(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function normName(string $value): string
    {
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Extract accessories from a set description. Returns an array of detected
     * components: [['type'=>'Monitor','brand'=>'ASUS','model'=>'23" MONITOR','serial'=>?], ...].
     *
     * Handles two real-world formats:
     *  1. Slash-separated — each segment is a component (first = parent, rest = accessories)
     *  2. Comma/keyword  — free-text description scanned for known keywords
     *
     * Rules:
     *  - Monitor       → always created (brand default to preceding word)
     *  - Speaker       → created only if brand present (GENIUS, CREATIVE, MULTIMEDIA)
     *  - UPS           → created only if brand present (SOCOMEC, PHOENIX, APC, EATON)
     *  - Webcam        → created only if brand present
     *  - Earphone      → created only if brand present
     *  - IP Phone/VoIP → created only if brand present
     *  - Mouse/Keyboard / Cables/Adapters → SKIPPED
     */
    private function extractAccessories(string $description): array
    {
        if (str_contains($description, '/')) {
            return $this->extractAccessoriesSlash($description);
        }
        return $this->extractAccessoriesKeyword($description);
    }

    /**
     * Parse slash-separated format. First segment is the parent item and is
     * skipped. Each subsequent segment is checked for accessory keywords.
     */
    private function extractAccessoriesSlash(string $description): array
    {
        $accessories = [];
        $parts = explode('/', $description);

        $skipWords = ['CABLE', 'ADAPTER', 'CONVERTER', 'HUB', 'SPLITTER', 'EXTENSION'];
        $upsBrands = ['SOCOMEC', 'PHOENIX', 'APC', 'EATON'];
        $speakerBrands = ['GENIUS', 'CREATIVE', 'MULTIMEDIA'];
        $knownBrands = ['ASUS', 'DELL', 'HP', 'HPE', 'LENOVO', 'ACER', 'SAMSUNG', 'AOC', 'LG',
                        'BENQ', 'PHILIPS', 'VIEWSONIC', 'AOPEN', 'LOGITECH', 'EPSON',
                        'BROTHER', 'CANON', 'FUJITSU', 'PANASONIC', 'RICOH', 'LEXMARK'];
        $stopWords = ['WITH', 'AND', 'OR', 'A', 'AN', 'THE', 'W/', 'WIRED', 'WIRELESS',
                      'USB', 'OPTICAL', 'MULTIMEDIA', 'STANDARD', 'SET', 'COMPLETE',
                      'BLACK', 'WHITE', 'GRAY', 'GREY', 'FOR', 'OF', 'IN', 'TO', 'LED', 'LCD'];

        for ($i = 1; $i < count($parts); $i++) {
            $segment = trim($parts[$i]);
            if ($segment === '') continue;
            $upper = strtoupper($segment);

            // Skip consumables
            $isSkip = false;
            foreach ($skipWords as $sw) {
                if (str_contains($upper, $sw)) { $isSkip = true; break; }
            }
            if ($isSkip) continue;

            // Detect type
            $type = null;
            if (preg_match('/\bMONITOR\b/', $upper)) $type = 'Monitor';
            elseif (preg_match('/\bUPS\b/', $upper) || str_contains($upper, 'UNINTERRUPTED')) $type = 'UPS';
            elseif (preg_match('/\bSPEAKER\b/', $upper)) $type = 'Speaker';
            elseif (preg_match('/\b(?:WEBCAM|CAMERA)\b/', $upper)) $type = 'Webcam';
            elseif (preg_match('/\b(?:EARPHONE|HEADSET)\b/', $upper)) $type = 'Earphone';
            elseif (preg_match('/\b(?:IP\s*PHONE|VOIP)\b/', $upper)) $type = 'IP Phone';
            elseif (preg_match('/\bMOUSE\b/', $upper) || preg_match('/\bKEYBOARD\b/', $upper)) continue;

            if ($type === null) continue;

            // Brand extraction
            $brand = $this->extractBrandFromSegment($segment, $type, $upper, $upsBrands, $speakerBrands, $knownBrands, $stopWords);

            // Non-Monitor types skip when brandless
            if ($type !== 'Monitor' && $brand === null) continue;

            // Model extraction
            $model = $this->extractModelFromSegment($segment, $brand, $type);

            $accessories[] = [
                'type' => $type,
                'brand' => $brand,
                'model' => $model,
            ];
        }

        return $accessories;
    }

    private function extractBrandFromSegment(string $segment, string $type, string $upper, array $upsBrands, array $speakerBrands, array $knownBrands, array $stopWords): ?string
    {
        switch ($type) {
            case 'UPS':
                foreach ($upsBrands as $b) {
                    if (str_contains($upper, $b)) return $b;
                }
                return null;
            case 'Speaker':
                foreach ($speakerBrands as $b) {
                    if (str_contains($upper, $b)) return $b;
                }
                return null;
        }

        // Try known brands first
        foreach ($knownBrands as $b) {
            if (str_contains($upper, $b)) return $b;
        }

        // Fallback: word immediately before type keyword
        $typeKw = $type === 'IP Phone' ? '(?:IP\s*)?PHONE|VOIP' : $type;
        if (preg_match('/([A-Z][A-Z0-9]{1,})\s+' . $typeKw . '/i', $segment, $m)) {
            $candidate = strtoupper($m[1]);
            if (!in_array($candidate, $stopWords)) return $candidate;
        }
        // Or the first word if it looks like a brand
        if (preg_match('/^([A-Z][A-Z0-9\-]+)\s/', $segment, $m)) {
            $candidate = strtoupper($m[1]);
            if (!in_array($candidate, $stopWords)) return $candidate;
        }
        return null;
    }

    private function extractModelFromSegment(string $segment, ?string $brand, string $type): ?string
    {
        $modelText = $segment;
        if ($brand) {
            $modelText = trim(str_ireplace($brand, '', $modelText));
        }
        $modelText = trim(preg_replace('/\b(?:MONITOR|SPEAKER|UPS|WEBCAM|CAMERA|EARPHONE|HEADSET|IP\s*PHONE|VOIP|WIRED|WIRELESS|USB|OPTICAL|MULTIMEDIA|STANDARD|LED|LCD|INCH)\b/i', '', $modelText));
        // Remove leading/trailing formatting punctuation (keep " for inches)
        $modelText = trim(preg_replace('/^[,\;\-\s]+/', '', trim($modelText)));
        $modelText = trim(preg_replace('/[,\;\-]+$/', '', $modelText));
        $modelText = trim(preg_replace('/\s+/', ' ', $modelText));
        return $modelText !== '' ? $modelText : null;
    }

    /**
     * Scan free-text description for accessory keywords (comma/keyword format).
     */
    private function extractAccessoriesKeyword(string $description): array
    {
        $accessories = [];
        $stopWords = ['WITH', 'AND', 'OR', 'A', 'AN', 'THE', 'W/', 'WIRED', 'WIRELESS',
                      'USB', 'OPTICAL', 'MULTIMEDIA', 'STANDARD', 'SET', 'COMPLETE',
                      'BLACK', 'WHITE', 'GRAY', 'GREY', 'FOR', 'OF', 'IN', 'TO', 'LED', 'LCD'];

        $patterns = [
            'Monitor'  => '/(?:(ASUS|DELL|HP|LENOVO|ACER|SAMSUNG|AOC|LG|BENQ|PHILIPS|VIEWSONIC|AOPEN)\s+)?(.*?)\s*(?:MONITOR|DISPLAY)(?:\s+(\d+\s*["\']?))?/i',
            'Speaker'  => '/(?:(GENIUS|CREATIVE|MULTIMEDIA|LOGITECH)\s+)?(?:USB\s+)?(?:MULTIMEDIA\s+)?SPEAKER(?:S)?(?:\s+([A-Z0-9][A-Z0-9\-]+))?/i',
            'UPS'      => '/(?:(SOCOMEC|PHOENIX|APC|EATON)\s+)?UPS(?:\s+([A-Z0-9][A-Z0-9\-]+(?:\s*(?:VA|W|WATTS?))?))?\b/i',
            'Webcam'   => '/(?:(LOGITECH|MICROSOFT|LENOVO|ASUS|HP|CREATIVE)\s+)?(?:WEBCAM|CAMERA)(?:\s+([A-Z0-9][A-Z0-9\-]+))?/i',
            'Earphone' => '/(?:(SONY|JBL|LOGITECH|PLANTRONICS|JABRA|LENOVO|ASUS|HP)\s+)?(?:EARPHONE|HEADSET)(?:S)?(?:\s+([A-Z0-9][A-Z0-9\-]+))?/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $description, $m)) {
                $rawBrand = trim($m[1] ?? '');
                $rawModel = trim($m[2] ?? '');

                $brandTokens = array_filter(
                    preg_split('/\s+/', strtoupper($rawBrand)),
                    fn ($t) => $t !== '' && !in_array($t, $stopWords, true)
                );
                $brand = implode(' ', $brandTokens) ?: null;

                // Non-Monitor types without brand → skip (unless model contains a known brand)
                if ($type !== 'Monitor' && $brand === null) continue;

                $model = null;
                if ($rawModel && preg_match('/[A-Z0-9]/i', $rawModel)) {
                    $model = strtoupper(trim(preg_replace('/\s+/', ' ', $rawModel)));
                }

                $accessories[] = array_filter([
                    'type' => $type,
                    'brand' => $brand,
                    'model' => $model,
                ], fn ($v) => $v !== null && $v !== '');
            }
        }

        return $accessories;
    }

    private function componentSuffix(string $type): string
    {
        return match ($type) {
            'Monitor' => 'M',
            'UPS'     => 'U',
            'Speaker' => 'S',
            'Webcam'  => 'W',
            'Earphone'=> 'E',
            'IP Phone' => 'IP',
            default   => 'X',
        };
    }
}
