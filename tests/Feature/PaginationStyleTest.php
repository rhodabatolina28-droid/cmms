<?php

namespace Tests\Feature;

use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Tests\TestCase;

/**
 * D9.40 - list-page pagination rendered as Laravel's raw default paginator.
 *
 * Root cause: the layout's legacy "Pagination Fix" CSS targeted a Bootstrap
 * style paginator (ul/li, .active span, li[aria-current]) but Laravel 11 renders
 * a div/span paginator. The blanket rule `nav[role="navigation"] a, span`
 * therefore boxed the "Showing 1 to 20 of 22 results" numbers, the current page
 * was never highlighted and the mobile prev/next block was force-hidden.
 *
 * Contract pinned here:
 *  1. Every list page renders the shared vendor/pagination/cmms view
 *     (styled summary + windowed page numbers).
 *  2. No page emits the raw Tailwind paginator markup or the legacy blanket CSS.
 *  3. Page numbers stay bounded (window = current +/- 2) as rows grow.
 */
class PaginationStyleTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICE = 'RESEARCH AND INFORMATION DIVISION';

    private int $counter = 0;

    private function user(string $role = 'user', array $attributes = []): User
    {
        $this->counter++;

        return User::create(array_merge([
            'full_name' => 'Pag ' . ucfirst($role) . ' ' . $this->counter,
            'email'     => 'pag-' . $role . '-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'Main Office',
            'office'    => self::OFFICE,
        ], $attributes));
    }

    private function ictRequests(User $owner, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->counter++;

            RequestModel::create([
                'user_id'        => $owner->id,
                'request_number' => 'REQ-2026-09-23-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
                'type'           => 'ICT',
                'requestor_name' => $owner->full_name,
                'region'         => 'NCR',
                'branch'         => 'Main Office',
                'office'         => self::OFFICE,
                'status'         => 'Ongoing',
                'description'    => 'Pagination fixture',
            ]);
        }
    }

    private function assertStyledPagination(string $html, int $from, int $to, int $total): void
    {
        $this->assertStringContainsString('cmms-pag__info', $html, 'List page must render the shared styled pagination.');
        $this->assertMatchesRegularExpression(
            '/Showing\s*[<]strong[>]' . $from . '[<]\/strong[>]\s*to\s*[<]strong[>]' . $to . '[<]\/strong[>]\s*of\s*[<]strong[>]' . $total . '[<]\/strong[>]\s*results/',
            $html,
            'Pagination summary must read Showing X to Y of Z results.'
        );
        $this->assertStringNotContainsString(
            'text-sm text-gray-700 leading-5 dark:text-gray-600',
            $html,
            'Raw Tailwind paginator markup must not be rendered any more.'
        );
        $this->assertStringNotContainsString(
            'nav[role="navigation"] a, nav[role="navigation"] span',
            $html,
            'The legacy blanket pagination CSS must be gone.'
        );
    }

    public function test_user_ict_list_pagination_is_styled_and_counted(): void
    {
        $user = $this->user('user');
        $this->ictRequests($user, 22);

        $response = $this->actingAs($user)->get(route('ict.index'));

        $response->assertOk();
        $this->assertStyledPagination($response->getContent(), 1, 20, 22);
    }

    public function test_admin_manage_requests_pagination_is_styled(): void
    {
        $admin = $this->user('admin');
        $requestor = $this->user('user');
        $this->ictRequests($requestor, 22);

        $response = $this->actingAs($admin)->get(route('ict.index'));

        $response->assertOk();
        $this->assertStyledPagination($response->getContent(), 1, 20, 22);
    }

    public function test_personnel_management_pagination_uses_the_shared_view(): void
    {
        $admin = $this->user('admin');
        for ($i = 0; $i < 20; $i++) {
            $this->user('user');
        }

        $response = $this->actingAs($admin)->get(route('personnel.index'));

        $response->assertOk();
        $this->assertStyledPagination($response->getContent(), 1, 20, 21);
    }

    public function test_page_numbers_stay_windowed_when_rows_grow(): void
    {
        $user = $this->user('user');
        $this->ictRequests($user, 200);

        $response = $this->actingAs($user)->get(route('ict.index', ['page' => 5]));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/[<]nav class="cmms-pag__nav"[^\n]*?[>].*?[<]\/nav[>]/s', $html);
        preg_match('/[<]nav class="cmms-pag__nav"[^\n]*?[>](.*?)[<]\/nav[>]/s', $html, $matches);
        $nav = $matches[1];

        $this->assertSame(7, substr_count($nav, 'cmms-pag__num'), 'Page numbers must stay windowed as data grows.');
        $this->assertSame(2, substr_count($nav, 'cmms-pag__gap'), 'Ellipsis must mark the collapsed ranges.');
        $this->assertStringContainsString('page=10', $nav, 'The last page must always be reachable.');
    }

    public function test_bare_links_uses_the_styled_view_by_default(): void
    {
        // The paginator default view is pinned in AppServiceProvider (D9.40) so a
        // list page added later can never fall back to the raw Tailwind paginator.
        $this->assertSame('vendor.pagination.cmms', Paginator::$defaultView);

        for ($i = 0; $i < 21; $i++) {
            $this->user('user');
        }

        $html = (string) User::query()->paginate(20)->links();

        $this->assertStringContainsString('cmms-pag__info', $html);
        $this->assertStringContainsString('of <strong>21</strong> results', $html);
        $this->assertStringNotContainsString('text-sm text-gray-700 leading-5 dark:text-gray-600', $html);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // D9.45 — the Supply Workspace was MISSED by the D9.40 sweep: its three tabs
    // explicitly rendered the legacy vendor.pagination.parts view (whose CSS
    // lives only inside inventory/parts.blade.php), so the Requisition Queue
    // pagination bar showed up as raw, unstyled links. Contract: every Supply
    // Workspace surface — server-rendered AND AJAX — uses the shared cmms view.
    // ──────────────────────────────────────────────────────────────────────────

    private function makeSupplyAdmin(array $attributes = []): User
    {
        return $this->user('admin', array_merge(['can_supply' => true], $attributes));
    }

    private function makeRequisition(User $requester): void
    {
        $ticket = RequestModel::create([
            'user_id'        => $this->makeUser()->id,
            'assigned_to'    => $requester->id,
            'request_number' => 'JO-NCR-2026-' . str_pad((string) $this->counter, 4, '0', STR_PAD_LEFT),
            'type'           => 'ICT',
            'requestor_name' => 'Queue Requestor',
            'description'    => 'D9.45 pagination fixture',
            'status'         => RequestModel::STATUS_ONGOING,
            'region'         => 'NCR',
        ]);

        \App\Models\Requisition::create([
            'request_id'   => $ticket->id,
            'requested_by' => $requester->id,
            'status'       => \App\Models\Requisition::STATUS_PENDING,
            'items'        => [['description' => 'Pagination item', 'quantity' => 1]],
            'remarks'      => null,
        ]);
    }

    private function makeUser(array $attributes = []): User
    {
        return $this->user('user', $attributes);
    }

    public function test_supply_queue_pagination_uses_the_shared_view(): void
    {
        $supply = $this->makeSupplyAdmin(['branch' => 'RCMB']);
        $requester = $this->makeUser(['branch' => 'RCMB']);
        for ($i = 0; $i < 22; $i++) {
            $this->makeRequisition($requester);
        }

        $response = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'queue']));

        $response->assertOk();
        $this->assertStyledPagination($response->getContent(), 1, 20, 22);
        $this->assertStringNotContainsString(
            'parts-pag-btns',
            $response->getContent(),
            'The Supply Workspace must not render the legacy parts paginator (its CSS only exists on the Parts page).'
        );
    }

    public function test_supply_queue_ajax_endpoint_returns_the_shared_pagination_view(): void
    {
        $supply = $this->makeSupplyAdmin(['branch' => 'RCMB']);
        $requester = $this->makeUser(['branch' => 'RCMB']);
        for ($i = 0; $i < 22; $i++) {
            $this->makeRequisition($requester);
        }

        $response = $this->actingAs($supply)->getJson(route('requisitions.queue.data', ['view' => 'queue', 'status' => 'all']));

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(22, $response->json('total'));
        $pagination = (string) $response->json('pagination');
        $this->assertStringContainsString('cmms-pag__info', $pagination, 'AJAX pagination must use the shared styled view.');
        $this->assertStringNotContainsString('parts-pag-btns', $pagination);
    }

    public function test_supply_job_orders_tab_pagination_uses_the_shared_view(): void
    {
        $supply = $this->makeSupplyAdmin(['branch' => 'RCMB']);
        $requester = $this->makeUser(['branch' => 'RCMB']);
        for ($i = 0; $i < 22; $i++) {
            $this->makeRequisition($requester);
        }

        $response = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'tickets']));

        $response->assertOk();
        $this->assertStyledPagination($response->getContent(), 1, 20, 22);
        $this->assertStringNotContainsString('parts-pag-btns', $response->getContent());
    }

    public function test_supply_purchase_requests_tab_pagination_uses_the_shared_view(): void
    {
        $supply = $this->makeSupplyAdmin(['branch' => 'RCMB']);
        $it = $this->makeUser(['role' => 'it', 'branch' => 'RCMB']);
        for ($i = 0; $i < 22; $i++) {
            \App\Models\PurchaseRequest::create([
                'pr_number'    => 'PR-2026-94' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'requisition_id' => null,
                'requested_by' => $it->id,
                'created_by'   => $it->id,
                'status'       => \App\Models\PurchaseRequest::STATUS_SUBMITTED,
                'items'        => [['description' => 'PR pagination item', 'quantity' => 1]],
                'total_amount' => 100,
            ]);
        }

        $response = $this->actingAs($supply)->get(route('requisitions.index', ['view' => 'purchase-requests']));

        $response->assertOk();
        $this->assertStringContainsString('cmms-pag__info', $response->getContent());
        $this->assertStringNotContainsString(
            'parts-pag-btns',
            $response->getContent(),
            'PR tab must render the shared cmms paginator, not the legacy parts one.'
        );
    }
}

