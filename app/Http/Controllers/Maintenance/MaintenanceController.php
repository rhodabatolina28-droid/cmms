<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreLinkedAssetRequest;
use App\Http\Requests\AssignItRequest;
use App\Http\Requests\UpdateMaintenanceRequest;
use Illuminate\Http\Request;
use App\Actions\Maintenance\ListMaintenanceRequestsAction;
use App\Actions\Maintenance\ListPmTasksAction;
use App\Actions\Maintenance\ListScheduledPmsAction;
use App\Actions\Maintenance\StartPmTaskAction;
use App\Actions\Maintenance\CreateMaintenanceFormAction;
use App\Actions\Maintenance\ShowMaintenanceRequestAction;
use App\Actions\Maintenance\EditMaintenanceRequestAction;
use App\Actions\Maintenance\DeleteMaintenanceRequestAction;
use App\Actions\PMGenerationSchedule\GetMaintenanceCalendarDataAction;

class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        return (new ListMaintenanceRequestsAction)->execute($request);
    }

    public function pmTasks(Request $request)
    {
        return (new ListPmTasksAction)->execute($request);
    }

    public function scheduled(Request $request)
    {
        return (new ListScheduledPmsAction)->execute($request);
    }

    public function start($id)
    {
        return (new StartPmTaskAction)->execute($id);
    }

    public function create()
    {
        return (new CreateMaintenanceFormAction)->execute();
    }

    public function store(StoreLinkedAssetRequest $request)
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        return (new \App\Actions\Maintenance\CreateMaintenanceTicketAction)->execute($request, $user);
    }

    public function show($id)
    {
        return (new ShowMaintenanceRequestAction)->execute($id);
    }

    public function edit($id)
    {
        return (new EditMaintenanceRequestAction)->execute($id);
    }

    public function assignIt(AssignItRequest $request, $id)
    {
        return (new \App\Actions\Maintenance\AssignMaintenanceTicketAction)->execute($request, $id);
    }

    public function update(UpdateMaintenanceRequest $request, $id)
    {
        return (new \App\Actions\Maintenance\UpdateMaintenanceTicketAction)->execute($request, $id);
    }

    public function downloadPdf($id)
    {
        return (new \App\Actions\Maintenance\DownloadMaintenancePdfAction)->execute($id);
    }

    public function disposalTag($id)
    {
        return (new \App\Actions\Maintenance\DownloadDisposalTagAction)->execute($id);
    }

    public function destroy($id)
    {
        return (new DeleteMaintenanceRequestAction)->execute($id);
    }

    // ── Calendar ──

    public function calendar()
    {
        return view('maintenance-calendar.index');
    }

    public function calendarEvents(Request $request)
    {
        $data = (new GetMaintenanceCalendarDataAction)->execute($request);
        return response()->json($data);
    }
}
