<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\InventoryHistory;
use App\Models\AuditLog;
use App\Http\Requests\StoreInventoryRequest;
use App\Services\InventoryCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        $this->scopeAssetsToActor($query, $user);

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

        return response()->json([
            'success' => true,
            'assets' => $assets->items(),
            'total' => $assets->total(),
            'per_page' => $assets->perPage(),
            'current_page' => $assets->currentPage(),
            'last_page' => $assets->lastPage(),
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
        $user = Auth::user();
        if (! $this->canWriteInventory($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory encoding is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validated();

        $validated['region'] = $user->region;

        $assignmentError = $this->validateAssignedUserScope($user, $validated['assigned_to_user'] ?? null, $validated['region'] ?? null);
        if ($assignmentError) {
            return response()->json(['success' => false, 'message' => $assignmentError], 422);
        }

        $this->applyInventoryOrgScope($validated, $user);

        // Specifications handling
        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $validated['specifications'] = $decoded;
            }
        }

        // Auto-enforce status integrity is now handled by InventoryAsset model event (booted())
        // This applies to all saves across all locations/regions

        // Auto-generate PAR only when asset is assigned to a custodian
        if (!empty($validated['assigned_to_user'])) {
            $validated['par_number'] = \App\Services\ParNumberService::generateNextParNumber();
        }

        $asset = InventoryAsset::create($validated);

        AuditLog::log(
            "Added New Asset", 
            "Inventory", 
            "Added {$asset->item_name} (SN: {$asset->serial_number}) to inventory",
            $asset->office
        );

        // Log history — no receipt generated, physical PTR handled outside system
        InventoryHistory::create([
            'asset_id' => $asset->asset_id,
            'action' => !empty($validated['assigned_to_user']) ? 'Asset Registered & Assigned' : 'Asset Added',
            'performed_by' => $user->id,
            'new_user_id' => $validated['assigned_to_user'] ?? null,
            'new_status' => $validated['status'],
            'remarks' => 'Initial entry into inventory',
        ]);

        return response()->json(['success' => true, 'message' => 'Asset added successfully', 'par_number' => $asset->par_number]);
    }

    public function previewImport(Request $request, InventoryCsvImportService $importer)
    {
        $user = Auth::user();
        if (! $this->canWriteInventory($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory import is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validate([
            'file' => 'required|file|max:20480|mimes:csv,txt',
        ]);

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

    public function commitImport(Request $request, InventoryCsvImportService $importer)
    {
        $user = Auth::user();
        if (! $this->canWriteInventory($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory import is handled by the Administrative supply admin.',
            ], 403);
        }

        $validated = $request->validate([
            'token' => 'required|string|uuid',
        ]);

        $token = $validated['token'];
        $relativePath = session("inventory_import_{$token}");
        if (! $relativePath || ! Storage::disk('local')->exists($relativePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Import preview expired. Upload the CSV again.',
            ], 422);
        }

        $absolutePath = Storage::disk('local')->path($relativePath);
        $rows = $importer->importableRows($absolutePath, $user);

        $created = 0;
        $setRows = 0;
        // Maps shared PAR numbers to their parent asset_id so child components
        // (e.g. a split-out Monitor) can resolve their parent_asset_id as the
        // batch is committed.
        $parentByPar = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                $rowWasSet = false;
                foreach ($row['records'] as $recordData) {
                    $isComponent = !empty($recordData['_is_component']);
                    unset($recordData['_is_component']);

                    if ($isComponent) {
                        $parKey = strtoupper(trim($recordData['par_number'] ?? ''));
                        if ($parKey && isset($parentByPar[$parKey])) {
                            $recordData['parent_asset_id'] = $parentByPar[$parKey];
                        }
                        $rowWasSet = true;
                    }

                    $asset = InventoryAsset::create($recordData);

                    // Remember the parent for any sibling components sharing this PAR.
                    if (!$isComponent) {
                        $parKey = strtoupper(trim($asset->par_number ?? ''));
                        if ($parKey) {
                            $parentByPar[$parKey] = $asset->asset_id;
                        }
                    }

                    InventoryHistory::create([
                        'asset_id' => $asset->asset_id,
                        'action' => $isComponent
                            ? ($asset->assigned_to_user ? 'Set Component Imported & Assigned' : 'Set Component Imported')
                            : ($asset->assigned_to_user ? 'Asset Imported & Assigned' : 'Asset Imported'),
                        'performed_by' => $user->id,
                        'new_user_id' => $asset->assigned_to_user,
                        'new_status' => $asset->status,
                        'remarks' => 'Imported from ICT CSV. Original responsible officer: ' . ($row['responsible_officer_raw'] ?: 'N/A'),
                    ]);

                    $created++;
                }

                if ($rowWasSet) {
                    $setRows++;
                }
            }

            AuditLog::log(
                'Imported Inventory CSV',
                'Inventory',
                "Imported {$created} ICT asset record(s)" . ($setRows ? " ({$setRows} split into set components)" : '') . " from CSV.",
                $user->office ?? $user->branch ?? 'Inventory'
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }

        Storage::disk('local')->delete($relativePath);
        session()->forget("inventory_import_{$token}");

        return response()->json([
            'success' => true,
            'message' => "Imported {$created} asset record(s)" . ($setRows ? " across {$setRows} set(s)" : '') . ".",
            'created' => $created,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $asset = InventoryAsset::findOrFail($id);

        if (! $this->canWriteInventory($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Inventory updates are handled by the Administrative supply admin.',
            ], 403);
        }

        if (! $this->assetInInventoryScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized to update this asset'], 403);
        }

        // FULL LOCK: Scrapped and For Disposal assets cannot be edited at all
        // Preserves disposal audit trail and DB integrity
        if (in_array($asset->status, \App\Enums\AssetStatus::LOCKED)) {
            return response()->json([
                'success' => false,
                'message' => "{$asset->status} assets are locked. All edits are disabled to preserve audit and disposal records.",
            ], 422);
        }

        // Block transfer of Defective or For Disposal assets
        $lockedStatuses = ['Defective', \App\Enums\AssetStatus::FOR_DISPOSAL, \App\Enums\AssetStatus::SCRAPPED];
        $newAssignee = $request->input('assigned_to_user');
        if (in_array($asset->status, $lockedStatuses) && $newAssignee != $asset->assigned_to_user) {
            return response()->json([
                'success' => false,
                'message' => "Cannot reassign a {$asset->status} asset. Resolve the asset status first."
            ], 422);
        }

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'serial_number' => 'required|string|max:255',
            'property_number' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'status' => 'required|in:Active,Spare,Defective,For Repair',
            // Note: Scrapped and For Disposal are system-only — set by repair/disposal workflow
            'assigned_to_user' => 'nullable|exists:users,id',
            'category' => 'nullable|string',
            'specifications' => 'nullable',
            'remarks' => 'nullable|string',
            'branch' => 'nullable|string|max:255',
            'office' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'date_acquired' => 'nullable|date',
            'warranty_expiration' => 'nullable|date',
            'acquisition_cost' => 'nullable|numeric|min:0',
            'end_of_useful_life' => 'nullable|date',
            'asset_notes' => 'nullable|string|max:1000',
        ]);

        $assignmentError = $this->validateAssignedUserScope($user, $validated['assigned_to_user'] ?? null, $asset->region);
        if ($assignmentError) {
            return response()->json(['success' => false, 'message' => $assignmentError], 422);
        }

        $this->applyInventoryOrgScope($validated, $user, $asset);

        $data = $validated;
        // Specifications handling
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $decoded = json_decode($data['specifications'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['specifications'] = $decoded;
            }
        }

        $previousStatus = $asset->status;
        $previousUser = $asset->assigned_to_user;
        $previousPar = $asset->par_number;

        // Auto-generate PAR when previously unassigned (Spare) asset gets assigned
        if (!$asset->par_number && !empty($data['assigned_to_user'])) {
            $data['par_number'] = \App\Services\ParNumberService::generateNextParNumber();
        }

        // PAR regeneration on reassignment: new custodian = new PAR (government compliance)
        if ($previousUser && !empty($data['assigned_to_user']) && (int) $data['assigned_to_user'] !== $previousUser) {
            $data['par_number'] = \App\Services\ParNumberService::generateNextParNumber();
        }

        $asset->update($data);

        AuditLog::log(
            "Updated Asset", 
            "Inventory", 
            "Updated details for {$asset->item_name} (SN: {$asset->serial_number})",
            $asset->office
        );

        if ($previousStatus !== $asset->status || $previousUser !== $asset->assigned_to_user) {
            $action = 'Asset Updated';
            if ($previousUser !== $asset->assigned_to_user) {
                $action = $asset->assigned_to_user ? 'Custodian Updated' : 'Asset Returned to Stock';
            }

            $remarks = $request->remarks ?? 'Asset details updated';
            if ($previousUser !== $asset->assigned_to_user && $previousPar && $asset->par_number !== $previousPar) {
                $remarks = "Reassigned from PAR {$previousPar} to PAR {$asset->par_number}. " . $remarks;
            }

            InventoryHistory::create([
                'asset_id' => $asset->asset_id,
                'action' => $action,
                'performed_by' => $user->id,
                'previous_user_id' => $previousUser,
                'new_user_id' => $asset->assigned_to_user,
                'previous_status' => $previousStatus,
                'new_status' => $asset->status,
                'remarks' => $remarks,
            ]);
        }

        // Auto-sync assigned user's department into asset (avoids manual "Update Department" action)
        if ($asset->assigned_to_user) {
            $assignedUser = User::find($asset->assigned_to_user);
            if ($assignedUser && $assignedUser->department !== $asset->department) {
                $asset->update(['department' => $assignedUser->department]);
            }
        }

        return response()->json(['success' => true, 'message' => 'Asset updated successfully']);
    }

    public function getHistory($assetId)
    {
        $asset = InventoryAsset::findOrFail($assetId);
        $user = Auth::user();

        if (! $user->canProcessSupply() && $user->role !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Inventory history is managed by the Administrative supply admin.'], 403);
        }

        if ($user->canProcessSupply() && ! $this->assetInActorViewScope($user, $asset)) {
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
    public function confirmScrapped(Request $request, $assetId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Only the supply officer can confirm disposal.'], 403);
        }

        $asset = InventoryAsset::findOrFail($assetId);

        if ($user->region && $asset->region !== $user->region) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your region scope.'], 403);
        }
        if ($user->branch && $asset->branch !== $user->branch) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your branch scope.'], 403);
        }

        if ($asset->status !== 'For Disposal') {
            return response()->json(['success' => false, 'message' => 'Asset must be tagged "' . \App\Enums\AssetStatus::FOR_DISPOSAL . '" before confirming scrapped.'], 422);
        }

        $validated = $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $remarks = $validated['remarks'] ?? 'Physical disposal confirmed by Supply Officer.';

        $previousUser = $asset->assigned_to_user;

        $asset->update([
            'status'           => 'Scrapped',
            'assigned_to_user' => null,
        ]);

        InventoryHistory::create([
            'asset_id'        => $asset->asset_id,
            'action'          => 'Disposal Confirmed — Scrapped',
            'performed_by'    => $user->id,
            'previous_user_id'=> $previousUser,
            'new_user_id'     => null,
            'previous_status' => 'For Disposal',
            'new_status'      => 'Scrapped',
            'remarks'         => $remarks,
        ]);

        AuditLog::log(
            'Asset Scrapped',
            'Inventory',
            "Supply Officer confirmed disposal of {$asset->item_name} (SN: {$asset->serial_number}). Asset is now Scrapped.",
            $asset->office
        );

        return response()->json(['success' => true, 'message' => 'Asset confirmed as Scrapped. Record is now permanently locked.']);
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

        if (!$this->assetInActorViewScope($user, $asset)) {
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
    public function uploadAttachment(Request $request, $assetId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $asset = InventoryAsset::findOrFail($assetId);

        if (!$this->assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Asset is outside your scope.'], 403);
        }

        $request->validate([
            'file'  => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,zip|mimetypes:application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png,image/gif,application/zip',
            'label' => 'nullable|string|max:100',
        ]);

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

        if ($attachment->asset && !$this->assetInActorViewScope($user, $attachment->asset)) {
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
        if ($user->canProcessSupply() && $attachment->asset && !$this->assetInActorViewScope($user, $attachment->asset)) {
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

		return response()->json([
			'success' => true,
			'assets' => $assets->items(),
			'total' => $assets->total(),
			'per_page' => $assets->perPage(),
			'current_page' => $assets->currentPage(),
			'last_page' => $assets->lastPage(),
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

    public function scopeAssetsToActor($query, User $actor): void
    {
        $query->where('region', $actor->region);

        if ($actor->canProcessSupply()) {
            if ($actor->branch) {
                $query->where('branch', $actor->branch);
            }
            return;
        }

        if (!$actor->branch && !$actor->office && !$actor->department) {
            return;
        }

        $query->where(function ($q) use ($actor) {
            // Assigned assets: match via the assigned user's org scope
            $q->where(function ($assetScope) use ($actor) {
                if ($actor->branch) {
                    $assetScope->where('branch', $actor->branch);
                }
                if ($actor->office) {
                    $assetScope->where('office', $actor->office);
                }
                if ($actor->department) {
                    $assetScope->where('department', $actor->department);
                }
            })->orWhereHas('assignedUser', function ($userScope) use ($actor) {
                if ($actor->branch) {
                    $userScope->where('branch', $actor->branch);
                }
                if ($actor->office) {
                    $userScope->where('office', $actor->office);
                }
                if ($actor->department) {
                    $userScope->where('department', $actor->department);
                }
            })->orWhere(function ($unassigned) use ($actor) {
                // Unassigned assets: require STRICT AND match on all org fields.
                // Prevents unassigned assets from leaking across divisions.
                $unassigned->whereNull('assigned_to_user');
                if ($actor->branch) {
                    $unassigned->where('branch', $actor->branch);
                }
                if ($actor->office) {
                    $unassigned->where(function ($o) use ($actor) {
                        $o->where('office', $actor->office)
                          ->orWhereNull('office');
                    });
                }
                if ($actor->department) {
                    $unassigned->where(function ($d) use ($actor) {
                        $d->where('department', $actor->department)
                          ->orWhereNull('department');
                    });
                }
            });
        });
    }

    private function assetInActorViewScope(User $actor, InventoryAsset $asset): bool
    {
        if ($actor->canProcessSupply()) {
            if ($asset->region !== $actor->region) {
                return false;
            }
            if ($actor->branch && $asset->branch !== $actor->branch) {
                return false;
            }
            return true;
        }

        if (!$actor->branch && !$actor->office && !$actor->department) {
            return true;
        }

        $assetMatches = (!$actor->branch || $asset->branch === $actor->branch)
            && (!$actor->office || $asset->office === $actor->office)
            && (!$actor->department || $asset->department === $actor->department);

        if ($assetMatches) {
            return true;
        }

        if (!$asset->assigned_to_user) {
            return false;
        }

        $assignedUser = User::find($asset->assigned_to_user);

        return $assignedUser ? $this->subjectInActorOrgScope($actor, $assignedUser) : false;
    }

    private function assetInInventoryScope(User $user, InventoryAsset $asset): bool
    {
        if (! $user->canProcessSupply() || ! $this->assetInActorViewScope($user, $asset)) {
            return false;
        }

        return true;
    }

    private function userInInventoryScope(User $actor, User $subject, ?string $assetRegion = null): bool
    {
        if (! $actor->canProcessSupply()) {
            return false;
        }

        return $this->subjectInActorOrgScope($actor, $subject);
    }

    private function subjectInActorOrgScope(User $actor, User $subject): bool
    {
        if ($actor->branch && $subject->branch !== $actor->branch) {
            return false;
        }
        
        // Administrative supply admin manages entire branch inventory (office-wide)
        // They don't need office/division match - only branch matters
        if ($actor->canProcessSupply()) {
            return true;
        }
        
        if ($actor->office && $subject->office !== $actor->office) {
            return false;
        }

        return true;
    }

    private function applyInventoryOrgScope(array &$data, User $actor, ?InventoryAsset $asset = null): void
    {
        $assignedUser = !empty($data['assigned_to_user']) ? User::find($data['assigned_to_user']) : null;

        if ($assignedUser) {
            $data['region'] = $assignedUser->region;
            $data['branch'] = $assignedUser->branch;
            $data['office'] = $assignedUser->office;
            $data['department'] = $assignedUser->department;
            return;
        }

        if ($asset) {
            $data['region'] = $asset->region;
            $data['branch'] = $asset->branch;
            $data['office'] = $asset->office;
            $data['department'] = $asset->department;
            return;
        }

        // Default to actor's scope for completely unassigned assets.
        $data['region'] = $actor->region;
        $data['branch'] = $actor->branch;
        $data['office'] = $actor->office;
        $data['department'] = $actor->department;
    }

    private function validateAssignedUserScope(User $actor, mixed $assignedUserId, ?string $assetRegion): ?string
    {
        if (empty($assignedUserId)) {
            return null;
        }

        $assignedUser = User::find($assignedUserId);
        if (!$assignedUser) {
            return 'Selected user does not exist.';
        }

        if (! $this->userInInventoryScope($actor, $assignedUser, $assetRegion)) {
            return 'Selected custodian is outside your inventory assignment scope.';
        }

        return null;
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
            $this->scopeAssetsToActor($query, $user);
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
        $user = Auth::user();
        if (!$user->canProcessSupply() && $user->role !== 'super_admin') {
            abort(403);
        }

        $query = InventoryAsset::with('assignedUser');

        if ($user->canProcessSupply()) {
            $this->scopeAssetsToActor($query, $user);
        } else {
            $query->where('region', $user->region);
            if ($user->branch) {
                $query->where('branch', $user->branch);
            }
        }

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

        $assets = $query->orderBy('created_at', 'desc')->get();

        $filename = 'inventory_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename={$filename}",
        ];

        $callback = function () use ($assets) {
            $file = fopen('php://output', 'w');
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'PAR No', 'Property No', 'Item Name', 'Serial No', 'Brand', 'Model',
                'Category', 'Status', 'Assigned To', 'Region', 'Branch', 'Office',
                'Department', 'Date Acquired', 'Warranty Expiration', 'Acquisition Cost',
                'End of Useful Life', 'Asset Notes',
            ]);

            foreach ($assets as $asset) {
                fputcsv($file, [
                    $asset->par_number,
                    $asset->property_number,
                    $asset->item_name,
                    $asset->serial_number,
                    $asset->brand,
                    $asset->model,
                    $asset->category,
                    $asset->status,
                    $asset->assignedUser?->full_name ?? 'Unassigned',
                    $asset->region,
                    $asset->branch,
                    $asset->office,
                    $asset->department,
                    $asset->date_acquired?->format('Y-m-d'),
                    $asset->warranty_expiration?->format('Y-m-d'),
                    $asset->acquisition_cost,
                    $asset->end_of_useful_life?->format('Y-m-d'),
                    $asset->asset_notes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
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
        $user = Auth::user();

        $asset = InventoryAsset::with(['assignedUser'])
            ->findOrFail($id);

        if (!$this->assetInActorViewScope($user, $asset)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $assetUserId = $asset->assigned_to_user;
        $repairHistory = \App\Models\Request::with(['repairRequest', 'maintenanceRequest'])
            ->where(function ($q) use ($id, $assetUserId) {
                $q->where('linked_asset_id', $id)
                  ->orWhere(function ($sub) use ($assetUserId) {
                      $sub->where('type', 'Preventive Maintenance')
                          ->where('is_auto_generated', true)
                          ->where('user_id', $assetUserId);
                  });
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $lastRepair = $repairHistory->firstWhere('type', 'repair');
        $lastPm = $repairHistory->firstWhere('type', 'Preventive Maintenance');

        $inventoryRoute = $user->canProcessSupply()
            ? route('inventory.detail', $id)
            : ($user->role === 'super_admin' ? route('super_admin.inventory.detail', $id) : null);

        $actions = [];
        if ($user->canProcessSupply() || $user->role === 'super_admin') {
            $actions[] = [
                'label' => 'View Detail',
                'url'   => $inventoryRoute,
                'icon'  => 'fa-eye',
            ];
            if (session('physical_count_session_id')) {
                $actions[] = [
                    'label' => 'Mark in Physical Count',
                    'url'   => route('physical-count.show', session('physical_count_session_id')),
                    'icon'  => 'fa-check',
                ];
            }
        }
        if (in_array($user->role, ['it', 'super_admin'], true)) {
            $actions[] = [
                'label' => 'Create Repair Ticket',
                'url'   => route('ict.create', ['asset_id' => $id]),
                'icon'  => 'fa-screwdriver-wrench',
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'asset' => [
                    'asset_id'          => $asset->asset_id,
                    'item_name'         => $asset->item_name,
                    'serial_number'     => $asset->serial_number,
                    'par_number'        => $asset->par_number,
                    'property_number'   => $asset->property_number,
                    'brand'             => $asset->brand,
                    'model'             => $asset->model,
                    'category'          => $asset->category,
                    'status'            => $asset->status,
                    'specifications'    => $asset->specifications,
                    'assigned_user'     => $asset->assignedUser ? [
                        'id'        => $asset->assignedUser->id,
                        'full_name' => $asset->assignedUser->full_name,
                        'office'    => $asset->assignedUser->office,
                    ] : null,
                    'date_acquired'      => $asset->date_acquired?->format('Y-m-d'),
                    'acquisition_cost'   => $asset->acquisition_cost,
                    'warranty_expiration' => $asset->warranty_expiration?->format('Y-m-d'),
                    'end_of_useful_life' => $asset->end_of_useful_life?->format('Y-m-d'),
                ],
                'history' => [
                    'last_repair' => $lastRepair?->created_at->format('M d, Y'),
                    'last_pm'     => $lastPm?->created_at->format('M d, Y'),
                    'total_repairs' => $repairHistory->count(),
                ],
                'actions' => $actions,
            ],
        ]);
    }
}
