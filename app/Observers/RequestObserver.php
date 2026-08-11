<?php

namespace App\Observers;

use App\Models\Request;

class RequestObserver
{
    /**
     * Auto-set assigned_at when assigned_to changes.
     * Auto-set completed_at when status changes to Completed.
     */
    public function updating(Request $request): void
    {
        // Set assigned_at when assigned_to changes (and is not null)
        if ($request->isDirty('assigned_to') && $request->assigned_to) {
            $request->assigned_at = now();
        }

        // Set completed_at when status changes to Completed
        if ($request->isDirty('status') && $request->status === Request::STATUS_COMPLETED) {
            $request->completed_at = now();
        }
    }
}