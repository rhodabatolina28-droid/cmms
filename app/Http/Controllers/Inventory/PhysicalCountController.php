<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;

use App\Http\Requests\MarkAssetPhysicalCountRequest;
use Illuminate\Http\Request;
use App\Actions\PhysicalCount\ListPhysicalCountsAction;
use App\Actions\PhysicalCount\StartPhysicalCountAction;
use App\Actions\PhysicalCount\ShowPhysicalCountAction;
use App\Actions\PhysicalCount\SearchPhysicalCountAssetAction;
use App\Actions\PhysicalCount\MarkPhysicalCountAssetAction;
use App\Actions\PhysicalCount\CompletePhysicalCountAction;
use App\Actions\PhysicalCount\ExportPhysicalCountAction;
use App\Actions\PhysicalCount\PrintPhysicalCountReportAction;

class PhysicalCountController extends Controller
{
    public function index()
    {
        return (new ListPhysicalCountsAction)->execute();
    }

    public function store(Request $request)
    {
        return (new StartPhysicalCountAction)->execute($request);
    }

    public function show(Request $request, $id)
    {
        return (new ShowPhysicalCountAction)->execute($request, $id);
    }

    public function searchAsset(Request $request, $sessionId)
    {
        return (new SearchPhysicalCountAssetAction)->execute($request, $sessionId);
    }

    public function markAsset(MarkAssetPhysicalCountRequest $request, $sessionId)
    {
        return (new MarkPhysicalCountAssetAction)->execute($request, $sessionId);
    }

    public function complete($id)
    {
        return (new CompletePhysicalCountAction)->execute($id);
    }

    public function export($id)
    {
        return (new ExportPhysicalCountAction)->execute($id);
    }

    public function printReport(Request $request, $id)
    {
        return (new PrintPhysicalCountReportAction)->execute($id, $request);
    }
}
