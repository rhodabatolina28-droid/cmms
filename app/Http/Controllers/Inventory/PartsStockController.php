<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\PartsStock\ListPartsStockAction;
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
}