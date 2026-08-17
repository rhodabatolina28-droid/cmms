<?php

namespace App\Services;

use App\Models\PartUnit;

/**
 * Parser at sorter para sa Parts & Consumables CSV import.
 * Format target: "PROPERTY NUMBERS - INTANGIBLE" (per-unit rows).
 */
class PartsCsvImportService
{
    /**
     * Mag-parse at mag-summary para sa preview (walang side effect).
     *
     * @return array{summary: array<string,int>, items: array<int,array<string,mixed>>}
     */
    public function preview(string $path)
    {
        $rows = $this->normalize($this->parseRows($path));

        $parts = [];
        $seenSerial = [];
        $duplicateSerials = [];

        foreach ($rows as $row) {
            $key = strtolower(trim($row['item_name'] . '|' . ($row['category'] ?? '')));
            $parts[$key] = ($parts[$key] ?? 0) + 1;

            if ($row['serial'] !== '') {
                $k = $key . '||' . $row['serial'];
                if (isset($seenSerial[$k])) {
                    $duplicateSerials[] = $row['serial'];
                } else {
                    $seenSerial[$k] = true;
                }
            }
        }

        return [
            'summary' => [
                'rows' => count($rows),
                'distinct_parts' => count($parts),
                'duplicate_serials' => count(array_unique($duplicateSerials)),
            ],
            'items' => collect($parts)->map(fn ($count, $part) => ['part' => $part, 'count' => $count])->values(),
        ];
    }

    /**
     * Normalized rows na handa nang i-commit.
     *
     * @return array<int,array<string,mixed>>
     */
    public function importableRows(string $path): array
    {
        return $this->normalize($this->parseRows($path));
    }

    protected function parseRows(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * I-convert ang raw CSV rows sa normalized array (skip ang junk header/section).
     *
     * @param  array<int,array>  $rawRows
     * @return array<int,array<string,mixed>>
     */
    protected function normalize(array $rawRows): array
    {
        $out = [];
        // Skip nang maaga ang mga obvious header/section rows (0-4).
        $dataRows = array_slice($rawRows, 4);

        foreach ($dataRows as $row) {
            if (! is_array($row) || count($row) < 3) {
                continue;
            }
            // Trim ang lahat ng field.
            $row = array_map(fn ($v) => trim((string) ($v ?? '')), $row);

            $itemName = $row[2] ?? '';
            if ($itemName === '') {
                continue; // walang description → junk/header/section
            }

            // Unit value — alisin ang mga comma/currency at i-sanitize.
            $value = str_replace(',', '', $row[7] ?? '');
            $value = is_numeric($value) ? (float) $value : null;

            $out[] = [
                'item_name' => $itemName,
                'category' => ($row[1] ?? '') !== '' ? $row[1] : 'Parts',
                'serial' => $row[4] ?? '',
                'property' => $row[5] ?? '',
                'unit' => ($row[6] ?? '') !== '' ? $row[6] : 'pcs',
                'unit_value' => $value,
            ];
        }

        return $out;
    }
}