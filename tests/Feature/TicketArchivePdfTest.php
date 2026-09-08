<?php

namespace Tests\Feature;

use App\Actions\Ticket\ArchiveTicketPdfAction;
use App\Models\PreventiveMaintenance;
use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * D6 — auto-archived final PDF copy on ticket completion.
 * Separate folders: ict-pdfs/ (ICT) vs pm-pdfs/ (Preventive Maintenance).
 * Record-date rule: month year from completed_at.
 */
class TicketArchivePdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        static $counter = 0;
        $counter++;
        return User::create(array_merge([
            'full_name' => 'Arc User ' . $counter,
            'email' => 'arc' . $counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
        ], $attrs));
    }

    private function makeIctTicket(User $user): RequestModel
    {
        $repair = RepairRequest::create([
            'end_user_last_name' => 'Dela',
            'end_user_first_name' => 'Cruz',
            'end_user_middle_name' => 'S',
            'end_user_sex' => 'Male',
            'division_office' => 'Test',
            'end_user_email' => 'a@b.c',
            'employee_no' => 'E1',
            'repair_description' => 'Archive test',
            'it_received_last_name' => 'Tech',
            'it_received_first_name' => 'Test',
            'it_received_middle_name' => 'T',
            'initial_diagnosis' => 'diag',
        ]);

        return RequestModel::create([
            'user_id' => $user->id,
            'request_number' => 'ICT-ARC-' . $repair->id,
            'type' => 'ICT',
            'requestor_name' => $user->full_name,
            'description' => 'Archive test',
            'status' => RequestModel::STATUS_COMPLETED,
            'completed_at' => now(),
            'region' => 'NCR',
            'detail_id' => $repair->id,
        ]);
    }

    private function makePmTicket(User $user): RequestModel
    {
        $pm = PreventiveMaintenance::create([
            'technician_name' => 'Tech',
            'end_user_name' => 'Archive End User',
            'maintenance_tasks_json' => json_encode(['task1' => 'YES']),
        ]);

        return RequestModel::create([
            'user_id' => $user->id,
            'request_number' => 'PM-ARC-' . $pm->id,
            'type' => 'Preventive Maintenance',
            'requestor_name' => $user->full_name,
            'description' => 'Archive test PM',
            'status' => RequestModel::STATUS_COMPLETED,
            'completed_at' => now(),
            'region' => 'NCR',
            'detail_id' => $pm->id,
        ]);
    }

    public function test_ict_ticket_archives_to_ict_pdfs_folder(): void
    {
        Storage::fake('local');
        $user = $this->makeUser();
        $ticket = $this->makeIctTicket($user);

        $path = ArchiveTicketPdfAction::generate($ticket);

        $this->assertNotNull($path);
        $this->assertStringStartsWith('ict-pdfs/', $path);
        $this->assertStringContainsString('/ARCH-ICT-ARC-', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertSame($path, $ticket->fresh()->archive_pdf_path);
    }

    public function test_pm_ticket_archives_to_pm_pdfs_folder(): void
    {
        Storage::fake('local');
        $user = $this->makeUser();
        $ticket = $this->makePmTicket($user);

        $path = ArchiveTicketPdfAction::generate($ticket);

        $this->assertNotNull($path);
        $this->assertStringStartsWith('pm-pdfs/', $path);
        $this->assertStringContainsString('/ARCH-PM-ARC-', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_non_completed_ticket_returns_null(): void
    {
        Storage::fake('local');
        $user = $this->makeUser();
        $ticket = $this->makeIctTicket($user);
        $ticket->update(['status' => RequestModel::STATUS_ONGOING]);

        $this->assertNull(ArchiveTicketPdfAction::generate($ticket->fresh()));
        $this->assertNull($ticket->fresh()->archive_pdf_path);
    }

    public function test_generate_is_idempotent(): void
    {
        Storage::fake('local');
        $user = $this->makeUser();
        $ticket = $this->makeIctTicket($user);

        $first = ArchiveTicketPdfAction::generate($ticket);
        $second = ArchiveTicketPdfAction::generate($ticket->fresh());

        $this->assertSame($first, $second);
        $this->assertCount(1, Storage::disk('local')->allFiles('ict-pdfs'));
    }
}