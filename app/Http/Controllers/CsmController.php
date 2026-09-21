<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Models\CsmSurvey;
use App\Services\CsmMonthlyReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Http\Requests\StoreCsmSurveyRequest;
use App\Actions\Csm\StoreCsmSurveyAction;
use App\Actions\Csm\ShowCsmSurveyFormAction;

class CsmController extends Controller
{
    public function create($requestId)
    {
        return (new ShowCsmSurveyFormAction)->execute($requestId, Auth::user());
    }

    public function store(StoreCsmSurveyRequest $request)
    {
        $user = Auth::user();

        return (new StoreCsmSurveyAction)->execute($request, $user);
    }

    /**
     * D9.32 — streams the CSM Monthly Summary PDF for a month (SA-only).
     * Generates on demand if the file has not been produced yet, so the
     * notification link works even for months that were never scheduled.
     */
    public function downloadReport($year, $month)
    {
        $date = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfMonth();
        $relative = CsmMonthlyReportService::storagePath($date);

        if (! Storage::disk('local')->exists($relative)) {
            $data = (new CsmMonthlyReportService)->build($date);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.csm-monthly-report', $data)
                ->setPaper('a4', 'portrait');
            Storage::disk('local')->put($relative, $pdf->output());
        }

        return Storage::disk('local')->download($relative);
    }
}
