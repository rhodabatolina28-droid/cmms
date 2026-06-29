<?php

namespace App\Http\Middleware;

use App\Models\Request as RequestModel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePendingSurvey
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'user') {
            return $next($request);
        }

        if ($request->routeIs('csm.create', 'csm.store', 'logout')) {
            return $next($request);
        }

        // Allow ongoing ICT access so the user can sign acceptance before that request becomes Completed
        if ($request->routeIs('ict.edit', 'ict.update', 'ict.show')) {
            $requestId = (int) $request->route('id');
            if ($requestId && RequestModel::where('id', $requestId)
                ->where('user_id', $user->id)
                ->where('type', 'ICT')
                ->where('status', RequestModel::STATUS_ONGOING)
                ->exists()) {
                return $next($request);
            }
        }

        $pending = $user->pendingSurveyRequest();

        if (!$pending) {
            return $next($request);
        }

        if ($request->routeIs('csm.create') && (int) $request->route('id') === $pending->id) {
            return $next($request);
        }

        return redirect()
            ->route('csm.create', $pending->id)
            ->with('info', 'Please complete the required satisfaction survey for ' . $pending->request_number . ' before continuing.');
    }
}
