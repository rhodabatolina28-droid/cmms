<?php

namespace Tests\Feature;

use App\Models\PreventiveMaintenance;
use App\Models\Request as RequestModel;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PM-PDF1 (docs/CMMS_IMPLEMENTATION_PLAN_OCT2026.md PHASE 3 — decision A,
 * user 2026-10-12): the PM form must always print as ONE page.
 *
 * Regression root cause: commit f600293 flipped @page legal -> letter, but a
 * filled DUAL-MONITOR record needs 293.3mm of content while letter offers
 * 271.9mm usable — dompdf spilled the tail onto a SECOND page ("nasisira ang
 * form, nagiging dalawa"; user report). Legal (348.1mm usable) fits every one
 * of the 43 current records (probe-verified), so the form stays on legal —
 * the paper it was originally designed for.
 */
class PmPdfOnePageTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function pmTicket(array $pmAttributes = []): array
    {
        $this->counter++;

        $user = User::create([
            'full_name' => 'PM OnePage ' . $this->counter,
            'email'     => 'pm-onepage-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'user',
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'NCR',
            'office'    => 'RESEARCH AND INFORMATION DIVISION',
        ]);

        $pm = PreventiveMaintenance::create(array_merge([
            'form_no'           => 'PM-NCR-RCMB-2026-10-12-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'end_user_name'     => $user->full_name,
            'end_user_division' => 'RESEARCH AND INFORMATION DIVISION',
            'maintenance_date'  => '2026-10-12',
        ], $pmAttributes));

        $ticket = RequestModel::create([
            'user_id'        => $user->id,
            'request_number' => $pm->form_no,
            'type'           => 'Preventive Maintenance',
            'requestor_name' => $user->full_name,
            'region'         => 'NCR',
            'branch'         => 'NCR',
            'office'         => 'RESEARCH AND INFORMATION DIVISION',
            'status'         => 'Scheduled',
            'is_deleted'     => false,
            'description'    => 'Scheduled Preventive Maintenance — OnePage test',
            'detail_id'      => $pm->id,
        ]);

        return [$ticket, $pm];
    }

    /** Mirrors DownloadMaintenancePdfAction's loadView + paper call. */
    private function renderPdf(RequestModel $ticket, PreventiveMaintenance $pm): string
    {
        $tasks = json_decode($pm->maintenance_tasks_json ?? '{}', true) ?: [];

        return Pdf::loadView('pdf.maintenance-form', [
            'request' => $ticket,
            'pm'      => $pm,
            'tasks'   => $tasks,
        ])->setPaper('legal', 'portrait')->output();
    }

    private function pageCount(string $pdf): int
    {
        return preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf);
    }

    /** The exact trigger config from the user report + probe. */
    public function test_dual_monitor_record_prints_as_one_legal_page(): void
    {
        [$ticket, $pm] = $this->pmTicket([
            'desktop_brand'     => 'DELL',
            'desktop_model'     => 'OptiPlex 7090',
            'desktop_pno'       => 'PAR-2026-0001',
            'monitor1_pno'      => 'PAR-2026-0010',
            'monitor1_brand'    => 'DELL',
            'monitor1_model'    => 'P2419H',
            'monitor2_pno'      => 'PAR-2026-0011',
            'monitor2_brand'    => 'DELL',
            'monitor2_model'    => 'P2419H',
            'printer1_pno'      => 'PAR-2026-0020',
            'printer1_brand'    => 'EPSON',
            'printer1_type'     => 'Inkjet',
            'printer2_pno'      => 'PAR-2026-0021',
            'printer2_brand'    => 'CANON',
            'printer2_type'     => 'Laserjet',
            'maintenance_tasks_json' => json_encode([
                'desktopCaseCleanup' => 'YES',
                'monitorScreenCleanup' => 'YES',
                'monitor2ScreenCleanup' => 'YES',
                'printer2CaseCleanup' => 'YES',
            ]),
        ]);

        $this->assertSame(1, $this->pageCount($this->renderPdf($ticket, $pm)));
    }

    /** Single-monitor records must stay one page (no regression from the fix). */
    public function test_single_monitor_record_still_prints_as_one_page(): void
    {
        [$ticket, $pm] = $this->pmTicket([
            'desktop_brand' => 'DELL',
            'desktop_pno'   => 'PAR-2026-0002',
            'monitor1_pno'  => 'PAR-2026-0012',
            'monitor1_brand'=> 'DELL',
        ]);

        $this->assertSame(1, $this->pageCount($this->renderPdf($ticket, $pm)));
    }

    /** The paper contract itself — @page must stay legal (dompdf honors CSS over setPaper). */
    public function test_form_declares_legal_paper_in_page_css(): void
    {
        [$ticket, $pm] = $this->pmTicket();

        $html = view('pdf.maintenance-form', [
            'request' => $ticket,
            'pm'      => $pm,
            'tasks'   => [],
        ])->render();

        $this->assertStringContainsString('size: legal portrait', $html);
        $this->assertStringNotContainsString('size: letter portrait', $html);
    }
}
