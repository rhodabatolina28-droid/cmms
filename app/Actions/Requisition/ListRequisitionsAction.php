<?php

namespace App\Actions\Requisition;

use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListRequisitionsAction
{
    /**
     * Display the requisition index based on user role.
     *
     * @param  \Illuminate\Http\Request  $httpRequest
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function execute(Request $httpRequest)
    {
        $user = Auth::user();

        // Supply admin gets supply view
        if ($user->canProcessSupply()) {
            return $this->supplyIndex($httpRequest, $user);
        }

        // IT personnel gets their requisition list
        if ($user->role === 'it') {
            return $this->itIndex($user, $httpRequest);
        }

        // Super Admin: if they have active assigned tickets, show IT-style view
        // so they can manage parts requests for tickets they are handling
        if ($user->role === 'super_admin') {
            return $this->superAdminRequisitionIndex($user, $httpRequest);
        }

        abort(403);
    }

    private function supplyIndex(Request $httpRequest, User $supply)
    {
        $supplyView = $httpRequest->query('view', 'queue');
        if (!in_array($supplyView, ['queue', 'tickets', 'purchase-requests'], true)) {
            $supplyView = 'queue';
        }

        // Default queue view = All records; the stat cards narrow it down.
        $filter = $httpRequest->query('status', 'all');
        $allowed = ['pending', 'approved', 'issued', 'rejected', 'all'];
        if (!in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        // Optional free-text search + sort (Supply queue only).
        $q = trim((string) $httpRequest->query('q', ''));
        $sort = $httpRequest->query('sort', 'newest');
        if (!in_array($sort, ['newest', 'oldest'], true)) {
            $sort = 'newest';
        }

        $counts = [
            'pending' => $this->supplyRequisitionCount($supply, Requisition::STATUS_PENDING),
            'approved' => $this->supplyRequisitionCount($supply, Requisition::STATUS_APPROVED),
            'issued' => $this->supplyRequisitionCount($supply, Requisition::STATUS_ISSUED),
            'rejected' => $this->supplyRequisitionCount($supply, Requisition::STATUS_REJECTED),
        ];

        // PR submitted count — always computed for the workspace tab badge.
        $prCounts = ['submitted' => \App\Models\PurchaseRequest::where('status', \App\Models\PurchaseRequest::STATUS_SUBMITTED)->count()];

        if ($supplyView === 'purchase-requests') {
            // PR document flow — queue + history via the PR list action.
            $prData = (new \App\Actions\PurchaseRequest\ListPurchaseRequestsAction)
                ->execute($httpRequest, $supply);
            $prCounts = $prData['counts'];

            // Submitted queue (awaiting Supply review/finalize).
            $prQueue = \App\Models\PurchaseRequest::with('requester', 'creator', 'requisition.ticket')
                ->where('status', \App\Models\PurchaseRequest::STATUS_SUBMITTED)
                ->orderByDesc('created_at')
                ->get();

            $requisitions = Requisition::whereRaw('0=1')->paginate(1);
            $ictTickets = RequestModel::whereRaw('0=1')->paginate(1);

            return view('requisitions.supply-index', array_merge(compact(
                'requisitions',
                'filter',
                'counts',
                'supplyView',
                'ictTickets',
                'q',
                'sort',
                'prCounts',
                'prQueue'
            ), $prData));
        }

        if ($supplyView === 'tickets') {
            $ticketQuery = RequestModel::with(['user', 'assignedTo', 'requisitions'])
                ->withCount('requisitions');
            \App\Support\RequestHelpers::scopeIctTicketsForSupplyAdmin($supply, $ticketQuery);

            if ($q !== '') {
                $ticketQuery->where(function ($w) use ($q) {
                    $w->where('request_number', 'like', "%{$q}%")
                        ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$q}%"))
                        ->orWhereHas('assignedTo', fn ($a) => $a->where('full_name', 'like', "%{$q}%"));
                });
            }

            $ictTickets = $ticketQuery
                ->orderByDesc('updated_at')
                ->paginate(20)
                ->withQueryString();

            $requisitions = Requisition::whereRaw('0=1')->paginate(1);

            return view('requisitions.supply-index', compact(
                'requisitions',
                'filter',
                'counts',
                'supplyView',
                'ictTickets',
                'q',
                'sort'
            ));
        }

        $query = Requisition::with(['ticket.linkedAsset.assignedUser', 'requester', 'reviewer']);
        \App\Support\RequestHelpers::scopeRequisitionsForSupplyOfficer($supply, $query);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        if ($q !== '') {
            // REQ-##### alias or plain id, requester name, job order number,
            // item descriptions (items JSON) or purpose text.
            $rawId = ctype_digit($q) ? (int) $q : null;
            if (!$rawId && preg_match('/^req[-_ ]?0*(\d+)$/i', $q, $m)) {
                $rawId = (int) $m[1];
            }

            $query->where(function ($w) use ($q, $rawId) {
                if ($rawId !== null) {
                    $w->orWhere('id', $rawId);
                }
                $w->orWhere(function ($s) use ($q) {
                    $s->whereHas('requester', fn ($r) => $r->where('full_name', 'like', "%{$q}%"));
                    $s->orWhereHas('ticket', fn ($t) => $t->where('request_number', 'like', "%{$q}%"));
                    $s->orWhere('items', 'like', "%{$q}%");
                    $s->orWhere('remarks', 'like', "%{$q}%");
                });
            });
        }

        $requisitions = $query
            ->orderBy('created_at', $sort === 'oldest' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        $ictTickets = RequestModel::whereRaw('0=1')->paginate(1);

        return view('requisitions.supply-index', compact(
            'requisitions',
            'filter',
            'counts',
            'supplyView',
            'ictTickets',
            'q',
            'sort'
        ));
    }

    /**
     * History-tab filters for IT / Super-Admin "My Parts Requisitions".
     * Defaults keep the full newest-first listing untouched.
     */
    private function historyFilters(Request $httpRequest): array
    {
        $status = $httpRequest->query('history_status', 'all');
        if (!in_array($status, ['all', 'pending', 'approved', 'issued', 'rejected'], true)) {
            $status = 'all';
        }

        $q = trim((string) $httpRequest->query('history_q', ''));

        return [$status, $q];
    }

    private function applyHistoryFilters($query, string $status, string $q)
    {
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $rawId = ctype_digit($q) ? (int) $q : null;
            if (!$rawId && preg_match('/^req[-_ ]?0*(\d+)$/i', $q, $m)) {
                $rawId = (int) $m[1];
            }
            $query->where(function ($w) use ($q, $rawId) {
                if ($rawId !== null) {
                    $w->orWhere('id', $rawId);
                }
                $w->orWhere(function ($s) use ($q) {
                    $s->orWhere('items', 'like', "%{$q}%");
                    $s->orWhere('remarks', 'like', "%{$q}%");
                });
            });
        }
        return $query;
    }

    private function itIndex(User $itUser, Request $httpRequest)
    {
        $activeTickets = RequestModel::with(['user', 'linkedAsset.assignedUser'])
            ->where('assigned_to', $itUser->id)
            ->where(function ($q) {
                $q->where('type', 'ICT')
                  ->orWhere(fn ($pm) => $pm->where('type', 'Preventive Maintenance')->whereNotNull('linked_asset_id'));
            })
            ->whereNotIn('status', [RequestModel::STATUS_COMPLETED, RequestModel::STATUS_CANCELLED])
            ->orderByDesc('created_at')
            ->get();

        [$historyStatus, $historyQ] = $this->historyFilters($httpRequest);

        $requisitions = $this->applyHistoryFilters(
            Requisition::with(['ticket', 'requester', 'reviewer'])->where('requested_by', $itUser->id),
            $historyStatus,
            $historyQ
        )->orderByDesc('created_at')->paginate(20)->withQueryString();

        $selectedTicketId = $httpRequest->query('request_id');

        // Open requisitions (pending/approved) per ticket, for the proactive
        // duplicate-parts guard on the Request Parts form.
        $openRequisitions = Requisition::where('requested_by', $itUser->id)
            ->whereIn('status', [
                Requisition::STATUS_PENDING,
                Requisition::STATUS_APPROVED,
            ])
            ->get(['id', 'request_id', 'status', 'items']);

        $partsStock = \App\Models\Part::where('is_active', true)
            ->when($itUser->region, fn ($q) => $q->where('region', $itUser->region))
            ->when($itUser->branch, fn ($q) => $q->where('branch', $itUser->branch))
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'unit', 'on_hand_qty']);

        // Own purchase requests (PR document flow) for the "My Purchase Requests" strip.
        $myPrs = \App\Models\PurchaseRequest::query()
            ->where(function ($q) use ($itUser) {
                $q->where('requested_by', $itUser->id)->orWhere('created_by', $itUser->id);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('requisitions.it-index', compact(
            'activeTickets',
            'requisitions',
            'selectedTicketId',
            'partsStock',
            'historyStatus',
            'historyQ',
            'openRequisitions',
            'myPrs'
        ));
    }

    /**
     * Super Admin requisition view — shows tickets they are assigned to (acting as IT)
     * and their submitted parts requests.
     */
    private function superAdminRequisitionIndex(User $superAdmin, Request $httpRequest)
    {
        // Active ICT tickets where Super Admin is the assigned personnel
        $activeTickets = RequestModel::with(['user', 'linkedAsset.assignedUser'])
            ->where('assigned_to', $superAdmin->id)
            ->where(function ($q) {
                $q->where('type', 'ICT')
                  ->orWhere(fn ($pm) => $pm->where('type', 'Preventive Maintenance')->whereNotNull('linked_asset_id'));
            })
            ->whereNotIn('status', [RequestModel::STATUS_COMPLETED, RequestModel::STATUS_CANCELLED])
            ->orderByDesc('created_at')
            ->get();

        // Their submitted requisitions (History tab filters)
        [$historyStatus, $historyQ] = $this->historyFilters($httpRequest);

        $requisitions = $this->applyHistoryFilters(
            Requisition::with(['ticket', 'requester', 'reviewer'])->where('requested_by', $superAdmin->id),
            $historyStatus,
            $historyQ
        )->orderByDesc('created_at')->paginate(20)->withQueryString();

        $selectedTicketId = $httpRequest->query('request_id');

        // Open requisitions (pending/approved) per ticket, for the proactive
        // duplicate-parts guard on the Request Parts form.
        $openRequisitions = Requisition::where('requested_by', $superAdmin->id)
            ->whereIn('status', [
                Requisition::STATUS_PENDING,
                Requisition::STATUS_APPROVED,
            ])
            ->get(['id', 'request_id', 'status', 'items']);

        $partsStock = \App\Models\Part::where('is_active', true)
            ->when($superAdmin->region, fn ($q) => $q->where('region', $superAdmin->region))
            ->when($superAdmin->branch, fn ($q) => $q->where('branch', $superAdmin->branch))
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'unit', 'on_hand_qty']);

        // Own purchase requests (PR document flow) for the "My Purchase Requests" strip.
        $myPrs = \App\Models\PurchaseRequest::query()
            ->where(function ($q) use ($superAdmin) {
                $q->where('requested_by', $superAdmin->id)->orWhere('created_by', $superAdmin->id);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('requisitions.it-index', compact(
            'activeTickets',
            'requisitions',
            'selectedTicketId',
            'partsStock',
            'historyStatus',
            'historyQ',
            'openRequisitions',
            'myPrs'
        ));
    }

    private function supplyRequisitionCount(User $supply, string $status): int
    {
        $query = Requisition::query()->where('status', $status);
        \App\Support\RequestHelpers::scopeRequisitionsForSupplyOfficer($supply, $query);

        return $query->count();
    }
}
