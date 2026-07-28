<?php

namespace App\Actions\ICT;

use App\Models\RepairRequest;
use App\Models\Request as RequestModel;
use App\Support\RequestAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateIctRequestAction
{
    /**
     * Update an existing ICT request (role-based sections).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $trackingRequest = RequestModel::with('repairRequest')->findOrFail($id);

            $savedSigFiles = [];
            $trackingRequest = RequestModel::findOrFail($id);

            if ($trackingRequest->status === 'Completed') {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'This request is already completed and cannot be modified.'], 403);
            }

            if ($request->has('last_updated_at') && (string)$trackingRequest->updated_at !== $request->last_updated_at) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Conflict Error: Another user has updated this request while you were viewing it. Please refresh the page.'
                ], 409);
            }

            $repairRequest = RepairRequest::findOrFail($trackingRequest->detail_id);
            $user = Auth::user();

            if (!RequestAuthorization::canUpdateIctTicket($user, $trackingRequest)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'You are not allowed to update this request.'], 403);
            }

            if ($user->role === 'user') {
                if ($trackingRequest->status === RequestModel::STATUS_REJECTED
                    && (int) $trackingRequest->user_id === (int) $user->id) {
                    $response = (new ResubmitIctTicketAction)->execute(
                        $request, $trackingRequest, $repairRequest, $user
                    );
                    DB::commit();
                    return $response;
                }

                $response = (new SignIctAcceptanceAction)->execute(
                    $request, $trackingRequest, $repairRequest, $user
                );
                DB::commit();
                return $response;
            } elseif ($user->role === 'it' || $user->role === 'super_admin') {
                $response = (new TechnicianUpdateIctTicketAction)->execute(
                    $request, $trackingRequest, $repairRequest, $user
                );
                DB::commit();
                return $response;
            } else {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Unauthorized Action'], 403);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            foreach ($savedSigFiles ?? [] as $sigPath) {
                if ($sigPath && Storage::disk('public')->exists($sigPath)) {
                    Storage::disk('public')->delete($sigPath);
                }
            }
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
