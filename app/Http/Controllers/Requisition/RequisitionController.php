<?php

namespace App\Http\Controllers\Requisition;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Http\Requests\StoreRequisitionRequest;
use App\Http\Requests\ReviewRequisitionRequest;
use App\Actions\Requisition\CreateRequisitionForTicketAction;
use App\Actions\Requisition\ShowRequisitionAction;
use App\Actions\Requisition\ListRequisitionsAction;
use App\Actions\Requisition\StoreRequisitionAction;
use App\Actions\Requisition\ReviewRequisitionAction;

class RequisitionController extends Controller
{
    public function createForTicket($requestId)
    {
        return (new CreateRequisitionForTicketAction)->execute($requestId);
    }

    public function show($id)
    {
        return (new ShowRequisitionAction)->execute($id);
    }

    public function index(Request $httpRequest)
    {
        return (new ListRequisitionsAction)->execute($httpRequest);
    }

    public function store(StoreRequisitionRequest $httpRequest, $requestId)
    {
        return (new StoreRequisitionAction)->execute($httpRequest, $requestId);
    }

    public function review(ReviewRequisitionRequest $httpRequest, $id)
    {
        return (new ReviewRequisitionAction)->execute($httpRequest, $id);
    }
}
