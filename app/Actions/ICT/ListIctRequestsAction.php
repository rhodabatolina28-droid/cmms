<?php

namespace App\Actions\ICT;

use App\Models\Request as RequestModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListIctRequestsAction
{
    /** Statuses offered by the ribbon filter (server side whitelist). */
    private const FILTER_STATUSES = ['Pending', 'Ongoing', 'Completed', 'Rejected'];

    /**
     * Show requests based on role (ICT only for users/admin, ICT+PM for IT/super_admin).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\View\View|\Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        $query = RequestModel::with(['user', 'repairRequest', 'assignedTo', 'linkedAsset:asset_id,category']);

        // D9.41 - server-side ribbon filters (q / status / category).
        $filters = $this->filters($request, $user);
        $apply = fn (Builder $q) => $this->applyFilters($q, $filters);
        if ($user->role === 'user') {
            $query->where('type', 'ICT')->where('user_id', $user->id);
            // Unfinished-first: Pending/Ongoing/waiting float, Completed sinks.
            $requests = $apply($query)->unfinishedFirst()->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'it') {
            // D4b: officials-first queue — official tickets jump to the top.
            $query->where('type', 'ICT')->where('assigned_to', $user->id);
            // Unfinished-first FIRST (primary key), then officials within the active group.
            $requests = $apply($query)->unfinishedFirst()->officialsFirst()->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
            }
            return view('requests.index', compact('requests'));
        } elseif ($user->role === 'admin' || $user->role === 'supply_officer' || $user->role === 'super_admin') {
            if ($user->role === 'admin' || $user->role === 'supply_officer') {
                $query->where('type', 'ICT')->whereHas('user', function($q) use ($user) {
                    if ($user->branch) {
                        $q->where('users.branch', $user->branch);
                    }
                    if ($user->office) {
                        $q->where('users.office', $user->office);
                    }
                });
                // Unfinished-first FIRST (primary key), then officials within the group.
                $requests = $apply($query)->unfinishedFirst()->officialsFirst()->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('admin.requests.index', compact('requests'));
            } else {
                $requests = $apply($query)->where('type', 'ICT')
                    ->where('division_admin_review_status', 'Approved')
                    ->whereHas('user', function ($q) use ($user) {
                        if ($user->branch) {
                            $q->where('users.branch', $user->branch);
                        }
                    })
                    ->unfinishedFirst()
                    ->officialsFirst()
                    ->orderBy('created_at', 'desc')
                    ->paginate(20)->withQueryString();
                if ($request->wantsJson() || $request->expectsJson()) {
                    return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
                }
                return view('super-admin.requests.index', compact('requests'));
            }
        }

        // Unfinished-first (primary key), then officials, then newest.
        $requests = $apply($query)->unfinishedFirst()->officialsFirst()->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json(['success' => true, 'requests' => $requests->items(), 'total' => $requests->total(), 'last_page' => $requests->lastPage(), 'current_page' => $requests->currentPage()]);
        }
        return view('requests.index', compact('requests'));
    }
    /**
     * Normalised ribbon filters from the request.
     *
     * @return array{search: string, status: string, category: string, broad: bool}
     */
    private function filters(Request $request, $user): array
    {
        $status = (string) $request->input('status', '');

        return [
            'search'   => mb_substr(trim((string) $request->input('q', '')), 0, 100),
            'status'   => in_array($status, self::FILTER_STATUSES, true) ? $status : '',
            'category' => trim((string) $request->input('category', '')),
            'broad'    => in_array($user->role, ['admin', 'supply_officer', 'super_admin'], true),
        ];
    }

    /**
     * Apply the ribbon filters to the list query (server side, before pagination).
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['category'] !== '') {
            $query->whereHas('linkedAsset', fn (Builder $asset) => $asset->where('category', $filters['category']));
        }

        if ($filters['search'] !== '') {
            $term = '%' . $filters['search'] . '%';

            // ID-aware na paghahanap: ang ID na nakikita sa page (`display_number`,
            // hal. ICT-2026-0027) ay galing sa request_number (hal.
            // REQ-NCR-RCMB-2026-0027) kaya hindi literal na tugma. ID-like lang ang
            // hahanapin nang ganito (walang space, may dash o puro numero) para hindi
            // magdulot ng false positives sa text search.
            $idGroups = (! str_contains($filters['search'], ' ')
                    && (str_contains($filters['search'], '-') || ctype_digit($filters['search'])))
                ? preg_split('/[^0-9]+/', $filters['search'], -1, PREG_SPLIT_NO_EMPTY)
                : [];

            $query->where(function (Builder $q) use ($term, $filters, $idGroups) {
                $q->where('requests.request_number', 'like', $term)
                    ->orWhere('requests.description', 'like', $term);

                if ($idGroups !== []) {
                    $q->orWhere(function (Builder $id) use ($idGroups) {
                        foreach ($idGroups as $group) {
                            $id->where('requests.request_number', 'like', '%' . $group . '%');
                        }
                    });
                }

                // Division/System admin + supply search requestor/office too.
                if ($filters['broad']) {
                    $q->orWhere('requests.requestor_name', 'like', $term)
                        ->orWhere('requests.office', 'like', $term)
                        ->orWhereHas('user', fn (Builder $u) => $u->where('users.full_name', 'like', $term));
                }
            });
        }

        return $query;
    }
}