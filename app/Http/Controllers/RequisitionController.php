<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Requisition;
use App\Models\Request as RequestModel;
use App\Models\User;
use App\Services\RequestNotificationService;
use App\Support\RequestAuthorization;
use App\Support\RequisitionSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function createForTicket($requestId)
    {
        $user = Auth::user();

        // IT or Super Admin acting as IT (assigned to ticket) can request parts
        abort_unless(in_array($user->role, ['it', 'super_admin']), 403);

        $ticket = RequestModel::findOrFail($requestId);
        abort_unless(
            RequisitionSupport::canItSubmitForTicket($user, $ticket),
            403,
            'Parts requests are only for ICT tickets assigned to you.'
        );

        if ($ticket->status === RequestModel::STATUS_COMPLETED) {
            return redirect()
                ->route('requisitions.index')
                ->with('error', 'This ticket is already completed.');
        }

        $hasPending = Requisition::where('request_id', $ticket->id)
            ->where('requested_by', $user->id)
            ->where('status', Requisition::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return redirect()
                ->route('requisitions.index', ['request_id' => $ticket->id])
                ->with('info', 'This ticket already has a pending parts request. You may continue through your request history.');
        }

        return redirect()->route('requisitions.index', ['request_id' => $ticket->id]);
    }

    public function show($id)
    {
        $user = Auth::user();
        $requisition = Requisition::with(['ticket.user', 'ticket.assignedTo', 'ticket.linkedAsset', 'requester', 'reviewer'])
            ->findOrFail($id);

        if (!RequestAuthorization::canViewRequisition($user, $requisition)) {
            abort(403);
        }

        $canReview = $user->canProcessSupply()
            && RequestAuthorization::canSupplyManageRequisition($user, $requisition);

        // For supply officers: check inventory availability per requested line item
        $inventoryMatches = collect();
        if ($user->canProcessSupply() || $user->role === 'super_admin') {
            $items = $requisition->items ?? [];
            foreach ($items as $index => $line) {
                $description = $line['description'] ?? '';
                if (empty($description)) continue;

                // Keyword-based search: split description into words, filter short ones
                $keywords = array_filter(
                    explode(' ', preg_replace('/[^a-zA-Z0-9 ]/', ' ', $description)),
                    fn($w) => strlen($w) >= 3
                );

                if (empty($keywords)) continue;

                $query = \App\Models\InventoryAsset::with('assignedUser')
                    ->whereIn('status', ['Spare', 'Active'])
                    ->where('region', $user->region);

                if ($user->branch) {
                    $query->where('branch', $user->branch);
                }

                // Match against item_name, brand, model, or specifications
                $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $word) {
                        $q->orWhere('item_name', 'LIKE', "%{$word}%")
                          ->orWhere('brand', 'LIKE', "%{$word}%")
                          ->orWhere('model', 'LIKE', "%{$word}%")
                          ->orWhere('category', 'LIKE', "%{$word}%");
                    }
                });

                $matches = $query->orderByRaw("FIELD(status, 'Spare', 'Active')")
                    ->limit(5)
                    ->get();

                if ($matches->isNotEmpty()) {
                    $inventoryMatches->put($index, [
                        'requested' => $line,
                        'assets' => $matches,
                    ]);
                }
            }
        }

        return view('requisitions.show', compact('requisition', 'canReview', 'inventoryMatches'));
    }

    public function index(Request $httpRequest)
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

    public function store(Request $httpRequest, $requestId)
    {
        $user = Auth::user();

        // IT or Super Admin acting as IT can request parts
        if (!in_array($user->role, ['it', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Only IT personnel or Super Admin (acting as IT) can request parts.'], 403);
        }

        $ticket = RequestModel::findOrFail($requestId);

        if (!RequisitionSupport::canItSubmitForTicket($user, $ticket)) {
            return response()->json(['success' => false, 'message' => 'You can only request parts for ICT tickets assigned to you.'], 403);
        }

        $validated = $httpRequest->validate([
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:2000',
            'set_awaiting_parts' => 'nullable|boolean',
            'submission_id' => 'nullable|string|max:64',
        ]);

        return DB::transaction(function () use ($validated, $ticket, $user) {
            Requisition::where('request_id', $ticket->id)
                ->where('requested_by', $user->id)
                ->where('status', Requisition::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            $existing = RequisitionSupport::findExistingSubmission(
                $ticket,
                $user,
                $validated['submission_id'] ?? null
            );

            if ($existing) {
                return response()->json($existing);
            }

            $requisition = Requisition::create(
                RequisitionSupport::buildCreatePayload($ticket, $user, $validated)
            );

            if (!empty($validated['set_awaiting_parts'])) {
                $ticket->update(['status' => RequestModel::STATUS_AWAITING_PARTS]);
                $ticket->refresh();
                RequestNotificationService::notifyRequestorTicketStatus(
                    $ticket,
                    RequestModel::STATUS_AWAITING_PARTS,
                    'IT requested parts from Supply Office. Your ticket is awaiting parts.'
                );
            }

            RequestNotificationService::notifySupplyOfficersOfRequisition($requisition);

            AuditLog::log(
                'Created Requisition',
                'Requisitions',
                "Parts request for {$ticket->request_number}",
                $ticket->region
            );

            return response()->json([
                'success' => true,
                'message' => 'Sent to Supply Office. You will be notified when they approve, issue, or reject.',
                'requisition_id' => $requisition->id,
            ]);
        });
    }

    public function review(Request $httpRequest, $id)
    {
        $supply = Auth::user();
        if (! $supply->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $validated = $httpRequest->validate([
            'action' => 'required|in:approve,reject,issue',
            'remarks' => 'nullable|string|max:2000',
        ]);

        return DB::transaction(function () use ($validated, $supply, $id) {
            $requisition = Requisition::with('ticket', 'requester')->lockForUpdate()->findOrFail($id);

            if (!RequestAuthorization::canSupplyManageRequisition($supply, $requisition)) {
                return response()->json(['success' => false, 'message' => 'This requisition is outside your scope.'], 403);
            }

            $action = $validated['action'];
            $error = RequisitionSupport::validateSupplyAction($action, $requisition->status);
            if ($error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }

            $newStatus = match ($action) {
                'approve' => Requisition::STATUS_APPROVED,
                'reject' => Requisition::STATUS_REJECTED,
                'issue' => Requisition::STATUS_ISSUED,
            };

            $requisition->update([
                'status' => $newStatus,
                'reviewed_by' => $supply->id,
                'reviewed_at' => now(),
                'remarks' => $validated['remarks'] ?? $requisition->remarks,
            ]);

            $ticket = $requisition->ticket;
            
            // Auto-revert ticket status when requisition is rejected
            if ($ticket && $action === 'reject' && $ticket->status === RequestModel::STATUS_AWAITING_PARTS) {
                $ticket->update(['status' => RequestModel::STATUS_ONGOING]);
                $ticket->refresh();
                RequestNotificationService::notifyRequestorTicketStatus(
                    $ticket,
                    RequestModel::STATUS_ONGOING,
                    'Parts request was rejected by Supply. Repair is now ongoing without parts.'
                );
            }
            
            if ($ticket && $action === 'issue' && $ticket->status === RequestModel::STATUS_AWAITING_PARTS) {
                $ticket->update(['status' => RequestModel::STATUS_ONGOING]);
                $ticket->refresh();
                RequestNotificationService::notifyRequestorTicketStatus(
                    $ticket,
                    RequestModel::STATUS_ONGOING,
                    'Parts were issued. Your repair request is ongoing again.'
                );
            }

            if ($requisition->status === Requisition::STATUS_ISSUED && $ticket && $ticket->linked_asset_id) {
                $asset = \App\Models\InventoryAsset::find($ticket->linked_asset_id);
                if ($asset) {
                    \App\Models\InventoryHistory::create([
                        'asset_id' => $asset->asset_id,
                        'action' => 'Parts Issued',
                        'performed_by' => $supply->id,
                        'previous_user_id' => $asset->assigned_to_user,
                        'new_user_id' => $asset->assigned_to_user,
                        'previous_status' => $asset->status,
                        'new_status' => $asset->status,
                        'remarks' => "Administrative supply admin issued requisition #{$requisition->id} for ICT request {$ticket->request_number}.",
                    ]);
                }
            }

            if ($requisition->requested_by && $ticket) {
                $msg = match ($action) {
                    'approve' => "Supply approved your parts request for {$ticket->request_number}. They will issue parts when ready.",
                    'reject' => "Supply rejected your parts request for {$ticket->request_number}.",
                    'issue' => "Parts were issued for {$ticket->request_number}. You may continue repair work.",
                };
                \App\Models\Notification::send(
                    $requisition->requested_by,
                    $ticket->id,
                    'Parts request — ' . ucfirst($newStatus),
                    $msg
                );
            }

            AuditLog::log(
                'Reviewed Requisition',
                'Requisitions',
                ucfirst($action) . " requisition #{$requisition->id} for {$ticket->request_number}",
                $ticket->region ?? $supply->region
            );

            $labels = [
                'approve' => 'Approved',
                'reject' => 'Rejected',
                'issue' => 'Issued',
            ];

            return response()->json([
                'success' => true,
                'message' => 'Requisition marked as ' . $labels[$action] . '.',
                'redirect' => route('requisitions.show', $requisition->id),
            ]);
        });
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

        $counts = [
            'pending' => $this->supplyRequisitionCount($supply, Requisition::STATUS_PENDING),
            'approved' => $this->supplyRequisitionCount($supply, Requisition::STATUS_APPROVED),
            'issued' => $this->supplyRequisitionCount($supply, Requisition::STATUS_ISSUED),
            'rejected' => $this->supplyRequisitionCount($supply, Requisition::STATUS_REJECTED),
        ];

        if ($supplyView === 'tickets') {
            $ticketQuery = RequestModel::with(['user', 'assignedTo', 'requisitions'])
                ->withCount('requisitions');
            RequestAuthorization::scopeIctTicketsForSupplyAdmin($supply, $ticketQuery);

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
                'ictTickets'
            ));
        }

        $query = Requisition::with(['ticket', 'requester', 'reviewer']);
        RequestAuthorization::scopeRequisitionsForSupplyOfficer($supply, $query);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        $requisitions = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $ictTickets = RequestModel::whereRaw('0=1')->paginate(1);

        return view('requisitions.supply-index', compact(
            'requisitions',
            'filter',
            'counts',
            'supplyView',
            'ictTickets'
        ));
    }

    private function itIndex(User $itUser, Request $httpRequest)
    {
        $activeTickets = \App\Models\Request::with('user')
            ->where('assigned_to', $itUser->id)
            ->where('type', 'ICT')
            ->whereNotIn('status', [\App\Models\Request::STATUS_COMPLETED, \App\Models\Request::STATUS_CANCELLED])
            ->orderByDesc('created_at')
            ->get();

        $requisitions = Requisition::with(['ticket', 'requester', 'reviewer'])
            ->where('requested_by', $itUser->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $selectedTicketId = $httpRequest->query('request_id');
        
        return view('requisitions.it-index', compact('activeTickets', 'requisitions', 'selectedTicketId'));
    }

    /**
     * Super Admin requisition view — shows tickets they are assigned to (acting as IT)
     * and their submitted parts requests.
     */
    private function superAdminRequisitionIndex(User $superAdmin, Request $httpRequest)
    {
        // Active ICT tickets where Super Admin is the assigned personnel
        $activeTickets = \App\Models\Request::with('user')
            ->where('assigned_to', $superAdmin->id)
            ->where('type', 'ICT')
            ->whereNotIn('status', [\App\Models\Request::STATUS_COMPLETED, \App\Models\Request::STATUS_CANCELLED])
            ->orderByDesc('created_at')
            ->get();

        // Their submitted requisitions
        $requisitions = Requisition::with(['ticket', 'requester', 'reviewer'])
            ->where('requested_by', $superAdmin->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        $selectedTicketId = $httpRequest->query('request_id');
        
        return view('requisitions.it-index', compact('activeTickets', 'requisitions', 'selectedTicketId'));
    }

    private function supplyRequisitionCount(User $supply, string $status): int
    {
        $query = Requisition::query()->where('status', $status);
        RequestAuthorization::scopeRequisitionsForSupplyOfficer($supply, $query);

        return $query->count();
    }
}
