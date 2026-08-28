<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\PartsStock\ListPartsStockAction;
use App\Actions\Inventory\PartsStock\ExportPartsStockAction;
use App\Actions\Inventory\PartsStock\StorePartUnitAction;
use App\Actions\Inventory\PartsStock\PreviewPartsImportAction;
use App\Actions\Inventory\PartsStock\CommitPartsImportAction;
use App\Actions\Inventory\PartsStock\StockInAction;
use App\Actions\Inventory\PartsStock\StockOutAction;
use App\Actions\Inventory\PartsStock\StorePartAction;
use App\Actions\Inventory\PartsStock\UpdatePartAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockInPartRequest;
use App\Http\Requests\StockOutPartRequest;
use App\Http\Requests\StorePartRequest;
use App\Http\Requests\UpdatePartRequest;
use App\Models\Part;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartsStockController extends Controller
{
    /**
     * Writable view — Supply Officer / Admin with canProcessSupply.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            abort(403, 'Parts stock is managed by the Administrative supply admin.');
        }

        $data = (new ListPartsStockAction)->execute($request, $user);

        return view('inventory.parts', array_merge($data, [
            'canWriteInventory' => true,
            'isSuperAdminView' => false,
        ]));
    }

    /**
     * Read-only view — Super Admin oversight.
     */
    public function superAdminIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            abort(403);
        }

        $data = (new ListPartsStockAction)->execute($request, $user);

        return view('inventory.parts', array_merge($data, [
            'canWriteInventory' => false,
            'isSuperAdminView' => true,
        ]));
    }

    public function stockOutContext(Request $request)
    {
        return (new \App\Actions\Inventory\PartsStock\ListStockOutContextAction)->execute();
    }


    public function store(StorePartRequest $request)
    {
        return (new StorePartAction)->execute($request);
    }

    public function update(UpdatePartRequest $request, Part $part)
    {
        return (new UpdatePartAction)->execute($request, $part);
    }

    public function stockIn(StockInPartRequest $request, Part $part)
    {
        return (new StockInAction)->execute($request, $part);
    }

    public function stockOut(StockOutPartRequest $request, Part $part)
    {
        return (new StockOutAction)->execute($request, $part);
    }

    public function movements(Part $part)
    {
        $movements = $part->movements()
            ->with('performedBy:id,full_name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'qty_change' => $m->qty_change,
                'reason' => $m->reason,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                // Resolve a human-readable number for purchase-request-linked
                // movements so the History modal can show a clickable PR link.
                'reference_number' => $m->reference_type === 'purchase_request'
                    ? \App\Models\PurchaseRequest::whereKey($m->reference_id)->value('pr_number')
                    : null,
                'performed_by' => $m->performedBy?->full_name ?? 'System',
                'created_at' => $m->created_at?->format('M d, Y g:i A'),
            ]);

        return response()->json([
            'success' => true,
            'item_name' => $part->item_name,
            'on_hand_qty' => $part->on_hand_qty,
            'movements' => $movements,
        ]);
    }

    /**
     * List ang per-unit records (serial/property) ng isang part.
     */
    public function units(Request $request, Part $part)
    {
        $user = Auth::user();
        if ($request->routeIs('super_admin.parts.units')) {
            if ($user->role !== 'super_admin') {
                abort(403);
            }
        } elseif (! $user->canProcessSupply()) {
            abort(403, 'Parts stock is managed by the Administrative supply admin.');
        }

        $units = $part->units()
            ->with('issuedTo:id,full_name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'serial_number' => $u->serial_number,
                'property_number' => $u->property_number,
                'unit_value' => $u->unit_value,
                'status' => $u->status,
                'issued_to' => $u->issuedTo?->full_name,
                'issued_at' => $u->issued_at?->format('M d, Y'),
                'created_at' => $u->created_at?->format('M d, Y'),
                // Origin PR for units created during receiving (PartUnit.request_id
                // is the job-order ticket; the PR itself is resolved via request_id too).
                'via_pr' => $u->request_id
                    ? \App\Models\PurchaseRequest::where('request_id', $u->request_id)
                        ->latest('id')->value('pr_number')
                    : null,
                'via_pr_id' => $u->request_id
                    ? \App\Models\PurchaseRequest::where('request_id', $u->request_id)
                        ->latest('id')->value('id')
                    : null,
            ]);

        return response()->json([
            'success' => true,
            'part_id' => $part->id,
            'on_hand_qty' => $part->on_hand_qty,
            'units' => $units,
        ]);
    }

    /**
     * Magdagdag ng isang per-unit record (serial/property) + on_hand +1.
     */
    public function addUnit(Request $request, Part $part)
    {
        return (new StorePartUnitAction)->execute($request, $part);
    }

    public function previewImport(Request $request)
    {
        return (new PreviewPartsImportAction)->execute($request);
    }

    public function commitImport(Request $request)
    {
        return (new CommitPartsImportAction)->execute($request);
    }
/**
     * JSON endpoint for the live (Ajax) parts list — gaya ng inventory data endpoint.
     */
    public function data(Request $request)
    {
        $user = Auth::user();

        if ($request->routeIs('super_admin.parts.data')) {
            if ($user->role !== 'super_admin') {
                abort(403);
            }
        } elseif (! $user->canProcessSupply()) {
            abort(403, 'Parts stock is managed by the Administrative supply admin.');
        }

        $payload = (new ListPartsStockAction)->data($request, $user);

        return response()->json($payload);
    }

    /**
     * CSV export ng parts (supply/admin at super_admin read-only).
     */
    public function export(Request $request)
    {
        $user = Auth::user();

        if ($request->routeIs('super_admin.parts.export')) {
            if ($user->role !== 'super_admin') {
                abort(403);
            }
        } elseif (! $user->canProcessSupply()) {
            abort(403, 'Parts stock is managed by the Administrative supply admin.');
        }

        return (new ExportPartsStockAction)->execute($request, $user);
    }
}