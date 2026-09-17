<?php

namespace Tests\Feature;

use App\Models\CsmSurvey;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\CsmStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.31 Phase 1 - CsmStatsService foundation.
 *
 * The CSM form stores SQD answers as ARTA scale text labels. The service
 * must score them case-insensitively (legacy rows contain variants such
 * as "Neither Agree Nor Disagree" with a capital N), skip unknown values,
 * and expose per-survey severity + averages used by the dashboard and the
 * upcoming alert command (D9.31 Phases 3-4).
 */
class CsmStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            "full_name" => "CSM User " . $this->counter,
            "email" => "csm-user-" . $this->counter . "@test.com",
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
            "description" => "CSM test ticket " . $number,
            "division_admin_review_status" => "Approved",
        ]);
    }

    private function survey(RequestModel $ticket, array $answers, array $attributes = []): CsmSurvey
    {
        $answers = array_replace(array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Agree"), $answers);

        return CsmSurvey::create(array_merge([
            "request_id" => $ticket->id,
            "age" => 30,
            "sex" => "Male",
            "cc1" => "2",
            "cc2" => "2",
            "cc3" => "2",
        ] + $answers, $attributes));
    }

    private function makeRow(array $answers): object
    {
        $row = new \stdClass();

        // Unanswered columns stay null → skipped by the scorer, so a test
        // row with a single override contributes exactly one cell.
        foreach (CsmStatsService::SQD_COLUMNS as $column) {
            $row->{$column} = $answers[$column] ?? null;
        }

        return $row;
    }

    public function test_scores_all_five_scale_labels(): void
    {
        $this->assertSame(5, CsmStatsService::scoreFor("Strongly Agree"));
        $this->assertSame(4, CsmStatsService::scoreFor("Agree"));
        $this->assertSame(3, CsmStatsService::scoreFor("Neither Agree nor Disagree"));
        $this->assertSame(2, CsmStatsService::scoreFor("Disagree"));
        $this->assertSame(1, CsmStatsService::scoreFor("Strongly Disagree"));
    }

    public function test_scoring_is_case_insensitive_and_trims_whitespace(): void
    {
        // Legacy backups contain "Neither Agree Nor Disagree" (capital N) —
        // it must still score 3 instead of silently dropping from averages.
        $this->assertSame(3, CsmStatsService::scoreFor("Neither Agree Nor Disagree"));
        $this->assertSame(5, CsmStatsService::scoreFor(" strongly agree "));
        $this->assertSame(1, CsmStatsService::scoreFor("STRONGLY DISAGREE"));
        $this->assertSame(4, CsmStatsService::scoreFor("agree"));
    }

    public function test_unknown_and_empty_labels_return_null(): void
    {
        $this->assertNull(CsmStatsService::scoreFor("N/A"));
        $this->assertNull(CsmStatsService::scoreFor(""));
        $this->assertNull(CsmStatsService::scoreFor(null));
        $this->assertNull(CsmStatsService::scoreFor("Satisfactory"));
    }

    public function test_na_answer_is_valid_but_excluded_from_averages(): void
    {
        $row = $this->makeRow(["sqd1" => "Strongly Agree", "sqd2" => "N/A", "sqd3" => "Agree"]);

        // N/A is a legitimate ARTA option (not-applicable question): accepted
        // by validation, but excluded — only 2 cells are scored here.
        $this->assertSame(["sqd1" => 5, "sqd3" => 4], CsmStatsService::surveyScores($row));
        $this->assertSame(4.5, CsmStatsService::averageForSurveys([$row]));
        $this->assertContains("N/A", CsmStatsService::validationLabels());
    }

    public function test_survey_scores_skip_unknown_answers(): void
    {
        $row = new \stdClass();
        $row->sqd1 = "Strongly Agree";
        $row->sqd2 = "Neither Agree Nor Disagree"; // legacy capital-N variant
        $row->sqd3 = "banana";                     // junk → skipped
        $row->sqd4 = null;                         // missing → skipped

        $scores = CsmStatsService::surveyScores($row);

        $this->assertSame(["sqd1" => 5, "sqd2" => 3], $scores);
    }

    public function test_average_matches_hand_computed_value(): void
    {
        $rows = [
            $this->makeRow(["sqd1" => "Strongly Agree"]),             // 5
            $this->makeRow(["sqd1" => "Neither Agree Nor Disagree"]), // 3 (legacy variant)
            $this->makeRow(["sqd1" => "Strongly Disagree"]),          // 1
        ];

        // (5 + 3 + 1) / 3 = 3.0 — the legacy variant must be counted.
        $this->assertSame(3.0, CsmStatsService::averageForSurveys($rows));
    }

    public function test_average_returns_null_when_nothing_is_scorable(): void
    {
        $this->assertNull(CsmStatsService::averageForSurveys([]));
        $this->assertNull(CsmStatsService::averageForSurveys([$this->makeRow(["sqd1" => "banana"])]));
    }

    public function test_severe_detection(): void
    {
        $allDisagree = $this->makeRow(array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Disagree"));
        $twoSd = $this->makeRow([
            "sqd1" => "Strongly Disagree",
            "sqd2" => "Strongly Disagree",
            "sqd3" => "Disagree",
        ]);
        $happy = $this->makeRow(array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Agree"));

        $this->assertSame(9, CsmStatsService::severeCount($allDisagree));
        $this->assertTrue(CsmStatsService::isSevere($allDisagree));
        $this->assertFalse(CsmStatsService::isSevere($twoSd)); // 2 SD < threshold 3
        $this->assertFalse(CsmStatsService::isSevere($happy));
        $this->assertSame(0, CsmStatsService::severeCount($happy));
    }

    public function test_per_column_averages_and_weakest_column(): void
    {
        $rows = [
            $this->makeRow(["sqd1" => "Strongly Agree", "sqd2" => "Disagree"]), // sqd1=5, sqd2=2
            $this->makeRow(["sqd1" => "Agree", "sqd2" => "Strongly Disagree"]), // sqd1=4, sqd2=1
            $this->makeRow(["sqd1" => "Agree", "sqd2" => "Strongly Disagree"]), // sqd1=4, sqd2=1
        ];

        $averages = CsmStatsService::perColumnAverages($rows);

        $this->assertEqualsWithDelta(4.3333333, $averages["sqd1"], 0.0000001);
        $this->assertEqualsWithDelta(1.3333333, $averages["sqd2"], 0.0000001);
        $weakest = CsmStatsService::weakestColumn($rows);
        $this->assertSame("sqd2", $weakest["column"]);
        $this->assertEqualsWithDelta(1.3333333, $weakest["average"], 0.0000001);
        $this->assertNull(CsmStatsService::weakestColumn([]));
    }

    public function test_service_reads_real_model_rows(): void
    {
        $requestor = $this->user();
        $ticketA = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0901");
        $ticketB = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0902");
        $ticketC = $this->completedTicket($requestor, "REQ-NCR-RCMB-2026-0903");

        // One happy survey + one legacy-variant survey (capital N, score 3)
        // + one severely negative survey (all Strongly Disagree).
        $this->survey($ticketA, []);
        $this->survey($ticketB, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Neither Agree Nor Disagree"));
        $this->survey($ticketC, array_fill_keys(CsmStatsService::SQD_COLUMNS, "Strongly Disagree"));

        $rows = CsmSurvey::query()->orderBy("id")->get();

        // 9×5 + 9×3 + 9×1 = 81 points over 27 cells = 3.0
        $this->assertSame(3.0, CsmStatsService::averageForSurveys($rows));
        $this->assertFalse(CsmStatsService::isSevere($rows->first()));
        $this->assertFalse(CsmStatsService::isSevere($rows->slice(1, 1)->first()));
        $this->assertTrue(CsmStatsService::isSevere($rows->last()));
    }

    public function test_option_labels_match_the_form_scale(): void
    {
        $this->assertSame(
            ["Strongly Agree", "Agree", "Neither Agree nor Disagree", "Disagree", "Strongly Disagree"],
            CsmStatsService::optionLabels()
        );
    }
}
