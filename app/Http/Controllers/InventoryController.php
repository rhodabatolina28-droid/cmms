<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\User;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\CommitImportRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Http\Requests\ConfirmDisposalRequest;
use App\Http\Requests\UploadAttachmentRequest;
use App\Services\InventoryCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Actions\Inventory\GetInventoryAssetsAction;
use App\Actions\Inventory\GetSuperAdminInventoryAssetsAction;
use App\Actions\Inventory\GetInventoryUsersAction;
use App\Actions\Inventory\ShowInventoryDetailAction;
use App\Actions\Inventory\ShowSuperAdminInventoryDetailAction;
use App\Actions\Inventory\UploadAssetAttachmentAction;
use App\Actions\Inventory\SearchInventoryAssetsAction;
use App\Actions\Inventory\DownloadAssetAttachmentAction;
use App\Actions\Inventory\PreviewInventoryImportAction;
use App\Actions\Inventory\GetInventoryHistoryAction;
use App\Actions\Inventory\DeleteAssetAttachmentAction;

class InventoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            abort(403, 'Inventory is managed by the Administrative supply admin.');
        }

        return view('inventory.index', [
            'canWriteInventory' => true,
        ]);
    }

    public function getAssets(Request $request)
    {
        return (new GetInventoryAssetsAction)->execute($request);
    }

    public function getUsers(Request $request)
    {
        return (new GetInventoryUsersAction)->execute($request);
    }

    public function store(StoreInventoryRequest $request)
    {
        return (new \App\Actions\Inventory\CreateInventoryAssetAction)->execute($request);
    }

    public function previewImport(PreviewImportRequest $request, InventoryCsvImportService $importer)
    {
        return (new PreviewInventoryImportAction)->execute($request, $importer);
    }

    public function commitImport(CommitImportRequest $request, InventoryCsvImportService $importer)
    {
        return (new \App\Actions\Inventory\CommitInventoryImportAction)->execute($request, $importer);
    }

    public function update(UpdateInventoryRequest $request, $id)
    {
        return (new \App\Actions\Inventory\UpdateInventoryAssetAction)->execute($request, $id);
    }

    public function getHistory($assetId)
    {
        return (new GetInventoryHistoryAction)->execute($assetId);
    }

    public function edit($id)
    {
        abort(404, 'Edit form is handled via modal.');
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Assets cannot be hard deleted. Change status to Scrapped instead.',
        ], 403);
    }

    public function confirmScrapped(ConfirmDisposalRequest $request, $assetId)
    {
        return (new \App\Actions\Inventory\ConfirmAssetDisposalAction)->execute($request, $assetId);
    }

    public function detail($assetId)
    {
        return (new ShowInventoryDetailAction)->execute($assetId);
    }

    public function uploadAttachment(UploadAttachmentRequest $request, $assetId)
    {
        return (new UploadAssetAttachmentAction)->execute($request, $assetId);
    }

    public function deleteAttachment($attachmentId)
    {
        return (new DeleteAssetAttachmentAction)->execute($attachmentId);
    }

    public function downloadAttachment($attachmentId)
    {
        return (new DownloadAssetAttachmentAction)->execute($attachmentId);
    }

    private function canWriteInventory(User $user): bool
    {
        return $user->canProcessSupply();
    }

    public function superAdminIndex()
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') abort(403);

        return view('inventory.index', [
            'canWriteInventory' => false,
            'isSuperAdminView'  => true,
        ]);
    }

    public function superAdminGetAssets(Request $request)
    {
        return (new GetSuperAdminInventoryAssetsAction)->execute($request);
    }

    public function superAdminDetail($assetId)
    {
        return (new ShowSuperAdminInventoryDetailAction)->execute($assetId);
    }

    public function searchAssets(Request $request)
    {
        return (new SearchInventoryAssetsAction)->execute($request);
    }

    public function export(Request $request)
    {
        return (new \App\Actions\Inventory\ExportInventoryAction)->execute($request);
    }

    public function qrSticker($assetId, Request $request)
    {
        $asset = InventoryAsset::findOrFail($assetId);
        $asset->qr_code = \App\Services\QrCodeService::generateForAsset($asset);

        return view('inventory.qr-sticker', compact('asset'));
    }

    public function qrBatchPrint()
    {
        return view('inventory.qr-batch');
    }

    public function publicProfile($id)
    {
        $asset = InventoryAsset::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'asset_id'      => $asset->asset_id,
                'item_name'     => $asset->item_name,
                'serial_number' => $asset->serial_number,
                'category'      => $asset->category,
            ],
            'authenticated' => Auth::check(),
        ]);
    }

    public function apiProfile($id)
    {
        return (new \App\Actions\Inventory\ApiAssetProfileAction)->execute($id);
    }
}
           