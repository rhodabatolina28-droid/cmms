<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D9.41 - the list filter ribbon only filtered the 20 rows already rendered in the
 * browser, so the moment a list crossed one page (20 rows) every match living on
 * page 2+ became invisible - exactly the situation the division admin hits now.
 *
 * The ribbon (q / status / category) is now submitted to the server as a GET form,
 * applied to the query before pagination and kept alive by withQueryString().
 */
class ServerSideFilterTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE = 'RESEARCH AND INFORMATION DIVISION';

    private int $counter = 0;

    private function user(string $role = 'user', ?string $name = null): User
    {
        $this->counter++;

        return User::create([
            'full_name' => $name ?? ('Filter ' . ucfirst($role) . ' ' . $this->counter),
            'email'     => 'filter-' . $role . '-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'Main Office',
            'office'    => self::OFFICE,
        ]);
    }

    private function ict(User $owner, string $description, string $status = 'Ongoing'): RequestModel
    {
        $this->counter++;

        return RequestModel::create([
            'user_id'        => $owner->id,
            'request_number' => 'REQ-2026-09-23-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type'           => 'ICT',
            'requestor_name' => $owner->full_name,
            'region'         => 'NCR',
            'branch'         => 'Main Office',
            'office'         => self::OFFICE,
            'status'         => $status,
            'description'    => $description,
        ]);
    }

    public function test_ict_list_filters_server_side_by_search_and_status(): void
    {
        $user = $this->user('user');
        for ($i = 0; $i < 20; $i++) {
            $this->ict($user, 'Printer jammed in lobby ' . $i, 'Ongoing');
        }
        for ($i = 0; $i < 5; $i++) {
            $this->ict($user, 'Monitor flicker at desk ' . $i, 'Pending');
        }

        // Walang filter: lahat ng 25 (2 pages).
        $all = $this->actingAs($user)->get(route('ict.index'))->assertOk()->getContent();
        $this->assertStringContainsString('of <strong>25</strong> results', $all);

        // Search na tumatakbo sa LAHAT ng rows, hindi lang sa unang page.
        $search = $this->actingAs($user)->get(route('ict.index', ['q' => 'Monitor']))->assertOk()->getContent();
        $this->assertStringContainsString('of <strong>5</strong> results', $search);
        $this->assertStringContainsString('Monitor flicker at desk', $search);
        $this->assertStringNotContainsString('Printer jammed in lobby', $search);

        // Status filter - server side.
        $status = $this->actingAs($user)->get(route('ict.index', ['status' => 'Pending']))->assertOk()->getContent();
        $this->assertStringContainsString('of <strong>5</strong> results', $status);
        $this->assertStringNotContainsString('Printer jammed in lobby', $status);

        // Pinagsama (search + status) - walang tugma.
        $both = $this->actingAs($user)->get(route('ict.index', ['q' => 'Monitor', 'status' => 'Ongoing']))->assertOk()->getContent();
        $this->assertStringContainsString('No requests found in your repository.', $both);
    }

    public function test_admin_manage_requests_search_covers_requestor_and_form(): void
    {
        $admin = $this->user('admin');
        $clerk = $this->user('user', 'Maria Santos');
        for ($i = 0; $i < 19; $i++) {
            $this->ict($this->user('user'), 'Printer jammed ' . $i);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->ict($clerk, 'Laptop battery ' . $i);
        }

        $all = $this->actingAs($admin)->get(route('ict.index'))->assertOk()->getContent();
        $this->assertStringContainsString('of <strong>22</strong> results', $all);
        $this->assertStringContainsString('<form method="GET"', $all);
        $this->assertStringContainsString('name="q"', $all);

        $search = $this->actingAs($admin)->get(route('ict.index', ['q' => 'Santos']))->assertOk()->getContent();
        $this->assertStringContainsString('of <strong>3</strong> results', $search);
        $this->assertStringContainsString('Maria Santos', $search);
        $this->assertStringNotContainsString('Filter User 3', $search);
    }

    public function test_filters_survive_pagination_links(): void
    {
        $user = $this->user('user');
        for ($i = 0; $i < 25; $i++) {
            $this->ict($user, 'Printer jammed ' . $i);
        }

        $html = $this->actingAs($user)->get(route('ict.index', ['q' => 'Printer']))->assertOk()->getContent();

        $this->assertStringContainsString('of <strong>25</strong> results', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*q=Printer[^"]*page=2[^"]*"/', $html);
    }

    public function test_filter_ribbon_is_a_get_form_without_client_side_row_hiding(): void
    {
        $user = $this->user('user');
        $this->ict($user, 'Monitor flicker', 'Pending');

        $html = $this->actingAs($user)
            ->get(route('ict.index', ['q' => 'Monitor', 'status' => 'Pending', 'category' => 'Monitor']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<form method="GET"', $html);
        $this->assertStringContainsString('name="status"', $html);
        $this->assertStringContainsString('name="category"', $html);
        $this->assertMatchesRegularExpression('/name="q"[^\n]*?value="Monitor"/', $html);
        $this->assertStringContainsString('<option value="Pending" selected>', $html);
        $this->assertStringContainsString('<option value="Monitor" selected>', $html);

        // Wala nang client-side row hiding.
        $this->assertStringNotContainsString('function filterRequests', $html);
        $this->assertStringNotContainsString('row.style.display', $html);
    }
}