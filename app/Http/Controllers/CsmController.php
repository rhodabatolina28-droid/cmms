<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Models\CsmSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Http\Requests\StoreCsmSurveyRequest;
use App\Actions\Csm\StoreCsmSurveyAction;

class CsmController extends Controller
{
    public function create($requestId)
    {
        $user = Auth::user();

        // Only regular users (role = 'user') can fill surveys
        if ($user->role !== 'user') {
            return redirect()->route($user->dashboardRouteName())
                ->with('error', 'Only end-users can submit CSM surveys.');
        }

        $ticket = RequestModel::findOrFail($requestId);

        // Ensure only the request owner can fill the survey
        if ($ticket->user_id !== $user->id) {
            return redirect()->route('dashboard.user')->with('error', 'Unauthorized access to survey.');
        }

        // Ensure the ticket is Completed
        if ($ticket->status !== 'Completed') {
            return redirect()->route('dashboard.user')->with('error', 'You can only rate completed requests.');
        }

        // Ensure they haven't submitted a survey already
        if ($ticket->csmSurvey()->exists()) {
            return redirect()->route('dashboard.user')->with('success', 'You have already submitted a survey for this request.');
        }

        return view('csm.form', compact('ticket'));
    }

    public function store(StoreCsmSurveyRequest $request)
    {
        $user = Auth::user();

        return (new StoreCsmSurveyAction)->execute($request, $user);
    }
}
