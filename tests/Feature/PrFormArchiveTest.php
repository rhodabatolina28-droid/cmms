<?php

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrFormArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name): User
    {
        return User::create([
            'full_name' => $name,
            'email' => 'prf-' . str()->slug($name) . '-' . uniqid() . '@test.local',
            'password' => bcrypt('password'),
        ]);
    }

    private function makeFinalizedPr(array $overrides = []): PurchaseRequest
    {
        $requester = $this->makeUser('Req Arc');
        $finalizer = $this->makeUser('Supply Arc');

        return PurchaseRequest::create(array_merge([
            'pr_number' => 'PR-ARC-' . uniqid(),
            'status' => PurchaseRequest::STATUS_FINALIZED,
            'items' => [
                ['description' => 'SSD 1TB M.2 NVMe', 'quantity' => 2, 'unit' => 'pcs', 'unit_cost' => 4500],
            ],
            'purpose' => 'PR form archive test',
            'total_amount' => 9000,
            'requested_by' => $requester->id,
            'created_by' => $requester->id,
            'finalized_by' => $finalizer->id,
            'finalized_at' => now(),
            'region' => 'NCR',
        ], $overrides));
    }

    public function test_finalized_pr_archives_form_to_pr_form_pdfs_folder(): void
    {
        Storage::fake('local');

        $pr = $this->makeFinalizedPr();

        $path = \App\Actions\PurchaseRequest\ArchivePrFormPdfAction::generate($pr->fresh());

        $this->assertNotNull($path);

        // {year}/{Month} folders from finalized_at (record-date rule)
        $expected = 'pr-forms/'
            . $pr->finalized_at->format('Y') . '/'
            . $pr->finalized_at->format('F') . '/'
            . $pr->pr_number . '.pdf';
        $this->assertSame($expected, $path);

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $pr->fresh()->pr_form_pdf_path);
    }

    public function test_submitted_pr_returns_null(): void
    {
        Storage::fake('local');

        $pr = $this->makeFinalizedPr(['status' => PurchaseRequest::STATUS_SUBMITTED, 'finalized_at' => null]);

        $path = \App\Actions\PurchaseRequest\ArchivePrFormPdfAction::generate($pr->fresh());

        $this->assertNull($path);
        $this->assertNull($pr->fresh()->pr_form_pdf_path);
    }

    public function test_generate_is_idempotent(): void
    {
        Storage::fake('local');

        $pr = $this->makeFinalizedPr();

        $first = \App\Actions\PurchaseRequest\ArchivePrFormPdfAction::generate($pr->fresh());
        $second = \App\Actions\PurchaseRequest\ArchivePrFormPdfAction::generate($pr->fresh());

        $this->assertSame($first, $second);
        Storage::disk('local')->assertExists($first);
    }
}