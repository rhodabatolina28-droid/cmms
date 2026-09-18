<?php

namespace Tests\Feature;

use App\Models\CsmSurvey;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\CsmStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.31b/c — CSM stat card on the SA dashboard.
 *
 * D9.31c (user decision): the card speaks ARTA/CSC language — the score is
 * coloured by its descriptive band ("Satisfied", "Neutral", ...) and the sub
 * line shows the % of satisfied clients on SDQ0 (the overall-satisfaction
 * question, DB column sqd1). The month-over-month trend lives in the hover
 * tooltip only and stays hidden until BOTH months hold MIN_TREND_SAMPLE
 * responses, so one respondent can never swing the arrow.
 */
class CsmDashboardCardTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            "full_name" => "CSM Card User " . $this->counter,
            "email" => "csm-card-user-" . $this->counter . "@test.com",
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
            "description" => "CSM card test ticket " . $number,
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

    public function test_csm_card_shows_arta_band_word_and_satisfied_share(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        // 3 x "Agree" on every SQD => average 4.0 => ARTA band "Satisfied"
        // (3.41–4.20); SDQ0 = Agree => 100% of clients satisfied.
        for ($i = 1; $i <= 3; $i++) {
            $ticket = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-" . str_pad((string) (100 + $i), 4, "0", STR_PAD_LEFT));
            $this->survey($ticket, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Agree"));
        }

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("Satisfied &middot; 100% satisfied", false)
            ->assertSee("4.0");
    }

    public function test_csm_card_value_coloured_by_arta_band(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        // All "Strongly Disagree" => average 1.0 => "Very Dissatisfied" (low).
        for ($i = 1; $i <= 3; $i++) {
            $ticket = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-" . str_pad((string) (200 + $i), 4, "0", STR_PAD_LEFT));
            $this->survey($ticket, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Disagree"));
        }

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("stat-csm-low")
            ->assertSee("Very Dissatisfied &middot; 0% satisfied", false);
    }

    public function test_csm_card_trend_tooltip_shows_when_both_months_meet_sample(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        // Last month: 3 x "Strongly Agree" (5.0). This month: 3 x "Agree" (4.0).
        $lastStart = now()->subMonthNoOverflow()->startOfMonth();

        for ($i = 1; $i <= 3; $i++) {
            $ticket = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-" . str_pad((string) (300 + $i), 4, "0", STR_PAD_LEFT));
            $this->survey($ticket, [], $lastStart->copy()->addHours(10)->format("Y-m-d H:i:s"));
        }
        for ($i = 1; $i <= 3; $i++) {
            $ticket = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-" . str_pad((string) (400 + $i), 4, "0", STR_PAD_LEFT));
            $this->survey($ticket, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Agree"));
        }

        $prevLabel = $lastStart->format("M Y");

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("Lower than {$prevLabel}", false)
            ->assertSee("5.0 &rarr; 4.0", false);
    }

    public function test_csm_card_trend_hidden_until_both_months_have_min_sample(): void
    {
        $sa = $this->user(["role" => "super_admin"]);
        $requestor = $this->user();

        // 3 this month but only ONE last month — below MIN_TREND_SAMPLE, so no
        // trend may appear (one respondent must not swing the arrow).
        $lastStart = now()->subMonthNoOverflow()->startOfMonth();
        $ticket = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0501");
        $this->survey($ticket, [], $lastStart->copy()->addHours(10)->format("Y-m-d H:i:s"));

        for ($i = 1; $i <= 3; $i++) {
            $ticket = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-" . str_pad((string) (510 + $i), 4, "0", STR_PAD_LEFT));
            $this->survey($ticket, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Agree"));
        }

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertDontSee("Lower than")
            ->assertDontSee("Higher than");
    }

    public function test_csm_card_empty_state(): void
    {
        $sa = $this->user(["role" => "super_admin"]);

        $this->actingAs($sa)
            ->get(route("dashboard.super-admin"))
            ->assertOk()
            ->assertSee("no verdicts yet");
    }

    public function test_arta_band_boundaries(): void
    {
        $this->assertSame("very_satisfied", CsmStatsService::artaBand(5.0)["key"]);
        $this->assertSame("very_satisfied", CsmStatsService::artaBand(4.21)["key"]);
        $this->assertSame("satisfied", CsmStatsService::artaBand(4.20)["key"]);
        $this->assertSame("satisfied", CsmStatsService::artaBand(3.41)["key"]);
        $this->assertSame("neutral", CsmStatsService::artaBand(2.61)["key"]);
        $this->assertSame("dissatisfied", CsmStatsService::artaBand(1.81)["key"]);
        $this->assertSame("very_dissatisfied", CsmStatsService::artaBand(1.0)["key"]);
        $this->assertSame("none", CsmStatsService::artaBand(null)["key"]);
    }

    public function test_percent_satisfied_uses_sqd1_and_excludes_na(): void
    {
        $requestor = $this->user();

        // SDQ0 (sqd1) drives the headline figure — sqd8 (SDQ7, online support)
        // must not influence it. N/A respondents drop out of both sides.
        $a = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0601");
        $this->survey($a, ["sqd1" => "Strongly Agree", "sqd8" => "Strongly Disagree"]);
        $b = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0602");
        $this->survey($b, ["sqd1" => "Agree"]);
        $c = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0603");
        $this->survey($c, ["sqd1" => "N/A"]);
        $d = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0604");
        $this->survey($d, ["sqd1" => "Disagree"]);

        // 2 of 3 scorable (N/A excluded) answered Agree-or-better => 67%.
        $surveys = CsmSurvey::all();
        $this->assertSame(67.0, CsmStatsService::percentSatisfied($surveys));
    }

    public function test_trend_direction_flags_flat_changes(): void
    {
        $this->assertSame("flat", CsmStatsService::trendDirection(0.04));
        $this->assertSame("flat", CsmStatsService::trendDirection(-0.04));
        $this->assertSame("up", CsmStatsService::trendDirection(0.1));
        $this->assertSame("down", CsmStatsService::trendDirection(-1.0));
    }
}
