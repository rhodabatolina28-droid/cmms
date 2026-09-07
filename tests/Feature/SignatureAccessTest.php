<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\RepairRequest;
use App\Models\User;
use App\Support\RequestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * D5b — signatures live on the PRIVATE disk and are served only through
 * the authed route /tickets/{ticket}/signature/{field}.
 */
class SignatureAccessTest extends TestCase
{
    use RefreshDatabase;

    private const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private function makeUser(array $attrs = []): User
    {
        static $counter = 0;
        $counter++;

        return User::create(array_merge([
            'full_name' => 'Sig User ' . $counter,
            'email' => 'sig' . $counter . '@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'is_active' => true,
            'can_supply' => false,
            'region' => 'NCR',
        ], $attrs));
    }

    private function makeIctTicketWithSignature(User $owner): RequestModel
    {
        $path = 'signatures/' . now()->format('Y') . '/' . now()->format('F') . '/test_tech_sig.png';
        Storage::disk('local')->put($path, base64_decode(self::TINY_PNG));

        $repair = RepairRequest::create([
            'end_user_last_name' => 'Dela Cruz',
            'end_user_first_name' => 'Juan',
            'end_user_middle_name' => 'Santos',
            'end_user_sex' => 'Male',
            'division_office' => 'Test Division',
            'end_user_email' => 'juan@test.com',
            'employee_no' => 'EMP-001',
            'repair_description' => 'Signature access test',
            'it_received_last_name' => 'Tech',
            'it_received_first_name' => 'Test',
            'it_received_middle_name' => 'Q',
            'initial_diagnosis' => 'Test diagnosis',
            'technician_signature' => $path,
        ]);

        return RequestModel::create([
            'user_id' => $owner->id,
            'request_number' => 'SIG-TEST-' . $repair->id,
            'type' => 'ICT',
            'requestor_name' => $owner->full_name,
            'description' => 'Signature access test',
            'status' => RequestModel::STATUS_COMPLETED,
            'region' => 'NCR',
            'detail_id' => $repair->id,
        ]);
    }

    private function completeTicketSurvey(RequestModel $ticket): void
    {
        // Satisfy RequirePendingSurvey middleware (completed ICT ticket needs a CSM survey)
        \App\Models\CsmSurvey::create([
            'request_id' => $ticket->id,
            'age' => 30,
            'sex' => 'Male',
            'cc1' => '2', 'cc2' => '2', 'cc3' => '2',
            'sqd1' => 'Agree', 'sqd2' => 'Agree', 'sqd3' => 'Agree',
            'sqd4' => 'Agree', 'sqd5' => 'Agree', 'sqd6' => 'Agree',
            'sqd7' => 'Agree', 'sqd8' => 'Agree', 'sqd9' => 'Agree',
        ]);
    }

    public function test_save_signature_writes_to_private_disk_with_month_folder(): void
    {
        $base64 = 'data:image/png;base64,' . self::TINY_PNG;

        $path = RequestHelpers::saveSignature($base64, 'ict_tech', 'Juan Dela Cruz');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('signatures/' . now()->format('Y') . '/' . now()->format('F') . '/', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertFalse(Storage::disk('public')->exists($path));
    }

    public function test_save_signature_rejects_non_image_payload(): void
    {
        $this->assertNull(RequestHelpers::saveSignature('not-a-base64-image', 'ict_tech', 'X'));
    }

    public function test_owner_can_view_signature_via_authed_route(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeIctTicketWithSignature($owner);
        $this->completeTicketSurvey($ticket);

        $response = $this->actingAs($owner)
            ->get("/tickets/{$ticket->id}/signature/technician_signature");

        $response->assertStatus(200);
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
    }

    public function test_unrelated_user_gets_403(): void
    {
        $owner = $this->makeUser();
        $stranger = $this->makeUser(['full_name' => 'Stranger', 'email' => 'stranger@test.com']);
        $ticket = $this->makeIctTicketWithSignature($owner);

        $this->actingAs($stranger)
            ->get("/tickets/{$ticket->id}/signature/technician_signature")
            ->assertStatus(403);
    }

    public function test_invalid_field_name_returns_404(): void
    {
        $owner = $this->makeUser();
        $ticket = $this->makeIctTicketWithSignature($owner);
        $this->completeTicketSurvey($ticket);

        $this->actingAs($owner)
            ->get("/tickets/{$ticket->id}/signature/password_hash")
            ->assertStatus(404);
    }

    public function test_local_disk_is_not_publicly_served(): void
    {
        // D5b: the /storage/{path} serve route (storage.local) must NOT exist —
        // otherwise private files would be reachable without auth.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('storage.local'));
    }
}
