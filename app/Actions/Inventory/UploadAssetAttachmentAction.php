<?php

namespace App\Actions\Inventory;

use App\Models\InventoryAsset;
use App\Models\AssetAttachment;
use App\Models\AuditLog;
use App\Models\Scopes\InventoryScope;
use App\Http\Requests\UploadAttachmentRequest;
use Illuminate\Support\Facades\Auth;

class UploadAssetAttachmentAction
{
    /**
     * Upload a file attachment for an asset.
     *
     * @param  \App\Http\Requests\UploadAttachmentRequest  $request
     * @param  int  $assetId
     * @return \Illuminate\Http\JsonResponse
     */
    public function execute(UploadAttachmentRequest $request, $assetId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $asset = InventoryAsset::findOrFail($assetId);

        if (!InventoryScope::assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your scope.'], 403);
        }

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $filepath = $file->store("asset-attachments/{$assetId}", 'public');

        $attachment = AssetAttachment::create([
            'asset_id'    => $assetId,
            'filename'    => $filename,
            'filepath'    => $filepath,
            'filetype'    => $file->getMimeType(),
            'label'       => $request->input('label', ''),
            'uploaded_by' => $user->id,
        ]);

        AuditLog::log('Asset Attachment Added', 'Inventory',
            "Uploaded '{$filename}' to asset #{$assetId}", $asset->office);

        return response()->json(['success' => true, 'message' => 'File uploaded.', 'attachment' => $attachment->load('uploader')]);
    }
}
