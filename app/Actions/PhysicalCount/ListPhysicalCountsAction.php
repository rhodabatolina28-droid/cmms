<?php

namespace App\Actions\PhysicalCount;

use App\Models\PhysicalCountSession;
use Illuminate\Support\Facades\Auth;

class ListPhysicalCountsAction
{
    /**
     * List physical count sessions for the current user's scope.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function execute()
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $ongoing = PhysicalCountSession::where('scope_region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('scope_branch', $user->branch))
            ->where('status', 'Ongoing')
            ->first();

        $sessions = PhysicalCountSession::with('startedBy')
            ->where('scope_region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('scope_branch', $user->branch))
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('inventory.physical-count', compact('sessions', 'ongoing'));
    }
}
