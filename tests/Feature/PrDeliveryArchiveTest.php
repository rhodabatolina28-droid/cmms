<?php

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrDeliveryArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeDeliveredPr(array $overrides = []): PurchaseRequest
    {
        $user = User::create([
            'full_name' => 'Archive PR User',
            'email' => 'pr-archive-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
        ]);

        return PurchaseRequest::create(array_merge([
            'pr_number' => 'PR-ARC-' . uniqid(),
            'status' => PurchaseRequest::STATUS_DELIVERED,
            'items' => [
                ['description' => 'SSD 1TB M.2 NVMe', 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 4500],
            ],
            'purpose' => 'Archive test',
            'total_amount' => 4500,
            'requested_by' => $user->id,
            'created_by' => $user->id,
            'delivered_by' => $user->id,
            'delivered_at' => now(),
            'region' => 'NCR',
        ], $overrides));
    }

    public function test_delivered_pr_archives_to_pr_pdfs_folder(): void
    {
        Storage::fake('local');

        $pr = $this->makeDeliveredPr();

        $path = \App\Actions\PurchaseRequest\ArchiveDeliveryConfirmationPdfAction::generate($pr->fresh());

        $this->assertNotNull($path);
        $this->assertStringStartsWith('pr-pdfs/', $path);
        $this->assertStringEndsWith($pr->pr_number . '.pdf', $path);

        // {year}/{Month} folders from delivered_at (record-date rule)
        $expected = 'pr-pdfs/'
            . $pr->delivered_at->format('Y') . '/'
            . $pr->delivered_at->format('F') . '/'
            . $pr->pr_number . '.pdf';
        $this->assertSame($expected, $path);

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $pr->fresh()->archive_pdf_path);
    }

    public function test_non_delivered_pr_returns_null(): void
    {
        Storage::fake('local');

        $pr = $this->makeDeliveredPr(['status' => PurchaseRequest::STATUS_FINALIZED, 'delivered_at' => null]);

        $path = \App\Actions\PurchaseRequest\ArchiveDeliveryConfirmationPdfAction::generate($pr->fresh());

        $this->assertNull($path);
        $this->assertNull($pr->fresh()->archive_pdf_path);
    }

    public function test_generate_is_idempotent(): void
    {
        Storage::fake('local');

        $pr = $this->makeDeliveredPr();

        $first = \App\Actions\PurchaseRequest\ArchiveDeliveryConfirmationPdfAction::generate($pr->fresh());
        $second = \App\Actions\PurchaseRequest\ArchiveDeliveryConfirmationPdfAction::generate($pr->fresh());

        $this->assertSame($first, $second);
        Storage::disk('local')->assertExists($first);
    }
}
