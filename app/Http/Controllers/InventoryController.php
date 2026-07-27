<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Http\Requests\StoreInventoryRequest;
use App\Http\Requests\PreviewImportRequest;
use App\Http\Requests\CommitImportRequest;
use App\Http\Requests\UpdateInventoryRequest;
use App\Http\Requests\ConfirmDisposalRequest;
use App\Http\Requests\UploadAttachmentRequest;
use App\Services\InventoryCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\User;

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
        $user = Auth::user();
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Inventory is managed by the Administrative supply admin.'], 403);
        }

        $query = InventoryAsset::with('assignedUser');

        \App\Models\Scopes\InventoryScope::scopeAssetsToActor($query, $user);

        $query->withCount('components');

        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(item_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

		$cats = ['Desktop','Laptop','Monitor','Printer/Scanner','Peripherals','Network/Server','Others'];
		$fieldList = implode(',', array_map(fn($c) => "'$c'", $cats));

		// Status priority: Active first, then Spare, then For Repair, then everything else
		$statusOrder = "'Active','Spare','For Repair','For Disposal','Scrapped','Disposed','Pending'";

		$perPage = min((int) $request->input('per_page', 50), 100);
		$page = max((int) $request->input('page', 1), 1);

		$assets = $query->orderByRaw("FIELD(status, $statusOrder)")
			->orderByRaw("FIELD(category, $fieldList)")
			->orderBy('created_at', 'desc')
			->paginate($perPage, ['*'], 'page', $page)
			->through(function ($asset) {
                if ($asset->assignedUser) {
                    $asset->assigned_to_name = $asset->assignedUser->full_name;
                    $asset->assigned_to_department = $asset->assignedUser->department ?? '';
                } else {
                    $asset->assigned_to_name = '';
                    $asset->assigned_to_department = '';
                }
                return $asset;
            });

        // Get total counts across all statuses (unfiltered by search/category/status)
        $baseQuery = InventoryAsset::query();
        \App\Models\Scopes\InventoryScope::scopeAssetsToActor($baseQuery, $user);
        $totalActive = (clone $baseQuery)->where('status', 'Active')->count();
        $totalSpare = (clone $baseQuery)->where('status', 'Spare')->count();
        $totalRepair = (clone $baseQuery)->where('status', 'For Repair')->count();
        $totalDisposal = (clone $baseQuery)->whereIn('status', ['For Disposal', 'Scrapped', 'Disposed'])->count();
        $totalAll = (clone $baseQuery)->count();

        return response()->json([
            'success' => true,
            'assets' => $assets->items(),
            'total' => $assets->total(),
            'per_page' => $assets->perPage(),
            'current_page' => $assets->currentPage(),
            'last_page' => $assets->lastPage(),
            'stats' => [
                'total' => $totalAll,
                'active' => $totalActive,
                'spare' => $totalSpare,
                'repair' => $totalRepair,
                'disposal' => $totalDisposal,
            ],
        ]);
    }

    public function getUsers(Request $request)
    {
        $user = Auth::user();
        
        if (! $user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only the Administrative supply admin can list custodians for inventory assignment.'], 403);
        }

        $query = User::query();

        // Strict region match — users without region are excluded to prevent data leaks
        // across supply officers from different regions.
        $query->where('region', $user->region);

        // Optional cascading filters (from modal dropdowns)
        if ($request->filled('branch')) {
            $query->where('branch', $request->branch);
        } elseif ($user->branch) {
            // Supply and admin users both default to branch scope.
            $query->where('branch', $user->branch);
        }

        if ($request->filled('office')) {
            $query->where('office', $request->office);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $users = $query->orderBy('full_name', 'asc')->limit(200)->get(['id', 'full_name as name', 'office', 'department']);

        return response()->json([
            'success' => true,
            'users' => $users
        ]);
    }

    public function store(StoreInventoryRequest $request)
    {
        return (new \App\Actions\Inventory\CreateInventoryAssetAction)->execute($request);
    }

    public function previewImport(PreviewImportRequest $request, InventoryCsvImportService $importer)
    {
        $user = Auth::user();
        if (! $this->canWriteInventory($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory import is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validated();

        $token = (string) Str::uuid();
        $relativePath = $validated['file']->storeAs('inventory-imports', "{$token}.csv", 'local');
        $absolutePath = Storage::disk('local')->path($relativePath);

        $preview = $importer->preview($absolutePath, $user);
        session(["inventory_import_{$token}" => $relativePath]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'summary' => $preview['summary'],
            'items' => array_slice($preview['items'], 0, 25),
            'preview_limit' => 25,
        ]);
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
        $asset = InventoryAsset::findOrFail($assetId);
        $user = Auth::user();

        if (! $user->canProcessSupply() && $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Inventory history is managed by the Administrative supply admin.'], 403);
        }

        if ($user->canProcessSupply() && ! \App\Models\Scopes\InventoryScope::assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to view this history'], 403);
        }

        $history = InventoryHistory::where('asset_id', $assetId)
            ->with(['performedByUser', 'previousUser', 'newUser'])
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'history' => $history]);
    }

    public function edit($id)
    {
        // The inventory edit form is handled via a frontend modal.
        // Direct access to the edit route is disabled.
        abort(404, 'Edit form is handled via modal.');
    }

    public function destroy($id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Assets cannot be hard deleted. Change status to Scrapped instead.',
        ], 403);
    }

    /**
     * Supply Officer confirms physical disposal — sets asset to Scrapped (permanent lock).
     */
    public function confirmScrapped(ConfirmDisposalRequest $request, $assetId)
    {
        return (new \App\Actions\Inventory\ConfirmAssetDisposalAction)->execute($request, $assetId);
    }

    /**
     * Asset detail page — full profile with repair history and attachments.
     */
    public function detail($assetId)
    {
        $user = Auth::user();
        // Only supply admin uses this route — super admin has their own /super-admin/inventory route
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $asset = InventoryAsset::with(['assignedUser', 'attachments.uploader', 'components', 'parentAsset.components'])
            ->findOrFail($assetId);

        if (!\App\Models\Scopes\InventoryScope::assetInActorViewScope($user, $asset)) {
            abort(403, 'Asset is outside your scope.');
        }

        $assetUserId = $asset->assigned_to_user;
        $repairHistory = \App\Models\Request::with(['user', 'repairRequest', 'maintenanceRequest', 'assignedTo'])
            ->where(function ($q) use ($assetId, $assetUserId) {
                $q->where('linked_asset_id', $assetId)
                  ->orWhere(function ($sub) use ($assetUserId) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('is_auto_generated', true)
                          ->where('user_id', $assetUserId);
                  })
                  ->orWhere(function ($sub) use ($assetId) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->whereHas('maintenanceRequest', function ($pm) use ($assetId) {
                              $pm->where('disposal_asset_id', $assetId);
                          });
                  });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $transferHistory = \App\Models\InventoryHistory::with(['performedByUser', 'previousUser', 'newUser'])
            ->where('asset_id', $assetId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('inventory.detail', compact('asset', 'repairHistory', 'transferHistory'));
    }

    /**
     * Upload a file attachment for an asset.
     */
    public function uploadAttachment(UploadAttachmentRequest $request, $assetId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $asset = InventoryAsset::findOrFail($assetId);

        if (!\App\Models\Scopes\InventoryScope::assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your scope.'], 403);
        }

        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $filepath = $file->store("asset-attachments/{$assetId}", 'public');

        $attachment = \App\Models\AssetAttachment::create([
            'asset_id'    => $assetId,
            'filename'    => $filename,
            'filepath'    => $filepath,
            'filetype'    => $file->getMimeType(),
            'label'       => $request->input('label', ''),
            'uploaded_by' => $user->id,
        ]);

        \App\Models\AuditLog::log('Asset Attachment Added', 'Inventory',
            "Uploaded '{$filename}' to asset #{$assetId}", $asset->office);

        return response()->json(['success' => true, 'message' => 'File uploaded.', 'attachment' => $attachment->load('uploader')]);
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment($attachmentId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $attachment = \App\Models\AssetAttachment::findOrFail($attachmentId);
        $attachment->load('asset');

        if ($attachment->asset && !\App\Models\Scopes\InventoryScope::assetInActorViewScope($user, $attachment->asset)) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your scope.'], 403);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->filepath);
        $attachment->delete();

        return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
    }

    /**
     * Download/view an attachment.
     */
    public function downloadAttachment($attachmentId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $attachment = \App\Models\AssetAttachment::findOrFail($attachmentId);
        $attachment->load('asset');

        // For supply admin: check asset scope; for super_admin: check branch via asset
        if ($user->canProcessSupply() && $attachment->asset && !\App\Models\Scopes\InventoryScope::assetInActorViewScope($user, $attachment->asset)) {
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

    private function canWriteInventory(User $user): bool
    {
        return $user->canProcessSupply();
    }

    /**
     * Super Admin — read-only inventory list.
     */
    public function superAdminIndex()
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') abort(403);

        return view('inventory.index', [
            'canWriteInventory' => false,
            'isSuperAdminView'  => true,
        ]);
    }

    /**
     * Super Admin — read-only asset data API.
     */
    public function superAdminGetAssets(Request $request)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Super Admin sees all NCR assets for oversight
        // If branch is set, scope to their branch; if null, see all NCR assets
        $query = InventoryAsset::with('assignedUser')
            ->where('region', $user->region);

        if ($user->branch) {
            $query->where('branch', $user->branch);
        }
        // If branch is null → sees ALL NCR assets (system-level oversight)

        // Set badge support: parent assets show their component count.
        $query->withCount('components');

        // Server-side search/filter
        if ($search = $request->input('search')) {
            $search = strtolower($search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(item_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

		$cats = ['Desktop','Laptop','Monitor','Printer/Scanner','Peripherals','Network/Server','Others'];
		$fieldList = implode(',', array_map(fn($c) => "'$c'", $cats));

		// Status priority: Active first, then Spare, then For Repair, then everything else
		$statusOrder = "'Active','Spare','For Repair','For Disposal','Scrapped','Disposed','Pending'";

		$perPage = min((int) $request->input('per_page', 50), 100);
		$page = max((int) $request->input('page', 1), 1);

		$assets = $query->orderByRaw("FIELD(status, $statusOrder)")
			->orderByRaw("FIELD(category, $fieldList)")
			->orderBy('created_at', 'desc')
			->paginate($perPage, ['*'], 'page', $page);

		// Get total counts across all statuses (unfiltered by search/category/status)
		$baseQuery = InventoryAsset::query();
		$baseQuery->where('region', $user->region);
		if ($user->branch) {
			$baseQuery->where('branch', $user->branch);
		}
		$totalActive = (clone $baseQuery)->where('status', 'Active')->count();
		$totalSpare = (clone $baseQuery)->where('status', 'Spare')->count();
		$totalRepair = (clone $baseQuery)->where('status', 'For Repair')->count();
		$totalDisposal = (clone $baseQuery)->whereIn('status', ['For Disposal', 'Scrapped', 'Disposed'])->count();
		$totalAll = (clone $baseQuery)->count();

		return response()->json([
			'success' => true,
			'assets' => $assets->items(),
			'total' => $assets->total(),
			'per_page' => $assets->perPage(),
			'current_page' => $assets->currentPage(),
			'last_page' => $assets->lastPage(),
			'stats' => [
				'total' => $totalAll,
				'active' => $totalActive,
				'spare' => $totalSpare,
				'repair' => $totalRepair,
				'disposal' => $totalDisposal,
			],
        ]);
    }

    /**
     * Super Admin — read-only asset detail page.
     */
    public function superAdminDetail($assetId)
    {
        $user = Auth::user();
        if ($user->role !== 'super_admin') abort(403);

        $asset = InventoryAsset::with(['assignedUser', 'attachments.uploader', 'components', 'parentAsset.components'])
            ->findOrFail($assetId);

        $assetUserId = $asset->assigned_to_user;
        $repairHistory = \App\Models\Request::with(['user', 'repairRequest', 'maintenanceRequest', 'assignedTo'])
            ->where(function ($q) use ($assetId, $assetUserId) {
                $q->where('linked_asset_id', $assetId)
                  ->orWhere(function ($sub) use ($assetUserId) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('is_auto_generated', true)
                          ->where('user_id', $assetUserId);
                  });
            })
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $transferHistory = \App\Models\InventoryHistory::with(['performedByUser', 'previousUser', 'newUser'])
            ->where('asset_id', $assetId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('inventory.detail', compact('asset', 'repairHistory', 'transferHistory') + [
            'isSuperAdminView' => true,
        ]);
    }

    public function searchAssets(Request $request)
    {
        $user = Auth::user();
        $q = $request->input('q', '');

        $query = InventoryAsset::with('assignedUser');

        if ($user->role === 'user') {
            $query->where('assigned_to_user', $user->id)
                  ->whereNotIn('status', ['For Repair', 'For Disposal', 'Scrapped']);
        } elseif ($user->role === 'it') {
            $query->where('region', $user->region);
            if ($user->branch) $query->where('branch', $user->branch);
        } else {
            \App\Models\Scopes\InventoryScope::scopeAssetsToActor($query, $user);
        }

        if (strlen($q) >= 2) {
            $q = strtolower($q);
            $query->where(function ($qry) use ($q) {
                $qry->whereRaw('LOWER(item_name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$q}%"]);
            });
        }

        $assets = $query->orderBy('item_name')->limit(50)->get(['asset_id', 'item_name', 'serial_number', 'property_number', 'category', 'status']);

        return response()->json(['success' => true, 'assets' => $assets]);
    }

    public function export(Request $request)
    {
        return (new \App\Actions\Inventory\ExportInventoryAction)->execute($request);
    }

    public function qrSticker($assetId, \Illuminate\Http\Request $request)
    {
        $asset = InventoryAsset::findOrFail($assetId);

        // Always generate fresh QR using current APP_URL — never rely on DB cache
        $asset->qr_code = \App\Services\QrCodeService::generateForAsset($asset);
        // Do NOT save to DB — generated on-the-fly so URL always reflects current config

        // ?raw=1 is used by batch print to extract just the SVG via fetch()
        if ($request->query('raw') === '1') {
            return view('inventory.qr-sticker', compact('asset'));
        }

        return view('inventory.qr-sticker', compact('asset'));
    }

    /**
     * Batch QR Sticker Print page — Supply Admin only.
     * Shows all assets with checkboxes; user picks which to print.
     */
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
