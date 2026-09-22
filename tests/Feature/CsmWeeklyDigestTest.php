<?php

namespace Tests\Feature;

use App\Models\CsmSurvey;
use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\CsmStatsService;
use App\Services\CsmWeeklyDigestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * D9.34 — CSM Weekly Digest: ARTA-aligned overall watch (<=3.40 or drop >=0.3
 * with a 5-survey guard), per-question BIG WARNING (>=3 disagrees or avg
 * <=2.60 with a 3-answer guard), recovery + milestone notes, and the
 * `csm:weekly-check` command (bell + email to SAs, deduped 1 per week).
 */
class CsmWeeklyDigestTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            "full_name" => "CSM Weekly User " . $this->counter,
            "email" => "csm-weekly-user-" . $this->counter . "@test.com",
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
            "description" => "CSM weekly digest test ticket " . $number,
            "division_admin_review_status" => "Approved",
        ]);
    }

    private function survey(RequestModel $ticket, array $answers = [], ?string $createdAt = null): CsmSurvey
    {
        $answers = array_replace(array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Agree"), $answers);

        $survey = CsmSurvey::create(array_merge([
            "request_id" => $ticket->id,
            "age" => 30,
            "sex" => "Male",
            "cc1" => "2",
            "cc2" => "2",
            "cc3" => "2",
        ], $answers));

        if ($createdAt !== null) {
            $survey->created_at = $createdAt;
            $survey->save();
        }

        return $survey->refresh();
    }

    /** Plant $count surveys answered $answer inside the week containing $monday. */
    private function plantWeek(User $requestor, string $monday, int $count, string $answer, int $offset = 0): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $t = $this->completedTicket(
                $requestor,
                sprintf("REQ-W%s-%03d", str_replace("-", "", $monday), $offset + $i)
            );
            $this->survey($t, array_fill_keys(CsmStatsService::SQD_COLUMNS, $answer), $monday . " 1" . $i . ":00:00");
        }
    }

    public function test_overall_in_neutral_band_or_below_flags(): void
    {
        $requestor = $this->user();
        // 5 surveys all "Neither" -> week average 3.0, inside the Neutral band.
        $this->plantWeek($requestor, "2026-09-14", 5, "Neither Agree nor Disagree");

        $data = (new CsmWeeklyDigestService)->build(Carbon::parse("2026-09-16"));

        $this->assertTrue($data["hasData"]);
        $this->assertSame(5, $data["respondents"]);
        $this->assertSame(3.0, $data["overall"]);
        $this->assertTrue($data["overallAlert"]);
        $this->assertSame([], $data["warnings"]); // avg 3.0 > 2.60, zero disagrees
    }

    public function test_drop_of_point_three_or_more_flags(): void
    {
        $requestor = $this->user();
        $this->plantWeek($requestor, "2026-09-07", 5, "Strongly Agree"); // prev week 5.0
        $this->plantWeek($requestor, "2026-09-14", 5, "Agree", 100);     // this week 4.0

        $data = (new CsmWeeklyDigestService)->build(Carbon::parse("2026-09-16"));

        $this->assertSame(4.0, $data["overall"]);
        $this->assertSame(5.0, $data["overallPrev"]);
        $this->assertTrue($data["overallAlert"]); // drop of 1.0 >= 0.3, despite a good band
        $this->assertFalse($data["recovered"]);   // previous week was healthy
    }

    public function test_small_sample_stays_quiet_on_overall_but_still_warns_per_question(): void
    {
        $requestor = $this->user();
        // Only 4 surveys (below the 5-survey guard), all terrible.
        $this->plantWeek($requestor, "2026-09-14", 4, "Strongly Disagree");

        $data = (new CsmWeeklyDigestService)->build(Carbon::parse("2026-09-16"));

        $this->assertFalse($data["overallAlert"]); // sample guard: no overall alarm
        $this->assertNotEmpty($data["warnings"]);  // per-question rule still fires
    }

    public function test_per_question_warning_fires_while_overall_is_healthy(): void
    {
        $requestor = $this->user();
        $t1 = $this->completedTicket($requestor, "REQ-2026-09-14-001");
        $t2 = $this->completedTicket($requestor, "REQ-2026-09-14-002");
        $t3 = $this->completedTicket($requestor, "REQ-2026-09-14-003");
        $t4 = $this->completedTicket($requestor, "REQ-2026-09-14-004");
        $t5 = $this->completedTicket($requestor, "REQ-2026-09-14-005");
        $this->survey($t1, [], "2026-09-15 10:00:00");
        $this->survey($t2, [], "2026-09-15 11:00:00");
        $this->survey($t3, ["sqd2" => "Disagree"], "2026-09-16 10:00:00");
        $this->survey($t4, ["sqd2" => "Disagree"], "2026-09-16 11:00:00");
        $this->survey($t5, ["sqd2" => "Disagree"], "2026-09-17 10:00:00");

        $data = (new CsmWeeklyDigestService)->build(Carbon::parse("2026-09-16"));

        $this->assertFalse($data["overallAlert"]); // overall ~4.8 — healthy
        $this->assertSame(1, count($data["warnings"]));
        $this->assertSame("sqd2", $data["warnings"][0]["column"]);
        $this->assertSame(3, $data["warnings"][0]["disagreeCount"]);
        $this->assertStringContainsString(
            "I spent a reasonable amount of time for my transaction.",
            $data["warnings"][0]["question"]
        );
    }

    public function test_milestone_four_straight_good_weeks(): void
    {
        $requestor = $this->user();
        $this->plantWeek($requestor, "2026-08-24", 3, "Strongly Agree");
        $this->plantWeek($requestor, "2026-08-31", 3, "Strongly Agree", 100);
        $this->plantWeek($requestor, "2026-09-07", 3, "Strongly Agree", 200);
        $this->plantWeek($requestor, "2026-09-14", 3, "Strongly Agree", 300);

        $data = (new CsmWeeklyDigestService)->build(Carbon::parse("2026-09-16"));

        $this->assertTrue($data["milestone"]);
        $this->assertFalse($data["recovered"]);
    }

    public function test_recovery_note_when_back_to_good_band(): void
    {
        $requestor = $this->user();
        $this->plantWeek($requestor, "2026-09-07", 5, "Neither Agree nor Disagree"); // prev: Neutral
        $this->plantWeek($requestor, "2026-09-14", 5, "Strongly Agree", 100);        // this: Very Satisfied

        $data = (new CsmWeeklyDigestService)->build(Carbon::parse("2026-09-16"));

        $this->assertTrue($data["recovered"]);     // Neutral -> Very Satisfied
        $this->assertFalse($data["milestone"]);    // the bad prev week breaks the streak
    }

    public function test_command_sends_digest_and_dedups_per_day(): void
    {
        Mail::fake();
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();
        $this->plantWeek($requestor, "2026-09-14", 5, "Strongly Agree");

        $this->artisan("csm:weekly-check", ["week" => "2026-09-14"])
            ->expectsOutputToContain("CSM weekly digest sent to 1 super admin(s)")
            ->assertSuccessful();

        // Bell lands on the SA dashboard AND the CSM* exception queues the email.
        $notification = Notification::where("type", "CSM Weekly Digest")
            ->where("user_id", $sa->id)
            ->first();
        $this->assertNotNull($notification, "SA must receive the weekly digest bell");
        $this->assertStringContainsString("Sep 14", $notification->message);
        $this->assertStringContainsString("No action needed this week.", $notification->message);
        Mail::assertQueued(\App\Mail\SystemNotificationMail::class, 1);

        // Same-day rerun is suppressed — 1 digest per week.
        $this->artisan("csm:weekly-check", ["week" => "2026-09-14"])
            ->expectsOutputToContain("already sent today")
            ->assertSuccessful();
        $this->assertSame(1, Notification::where("type", "CSM Weekly Digest")->count());
        Mail::assertQueued(\App\Mail\SystemNotificationMail::class, 1); // still 1
    }
}
