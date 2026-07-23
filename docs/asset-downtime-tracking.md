# Asset Downtime Tracking — Implementation Plan

## Overview
Track asset downtime per-ticket (ICT and PM) with dashboard widgets for monitoring.

## Phase 1: Database Migration (15 min)

### 1.1 Add downtime columns to `requests` table
```php
// database/migrations/YYYY_MM_DD_add_downtime_to_requests.php
Schema::table('requests', function (Blueprint $table) {
    $table->timestamp('downtime_start')->nullable()->after('status');
    $table->timestamp('downtime_end')->nullable()->after('downtime_start');
    $table->integer('downtime_duration')->nullable()->after('downtime_end'); // minutes
});
```

### 1.2 Add total_downtime to `inventory_assets` table
```php
Schema::table('inventory_assets', function (Blueprint $table) {
    $table->integer('total_downtime')->default(0)->after('status'); // minutes
});
```

## Phase 2: Model Updates (10 min)

### 2.1 Request model (`app/Models/Request.php`)
```php
public function getDowntimeDurationAttribute(): string
{
    if (!$this->downtime_duration) return 'N/A';
    $hours = floor($this->downtime_duration / 60);
    $minutes = $this->downtime_duration % 60;
    return "{$hours}h {$minutes}m";
}

public function getIsDowntimeAttribute(): bool
{
    return $this->status === 'Ongoing' && $this->downtime_start !== null;
}
```

### 2.2 InventoryAsset model (`app/Models/InventoryAsset.php`)
```php
public function getTotalDowntimeAttribute(): string
{
    $minutes = $this->attributes['total_downtime'] ?? 0;
    $hours = floor($minutes / 60);
    $days = floor($hours / 24);
    $hours = $hours % 24;
    return "{$days}d {$hours}h";
}

public function downtimeTickets()
{
    return $this->hasMany(Request::class, 'linked_asset_id', 'asset_id')
        ->whereNotNull('downtime_duration');
}
```

## Phase 3: Controller Triggers (20 min)

### 3.1 ICTRequestController
```php
// When IT clicks "Start Repair"
public function start($id)
{
    $request = Request::findOrFail($id);
    $request->update([
        'status' => 'Ongoing',
        'downtime_start' => now(),
    ]);

    // Update asset status
    if ($request->linked_asset_id) {
        InventoryAsset::where('asset_id', $request->linked_asset_id)
            ->update(['status' => 'For Repair']);
    }
}

// When IT clicks "Complete"
public function complete($id)
{
    $request = Request::findOrFail($id);
    $duration = now()->diffInMinutes($request->downtime_start);
    $request->update([
        'status' => 'Completed',
        'downtime_end' => now(),
        'downtime_duration' => $duration,
    ]);

    // Update asset status + total_downtime
    if ($request->linked_asset_id) {
        $asset = InventoryAsset::where('asset_id', $request->linked_asset_id)->first();
        $asset->update(['status' => 'Active']);
        $asset->increment('total_downtime', $duration);
    }
}
```

### 3.2 MaintenanceController (same pattern)
- `start()` — set `downtime_start`, asset status → 'For Repair'
- `complete()` — set `downtime_end` + `downtime_duration`, asset status → 'Active', increment `total_downtime`

## Phase 4: Asset Detail Page (15 min)

### 4.1 InventoryController::detail()
```php
$repairHistory = Request::with(['user', 'assignedTo'])
    ->where('linked_asset_id', $assetId)
    ->orderByDesc('created_at')
    ->limit(50)
    ->get();

return view('inventory.detail', compact('asset', 'repairHistory', 'transferHistory'));
```

### 4.2 inventory/detail.blade.php
Add to Repair History section:
```html
<div class="downtime-summary">
    <strong>Total Downtime: {{ $asset->total_downtime }}</strong>
</div>

<!-- Per ticket -->
<span class="downtime-badge">
    Downtime: {{ $ticket->downtime_duration }}
    ({{ $ticket->downtime_start }} - {{ $ticket->downtime_end ?? 'Ongoing' }})
</span>
```

## Phase 5: Dashboard Widgets (15 min)

### 5.1 DashboardController::superAdmin()
```php
// Top 5 assets by downtime
$topDowntimeAssets = InventoryAsset::where('region', $user->region)
    ->when($user->branch, fn($q) => $q->where('branch', $user->branch))
    ->orderByDesc('total_downtime')
    ->limit(5)
    ->get();

// Assets currently in downtime
$assetsInDowntime = InventoryAsset::where('status', 'For Repair')
    ->where('region', $user->region)
    ->when($user->branch, fn($q) => $q->where('branch', $user->branch))
    ->limit(10)
    ->get();
```

### 5.2 dashboard/super-admin.blade.php
Two widgets:
1. **TOP 5 ASSETS BY DOWNTIME** — table with asset name, total downtime, # tickets
2. **ASSETS CURRENTLY IN DOWNTIME** — list with asset name, current downtime duration, ticket link

## Phase 6: Testing & Commit (10 min)

### Test scenarios:
1. ICT ticket: create → start → complete → verify downtime recorded
2. PM ticket: create → start → complete → verify downtime recorded
3. Asset detail page: verify downtime display
4. Dashboard: verify both widgets show correct data
5. Multiple tickets: verify total_downtime accumulates correctly

### Git:
```bash
git add -A
git commit -m "feat: Add asset downtime tracking (ICT + PM)"
git push origin develop
```

## Key Design Decisions

1. **Per-ticket downtime** — each ICT/PM ticket tracks its own downtime
2. **Asset-level total** — `total_downtime` field accumulates all ticket downtimes
3. **Status-based triggers** — downtime starts on "Ongoing", ends on "Completed"
4. **Both ICT and PM** — same `requests` table handles both types
5. **Dashboard widgets** — database aggregation for performance (no N+1)

## Git Checkpoints
- `checkpoint/pre-multilocation-fix` — before region scoping fixes
- `9f19ee8` — region scoping fixes (pushed to develop)
- New checkpoint after downtime implementation
