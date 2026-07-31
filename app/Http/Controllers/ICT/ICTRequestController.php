<?php

namespace App\Http\Controllers\ICT;

use App\Http\Controllers\Controller;

use App\Http\Requests\UpdateIctStatusRequest;
use App\Http\Requests\AssignItRequest;
use App\Http\Requests\ReviewIctRequest;
use App\Http\Requests\StoreLinkedAssetRequest;
use App\Http\Requests\UpdateIctRequest;
use Illuminate\Http\Request;
use App\Actions\ICT\ListIctRequestsAction;
use App\Actions\ICT\CreateIctFormAction;
use App\Actions\ICT\UpdateIctRequestAction;
use App\Actions\ICT\ShowIctRequestAction;
use App\Actions\ICT\EditIctRequestAction;
use App\Actions\ICT\ShowIctTicketAction;

class ICTRequestController extends Controller
{
    public function index(Request $request)
    {
        return (new ListIctRequestsAction)->execute($request);
    }

    public function updateStatus(UpdateIctStatusRequest $request)
    {
        return (new \App\Actions\ICT\QuickUpdateStatusAction)->execute($request);
    }

    public function create(Request $request)
    {
        return (new CreateIctFormAction)->execute($request);
    }

    public function show($id)
    {
        return (new ShowIctRequestAction)->execute($id);
    }

    public function edit($id)
    {
        return (new EditIctRequestAction)->execute($id);
    }

    public function ticket($id)
    {
        return (new ShowIctTicketAction)->execute($id);
    }

    public function assignIt(AssignItRequest $request, $id)
    {
        $trackingRequest = \App\Models\Request::with('assignedTo')->findOrFail($id);

        if (!\Illuminate\Support\Facades\Auth::user()->can('viewIct', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        return (new \App\Actions\ICT\AssignItTicketAction)->execute($request, $trackingRequest);
    }

    public function review(ReviewIctRequest $request, $id)
    {
        return (new \App\Actions\ICT\ReviewIctTicketAction)->execute($request, $id);
    }

    public function downloadPdf($id)
    {
        $trackingRequest = \App\Models\Request::findOrFail($id);

        if (!\Illuminate\Support\Facades\Auth::user()->can('viewIct', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        return (new \App\Actions\ICT\DownloadIctPdfAction)->execute($trackingRequest);
    }

    public function store(StoreLinkedAssetRequest $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        return (new \App\Actions\ICT\CreateIctTicketAction)->execute($request, $user);
    }

    public function update(UpdateIctRequest $request, $id)
    {
        return (new UpdateIctRequestAction)->execute($request, $id);
    }

    public function recommendDisposal($id)
    {
        $trackingRequest = \App\Models\Request::with('linkedAsset')->findOrFail($id);

        if (!\Illuminate\Support\Facades\Auth::user()->can('viewIct', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        $user = \Illuminate\Support\Facades\Auth::user();
        return (new \App\Actions\ICT\RecommendAssetDisposalAction)->execute($trackingRequest, $user);
    }

    public function disposalTag($id)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        if (!in_array($user->role, ['it', 'super_admin'])) {
            abort(403, 'Only IT personnel and Super Admin can access the disposal tag.');
        }

        $trackingRequest = \App\Models\Request::with(['linkedAsset', 'user'])->findOrFail($id);

        if (!$user->can('viewIct', $trackingRequest)) {
            abort(403, 'Unauthorized access to this request.');
        }

        return (new \App\Actions\ICT\PrintDisposalTagAction)->execute($trackingRequest);
    }

    public function destroy($id)
    {
        return (new \App\Actions\ICT\DeleteIctTicketAction)->execute($id);
    }
}
