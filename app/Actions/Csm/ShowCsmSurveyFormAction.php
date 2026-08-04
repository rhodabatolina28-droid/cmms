<?php

namespace App\Actions\Csm;

use App\Models\Request as RequestModel;
use App\Models\User;

class ShowCsmSurveyFormAction
{
    public function execute($requestId, User $user)
    {
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
}
