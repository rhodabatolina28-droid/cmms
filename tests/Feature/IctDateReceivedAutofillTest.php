<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DATE RECEIVED must auto-fill with the RECEIPT date — when the request
 * actually landed in the system for the system admin (the ticket's
 * created_at) — NOT with the day the admin happens to OPEN the form
 * (the old fmtDate(... ?? now()) default, D9.26).
 */
class IctDateReceivedAutofillTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'DateRecv User ' . $this->counter,
            'email' => 'daterecv-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
        ], $attributes));
    }

    /**
     * Ongoing, Approved ICT ticket assigned to IT, date_received = null.
     * Both created_at columns are back-dated so "received" != "opened today".
     *
     * @return array{0: RequestModel, 1: User, 2: User, 3: RepairRequest}
     */
    private function ticketReceivedDaysAgo(int $days): array
    {
        $this->counter++;
        $requestor = $this->user();
        $it = $this->user(['role' => 'it']);

        $repair = RepairRequest::create([
            'end_user_last_name' => 'DateRecv',
            'end_user_first_name' => 'Tester',
            'end_user_sex' => 'MALE',
            'division_office' => $requestor->office,
            'end_user_email' => $requestor->email,
            'employee_no' => 'EMP-DR-' . $this->counter,
            'repair_description' => 'DATE RECEIVED autofill test',
        ]);

        $ticket = RequestModel::create([
            'user_id' => $requestor->id,
            'request_number' => 'REQ-NCR-RCMB-2026-95' . str_pad((string) $this->counter, 2, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => $requestor->region,
            'branch' => $requestor->branch,
            'office' => $requestor->office,
            'status' => RequestModel::STATUS_ONGOING,
            'is_deleted' => false,
            'description' => 'DATE RECEIVED autofill ticket',
            'division_admin_review_status' => 'Approved',
            'assigned_to' => $it->id,
            'detail_id' => $repair->id,
        ]);

        // Back-date BOTH rows to the receipt moment (query-builder update
        // bypasses Eloquent timestamps). date_received stays NULL.
        $receivedAt = now()->subDays($days);
        RequestModel::whereKey($ticket->id)->update(['created_at' => $receivedAt]);
        RepairRequest::whereKey($repair->id)->update(['created_at' => $receivedAt]);
        $ticket->refresh();
        $repair->refresh();

        return [$ticket, $requestor, $it, $repair];
    }

    public function test_date_received_autofills_with_receipt_date_not_the_day_the_form_was_opened(): void
    {
        [$ticket, , $it, $repair] = $this->ticketReceivedDaysAgo(3);

        $receiptDate = $repair->created_at->format('Y-m-d');
        $openedToday = now()->format('Y-m-d');
        $this->assertNotSame($receiptDate, $openedToday, 'Sanity: receipt date must differ from today');

        $res = $this->actingAs($it)->get(route('ict.edit', $ticket->id));

        $res->assertOk()->assertSee(
            'id="dateReceived" name="dateReceived" value="' . $receiptDate . '"',
            false,
            'DATE RECEIVED must default to the receipt (created_at) date'
        );
        $res->assertDontSee(
            'id="dateReceived" name="dateReceived" value="' . $openedToday . '"',
            false,
            'DATE RECEIVED must NOT default to the day the admin opened the form'
        );
    }

    /** Already-saved date_received still wins over the receipt-date default. */
    public function test_saved_date_received_is_preserved(): void
    {
        [$ticket, , $it, $repair] = $this->ticketReceivedDaysAgo(3);

        $saved = now()->subDay()->format('Y-m-d');
        RepairRequest::whereKey($repair->id)->update(['date_received' => $saved]);

        $this->actingAs($it)->get(route('ict.edit', $ticket->id))
            ->assertOk()
            ->assertSee('id="dateReceived" name="dateReceived" value="' . $saved . '"', false);
    }

    /** End-user / non-editable view: DATE RECEIVED stays blank (D9.26 lock). */
    public function test_view_mode_keeps_date_received_blank_when_empty(): void
    {
        [$ticket, $requestor] = $this->ticketReceivedDaysAgo(3);

        $this->actingAs($requestor)->get(route('ict.edit', $ticket->id))
            ->assertOk()
            ->assertSee('id="dateReceived" name="dateReceived" value=""', false);
    }
}