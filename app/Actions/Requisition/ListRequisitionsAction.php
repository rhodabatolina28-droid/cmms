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
        if (!in_array($supplyView, ['queue', 'tickets'], true)) {
            $supplyView = 'queue';
        }

        $filter = $httpRequest->query('status', 'pending');
        $allowed = ['pending', 'approved', 'issued', 'rejected', 'all'];
        if (!in_array($filter, $allowed, true)) {
            $filter = 'pending';
        }

        // Optional free-text search + sort (Supply queue only; defaults keep the
        // original newest-first listing untouched).
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

        $requisitions = Requisition::with(['ticket', 'requester', 'reviewer'])
            ->where('requested_by', $itUser->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $selectedTicketId = $httpRequest->query('request_id');

        $partsStock = \App\Models\Part::where('is_active', true)
            ->when($itUser->region, fn ($q) => $q->where('region', $itUser->region))
            ->when($itUser->branch, fn ($q) => $q->where('branch', $itUser->branch))
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'unit', 'on_hand_qty']);

        return view('requisitions.it-index', compact('activeTickets', 'requisitions', 'selectedTicketId', 'partsStock'));
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

        // Their submitted requisitions
        $requisitions = Requisition::with(['ticket', 'requester', 'reviewer'])
            ->where('requested_by', $superAdmin->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $selectedTicketId = $httpRequest->query('request_id');

        $partsStock = \App\Models\Part::where('is_active', true)
            ->when($superAdmin->region, fn ($q) => $q->where('region', $superAdmin->region))
            ->when($superAdmin->branch, fn ($q) => $q->where('branch', $superAdmin->branch))
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'unit', 'on_hand_qty']);

        return view('requisitions.it-index', compact('activeTickets', 'requisitions', 'selectedTicketId', 'partsStock'));
    }

    private function supplyRequisitionCount(User $supply, string $status): int
    {
        $query = Requisition::query()->where('status', $status);
        \App\Support\RequestHelpers::scopeRequisitionsForSupplyOfficer($supply, $query);

        return $query->count();
    }
}
