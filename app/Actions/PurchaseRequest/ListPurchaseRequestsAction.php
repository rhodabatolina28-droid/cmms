<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ListPurchaseRequestsAction
{
    /**
     * PR history list.
     *
     * Scoping:
     *  - Supply Officer: all PRs in their region/branch (requisition-linked via
     *    the ticket, standalone via the document creator's org).
     *  - Super Admin: everything (branch-wide view, no narrowing here).
     *  - IT: only their own requests (requested_by or created_by).
     *
     * @param  User  $user
     * @return array<string, mixed>
     */
    public function execute(Request $request, User $user): array
    {
        $query = PurchaseRequest::with('requisition.ticket', 'requester', 'creator', 'finalizer');

        $this->applyOrgScope($query, $user);

        if ($user->role === 'it') {
            $query->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        // Status filter: current flow statuses + "legacy" + old statuses + all.
        $filter = (string) $request->query('status', 'all');
        if ($filter !== 'all') {
            if (in_array($filter, PurchaseRequest::CURRENT_STATUSES, true)) {
                $query->where('status', $filter);
            } elseif ($filter === 'legacy') {
                $query->whereNotIn('status', PurchaseRequest::CURRENT_STATUSES);
            } elseif (in_array($filter, [
                PurchaseRequest::STATUS_PENDING,
                PurchaseRequest::STATUS_APPROVED,
                PurchaseRequest::STATUS_RECEIVED,
                PurchaseRequest::STATUS_CANCELLED,
            ], true)) {
                $query->where('status', $filter);
            } else {
                $filter = 'all';
            }
        }

        $requests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [];
        foreach (array_merge(PurchaseRequest::CURRENT_STATUSES, ['legacy']) as $key) {
            $base = PurchaseRequest::query();
            if ($key === 'legacy') {
                $base->whereNotIn('status', PurchaseRequest::CURRENT_STATUSES);
            } else {
                $base->where('status', $key);
            }

            $counts[$key] = $this->countScoped($base, $user);
        }

        return compact('requests', 'filter', 'counts');
    }

    private function applyOrgScope($query, User $user): void
    {
        if ($user->role === 'super_admin') {
            return; // branch-wide visibility
        }

        $query->where(function ($q) use ($user) {
            // Requisition-linked PRs: scope via the linked ticket.
            $q->whereHas('requisition.ticket', function ($t) use ($user) {
                if ($user->region) {
                    $t->where('region', $user->region);
                }
                if ($user->branch) {
                    $t->where('branch', $user->branch);
                }
            // Standalone PRs (no requisition): scope via the document creator.
            })->orWhere(function ($standalone) use ($user) {
                $standalone->whereNull('requisition_id')
                    ->where(function ($c) use ($user) {
                        // Creator org scope for new documents...
                        $c->whereHas('creator', function ($cc) use ($user) {
                            if ($user->region) {
                                $cc->where('region', $user->region);
                            }
                            if ($user->branch) {
                                $cc->where('branch', $user->branch);
                            }
                        });
                        // ...and legacy records (pre-revamp) have no creator —
                        // treat as historical and visible office-wide.
                        $c->orWhereNull('created_by');
                    });
            });
        });
    }

    private function countScoped($query, User $user): int
    {
        $this->applyOrgScope($query, $user);

        if ($user->role === 'it') {
            $query->where(function ($q) use ($user) {
                $q->where('requested_by', $user->id)
                    ->orWhere('created_by', $user->id);
            });
        }

        return $query->count();
    }
}
