<?php

namespace App\Http\Controllers\PurchaseRequest;

use App\Actions\PurchaseRequest\ApprovePurchaseRequestAction;
use App\Actions\PurchaseRequest\CancelPurchaseRequestAction;
use App\Actions\PurchaseRequest\CreatePurchaseRequestAction;
use App\Actions\PurchaseRequest\ListPurchaseRequestsAction;
use App\Actions\PurchaseRequest\ReceivePurchaseRequestAction;
use App\Actions\PurchaseRequest\ShowPurchaseRequestAction;
use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PurchaseRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            abort(403);
        }

        $data = (new ListPurchaseRequestsAction)->execute($request, $user);

        return view('purchase-requests.index', array_merge($data, [
            'canWrite' => true,
            'isSuperAdminView' => false,
        ]));
    }

    public function superAdminIndex(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            abort(403);
        }

        $data = (new ListPurchaseRequestsAction)->execute($request, $user);

        return view('purchase-requests.index', array_merge($data, [
            'canWrite' => false,
            'isSuperAdminView' => true,
        ]));
    }

    public function show($id)
    {
        return (new ShowPurchaseRequestAction)->execute($id);
    }

    public function create(Requisition $requisition)
    {
        return (new CreatePurchaseRequestAction)->execute($requisition);
    }

    public function approve(PurchaseRequest $purchaseRequest)
    {
        return (new ApprovePurchaseRequestAction)->execute($purchaseRequest);
    }

    public function receive(PurchaseRequest $purchaseRequest)
    {
        return (new ReceivePurchaseRequestAction)->execute($purchaseRequest);
    }

    public function cancel(PurchaseRequest $purchaseRequest)
    {
        return (new CancelPurchaseRequestAction)->execute($purchaseRequest);
    }
}