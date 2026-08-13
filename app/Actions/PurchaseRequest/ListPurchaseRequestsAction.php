<?php

namespace App\Actions\PurchaseRequest;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Http\Request;

class ListPurchaseRequestsAction
{
    /**
     * @param  User  $user  (supply officer / admin / super_admin)
     * @return array<string, mixed>
     */
    public function execute(Request $request, User $user): array
    {
        $query = PurchaseRequest::with('requisition.ticket', 'requester', 'approver', 'receiver');

        // Scoped to the user's region/branch via the linked requisition's ticket.
        $query->whereHas('requisition.ticket', function ($q) use ($user) {
            if ($user->region) {
                $q->where('region', $user->region);
            }
            if ($user->branch) {
                $q->where('branch', $user->branch);
            }
        });

        $filter = (string) $request->query('status', 'pending');
        if (in_array($filter, [
            PurchaseRequest::STATUS_PENDING,
            PurchaseRequest::STATUS_APPROVED,
            PurchaseRequest::STATUS_RECEIVED,
            PurchaseRequest::STATUS_CANCELLED,
            'all',
        ], true) && $filter !== 'all') {
            $query->where('status', $filter);
        }

        $requests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'pending' => $this->countFor($user, PurchaseRequest::STATUS_PENDING),
            'approved' => $this->countFor($user, PurchaseRequest::STATUS_APPROVED),
            'received' => $this->countFor($user, PurchaseRequest::STATUS_RECEIVED),
            'cancelled' => $this->countFor($user, PurchaseRequest::STATUS_CANCELLED),
        ];

        return compact('requests', 'filter', 'counts');
    }

    private function countFor(User $user, string $status): int
    {
        return PurchaseRequest::where('status', $status)
            ->whereHas('requisition.ticket', function ($q) use ($user) {
                if ($user->region) {
                    $q->where('region', $user->region);
                }
                if ($user->branch) {
                    $q->where('branch', $user->branch);
                }
            })
            ->count();
    }
}