<?php

namespace Tests\Feature;

use App\Models\CsmSurvey;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * D5a â€” CSM auto-archived PDF copy.
 * On submit, the survey is saved and a PDF copy is generated into
 * private storage (csm-copies/{year}/{Month}/) â€” non-blocking.
 */
class CsmArchivePdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'full_name' => 'Csm User ' . $counter,
            'email' => 'csm' . $counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
            'office' => 'Research Division',
        ], $attrs));
    }

    private function makeCompletedTicket(User $user): RequestModel
    {
        return RequestModel::create([
            'user_id' => $user->id,
            'request_number' => 'REQ-CSM-' . date('Y') . '-' . str_pad((string)($user->id + 1), 3, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $user->full_name,
            'description' => 'CSM archive test',
            'status' => RequestModel::STATUS_COMPLETED,
            'region' => 'NCR',
        ]);
    }

    private function surveyPayload(int $requestId): array
    {
        return [
            'request_id'  => $requestId,
            'consent'     => 'yes',
            'age'         => 30,
            'sex'         => 'Male',
            'cc1'         => ['2'],
            'cc2'         => ['1'],
            'cc3'         => ['3'],
            'sqd1'        => 'Agree',
            'sqd2'        => 'Strongly Agree',
            'sqd3'        => 'Agree',
            'sqd4'        => 'Agree',
            'sqd5'        => 'N/A',
            'sqd6'        => 'Agree',
            'sqd7'        => 'Strongly Agree',
            'sqd8'        => 'Agree',
            'sqd9'        => 'Agree',
            'suggestions' => 'Mabilis ang processing.',
        ];
    }

    public function test_submit_creates_archived_pdf_in_month_folder(): void
    {
        Storage::fake('local');

        $user = $this->makeUser();
        $ticket = $this->makeCompletedTicket($user);

        $this->actingAs($user)->post('/survey', $this->surveyPayload($ticket->id));

        $this->assertDatabaseHas('csm_surveys', ['request_id' => $ticket->id]);

        $survey = CsmSurvey::where('request_id', $ticket->id)->first();
        $this->assertNotNull($survey->pdf_path);
        $this->assertStringStartsWith(
            'csm-copies/' . $survey->created_at->format('Y') . '/' . $survey->created_at->format('F') . '/',
            $survey->pdf_path
        );
        $this->assertTrue(Storage::disk('local')->exists($survey->pdf_path));
    }

    public function test_pdf_failure_still_saves_survey(): void
    {
        Storage::fake('local');

        $user = $this->makeUser();
        $ticket = $this->makeCompletedTicket($user);

        // Force DomPDF to fail after the DB commit â€” the survey must still be saved.
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')
            ->andThrow(new \RuntimeException('boom'));

        $this->actingAs($user)->post('/survey', $this->surveyPayload($ticket->id));

        $this->assertDatabaseHas('csm_surveys', ['request_id' => $ticket->id]);

        $survey = CsmSurvey::where('request_id', $ticket->id)->first();
        $this->assertNull($survey->pdf_path);
    }
}
