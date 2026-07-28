<?php

namespace App\Actions\Inventory;

use App\Models\AssetAttachment;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DeleteAssetAttachmentAction
{
    /**
     * Delete an attachment.
     *
     * @param  int  $attachmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute($attachmentId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $attachment = AssetAttachment::findOrFail($attachmentId);
        $attachment->load('asset');

        if ($attachment->asset && !InventoryScope::assetInActorViewScope($user, $attachment->asset)) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your scope.'], 403);
        }

        Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
    }
}
