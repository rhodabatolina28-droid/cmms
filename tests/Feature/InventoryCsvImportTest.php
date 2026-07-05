<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\InventoryAsset;
use App\Models\Request as RequestModel;
use App\Services\InventoryCsvImportService;

/**
 * Feature tests for the CSV import refactor.
 *
 * Covers:
 *  1. Singleton row → 1 asset created, no parent/child.
 *  2. Complete Set desktop with monitor S/N → 2 assets, shared PAR, parent_asset_id linked.
 *  3. Complete Set desktop WITHOUT monitor S/N → 2 assets, monitor serial_number = null.
 *  4. Duplicate PAR within a set is allowed; duplicate PAR across sets is blocked.
 *  5. asset_notes does NOT contain raw CPU/RAM specs (regression for goal #1).
 *  6. Existing ICT repair request on a parent asset still resolves after child Monitor is created.
 *  7. Preview summary correctly counts set_rows and component_rows.
 *
 * Uses SQLite in-memory (RefreshDatabase) — no MySQL needed.
 */
class InventoryCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private InventoryCsvImportService $importer;
    private User $supplyOfficer;
    private int $userCounter = 0;

    // =========================================================================
    // SETUP
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = app(InventoryCsvImportService::class);

        $this->supplyOfficer = $this->makeUser([
            'full_name'  => 'Supply Officer',
            'email'      => 'supply@test.com',
            'role'       => 'supply_officer',
            'region'     => 'NCR',
            'branch'     => 'NCMB Main Office',
            'office'     => 'Administrative Division',
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function makeUser(array $attrs = []): User
    {
        $this->userCounter++;
        return User::create(array_merge([
            'full_name'  => 'Test User ' . $this->userCounter,
            'email'      => 'user' . $this->userCounter . '@test.com',
            'password'   => bcrypt('password'),
            'role'       => 'user',
            'is_active'  => true,
            'office'     => null,
            'department' => null,
            'branch'     => null,
            'region'     => 'NCR',
        ], $attrs));
    }

    /**
     * Write CSV content to a temp file and return its path.
     * The caller is responsible for unlinking after use (or PHP auto-cleans on shutdown).
     */
    private function csvFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/ict_test_' . uniqid() . '.csv';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Build a minimal CSV row string. Columns:
     * 0:PAR, 1:Article, 2:Description, 3:Date, 4:SerialBlock,
     * 5:OldProp, 6:NewPropNo, 7:Unit, 8:UnitValue,
     * 9:QtyCard, 10:QtyPhys, 11:ShortQty, 12:ShortVal, 13:Officer, 14:Location
     */
    private function csvRow(array $fields): string
    {
        // Wrap each field in double quotes, escape internal quotes.
        $quoted = array_map(fn ($f) => '"' . str_replace('"', '""', (string) $f) . '"', $fields);
        return implode(',', $quoted) . "\n";
    }

    // =========================================================================
    // TEST 1: Singleton (non-set) row → exactly 1 asset, no parent/child
    // =========================================================================

    public function test_singleton_row_creates_one_asset()
    {
        $row = $this->csvRow([
            'ICT-2024-100',        // PAR
            'Laptop Computer',     // Article
            'HP ProBook 450 G8, Intel Core i5-1135G7, 8GB RAM, 512GB SSD, Windows 11, S/N: HP-LT-100',
            '2022-08-15',          // Date
            'S/N: HP-LT-100',      // SerialBlock
            '',                    // OldProp
            'PROP-2024-100',       // NewPropNo
            'UNIT',                // Unit — NOT a set
            '55000',               // UnitValue
            '1', '1', '0', '0',   // Qty cols
            'TRANSFERRED',         // Officer — becomes Spare
            'Main Office',         // Location
        ]);

        $path = $this->csvFile($row);
        $preview = $this->importer->preview($path, $this->supplyOfficer);

        // Should yield exactly 1 item with 1 record (singleton)
        $this->assertCount(1, $preview['items']);
        $this->assertCount(1, $preview['items'][0]['records']);
        $this->assertFalse($preview['items'][0]['is_set']);

        // Summary: no sets created
        $this->assertEquals(0, $preview['summary']['set_rows']);
        $this->assertEquals(0, $preview['summary']['component_rows']);

        unlink($path);
    }

    // =========================================================================
    // TEST 2: Complete Set with monitor S/N → 2 assets, shared PAR, linked
    // =========================================================================

    public function test_complete_set_with_monitor_serial_creates_two_linked_assets()
    {
        // Create custodian so the asset gets Assigned, not Spare
        $custodian = $this->makeUser([
            'full_name' => 'Juan Dela Cruz',
            'region'    => 'NCR',
            'branch'    => 'NCMB Main Office',
        ]);

        $row = $this->csvRow([
            'ICT-2024-200',
            'Desktop Computer',
            'LENOVO ThinkCentre M70q, Intel Core i5-10400, 8GB RAM, 256GB SSD, Windows 10 Pro (Complete Set) w/ 22-inch MONITOR',
            '2022-06-01',
            'S/N: CPU-AA-111 (CPU); S/N: MON-BB-222 (MONITOR)',
            '',
            'PROP-2024-200',
            'SET',
            '45000',
            '1', '1', '0', '0',
            'Juan Dela Cruz',
            'Main Office',
        ]);

        $path = $this->csvFile($row);
        $rows = $this->importer->importableRows($path, $this->supplyOfficer);

        $this->assertCount(1, $rows, 'One CSV row should produce 1 preview item');
        $this->assertTrue($rows[0]['is_set'], 'Row should be flagged as a set');
        $this->assertCount(2, $rows[0]['records'], 'Set row must split into 2 records');

        // Commit the import
        $created = 0;
        $parentByPar = [];

        \Illuminate\Support\Facades\DB::beginTransaction();
        foreach ($rows as $row) {
            foreach ($row['records'] as $recordData) {
                $isComponent = !empty($recordData['_is_component']);
                unset($recordData['_is_component']);

                if ($isComponent) {
                    $parKey = strtoupper(trim($recordData['par_number'] ?? ''));
                    if ($parKey && isset($parentByPar[$parKey])) {
                        $recordData['parent_asset_id'] = $parentByPar[$parKey];
                    }
                }

                $asset = InventoryAsset::create($recordData);

                if (!$isComponent) {
                    $parKey = strtoupper(trim($asset->par_number ?? ''));
                    if ($parKey) $parentByPar[$parKey] = $asset->asset_id;
                }

                $created++;
            }
        }
        \Illuminate\Support\Facades\DB::commit();

        $this->assertEquals(2, $created, 'Should create exactly 2 asset records');

        // Parent (CPU)
        $cpu = InventoryAsset::where('category', 'Desktop')->first();
        $this->assertNotNull($cpu);
        $this->assertEquals('ICT-2024-200', $cpu->par_number);
        $this->assertEquals('PROP-2024-200', $cpu->property_number);
        $this->assertEquals('CPU-AA-111', $cpu->serial_number);
        $this->assertNull($cpu->parent_asset_id, 'Parent CPU must have no parent');

        // Child (Monitor)
        $monitor = InventoryAsset::where('category', 'Monitor')->first();
        $this->assertNotNull($monitor);
        $this->assertEquals('ICT-2024-200', $monitor->par_number, 'Monitor shares PAR with CPU');
        $this->assertEquals('PROP-2024-200-M', $monitor->property_number, 'Monitor has -M suffix');
        $this->assertEquals('MON-BB-222', $monitor->serial_number);
        $this->assertEquals($cpu->asset_id, $monitor->parent_asset_id, 'Monitor parent_asset_id must point to CPU');

        unlink($path);
    }

    // =========================================================================
    // TEST 3: Complete Set WITHOUT monitor S/N → 2 assets, monitor serial null
    // =========================================================================

    public function test_complete_set_without_monitor_serial_creates_null_serial_monitor()
    {
        $row = $this->csvRow([
            'ICT-2024-300',
            'Desktop Computer',
            'DELL OptiPlex 3080, Intel Core i3-10100, 8GB RAM, 1TB HDD, Windows 10 (Complete Set) w/ MONITOR',
            '2021-03-10',
            '',    // No serial block at all
            '',
            'PROP-2024-300',
            'SET',
            '38000',
            '1', '1', '0', '0',
            'TRANSFERRED',
            'Branch Office',
        ]);

        $path = $this->csvFile($row);
        $preview = $this->importer->preview($path, $this->supplyOfficer);

        $this->assertCount(1, $preview['items']);
        $item = $preview['items'][0];

        // Must be flagged as a set (2 records)
        $this->assertTrue($item['is_set']);
        $this->assertCount(2, $item['records']);

        // Monitor record should have null serial
        $monitorRecord = collect($item['records'])->first(fn ($r) => !empty($r['_is_component']));
        $this->assertNotNull($monitorRecord);
        $this->assertNull($monitorRecord['serial_number'], 'Monitor with no S/N should have null serial_number');

        // A warning should mention S/N pending verification
        $hasSnWarning = collect($item['warnings'])->contains(fn ($w) => str_contains(strtolower($w), 'monitor s/n not found') || str_contains(strtolower($w), 'null serial'));
        $this->assertTrue($hasSnWarning, 'Should warn about missing monitor S/N');

        unlink($path);
    }

    // =========================================================================
    // TEST 4: Duplicate PAR within a set allowed; duplicate PAR across sets blocked
    // =========================================================================

    public function test_par_uniqueness_rules()
    {
        // Row 1: Complete set, PAR = ICT-2024-400
        $row1 = $this->csvRow([
            'ICT-2024-400',
            'Desktop Computer',
            'HP Desktop (Complete Set) w/ MONITOR',
            '2022-01-01',
            'S/N: CPU-400 (CPU); S/N: MON-400 (MONITOR)',
            '',
            'PROP-2024-400',
            'SET',
            '40000',
            '1', '1', '0', '0',
            'TRANSFERRED',
            'Main Office',
        ]);

        // Row 2: Different singleton, same PAR = ICT-2024-400 → should be BLOCKED
        $row2 = $this->csvRow([
            'ICT-2024-400',        // <-- duplicate PAR
            'Printer',
            'EPSON L3210 EcoTank, S/N: EPX-400',
            '2022-05-01',
            'S/N: EPX-400',
            '',
            'PROP-2024-401',
            'UNIT',
            '8500',
            '1', '1', '0', '0',
            'TRANSFERRED',
            'Main Office',
        ]);

        $path = $this->csvFile($row1 . $row2);
        $preview = $this->importer->preview($path, $this->supplyOfficer);

        $this->assertCount(2, $preview['items']);

        // The set row (row 1) should be valid (no PAR duplicate error)
        $setItem = $preview['items'][0];
        $parErrors = collect($setItem['errors'])->filter(fn ($e) => str_contains($e, 'PAR'));
        $this->assertEmpty($parErrors, 'Set parent row should not get a duplicate PAR error for its own internal PAR');

        // The singleton row (row 2) SHOULD be blocked with a duplicate PAR error
        $singletonItem = $preview['items'][1];
        $this->assertEquals('blocked', $singletonItem['status'], 'Singleton with duplicate PAR must be blocked');
        $hasDupParError = collect($singletonItem['errors'])->contains(fn ($e) => str_contains(strtolower($e), 'duplicate par'));
        $this->assertTrue($hasDupParError, 'Blocked item should carry a duplicate PAR error');

        unlink($path);
    }

    // =========================================================================
    // TEST 5: asset_notes must NOT contain raw CPU/RAM specs
    // =========================================================================

    public function test_asset_notes_does_not_contain_raw_specs()
    {
        $row = $this->csvRow([
            'ICT-2024-500',
            'Laptop Computer',
            'HP ProBook 450 G8, Intel Core i5-1135G7, 8GB RAM, 512GB SSD, Windows 11 Pro, S/N: HP-LT-500',
            '2022-09-01',
            'S/N: HP-LT-500',
            '',
            'PROP-2024-500',
            'UNIT',
            '55000',
            '1', '1', '0', '0',
            'TRANSFERRED',
            'RID Office',
        ]);

        $path = $this->csvFile($row);
        $preview = $this->importer->preview($path, $this->supplyOfficer);

        $this->assertCount(1, $preview['items']);
        $record = $preview['items'][0]['records'][0];
        $notes = $record['asset_notes'] ?? '';

        // These must NOT appear in asset_notes
        $forbiddenPatterns = ['Intel Core', 'i5-', 'i3-', 'i7-', 'RAM', 'SSD', 'HDD', 'Windows'];
        foreach ($forbiddenPatterns as $pattern) {
            $this->assertStringNotContainsStringIgnoringCase(
                $pattern,
                $notes,
                "asset_notes should not contain raw spec keyword: '{$pattern}'"
            );
        }

        // Notes should only carry officer + location audit info
        $this->assertStringContainsString('CSV import', $notes);

        unlink($path);
    }

    // =========================================================================
    // TEST 6: ICT repair request on parent asset survives monitor child creation
    // =========================================================================

    public function test_ict_repair_link_survives_set_split()
    {
        // Pre-existing parent CPU asset (manually created, not via CSV)
        $existingCpu = InventoryAsset::create([
            'category'        => 'Desktop',
            'item_name'       => 'Pre-existing CPU',
            'serial_number'   => 'PRE-CPU-001',
            'property_number' => 'PRE-PROP-001',
            'par_number'      => 'PRE-PAR-001',
            'status'          => 'Active',
            'region'          => 'NCR',
            'branch'          => 'NCMB Main Office',
        ]);

        // Create a repair request linked to that CPU
        $repairRequest = RequestModel::create([
            'request_number'  => 'ICT-REQ-001',
            'type'            => 'ICT',
            'status'          => 'Pending',
            'requestor_name'  => 'Test Requestor',
            'description'     => 'Repair request for testing link safety',
            'user_id'         => $this->supplyOfficer->id,
            'linked_asset_id' => $existingCpu->asset_id,
            'branch'          => 'NCMB Main Office',
        ]);

        // Now import a NEW set with a different PAR — this must NOT disturb the existing link
        $row = $this->csvRow([
            'ICT-2024-600',
            'Desktop Computer',
            'ASUS Desktop (Complete Set) w/ MONITOR S/N: MON-600',
            '2023-01-01',
            'S/N: CPU-600 (CPU); S/N: MON-600 (MONITOR)',
            '',
            'PROP-2024-600',
            'SET',
            '42000',
            '1', '1', '0', '0',
            'TRANSFERRED',
            'Main Office',
        ]);

        $path = $this->csvFile($row);
        $rows = $this->importer->importableRows($path, $this->supplyOfficer);

        $parentByPar = [];
        \Illuminate\Support\Facades\DB::beginTransaction();
        foreach ($rows as $row) {
            foreach ($row['records'] as $recordData) {
                $isComponent = !empty($recordData['_is_component']);
                unset($recordData['_is_component']);
                if ($isComponent) {
                    $parKey = strtoupper(trim($recordData['par_number'] ?? ''));
                    if ($parKey && isset($parentByPar[$parKey])) {
                        $recordData['parent_asset_id'] = $parentByPar[$parKey];
                    }
                }
                $asset = InventoryAsset::create($recordData);
                if (!$isComponent) {
                    $parKey = strtoupper(trim($asset->par_number ?? ''));
                    if ($parKey) $parentByPar[$parKey] = $asset->asset_id;
                }
            }
        }
        \Illuminate\Support\Facades\DB::commit();

        // The pre-existing repair request must still point to the original CPU
        $repairRequest->refresh();
        $this->assertEquals(
            $existingCpu->asset_id,
            $repairRequest->linked_asset_id,
            'Creating new set assets must never alter existing ICT repair request links'
        );

        unlink($path);
    }

    // =========================================================================
    // TEST 7: Preview summary counts set_rows and component_rows correctly
    // =========================================================================

    public function test_preview_summary_counts_sets_and_components()
    {
        // 2 set rows + 1 singleton
        $setRow1 = $this->csvRow([
            'ICT-2024-701', 'Desktop Computer',
            'HP Desktop (Complete Set) w/ MONITOR', '2022-01-01',
            'S/N: CPU-701 (CPU); S/N: MON-701 (MONITOR)',
            '', 'PROP-2024-701', 'SET', '40000',
            '1', '1', '0', '0', 'TRANSFERRED', 'Main Office',
        ]);
        $setRow2 = $this->csvRow([
            'ICT-2024-702', 'Desktop Computer',
            'DELL Desktop Complete Set w/ MONITOR', '2022-01-01',
            'S/N: CPU-702 (CPU)', // monitor S/N absent
            '', 'PROP-2024-702', 'SET', '41000',
            '1', '1', '0', '0', 'TRANSFERRED', 'Branch Office',
        ]);
        $singletonRow = $this->csvRow([
            'ICT-2024-703', 'Printer',
            'EPSON L3210, S/N: EPX-703', '2023-01-01',
            'S/N: EPX-703',
            '', 'PROP-2024-703', 'UNIT', '8500',
            '1', '1', '0', '0', 'TRANSFERRED', 'Main Office',
        ]);

        $path = $this->csvFile($setRow1 . $setRow2 . $singletonRow);
        $preview = $this->importer->preview($path, $this->supplyOfficer);

        $summary = $preview['summary'];

        $this->assertEquals(3, $summary['total_rows'], 'Total CSV rows = 3');
        $this->assertEquals(2, $summary['set_rows'], '2 set rows expected');
        $this->assertEquals(2, $summary['component_rows'], '2 monitor components (one per set)');

        unlink($path);
    }
}
