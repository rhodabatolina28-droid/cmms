<?php

namespace App\Actions\PhysicalCount;

use App\Models\PhysicalCountSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StartPhysicalCountAction
{
    /**
     * Start a new physical count session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $ongoing = PhysicalCountSession::where('scope_region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('scope_branch', $user->branch))
            ->where('status', 'Ongoing')
            ->first();

        if ($ongoing) {
            return redirect()->route('physical-count.show', $ongoing->id)
                ->with('info', 'You already have an ongoing physical count session.');
        }

        $session = PhysicalCountSession::create([
            'started_by'   => $user->id,
            'started_at'   => now(),
            'status'       => 'Ongoing',
            'scope_region' => $user->region,
            'scope_branch' => $user->branch,
        ]);

        return redirect()->route('physical-count.show', $session->id)
            ->with('success', 'Physical count session started.');
    }
}
