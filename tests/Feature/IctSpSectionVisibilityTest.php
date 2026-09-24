<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.43 — the "TO BE FILLED-UP BY SERVICE PROVIDER" WEB-FORM section is visible
 * ONLY when REPAIR TYPE = "REFERRED TO SERVICE PROVIDER" (Option A: strict hide,
 * applies to every role — admin, IT, and end user). The PDF keeps the section
 * unconditionally (official printable form); that is asserted elsewhere/untouched.
 */
class IctSpSectionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const SP_HEADER = 'TO BE FILLED-UP BY SERVICE PROVIDER';

    private int $counter = 0;

    private function user(array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'D943 User ' . $this->counter,
            'email' => 'd943-user-' . $this->counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'region' => 'NCR',
            'branch' => 'RCMB',
            'office' => 'RESEARCH AND INFORMATION DIVISION',
        ], $attributes));
    }

    /**
     * Build an Ongoing, Approved, IT-assigned ICT ticket with the given repair type.
     *
     * @return array{0: RequestModel, 1: User, 2: User} [ticket, requestor, assigned IT]
     */
    private function ticket(array $repairTypes): array
    {
        $this->counter++;
        $requestor = $this->user();
        $it = $this->user(['role' => 'it']);

        $repair = RepairRequest::create([
            'end_user_last_name' => 'D943',
            'end_user_first_name' => 'Tester',
            'end_user_sex' => 'MALE',
            'division_office' => $requestor->office,
            'end_user_email' => $requestor->email,
            'employee_no' => 'EMP-D943-' . $this->counter,
            'repair_description' => 'D9.43 SP section visibility',
            'repair_type' => json_encode($repairTypes),
        ]);

        $ticket = RequestModel::create([
            'user_id' => $requestor->id,
            'request_number' => 'REQ-NCR-RCMB-2026-94' . str_pad((string) $this->counter, 2, '0', STR_PAD_LEFT),
            'type' => 'ICT',
            'requestor_name' => $requestor->full_name,
            'region' => $requestor->region,
            'branch' => $requestor->branch,
            'office' => $requestor->office,
            'status' => RequestModel::STATUS_ONGOING,
            'is_deleted' => false,
            'description' => 'D9.43 SP visibility ticket',
            'division_admin_review_status' => 'Approved',
            'assigned_to' => $it->id,
            'detail_id' => $repair->id,
        ]);

        return [$ticket, $requestor, $it];
    }

    public function test_admin_sees_the_sp_section_only_when_referred(): void
    {
        $admin = $this->user(['role' => 'admin']);

        // The form's hiding mechanism must be present: .hidden { display: none; }
        [$internal] = $this->ticket(['INTERNAL REPAIR', 'WITHIN WARRANTY']);
        $this->actingAs($admin)->get(route('ict.edit', $internal->id))
            ->assertOk()
            ->assertSee('.hidden { display: none; }', false)
            ->assertSee('<div id="serviceProviderSectionWrap" class="hidden"', false);

        [$referred] = $this->ticket(['REFERRED TO SERVICE PROVIDER', 'BEYOND WARRANTY']);
        $this->actingAs($admin)->get(route('ict.edit', $referred->id))
            ->assertOk()
            ->assertSee('<div id="serviceProviderSectionWrap" class=""', false)
            ->assertSee(self::SP_HEADER);
    }

    public function test_it_personnel_sees_the_sp_section_only_when_referred(): void
    {
        [$internal, , $it] = $this->ticket(['INTERNAL REPAIR', 'BEYOND WARRANTY']);
        $this->actingAs($it)->get(route('ict.edit', $internal->id))
            ->assertOk()
            ->assertSee('<div id="serviceProviderSectionWrap" class="hidden"', false);

        [$referred, , $itAgain] = $this->ticket(['REFERRED TO SERVICE PROVIDER', 'WITHIN WARRANTY']);
        $this->actingAs($itAgain)->get(route('ict.edit', $referred->id))
            ->assertOk()
            ->assertSee('<div id="serviceProviderSectionWrap" class=""', false)
            ->assertSee(self::SP_HEADER);
    }

    public function test_end_user_sees_the_sp_section_only_when_referred(): void
    {
        [$internal, $requestor] = $this->ticket(['INTERNAL REPAIR']);
        $this->actingAs($requestor)->get(route('ict.edit', $internal->id))
            ->assertOk()
            ->assertSee('<div id="serviceProviderSectionWrap" class="hidden"', false);

        [$referred, $requestorAgain] = $this->ticket(['REFERRED TO SERVICE PROVIDER']);
        $this->actingAs($requestorAgain)->get(route('ict.edit', $referred->id))
            ->assertOk()
            ->assertSee('<div id="serviceProviderSectionWrap" class=""', false)
            ->assertSee(self::SP_HEADER);
    }

    public function test_saved_sp_data_does_not_reveal_the_section_when_type_is_not_referred(): void
    {
        // Option A (strict): may naka-save nang SP data pero INTERNAL ang type →
        // naka-hide pa rin sa web form (data stays safe in DB and visible in the PDF).
        $admin = $this->user(['role' => 'admin']);
        [$ticket] = $this->ticket(['INTERNAL REPAIR', 'WITHIN WARRANTY']);

        RepairRequest::where('id', $ticket->detail_id)->update([
            'company_name' => 'ACME Servicing Center',
            'service_date' => now()->toDateString(),
        ]);

        $this->actingAs($admin)->get(route('ict.edit', $ticket->id))
            ->assertOk()
            ->assertSee('<div id="serviceProviderSectionWrap" class="hidden"', false);
    }
}
