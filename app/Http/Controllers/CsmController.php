<?php

namespace App\Http\Controllers;

use App\Models\Request as RequestModel;
use App\Models\CsmSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use App\Http\Requests\StoreCsmSurveyRequest;

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
        $validated = $request->validated();

        $user = Auth::user();

        // Only regular users (role = 'user') can submit surveys
        if ($user->role !== 'user') {
            return redirect()->route('dashboard.user')->with('error', 'Only end-users can submit CSM surveys.');
        }

        $validated['cc1'] = $validated['cc1'][0];
        $validated['cc2'] = $validated['cc2'][0];
        $validated['cc3'] = $validated['cc3'][0];
        unset($validated['consent']);

        try {
            DB::transaction(function () use ($validated, $user) {
                $ticket = RequestModel::where('id', $validated['request_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($ticket->user_id !== $user->id || $ticket->status !== RequestModel::STATUS_COMPLETED) {
                    throw new \RuntimeException('Invalid survey submission.');
                }

                if ($ticket->csmSurvey()->exists()) {
                    throw new \RuntimeException('Survey already submitted.');
                }

                CsmSurvey::create($validated);
            });
        } catch (UniqueConstraintViolationException) {
            return redirect()->route('dashboard.user')->with('success', 'You have already submitted a survey for this request.');
        } catch (\RuntimeException $e) {
            return redirect()->route('dashboard.user')->with('error', $e->getMessage());
        }

        $nextPending = $user->pendingSurveyRequest();

        if ($nextPending) {
            return redirect()
                ->route('csm.create', $nextPending->id)
                ->with('info', 'Thank you! Please complete the survey for request ' . $nextPending->request_number . '.');
        }

        return redirect()
            ->route('dashboard.user')
            ->with('success', 'Thank you for completing the survey! You are all done.');
    }
}
