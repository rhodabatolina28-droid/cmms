<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\CsmStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CSM submission feedback (2026-10-08 user report: "after mag-answer ng CSM
 * walang lumabas na sweet alert, dashboard agad — kaya hinala ko hindi na save").
 *
 * Investigation: the SAVE itself works (DB probe — 87 rows; the 3 latest landed
 * within ~1 minute of each 'Request Completed' email; no failed-submit log
 * entries). The defect is FEEDBACK:
 *  - the user dashboard rendered NO visible message for session('error') nor
 *    for a success that did not match the one thank-you string (duplicate-submit
 *    path) — those redirects were completely silent;
 *  - the happy path keeps the BRANDED thank-you modal (user preference
 *    2026-10-08 — it did render; .modal-overlay is display:flex) while error
 *    and duplicate flashes fire the system-wide SweetAlert2 from layouts/app;
 *  - the standalone CSM form never displayed $errors, so a server-side
 *    validation bounce looked like a silent no-op too.
 */
class CsmSubmissionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    /** LOCK: a valid submission IS saved (the report's actual suspicion). */
    public function test_valid_submission_is_saved_and_redirects_to_dashboard_with_success(): void
    {
        $user = $this->user();
        $ticket = $this->completedIctTicket($user);

        $response = $this->actingAs($user)
            ->post(route('csm.store'), $this->validPayload($ticket));

        $this->assertDatabaseHas('csm_surveys', ['request_id' => $ticket->id]);
        $response->assertRedirect(route('dashboard.user'));
        $response->assertSessionHas('success');
    }

    public function test_dashboard_surfaces_success_and_error_flashes_as_sweetalert(): void
    {
        $user = $this->user(); // no tickets → no pending survey → dashboard renders

        // Happy path → BRANDED thank-you modal (user preference 2026-10-08: this
        // popup existed and rendered before — .modal-overlay is display:flex — and
        // the user prefers it over a generic Swal). Must NOT also fire the Swal
        // flash (no double popup).
        $html = $this->flushSession()
            ->withSession(['success' => 'Thank you for completing the survey! You are all done.'])
            ->actingAs($user)
            ->get(route('dashboard.user'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="thankYouModal"', $html);
        $this->assertStringContainsString('Thank You!', $html);
        $this->assertStringNotContainsString('id="dashboardFlash"', $html); // branded modal owns this path

        // Error flash — previously rendered NOWHERE on this dashboard (silent).
        $html = $this->flushSession()
            ->withSession(['error' => 'Invalid survey submission.'])
            ->actingAs($user)
            ->get(route('dashboard.user'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="dashboardFlash"', $html);
        $this->assertStringContainsString('data-icon="error"', $html);
        $this->assertStringContainsString('Invalid survey submission.', $html);

        // Duplicate-submit success (string ≠ thank-you) — also previously silent.
        $html = $this->flushSession()
            ->withSession(['success' => 'You have already submitted a survey for this request.'])
            ->actingAs($user)
            ->get(route('dashboard.user'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('id="dashboardFlash"', $html);
        $this->assertStringContainsString('already submitted a survey', $html);
    }

    public function test_csm_form_displays_server_side_validation_errors(): void
    {
        $user = $this->user();
        $ticket = $this->completedIctTicket($user);

        // consent + cc/sqd missing → FormRequest bounces back (302), flashed errors
        // must SURVIVE the redirect and RENDER on the form. NOTE: no session-state
        // assertions between POST and GET — re-reading the session store mid-test
        // re-starts it and double-marshals the json-serialized error bag (empty bag
        // artifact, prod is unaffected: fresh Store per request).
        $validationResponse = $this->actingAs($user)
            ->post(route('csm.store'), ['request_id' => $ticket->id, 'age' => 30, 'sex' => 'Male']);
        $validationResponse->assertRedirect();
        $this->assertDatabaseMissing('csm_surveys', ['request_id' => $ticket->id]);

        // The form must SAY what went wrong (previously: no $errors block at all).
        $this->actingAs($user)
            ->get(route('csm.create', $ticket->id))
            ->assertOk()
            ->assertSee('Please review your answers')
            ->assertSee('consent');
    }



    private function validPayload(RequestModel $ticket): array
    {
        $payload = [
            'request_id' => $ticket->id,
            'consent' => 'yes',
            'age' => 30,
            'sex' => 'Male',
            'cc1' => ['2'],
            'cc2' => ['2'],
            'cc3' => ['2'],
            'suggestions' => 'Feedback test suggestions.',
        ];
        foreach (CsmStatsService::SQD_COLUMNS as $column) {
            $payload[$column] = 'Agree';
        }

        return $payload;
    }

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'CSM FB User ' . $this->counter,
            'email' => 'csm-fb-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
        ], $attributes));
    }

    private function completedIctTicket(User $owner): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id' => $owner->id,
            'request_number' => 'ICT-CSMFB-2026-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $owner->full_name,
            'region' => 'NCR',
            'branch' => 'Main Office',
            'office' => 'Administrative Division',
            'status' => 'Completed',
            'is_deleted' => false,
            'description' => 'CSM feedback test ticket ' . $this->counter,
            'division_admin_review_status' => 'Approved',
        ]);
    }
}
