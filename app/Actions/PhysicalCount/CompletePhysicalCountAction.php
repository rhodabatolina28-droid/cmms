<?php

namespace App\Actions\PhysicalCount;

use App\Models\PhysicalCountSession;
use Illuminate\Support\Facades\Auth;

class CompletePhysicalCountAction
{
    /**
     * Complete a physical count session.
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute($id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = PhysicalCountSession::findOrFail($id);
        $session->update([
            'status'       => 'Completed',
            'completed_at' => now(),
        ]);

        // D7b: auto-archive the final Physical Count Report PDF (afterCommit —
        // DomPDF never runs inside the transaction). One archive per session;
        // failure is non-blocking (retry via counts:generate-archive-pdfs).
        \Illuminate\Support\Facades\DB::afterCommit(function () use ($session) {
            try {
                (new \App\Actions\PhysicalCount\ArchiveCountReportAction)->generate($session->fresh());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Count report archive failed for session ' . $session->id . ': ' . $e->getMessage());
            }
        });

        return redirect()->route('physical-count.show', $session->id)
            ->with('success', 'Physical count session completed.');
    }
}
