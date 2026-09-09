<?php

namespace App\Actions\PMSchedule;

use App\Models\Request as RequestModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GetOrdersDataAction
{
    /**
     * AJAX endpoint — returns paginated work orders with status filter.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $status = $request->input('status', 'all');

        $query = RequestModel::with(['user', 'assignedTo', 'maintenanceRequest'])
            ->where('type', 'Preventive Maintenance')
            ->where('is_auto_generated', true);

        if ($user->branch) {
            $query->where('branch', $user->branch);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $page    = max((int) $request->input('page', 1), 1);

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $sortedItems = collect($orders->items())->sortBy(function($order) {
            $orderMap = ['Scheduled' => 0, 'Ongoing' => 1, 'Awaiting Signature' => 2, 'Completed' => 3];
            return $orderMap[$order->status] ?? 99;
        })->values()->map(function ($order) {
            // D2: aging fields — null for terminal statuses (history, not alarm).
            // array_merge OVERRIDES the serialized accessor values (the age
            // accessors recompute from created_at and ignore stored attributes).
            return array_merge($order->toArray(), [
                'age_bucket'  => $order->should_show_age ? $order->aging_bucket : null,
                'age_display' => $order->should_show_age ? $order->age_display : null,
            ]);
        });

        return response()->json([
            'success'      => true,
            'orders'       => $sortedItems,
            'total'        => $orders->total(),
            'per_page'     => $orders->perPage(),
            'current_page' => $orders->currentPage(),
            'last_page'    => $orders->lastPage(),
        ]);
    }
}
