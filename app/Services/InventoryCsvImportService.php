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
        $rows = $this->assetRows($path);
        $users = $this->candidateUsers($actor);

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
            $mapped = $this->mapRow($row, $actor, $users);
            $warnings = $mapped['warnings'];
            $records = $mapped['records'];
            $errors = [];

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

                // Property number: always enforced (monitor carries the -M suffix to stay unique).
                if (!$propKey) {
                    $errors[] = 'Missing property number.';
                } elseif (isset($seenProperty[$propKey]) || InventoryAsset::where('property_number', $record['property_number'])->exists()) {
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

            if (count($records) > 1) {
                $summary['set_rows']++;
                $summary['component_rows'] += count($records) - 1;
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
                'is_set' => count($records) > 1,
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

    private function assetRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return [];

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $row = array_pad($row, 16, '');
            if (str_starts_with(trim((string) $row[0]), 'ICT-')) {
                $rows[] = $row;
            }
        }
        fclose($handle);
        return $rows;
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

        // Detect peripherals mentioned in the description and store them as
        // rich objects {type, brand, model} inside the parent asset's specs.
        // They do NOT become separate inventory records.
        $setIncludes = $isSet ? $this->extractSetIncludes($description) : [];

        $baseSpecs = array_filter([
            'cpu' => $parsed['processor'] ?? null,
            'ram' => $parsed['ram'] ?? null,
            'hd1' => $parsed['storage'] ?? null,
            'os' => $parsed['operating_system'] ?? null,
            'set_type' => $parsed['set_type'] ?? null,
            'speed' => $parsed['speed'] ?? null,
            'capacity' => $parsed['capacity'] ?? null,
            'network_role' => $parsed['network_role'] ?? null,
            'set_includes' => !empty($setIncludes) ? $setIncludes : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        $itemName = $this->itemName($description, $raw['article']);
        $shouldSplit = $this->shouldSplitSet($raw, $category, $serials, $parsed, $description);

        // Build the parent (main unit) record always.
        $parentCpuSerial = $serials['cpu'] ?? ($parsed['serial_number'] ?? null);
        $parentNotes = $this->buildNotes($raw, $isTransferred, $shouldSplit && empty($serials['monitor']));

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

        // Split out a Monitor as its own accountable record when this row is a
        // Desktop/Laptop Complete Set that includes a monitor.
        if ($shouldSplit) {
            $monitorSerial = $serials['monitor'] ?? null;
            $monitorBrand = $this->extractMonitorBrand($description, $parsed);
            $monitorModel = $this->extractMonitorModel($description, $parsed);

            $monitorSpecs = array_filter([
                'monitor_brand' => $monitorBrand,
                'monitor_model' => $monitorModel,
                'monitor_size' => $parsed['monitor_size'] ?? null,
                'monitor_resolution' => $parsed['monitor_resolution'] ?? null,
                'monitor_notes' => $monitorSerial ? null : 'S/N to verify during physical inventory',
            ], fn ($value) => $value !== null && $value !== '');

            $records[] = [
                'category' => 'Monitor',
                'item_name' => 'Monitor (set component)',
                'serial_number' => $monitorSerial,
                'property_number' => $raw['new_property_number'] . '-M',
                'par_number' => $raw['par_number'],
                'brand' => $monitorBrand,
                'model' => $monitorModel,
                'specifications' => $monitorSpecs,
                'assigned_to_user' => $custodian?->id,
                'region' => $actor->region,
                'branch' => $actor->branch,
                'office' => $custodian?->office ?: $office,
                'department' => $custodian?->department,
                'status' => $custodian ? 'Active' : 'Spare',
                'date_acquired' => $this->parseDate($raw['date_of_acquisition']),
                'acquisition_cost' => null,
                'asset_notes' => $parentNotes,
                '_is_component' => true,
            ];

            if (empty($monitorSerial)) {
                $warnings[] = 'Monitor S/N not found in CSV — created with null serial for verification.';
            }
        }

        // Peripherals (Keyboard, Mouse, Speaker, UPS, Camera) are stored inside
        // the parent asset's specifications['set_includes'] array — NOT as separate
        // inventory records. Only Monitor is split as its own accountable record.
        // (set_includes was already injected into $baseSpecs and applied to $parent above)

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
     * Decide whether a row should be split into a CPU parent + Monitor child.
     * Triggered only for Desktop/Laptop Complete Sets that mention a monitor
     * or carry a monitor S/N — keyboards/mice/etc. stay inside the CPU specs.
     */
    private function shouldSplitSet(array $raw, string $category, array $serials, array $parsed, string $description): bool
    {
        if (!in_array($category, ['Desktop', 'Laptop'], true)) {
            return false;
        }
        $unitUpper = strtoupper($raw['unit_measure']);
        $descUpper = strtoupper($description);
        $isSet = $unitUpper === 'SET'
            || str_contains($descUpper, 'COMPLETE SET')
            || !empty($parsed['set_type']);
        if (!$isSet) {
            return false;
        }
        $mentionsMonitor = str_contains($descUpper, 'MONITOR')
            || !empty($serials['monitor']);

        return $mentionsMonitor;
    }

    /**
     * Extract CPU and monitor serial numbers. Newer CSVs carry the S/N block
     * in column 4 with explicit "(CPU)"/"(monitor)" labels and embedded
     * newlines. Older CSVs only carry one S/N inline in the description.
     *
     * Returns ['cpu' => ?string, 'monitor' => ?string].
     */
    private function extractSerials(string $serialBlock, string $description): array
    {
        $cpu = null;
        $monitor = null;

        // Prefer the structured col-4 block when present.
        if ($serialBlock !== '' && stripos($serialBlock, 'S/N') !== false) {
            preg_match_all('/S\/N\s*[:;#\-]?\s*([A-Z0-9][A-Z0-9\-]*)\s*(?:\((CPU|MONITOR)\))?/i', $serialBlock, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $value = trim($m[1], " .;,)");
                $label = strtoupper($m[2] ?? '');
                if ($label === 'MONITOR' || ($monitor === null && $cpu !== null && $label === '')) {
                    if ($label === 'MONITOR') {
                        $monitor = $value;
                    } else {
                        // Unlabeled second S/N — best effort assign to monitor.
                        $monitor = $monitor ?? $value;
                    }
                } else {
                    $cpu = $cpu ?? $value;
                }
            }
        }

        // Fall back to the description for the (single) S/N when col 4 was empty.
        if ($cpu === null && preg_match('/(?:S\/N|S\.N\.|SN|SERIAL(?:\s+NO\.?)?)\s*[:;#\-]?\s*([A-Z0-9][A-Z0-9\-]+)/i', $description, $m)) {
            $cpu = trim($m[1], " .;,)");
        }

        return array_filter([
            'cpu' => $cpu,
            'monitor' => $monitor,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function buildNotes(array $raw, bool $isTransferred, bool $monitorNeedsVerification): string
    {
        $parts = ["CSV import. Officer: {$raw['responsible_officer']}; Location: {$raw['location']}"];
        if ($isTransferred) {
            $parts[] = 'TRANSFERRED per CSV — awaiting reassignment';
        }
        if ($monitorNeedsVerification) {
            $parts[] = 'Monitor S/N to verify during physical inventory';
        }
        return trim(implode('; ', $parts));
    }

    private function extractMonitorBrand(string $description, array $parsed): ?string
    {
        if (preg_match('/(ASUS|DELL|HP|LENOVO|ACER|SAMSUNG|AOC|LG|BENQ|PHILIPS|VIEWSONIC)\s+(?:[A-Z0-9][A-Z0-9\-]{2,}\s+)?(?:LED\s+)?(?:LCD\s+)?(?:MONITOR|FH|HZ|FLATRON|DISPLAY)/i', $description, $m)) {
            return strtoupper(trim($m[1]));
        }
        return $parsed['brand'] ?? null;
    }

    private function extractMonitorModel(string $description, array $parsed): ?string
    {
        // Surface a monitor-specific model only when explicitly described.
        // Example: "SAMSUNG 34-inch MONITOR" or "HP 324pf 100Hz MONITOR"
        if (preg_match('/(?:ASUS|DELL|HP|LENOVO|ACER|SAMSUNG|AOC|LG|BENQ|PHILIPS|VIEWSONIC)\s+(?:SERIES\s+[A-Z0-9\s]+\s+)?([A-Z0-9][A-Z0-9\-]{2,})\s+(?:LED\s+)?(?:LCD\s+)?(?:MONITOR|FH|HZ|FLATRON|DISPLAY)/i', $description, $m)) {
            return trim($m[1]);
        }
        return null;
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

        $brands = ['LENOVO', 'DELL', 'HP', 'HPE', 'ACER', 'ASUS', 'EPSON', 'CANON', 'BROTHER', 'SAMSUNG', 'CISCO', 'FORTINET', 'APC', 'EATON', 'FUJITSU', 'PANASONIC', 'RICOH'];
        foreach ($brands as $brand) {
            if (str_contains($upper, $brand)) {
                $parsed['brand'] = $brand;
                $parsed['model'] = $this->extractModelAfterBrand($text, $brand);
                break;
            }
        }

        if (preg_match('/((?:INTEL\s+)?CORE\s+I[3579][-\s]?\d{3,5}[A-Z]*|INTEL\s+CORE\s+2\s+DUO|XEON\s+[A-Z0-9\-]+|RYZEN\s+\d\s*[A-Z0-9\-]*)/i', $text, $m)) {
            $parsed['processor'] = trim($m[1]);
        }

        if (preg_match('/(\d+\s*GB)\s*(?:RAM|MEMORY)?/i', $text, $m)) {
            $parsed['ram'] = strtoupper(str_replace(' ', '', $m[1]));
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
     * Parse peripheral accessories from a set description.
     * Returns an array of objects: [['type'=>'Speaker','brand'=>'HT','model'=>'HT-208'], ...]
     *
     * Handles patterns like:
     *  - "MULTIMEDIA USB SPEAKER HT-208"
     *  - "APC UPS 650VA"
     *  - "LOGITECH WEBCAM C270"
     *  - "WIRED USB KEYBOARD"
     *  - "OPTICAL MOUSE"
     *  - "ASUS EXPERTCENTER C2241FH 100HZ MONITOR"  ← already split as separate record, skip here
     */
    private function extractSetIncludes(string $description): array
    {
        $items = [];

        // Helper: grab the token(s) immediately before the keyword as brand,
        // and any alphanumeric token after the keyword as model.
        // Pattern: ([BRAND_WORDS...]) KEYWORD ([MODEL])?
        $peripherals = [
            'Keyboard' => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,3})\s+)?KEYBOARD(?:\s+([\w\-]+))?/i',
            'Mouse'    => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,3})\s+)?MOUSE(?:\s+([\w\-]+))?/i',
            'Speaker'  => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,3})\s+)?SPEAKER(?:S)?(?:\s+([\w\-]+))?/i',
            'UPS'      => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,2})\s+)?UPS(?:\s+([\w\-]+(?:\s*(?:VA|W|WATTS?))?))?\b/i',
            'Camera'   => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,2})\s+)?(?:CAMERA|WEBCAM)(?:\s+([\w\-]+))?/i',
            'Scanner'  => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,2})\s+)?SCANNER(?:\s+([\w\-]+))?/i',
            'Headset'  => '/(?:([\w\-\/&]+(?:\s+[\w\-\/&]+){0,2})\s+)?(?:HEADSET|EARPHONE)(?:S)?(?:\s+([\w\-]+))?/i',
        ];

        // Words that are NOT brands (articles, descriptors, conjunctions)
        $stopWords = ['WITH', 'AND', 'OR', 'A', 'AN', 'THE', 'W/', 'WIRED', 'WIRELESS',
                      'USB', 'OPTICAL', 'MULTIMEDIA', 'STANDARD', 'SET', 'COMPLETE',
                      'BLACK', 'WHITE', 'GRAY', 'GREY', 'FOR', 'OF', 'IN', 'TO'];

        foreach ($peripherals as $type => $pattern) {
            if (preg_match($pattern, $description, $m)) {
                $rawBrand = trim($m[1] ?? '');
                $rawModel = trim($m[2] ?? '');

                // Strip stop words from the brand side
                $brandTokens = array_filter(
                    preg_split('/\s+/', strtoupper($rawBrand)),
                    fn ($t) => $t !== '' && !in_array($t, $stopWords, true)
                );
                $brand = implode(' ', $brandTokens) ?: null;

                // Model: must look like a real model code (letters+digits or has a dash)
                $model = null;
                if ($rawModel && preg_match('/[0-9]/', $rawModel)) {
                    $model = strtoupper(trim($rawModel));
                }

                $items[] = array_filter([
                    'type'  => $type,
                    'brand' => $brand,
                    'model' => $model,
                ], fn ($v) => $v !== null && $v !== '');
            }
        }

        return $items;
    }
}
