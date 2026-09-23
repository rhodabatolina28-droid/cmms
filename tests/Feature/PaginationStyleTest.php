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

    private function user(string $role = 'user'): User
    {
        $this->counter++;

        return User::create([
            'full_name' => 'Pag ' . ucfirst($role) . ' ' . $this->counter,
            'email'     => 'pag-' . $role . '-' . $this->counter . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => $role,
            'is_active' => true,
            'region'    => 'NCR',
            'branch'    => 'Main Office',
            'office'    => self::OFFICE,
        ]);
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
}
