<?php

namespace App\Http\Controllers\PurchaseRequest;

use App\Actions\PurchaseRequest\CreatePurchaseRequestAction;
use App\Actions\PurchaseRequest\FinalizePurchaseRequestAction;
use App\Actions\PurchaseRequest\ShowPurchaseRequestAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Part;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PurchaseRequestController extends Controller
{
    /**
     * PR Form page (GET). Optional prefill sources:
     *  - ?requisition_id=N  → deficit lines of that requisition
     *  - ?description=X&quantity=Y → a single manually-typed item
     *    (contextual "Create Purchase Request" from My Parts Requests).
     */
    public function createForm(Request $request)
    {
        $user = Auth::user();
        if (! $this->canCreate($user)) {
            abort(403);
        }

        $requisition = null;
        $prefill = [
            'items' => [],
            'requested_by' => null,
            'purpose' => null,
            'office_unit' => $user->office,
            'fund_cluster' => old('fund_cluster'),
            'responsibility_center' => old('responsibility_center'),
        ];

        if ($request->filled('requisition_id')) {
            $requisition = Requisition::with('ticket')->findOrFail($request->integer('requisition_id'));
            $prefill = array_merge($prefill, (new CreatePurchaseRequestAction)->prefillFromRequisition($requisition));
        } elseif ($request->filled('description')) {
            $prefill['items'] = [[
                'description' => $request->string('description')->toString(),
                'quantity' => max(1, $request->integer('quantity', 1)),
                'unit_cost' => null,
                'part_id' => null,
                'unit' => null,
            ]];
        }

        // Parts catalog for the manual item picker (id + name + unit).
        $parts = Part::orderBy('item_name')->get(['id', 'item_name', 'unit']);

        return view('purchase-requests.create', [
            'requisition' => $requisition,
            'prefill' => $prefill,
            'parts' => $parts,
            'prNumberPreview' => (new CreatePurchaseRequestAction)->previewNextPrNumber(),
        ]);
    }

    /**
     * Store the submitted PR document (POST). Status becomes "submitted"
     * and lands on the Supply Officer's queue.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (! $this->canCreate($user)) {
            abort(403);
        }

        // Ignore blank padding rows the UI may submit (the client strips them,
        // but stay resilient to cached scripts / restored old input).
        $request->merge(['items' => $this->normalizeSubmittedItems($request)]);

        $validated = $request->validate([
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            'items.*.property_no' => ['nullable', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.part_id' => ['nullable', 'integer', 'exists:parts,id'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            'requisition_id' => ['nullable', 'integer', 'exists:requisitions,id'],
            'fund_cluster' => ['nullable', 'string', 'max:64'],
            'responsibility_center' => ['nullable', 'string', 'max:64'],
            'office_unit' => ['nullable', 'string', 'max:160'],
        ]);

        $purchaseRequest = (new CreatePurchaseRequestAction)->createFromForm($user, $validated);

        return redirect()
            ->route('purchase_requests.show', $purchaseRequest->id)
            ->with('success', "{$purchaseRequest->pr_number} submitted to the Supply Officer.");
    }

    /**
     * Strip blank padding rows and give quantities a sane default.
     * Shared by store() and update().
     */
    private function normalizeSubmittedItems(Request $request): array
    {
        return collect($request->input('items', []))
            ->filter(fn ($item) => trim((string) ($item['description'] ?? '')) !== '')
            ->map(function ($item) {
                $item['quantity'] = max(1, (int) ($item['quantity'] ?? 0));

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Authorization: who may edit a submitted PR document.
     * Supply Officer / Super Admin — any submitted PR.
     * IT — only their own. Finalized documents are locked for everyone.
     */
    private function canEdit(User $user, PurchaseRequest $pr): bool
    {
        if ($pr->status !== PurchaseRequest::STATUS_SUBMITTED) {
            return false; // finalized/draft documents are locked
        }
        if ($user->canProcessSupply() || $user->role === 'super_admin') {
            return true;
        }
        if ($user->role === 'it') {
            return $pr->requested_by === $user->id || $pr->created_by === $user->id;
        }

        return false;
    }

    /**
     * Edit form for a submitted PR document.
     */
    public function edit(PurchaseRequest $purchaseRequest)
    {
        if (! $this->canEdit(Auth::user(), $purchaseRequest)) {
            abort(403);
        }

        // Parts catalog for the manual item picker.
        $parts = Part::orderBy('item_name')->get(['id', 'item_name', 'unit']);

        return view('purchase-requests.edit', [
            'purchaseRequest' => $purchaseRequest,
            'parts' => $parts,
        ]);
    }

    /**
     * Save corrections to a submitted PR: header fields + items + totals.
     */
    public function update(Request $request, PurchaseRequest $purchaseRequest)
    {
        $user = Auth::user();
        if (! $this->canEdit($user, $purchaseRequest)) {
            abort(403);
        }

        $request->merge(['items' => $this->normalizeSubmittedItems($request)]);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:32'],
            'items.*.property_no' => ['nullable', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.part_id' => ['nullable', 'integer', 'exists:parts,id'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'fund_cluster' => ['nullable', 'string', 'max:64'],
            'responsibility_center' => ['nullable', 'string', 'max:64'],
            'office_unit' => ['nullable', 'string', 'max:160'],
        ]);

        DB::transaction(function () use ($purchaseRequest, $validated, $user) {
            $items = collect($validated['items'])->map(fn (array $line) => [
                'description' => trim((string) $line['description']),
                'unit' => trim((string) ($line['unit'] ?? '')) ?: null,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'unit_cost' => isset($line['unit_cost']) && $line['unit_cost'] !== null && $line['unit_cost'] !== ''
                    ? round((float) $line['unit_cost'], 2)
                    : null,
                'part_id' => ! empty($line['part_id']) ? (int) $line['part_id'] : null,
            ])->values()->all();

            $total = collect($items)->sum(fn (array $line) => ($line['unit_cost'] ?? 0) * $line['quantity']);

            $purchaseRequest->update([
                'items' => $items,
                'purpose' => $validated['purpose'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'total_amount' => $total > 0 ? round($total, 2) : null,
                'fund_cluster' => $validated['fund_cluster'] ?? null,
                'responsibility_center' => $validated['responsibility_center'] ?? null,
                'office_unit' => $validated['office_unit'] ?? null,
            ]);

            AuditLog::log(
                'Updated Purchase Request',
                'Purchase Request',
                "Edited {$purchaseRequest->pr_number} (" . count($items) . ' item(s), total ' .
                    ($total > 0 ? number_format($total, 2) : 'N/A') . ')'
            );
        });

        return redirect()
            ->route('purchase_requests.show', $purchaseRequest->id)
            ->with('success', "{$purchaseRequest->pr_number} updated.");
    }

    /**
     * PR document view (Appendix 60-adapted, printable).
     * Access rules live in ShowPurchaseRequestAction.
     */
    public function show(PurchaseRequest $purchaseRequest)
    {
        return (new ShowPurchaseRequestAction)->execute($purchaseRequest->id);
    }

    /**
     * Supply Officer finalizes a submitted PR — ready to print and physically
     * submit to Procurement (outside this system).
     */
    public function finalize(PurchaseRequest $purchaseRequest)
    {
        if (! Auth::user()->canProcessSupply()) {
            abort(403);
        }

        $result = (new FinalizePurchaseRequestAction)->execute($purchaseRequest, Auth::user());

        if (! $result['success']) {
            return back()->withErrors(['finalize' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    private function canCreate($user): bool
    {
        return $user->canProcessSupply()
            || in_array($user->role, ['super_admin', 'it'], true);
    }
}
