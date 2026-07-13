<?php

namespace App\Http\Controllers;

use App\Models\InventoryAsset;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PhysicalCountController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $ongoing = PhysicalCountSession::where('scope_region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('scope_branch', $user->branch))
            ->where('status', 'Ongoing')
            ->first();

        $sessions = PhysicalCountSession::with('startedBy')
            ->where('scope_region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('scope_branch', $user->branch))
            ->orderByDesc('started_at')
            ->paginate(20);

        return view('inventory.physical-count', compact('sessions', 'ongoing'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $ongoing = PhysicalCountSession::where('scope_region', $user->region)
            ->when($user->branch, fn ($q) => $q->where('scope_branch', $user->branch))
            ->where('status', 'Ongoing')
            ->first();

        if ($ongoing) {
            return redirect()->route('physical-count.show', $ongoing->id)
                ->with('info', 'You already have an ongoing physical count session.');
        }

        $session = PhysicalCountSession::create([
            'started_by'   => $user->id,
            'started_at'   => now(),
            'status'       => 'Ongoing',
            'scope_region' => $user->region,
            'scope_branch' => $user->branch,
        ]);

        return redirect()->route('physical-count.show', $session->id)
            ->with('success', 'Physical count session started.');
    }

    public function show(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $session = PhysicalCountSession::with(['startedBy', 'counts.asset.assignedUser', 'counts.countedBy'])
            ->findOrFail($id);

        if ($session->scope_region && $session->scope_region !== $user->region) {
            abort(403);
        }
        if ($user->branch && $session->scope_branch && $session->scope_branch !== $user->branch) {
            abort(403);
        }

        // Get total count for summary (unpaginated)
        $totalAssetsQuery = InventoryAsset::query();
        app(InventoryController::class)->scopeAssetsToActor($totalAssetsQuery, $user);
        $totalCount = $totalAssetsQuery->count();

        // Paginated assets for table display
        $allAssets = InventoryAsset::with('assignedUser');
        app(InventoryController::class)->scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')
            ->paginate(50);

        $countedIds = $session->counts->pluck('asset_id')->toArray();

        $summary = [
            'total'   => $totalCount,
            'counted' => $session->counts->count(),
            'present' => $session->counts->where('status', 'Present')->count(),
            'missing' => $session->counts->where('status', 'Missing')->count(),
            'damaged' => $session->counts->where('status', 'Damaged')->count(),
        ];

        return view('inventory.physical-count-show', compact('session', 'allAssets', 'countedIds', 'summary'));
    }

    public function searchAsset(Request $request, $sessionId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = PhysicalCountSession::findOrFail($sessionId);
        if ($session->status !== 'Ongoing') {
            return response()->json(['success' => false, 'message' => 'Session is already completed.'], 422);
        }

        $q = trim($request->input('q', ''));
        $assetId = $request->input('asset_id');

        // Detect QR content format: ID:{number}, URL (/r/{number}), or JSON
        if (!$assetId && strlen($q) >= 1) {
            $idMatch = preg_match('/^ID[:\s]*(\d+)$/i', $q, $m);
            if ($idMatch) {
                $assetId = (int) $m[1];
            } else {
                // Matches /r/123 even if there are trailing slashes or parameters
                $urlMatch = preg_match('/\/r\/(\d+)(?:\/|\?|$)/i', $q, $urlM);
                if ($urlMatch) {
                    $assetId = (int) $urlM[1];
                } else {
                    $decoded = json_decode($q, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decoded['id'])) {
                        $assetId = (int) $decoded['id'];
                    }
                }
            }
        }

        $query = InventoryAsset::with('assignedUser');
        app(InventoryController::class)->scopeAssetsToActor($query, $user);

        if ($assetId) {
            $query->where('asset_id', $assetId);
        } elseif (strlen($q) >= 1) {
            $q = strtolower($q);
            $query->where(function ($qry) use ($q) {
                $qry->whereRaw('LOWER(item_name) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(serial_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(par_number) LIKE ?', ["%{$q}%"])
                    ->orWhereRaw('LOWER(property_number) LIKE ?', ["%{$q}%"]);
            });
        }

        $assets = $query->orderBy('item_name')->limit(20)->get();

        $countedIds = PhysicalCount::where('session_id', $sessionId)
            ->pluck('asset_id')
            ->toArray();

        // Get other assets of the same user if an asset was scanned
        $userAssets = collect();
        $scannedUserId = null;
        if ($assetId) {
            $scannedAsset = InventoryAsset::find($assetId);
            if ($scannedAsset && $scannedAsset->assigned_to_user) {
                $scannedUserId = $scannedAsset->assigned_to_user;
                $userAssets = InventoryAsset::with('assignedUser')
                    ->where('assigned_to_user', $scannedUserId)
                    ->where('asset_id', '!=', $assetId)
                    ->whereNotIn('status', ['For Disposal', 'Scrapped'])
                    ->orderBy('category')
                    ->orderBy('item_name')
                    ->get();
            }
        }

        return response()->json([
            'success'    => true,
            'assets'     => $assets,
            'counted_ids' => $countedIds,
            'user_assets' => $userAssets,
            'scanned_user_id' => $scannedUserId,
        ]);
    }

    public function markAsset(Request $request, $sessionId)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = PhysicalCountSession::findOrFail($sessionId);
        if ($session->status !== 'Ongoing') {
            return response()->json(['success' => false, 'message' => 'Session is already completed.'], 422);
        }

        $validated = $request->validate([
            'asset_id' => 'required|exists:inventory_assets,asset_id',
            'status'   => 'required|in:Present,Missing,Damaged',
            'remarks'  => 'nullable|string|max:500',
        ]);

        $asset = InventoryAsset::findOrFail($validated['asset_id']);

        $existing = PhysicalCount::where('session_id', $sessionId)
            ->where('asset_id', $asset->asset_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => "Asset already counted as {$existing->status}. Cannot change status.",
            ], 422);
        }

        $count = PhysicalCount::create([
            'session_id' => $sessionId,
            'asset_id'   => $asset->asset_id,
            'counted_by' => $user->id,
            'status'     => $validated['status'],
            'remarks'    => $validated['remarks'] ?? null,
            'counted_at' => now(),
        ]);

        \App\Models\AuditLog::log(
            'Physical Count',
            'Inventory',
            "Asset #{$asset->asset_id} ({$asset->item_name}) marked as {$validated['status']}",
            $asset->office
        );

        return response()->json(['success' => true, 'message' => "Asset marked as {$validated['status']}."]);
    }

    public function complete($id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $session = PhysicalCountSession::findOrFail($id);
        $session->update([
            'status'       => 'Completed',
            'completed_at' => now(),
        ]);

        return redirect()->route('physical-count.show', $session->id)
            ->with('success', 'Physical count session completed.');
    }

    public function export($id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $session = PhysicalCountSession::with(['startedBy', 'counts.asset.assignedUser', 'counts.countedBy'])
            ->findOrFail($id);

        if ($session->scope_region && $session->scope_region !== $user->region) {
            abort(403);
        }
        if ($user->branch && $session->scope_branch && $session->scope_branch !== $user->branch) {
            abort(403);
        }

        $allAssets = InventoryAsset::with('assignedUser');
        app(InventoryController::class)->scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')->get();

        $filename = 'physical-count-' . $session->id . '-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($session, $allAssets) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, ['PHYSICAL INVENTORY COUNT REPORT']);
            fputcsv($output, ['Session #', $session->id]);
            fputcsv($output, ['Region', $session->scope_region]);
            fputcsv($output, ['Branch', $session->scope_branch ?? 'All']);
            fputcsv($output, ['Started By', $session->startedBy->full_name ?? $session->startedBy->name ?? 'Unknown']);
            fputcsv($output, ['Date Started', $session->started_at->format('F j, Y g:i A')]);
            fputcsv($output, ['Status', $session->status]);
            if ($session->completed_at) {
                fputcsv($output, ['Date Completed', $session->completed_at->format('F j, Y g:i A')]);
            }
            fputcsv($output, []);

            $counted = $session->counts;
            fputcsv($output, ['Total Assets', $allAssets->count()]);
            fputcsv($output, ['Counted', $counted->count()]);
            fputcsv($output, ['Present', $counted->where('status', 'Present')->count()]);
            fputcsv($output, ['Missing', $counted->where('status', 'Missing')->count()]);
            fputcsv($output, ['Damaged', $counted->where('status', 'Damaged')->count()]);
            fputcsv($output, []);

            fputcsv($output, ['Asset ID', 'Item Name', 'Serial Number', 'PAR Number', 'Property Number', 'Category', 'Count Status', 'Counted By', 'Counted At', 'Remarks']);

            $countedByAsset = $session->counts->keyBy('asset_id');

            foreach ($allAssets as $asset) {
                $c = $countedByAsset->get($asset->asset_id);
                fputcsv($output, [
                    $asset->asset_id,
                    $asset->item_name,
                    $asset->serial_number ?? '',
                    $asset->par_number ?? '',
                    $asset->property_number ?? '',
                    $asset->category ?? '',
                    $c ? $c->status : 'Not Counted',
                    $c ? ($c->countedBy->full_name ?? $c->countedBy->name ?? '') : '',
                    $c ? $c->counted_at->format('Y-m-d H:i:s') : '',
                    $c ? ($c->remarks ?? '') : '',
                ]);
            }

            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function printReport($id)
    {
        $user = Auth::user();
        if (!$user->canProcessSupply()) {
            abort(403);
        }

        $session = PhysicalCountSession::with(['startedBy', 'counts.asset.assignedUser', 'counts.countedBy'])
            ->findOrFail($id);

        if ($session->scope_region && $session->scope_region !== $user->region) {
            abort(403);
        }
        if ($user->branch && $session->scope_branch && $session->scope_branch !== $user->branch) {
            abort(403);
        }

        $allAssets = InventoryAsset::with('assignedUser');
        app(InventoryController::class)->scopeAssetsToActor($allAssets, $user);
        $allAssets = $allAssets->orderBy('category')->orderBy('item_name')->get();

        $summary = [
            'total'   => $allAssets->count(),
            'counted' => $session->counts->count(),
            'present' => $session->counts->where('status', 'Present')->count(),
            'missing' => $session->counts->where('status', 'Missing')->count(),
            'damaged' => $session->counts->where('status', 'Damaged')->count(),
        ];

        // Group assets by category for organized display
        $grouped = $allAssets->groupBy('category')->sortKeys();

        return view('inventory.physical-count-print', compact('session', 'allAssets', 'summary', 'grouped'));
    }
}
