<?php

namespace Tests\Feature;

use App\Models\CsmSurvey;
use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\CsmSevereAlertService;
use App\Services\CsmStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * D9.33 — Real-time severe CSM alert: fires per single severe survey
 * (Strongly Disagree on >= 3 of 9 questions), bell + email to every SA
 * with the full failed-question list (aggregate-only), deduped to 1 per day.
 */
class CsmSevereAlertTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            "full_name" => "CSM Alert User " . $this->counter,
            "email" => "csm-alert-user-" . $this->counter . "@test.com",
            "password" => bcrypt("password"),
            "role" => "user",
            "is_active" => true,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
        ], $attributes));
    }

    private function completedTicket(User $requestor, string $number): RequestModel
    {
        return RequestModel::create([
            "user_id" => $requestor->id,
            "request_number" => $number,
            "type" => "ICT",
            "requestor_name" => $requestor->full_name,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
            "status" => "Completed",
            "is_deleted" => false,
            "description" => "CSM severe alert test ticket " . $number,
            "division_admin_review_status" => "Approved",
        ]);
    }

    private function survey(RequestModel $ticket, array $answers = []): CsmSurvey
    {
        $answers = array_replace(array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Agree"), $answers);

        return CsmSurvey::create(array_merge([
            "request_id" => $ticket->id,
            "age" => 30,
            "sex" => "Male",
            "cc1" => "2",
            "cc2" => "2",
            "cc3" => "2",
        ], $answers));
    }

    /** A survey that crosses the severe line: 3 Strongly Disagrees. */
    private function severeSurvey(RequestModel $ticket): CsmSurvey
    {
        return $this->survey($ticket, [
            "sqd1" => "Strongly Disagree",
            "sqd2" => "Strongly Disagree",
            "sqd3" => "Strongly Disagree",
        ]);
    }

    public function test_severe_survey_alerts_every_super_admin_with_question_list(): void
    {
        Mail::fake();
        $sa1 = $this->user(["role" => "super_admin"]);
        $sa2 = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();
        $survey = $this->severeSurvey($this->completedTicket($requestor, "REQ-2026-09-01-0001"));

        $fired = (new CsmSevereAlertService)->check($survey);

        $this->assertTrue($fired);
        $this->assertSame(2, Notification::where("type", "CSM Severe Alert")->count());
        $this->assertNotNull(Notification::where("type", "CSM Severe Alert")->where("user_id", $sa1->id)->first());
        $this->assertNotNull(Notification::where("type", "CSM Severe Alert")->where("user_id", $sa2->id)->first());

        $message = Notification::where("type", "CSM Severe Alert")->first()->message;
        $this->assertStringContainsString("3 of 9", $message);
        // Full printed question text — never DB column codes.
        $this->assertStringContainsString("I am satisfied with the service that I availed.", $message);
        $this->assertStringContainsString("I spent a reasonable amount of time for my transaction.", $message);

        // CSM* email exception must let both SA emails through.
        Mail::assertQueued(\App\Mail\SystemNotificationMail::class, 2);
    }

    public function test_non_severe_survey_stays_silent(): void
    {
        Mail::fake();
        $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        // Only 2 Strongly Disagrees — below the 3/9 severe threshold.
        $survey = $this->survey($this->completedTicket($requestor, "REQ-2026-09-01-0002"), [
            "sqd1" => "Strongly Disagree",
            "sqd2" => "Strongly Disagree",
        ]);

        $this->assertFalse((new CsmSevereAlertService)->check($survey));
        $this->assertSame(0, Notification::where("type", "CSM Severe Alert")->count());
        Mail::assertNothingQueued();
    }

    public function test_only_one_alert_per_day(): void
    {
        Mail::fake();
        $this->user(["role" => "super_admin"]);
        $requestor = $this->user();
        $service = new CsmSevereAlertService;

        $this->assertTrue($service->check($this->severeSurvey($this->completedTicket($requestor, "REQ-2026-09-01-0003"))));

        // A second severe survey the same day is bundled, not repeated.
        $this->assertFalse($service->check($this->severeSurvey($this->completedTicket($requestor, "REQ-2026-09-01-0004"))));

        $this->assertSame(1, Notification::where("type", "CSM Severe Alert")->count());
    }

    public function test_alert_fires_again_the_next_day(): void
    {
        Mail::fake();
        $this->user(["role" => "super_admin"]);
        $requestor = $this->user();
        $service = new CsmSevereAlertService;

        $this->assertTrue($service->check($this->severeSurvey($this->completedTicket($requestor, "REQ-2026-09-01-0005"))));

        // Roll the clock: yesterday's alert must not suppress today's.
        $yesterday = Notification::where("type", "CSM Severe Alert")->first();
        $yesterday->created_at = now()->subDay();
        $yesterday->save();

        $this->assertTrue($service->check($this->severeSurvey($this->completedTicket($requestor, "REQ-2026-09-01-0006"))));
        $this->assertSame(2, Notification::where("type", "CSM Severe Alert")->count());
    }

    public function test_alert_is_aggregate_only(): void
    {
        Mail::fake();
        $this->user(["role" => "super_admin"]);
        $requestor = $this->user();
        $survey = $this->severeSurvey($this->completedTicket($requestor, "REQ-2026-09-01-0007"));

        (new CsmSevereAlertService)->check($survey);

        $message = Notification::where("type", "CSM Severe Alert")->first()->message;
        // Confidentiality rule: never a name, never a ticket number.
        $this->assertStringNotContainsString($requestor->full_name, $message);
        $this->assertStringNotContainsString("REQ-2026-09-01-0007", $message);
    }
}
