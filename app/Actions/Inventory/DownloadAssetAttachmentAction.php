<?php

namespace App\Actions\Inventory;

use App\Models\AssetAttachment;
use App\Models\Scopes\InventoryScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class DownloadAssetAttachmentAction
{
    /**
     * Download/view an attachment.
     *
     * @param  int  $attachmentId
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function execute($attachmentId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $attachment = AssetAttachment::findOrFail($attachmentId);
        $attachment->load('asset');

        if ($user->canProcessSupply() && $attachment->asset && !InventoryScope::assetInActorViewScope($user, $attachment->asset)) {
            abort(403, 'Asset is outside your scope.');
        }
        if ($user->role === 'super_admin' && $attachment->asset) {
            if ($user->branch && $attachment->asset->branch !== $user->branch) {
                abort(403, 'Asset is outside your branch scope.');
            }
        }

        $path = storage_path('app/public/' . $attachment->filepath);

        if (!file_exists($path)) {
            abort(404, 'File not found.');
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . $attachment->filename . '"',
        ]);
    }
}
