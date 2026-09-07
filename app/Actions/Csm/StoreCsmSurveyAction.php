<?php

namespace App\Actions\Csm;

use App\Http\Requests\StoreCsmSurveyRequest;
use App\Models\CsmSurvey;
use App\Models\Request as RequestModel;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class StoreCsmSurveyAction
{
    public function execute(StoreCsmSurveyRequest $request, User $user)
    {
        $validated = $request->validated();

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
// D5a: generate the archival PDF copy AFTER the DB transaction commits.
        // Non-blocking — if generation fails the survey is still saved (pdf_path stays null).
        // Record-date rule (D5.1b): folder month comes from the survey's created_at.
        try {
            $survey = CsmSurvey::with('request.user')
                ->where('request_id', $validated['request_id'])
                ->latest('id')
                ->first();

            if ($survey) {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.csm-form', ['survey' => $survey])
                    ->setPaper('a4', 'portrait');

                $relative = 'csm-copies/'
                    . $survey->created_at->format('Y') . '/'
                    . $survey->created_at->format('F') . '/'
                    . 'CSM-' . ($survey->request?->request_number ?? 'SURVEY-' . $survey->id) . '.pdf';

                \Illuminate\Support\Facades\Storage::disk('local')->put($relative, $pdf->output());

                $survey->update(['pdf_path' => $relative]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CSM archives PDF generation failed (survey #' . ($survey->id ?? '') . '): ' . $e->getMessage());
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
