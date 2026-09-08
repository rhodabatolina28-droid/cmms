<?php

namespace Tests\Feature;

use App\Models\InventoryAsset;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CountReportArchiveTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Test User ' . $this->counter,
            'email' => 'count-arc-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
        ], $attributes));
    }

    private function asset(array $attributes = []): InventoryAsset
    {
        $this->counter++;

        return InventoryAsset::create(array_merge([
            'category' => 'Desktop',
            'item_name' => 'Desktop ' . $this->counter,
            'serial_number' => 'PC-SN-' . $this->counter,
            'property_number' => 'PC-PROP-' . $this->counter,
            'par_number' => 'PC-PAR-' . $this->counter,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Active',
        ], $attributes));
    }

    private function makeSession(User $actor, array $overrides = []): PhysicalCountSession
    {
        return PhysicalCountSession::create(array_merge([
            'started_by' => $actor->id,
            'started_at' => now(),
            'status' => 'Completed',
            'completed_at' => now(),
            'scope_region' => $actor->region,
            'scope_branch' => $actor->branch,
        ], $overrides));
    }

    public function test_completed_session_archives_to_count_pdfs_folder(): void
    {
        Storage::fake('local');

        $supply = $this->user(['role' => 'supply_officer', 'can_supply' => true, 'full_name' => 'Supply Officer Arc']);
        $custodian = $this->user(['full_name' => 'Maria Arc']);
        $a1 = $this->asset(['assigned_to_user' => $custodian->id]);
        $a2 = $this->asset(['assigned_to_user' => $custodian->id]);
        $this->asset(['status' => 'Spare']); // unassigned â€” must still appear

        $session = $this->makeSession($supply);

        PhysicalCount::create([
            'session_id' => $session->id,
            'asset_id' => $a1->asset_id,
            'counted_by' => $supply->id,
            'status' => 'Present',
            'counted_at' => now(),
        ]);
        PhysicalCount::create([
            'session_id' => $session->id,
            'asset_id' => $a2->asset_id,
            'counted_by' => $supply->id,
            'status' => 'Missing',
            'counted_at' => now(),
        ]);

        $path = (new \App\Actions\PhysicalCount\ArchiveCountReportAction)->generate($session->fresh());

        $this->assertNotNull($path);

        // {year} folder from completed_at (record-date rule) + COUNT-{id}.pdf
        $expected = 'count-pdfs/' . $session->completed_at->format('Y') . '/COUNT-' . $session->id . '.pdf';
        $this->assertSame($expected, $path);

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $session->fresh()->report_pdf_path);
    }

    public function test_ongoing_session_returns_null(): void
    {
        Storage::fake('local');

        $supply = $this->user(['role' => 'supply_officer', 'can_supply' => true, 'full_name' => 'Supply Officer Arc 2']);
        $session = $this->makeSession($supply, ['status' => 'Ongoing', 'completed_at' => null]);

        $path = (new \App\Actions\PhysicalCount\ArchiveCountReportAction)->generate($session->fresh());

        $this->assertNull($path);
        $this->assertNull($session->fresh()->report_pdf_path);
    }

    public function test_generate_is_idempotent(): void
    {
        Storage::fake('local');

        $supply = $this->user(['role' => 'supply_officer', 'can_supply' => true, 'full_name' => 'Supply Officer Arc 3']);
        $this->asset();
        $session = $this->makeSession($supply);

        $first = (new \App\Actions\PhysicalCount\ArchiveCountReportAction)->generate($session->fresh());
        $second = (new \App\Actions\PhysicalCount\ArchiveCountReportAction)->generate($session->fresh());

        $this->assertSame($first, $second);
        Storage::disk('local')->assertExists($first);
    }
}
