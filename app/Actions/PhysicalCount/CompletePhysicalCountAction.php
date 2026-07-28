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

        return redirect()->route('physical-count.show', $session->id)
            ->with('success', 'Physical count session completed.');
    }
}
