<?php

namespace Tests\Feature;

use App\Models\CsmSurvey;
use App\Models\Notification;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\CsmMonthlyReportService;
use App\Services\CsmStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * D9.32 — CSM Monthly Summary Report: service math (per-scale-point counts,
 * MoM, weakest), the generator command (PDF + SA bell/email notification),
 * the SA-only download route, and the CSM* email exception.
 */
class CsmMonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            "full_name" => "CSM Report User " . $this->counter,
            "email" => "csm-report-user-" . $this->counter . "@test.com",
            "password" => bcrypt("password"),
            "role" => "user",
            "is_active" => true,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
        ], $attributes));
    }

    private function completedTicket(User $requestor, string $number, ?string $completedAt = null): RequestModel
    {
        $t = RequestModel::create([
            "user_id" => $requestor->id,
            "request_number" => $number,
            "type" => "ICT",
            "requestor_name" => $requestor->full_name,
            "region" => "NCR",
            "branch" => "Main Office",
            "office" => "RESEARCH AND INFORMATION DIVISION",
            "status" => "Completed",
            "is_deleted" => false,
            "description" => "CSM report test ticket " . $number,
            "division_admin_review_status" => "Approved",
        ]);

        if ($completedAt !== null) {
            $t->completed_at = $completedAt;
            $t->save();
        }

        return $t->refresh();
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

    public function test_service_counts_per_scale_point_and_summary(): void
    {
        $requestor = $this->user();
        $month = Carbon::parse("2026-09-15");

        $a = $this->completedTicket($requestor, "REQ-2026-09-01-0001", "2026-09-05 10:00:00");
        $this->survey($a); // all Strongly Agree (5)
        $b = $this->completedTicket($requestor, "REQ-2026-09-02-0002", "2026-09-06 10:00:00");
        $this->survey($b, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Agree")); // all 4

        $data = (new CsmMonthlyReportService)->build($month);

        $this->assertTrue($data["hasData"]);
        $this->assertSame(2, $data["respondents"]);
        $this->assertSame(4.5, $data["overall"]);
        $this->assertSame("Very Satisfied", $data["band"]["label"]);
        $this->assertSame(100.0, $data["satisfiedPct"]);
        $this->assertSame(2, $data["completedCount"]);
        $this->assertSame(100.0, $data["responseRate"]);

        $row = $data["questions"]["sqd1"];
        $this->assertSame([5 => 1, 4 => 1, 3 => 0, 2 => 0, 1 => 0], $row["counts"]);
        $this->assertSame(4.5, $row["average"]);
        $this->assertSame(0, $row["disagreeCount"]);
        $this->assertSame("I am satisfied with the service that I availed.", $row["question"]);
    }

    public function test_service_identifies_weakest_and_mom_delta(): void
    {
        $requestor = $this->user();

        // PREVIOUS month: everyone agrees everywhere (4.0 average).
        $prevStart = Carbon::parse("2026-09-15")->subMonthNoOverflow()->startOfMonth();
        $a = $this->completedTicket($requestor, "REQ-2026-08-01-0001", $prevStart->format("Y-m-d 10:00:00"));
        $this->survey($a, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Agree"),
            $prevStart->copy()->addHours(10)->format("Y-m-d H:i:s"));

        // THIS month: sqd3 gets a Strongly Disagree -> weakest column.
        $b = $this->completedTicket($requestor, "REQ-2026-09-01-0002", "2026-09-10 10:00:00");
        $this->survey($b, ["sqd3" => "Strongly Disagree"]);

        $data = (new CsmMonthlyReportService)->build(Carbon::parse("2026-09-15"));

        $this->assertSame("sqd3", $data["weakest"]["column"]);
        $this->assertSame(1.0, $data["weakest"]["average"]);
        $this->assertSame(4.0, $data["weakest"]["prevAverage"]);
        $this->assertSame(4.0, $data["overallPrev"]);
        $this->assertSame("The office followed the transaction's requirements and steps based on the information provided.", $data["weakest"]["question"]);
    }

    public function test_service_handles_empty_month(): void
    {
        $data = (new CsmMonthlyReportService)->build(Carbon::parse("2026-01-15"));

        $this->assertFalse($data["hasData"]);
        $this->assertSame(0, $data["respondents"]);
        $this->assertNull($data["overall"]);
        $this->assertNull($data["weakest"]);
        $this->assertSame("No data", $data["band"]["label"]);
    }

    public function test_command_generates_pdf_and_notifies_super_admins(): void
    {
        Mail::fake();
        Storage::fake("local");

        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        $t = $this->completedTicket($requestor, "REQ-2026-09-01-0009", "2026-09-05 10:00:00");
        $this->survey($t);

        $this->artisan("csm:monthly-report", ["month" => "2026-09"])
            ->expectsOutputToContain("CSM report written")
            ->assertSuccessful();

        $path = CsmMonthlyReportService::storagePath(Carbon::parse("2026-09-01"));
        Storage::disk("local")->assertExists($path);

        $notification = Notification::where("type", "CSM Monthly Report")
            ->where("user_id", $sa->id)
            ->first();
        $this->assertNotNull($notification, "SA must receive a bell notification");
        $this->assertStringContainsString("September 2026", $notification->message);
        $this->assertNotNull($notification->url);

        // The CSM* email exception must let the SA email through.
        Mail::assertQueued(\App\Mail\SystemNotificationMail::class, 1);
    }

    public function test_download_route_is_sa_only_and_streams_pdf(): void
    {
        Mail::fake();
        Storage::fake("local");

        $sa = $this->user(["role" => "super_admin"]);
        $plain = $this->user();

        // Wrong role -> blocked by the role middleware.
        $this->actingAs($plain)
            ->get(route("csm.reports.download", ["year" => 2026, "month" => 9]))
            ->assertStatus(302);

        // SA -> 200 + on-demand generation onto the (faked) private disk.
        $this->actingAs($sa)
            ->get(route("csm.reports.download", ["year" => 2026, "month" => 9]))
            ->assertOk()
            ->assertHeader("content-type", "application/pdf");

        Storage::disk("local")->assertExists(
            CsmMonthlyReportService::storagePath(Carbon::parse("2026-09-01"))
        );
    }

    public function test_csm_email_exception_only_applies_to_csm_types(): void
    {
        Mail::fake();
        $sa = $this->user(["role" => "super_admin"]);

        // CSM type -> SA receives email.
        Notification::send($sa->id, null, "CSM Monthly Report", "Test CSM message", "http://localhost/x");
        Mail::assertQueued(\App\Mail\SystemNotificationMail::class, 1);

        // Non-CSM type -> the flood rule still applies (in-app only, no email).
        Notification::send($sa->id, null, "PM Scheduled", "Test PM message", null);
        Mail::assertQueued(\App\Mail\SystemNotificationMail::class, 1); // still 1
    }
}
