<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use App\Support\RequestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.24 — date-based service request numbers (updated by D9.42).
 * Stored format now: {ICT|PM}-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}
 *   (ICT-NCR-RCMB-2026-09-16-0001 — per-region daily sequence).
 * Legacy REQ-NCR-RCMB-2026-0001 and D9.24 REQ-2026-09-16-0001 numbers
 * must still display until the Phase 3 backfill renumbers them.
 */
class ServiceRequestNumberFormatTest extends TestCase
{
    use RefreshDatabase;

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'D924 User ' . $this->counter,
            'email' => 'd924-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
        ], $attributes));
    }

    private function ticket(string $number, array $extra = []): RequestModel
    {
        $requestor = $this->user();

        return RequestModel::create(array_merge([
            'user_id' => $requestor->id,
            'request_number' => $number,
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => $requestor->region,
            'branch' => $requestor->branch,
            'office' => $requestor->office,
            'status' => 'Pending',
            'is_deleted' => false,
            'description' => 'D9.24 number test ' . $number,
        ], $extra));
    }

    public function test_ict_request_number_uses_date_based_format(): void
    {
        $number = RequestHelpers::generateRequestNumber('ICT', $this->user());

        $this->assertMatchesRegularExpression('/^ICT-NCR-RCMB-\d{4}-\d{2}-\d{2}-\d{4}$/', $number);
        $this->assertStringStartsWith('ICT-NCR-RCMB-' . now()->format('Y-m-d') . '-', $number);
    }

    public function test_pm_request_number_uses_pm_prefix_and_date_format(): void
    {
        $number = RequestHelpers::generateRequestNumber('PM', $this->user());

        $this->assertMatchesRegularExpression('/^PM-NCR-RCMB-\d{4}-\d{2}-\d{2}-\d{4}$/', $number);
        $this->assertStringStartsWith('PM-NCR-RCMB-' . now()->format('Y-m-d') . '-', $number);
    }

    public function test_sequence_increments_within_the_same_day(): void
    {
        $user = $this->user();

        $first = RequestHelpers::generateRequestNumber('ICT', $user);
        $this->ticket($first);

        $second = RequestHelpers::generateRequestNumber('ICT', $user);

        $this->assertStringEndsWith('-0001', $first);
        $this->assertStringEndsWith('-0002', $second);
    }

    public function test_ict_and_pm_counters_are_independent(): void
    {
        $user = $this->user();

        $this->ticket(RequestHelpers::generateRequestNumber('ICT', $user));

        $pm = RequestHelpers::generateRequestNumber('PM', $user);

        $this->assertStringEndsWith('-0001', $pm, 'The PM counter must not consume the ICT counter.');
    }

    public function test_legacy_numbers_do_not_block_the_new_sequence(): void
    {
        $this->ticket('REQ-NCR-RCMB-2026-0028');

        $number = RequestHelpers::generateRequestNumber('ICT', $this->user());

        $this->assertStringEndsWith('-0001', $number);
    }

    public function test_display_number_renders_the_new_date_format(): void
    {
        $ticket = $this->ticket('REQ-2026-09-16-0001');

        $this->assertSame('ICT-2026-09-16-0001', $ticket->display_number);
    }

    public function test_display_number_keeps_legacy_numbers_readable(): void
    {
        $ticket = $this->ticket('REQ-NCR-RCMB-2026-0042');

        $this->assertSame('ICT-2026-0042', $ticket->display_number);
    }

    public function test_display_number_for_new_pm_format(): void
    {
        $ticket = $this->ticket('PM-2026-09-16-0007', [
            'type' => 'Preventive Maintenance',
        ]);

        $this->assertSame('PM-2026-09-16-0007', $ticket->display_number);
    }

    public function test_full_display_number_uses_region_and_branch_columns(): void
    {
        $ticket = $this->ticket('REQ-2026-09-16-0003');

        $this->assertSame('ICT-NCR-RCMB-2026-09-16-0003', $ticket->full_display_number);
    }

    public function test_full_display_number_skips_empty_region_and_branch(): void
    {
        $ticket = $this->ticket('REQ-2026-09-16-0004', [
            'region' => '',
            'branch' => '',
        ]);

        $this->assertSame('ICT-2026-09-16-0004', $ticket->full_display_number);
    }

    public function test_sequence_restarts_on_a_new_day(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');

        $this->ticket("REQ-{$yesterday}-0007");

        $first = RequestHelpers::generateRequestNumber('ICT', $this->user());
        $this->assertStringEndsWith('-0001', $first, 'A new day must start at 0001.');

        $this->ticket($first);

        $second = RequestHelpers::generateRequestNumber('ICT', $this->user());
        $this->assertStringEndsWith('-0002', $second);
    }

    public function test_previous_year_numbers_do_not_block_todays_sequence(): void
    {
        $lastYear = (string) ((int) now()->format('Y') - 1);

        $this->ticket("REQ-{$lastYear}-12-31-0009");

        $number = RequestHelpers::generateRequestNumber('ICT', $this->user());

        $this->assertStringEndsWith('-0001', $number);
    }}
