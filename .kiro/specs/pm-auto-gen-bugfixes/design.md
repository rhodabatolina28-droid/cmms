# Design Document: PM Auto-Generation Bugfixes

## Overview

Three surgical fixes targeting the PM auto-generation pipeline. No new tables, no schema changes, no architectural shifts. Each fix is isolated to the specific method that owns the broken logic.

---

## Bug #1 — `last_pm_date` Not Updated for Auto-Generated PMs

### Root Cause

`MaintenanceController::update()` has a single completion block:

```php
if ($newStatus === RequestModel::STATUS_COMPLETED && $trackingRequest->linked_asset_id) {
    $asset = InventoryAsset::find($trackingRequest->linked_asset_id);
    ...
}
```

Auto-generated PMs have `linked_asset_id = null`, so the guard `&& $trackingRequest->linked_asset_id` always evaluates to false. The block never runs. Assets assigned to the completed user never get their PM dates updated.

### Fix Design

Replace the single conditional block with two branches — one for manual PMs (existing), one for auto-generated PMs (new):

```php
if ($newStatus === RequestModel::STATUS_COMPLETED) {

    // --- Manual PM: single linked asset ---
    if ($trackingRequest->linked_asset_id) {
        $asset = \App\Models\InventoryAsset::find($trackingRequest->linked_asset_id);
        if ($asset) {
            $asset->last_pm_date    = now();
            $asset->next_pm_due_date = $this->resolveNextPmDate($trackingRequest);
            $asset->save();
        }
    }

    // --- Auto-generated (bundled) PM: all active assets assigned to user ---
    elseif ($trackingRequest->is_auto_generated && $trackingRequest->user_id) {
        $userAssets = \App\Models\InventoryAsset::where('assigned_to_user', $trackingRequest->user_id)
            ->where('status', 'Active')
            ->get();

        $nextDate = $this->resolveNextPmDate($trackingRequest);

        foreach ($userAssets as $asset) {
            $asset->last_pm_date     = now();
            $asset->next_pm_due_date = $nextDate;
            $asset->save();
        }
    }
}
```

Extract the `next_pm_due_date` calculation into a private helper to avoid duplication:

```php
private function resolveNextPmDate(RequestModel $trackingRequest): string
{
    if ($trackingRequest->pm_schedule_id) {
        $schedule = \App\Models\PMSchedule::find($trackingRequest->pm_schedule_id);
        if ($schedule && $schedule->is_active) {
            return $schedule->calculateNextDate();
        }
    }
    return now()->addMonths(3)->toDateString();
}
```

### Why Not Option A (update all assets for user_id blindly)?

Option A would work, but must be scoped to `status = 'Active'` to avoid stamping PM dates on assets that are `For Disposal`, `Defective`, or `Scrapped`. The design above applies this scope correctly.

### Transaction Safety

The new block sits inside the existing `DB::beginTransaction()` / `DB::commit()` / `DB::rollBack()` wrapping the entire `update()` method. No changes to transaction boundaries are needed.

### No Schema Changes Required

`last_pm_date` and `next_pm_due_date` already exist on `inventory_assets` and are already in `$fillable`.

---

## Bug #3 — `division_filter` Stored but Never Applied

### Root Cause

`getEligibleUsers()` has an explicit comment:

```php
// NOTE: Division filter is NOT applied here - it's applied later when selecting focus division
```

But the focus division selection inside `getEligibleUsers()` is purely positional (oldest-asset-date wins across all divisions). The `division_filter` is only read in `getQueueStatus()` for display — it never restricts which users get PM work orders.

`getNextEligibleDivision()` also fetches all assets without division restriction, so even if `getEligibleUsers()` were fixed, cycle-advance would still leak into other divisions.

### Fix Design

**Step 1 — Extract a reusable `applyDivisionFilter()` helper:**

```php
private function applyDivisionFilter(\Illuminate\Database\Eloquent\Builder $query, PMSchedule $schedule): void
{
    if (!$schedule->division_filter) {
        return;
    }

    $divisionMappings = [
        'RID'  => ['RESEARCH AND INFORMATION', 'RID'],
        'AD'   => ['ADMINISTRATIVE', 'AD'],
        'FMD'  => ['FINANCIAL AND MANAGEMENT', 'FMD'],
        'COA'  => ['COMMISSION ON AUDIT', 'COA'],
        'CMD'  => ['CONCILIATION AND MEDIATION', 'CMD'],
        'VAD'  => ['VOLUNTARY ARBITRATION', 'VAD'],
        'WRED' => ['WORKPLACE RELATIONS', 'WRED'],
        'OED'  => ['EXECUTIVE DIRECTOR', 'OED'],
    ];

    $keywords = $divisionMappings[$schedule->division_filter] ?? [$schedule->division_filter];

    $query->where(function ($q) use ($keywords) {
        foreach ($keywords as $kw) {
            $q->orWhere('office', 'LIKE', "%{$kw}%")
              ->orWhere('department', 'LIKE', "%{$kw}%");
        }
    });
}
```

**Step 2 — Call it in `getEligibleUsers()`**, right after the `asset_categories` filter and before `->get()`:

```php
// Filter by asset categories if specified
if (!empty($schedule->asset_categories)) {
    $query->whereIn('category', $schedule->asset_categories);
}

// Apply division filter if configured (restricts generation to one division)
$this->applyDivisionFilter($query, $schedule);

$assets = $query->get();
```

**Step 3 — Call it in `getNextEligibleDivision()`** in the same position:

```php
if (!empty($schedule->asset_categories)) {
    $query->whereIn('category', $schedule->asset_categories);
}

// Apply division filter to prevent cycle-advance into other divisions
$this->applyDivisionFilter($query, $schedule);

$assets = $query->get();
```

**Step 4 — Remove the duplicate inline block from `getQueueStatus()`** (it already has its own inline version). The `getQueueStatus()` method is display-only and can keep its own copy to avoid coupling, OR it can call `applyDivisionFilter()` too. Either approach is acceptable; keeping `getQueueStatus()` unchanged is the safest path.

### Backward Compatibility

- When `division_filter` is null (the majority of schedules), `applyDivisionFilter()` returns immediately — zero behavior change.
- Only schedules with a non-null `division_filter` are affected.

---

## Bug #7 — Speaker/Earphone Asset Mapping Overlap

### Root Cause

`mapUserAssetsToPMForm()` has a single combined branch:

```php
} elseif (str_contains($cat, 'speaker') || str_contains($cat, 'earphone')) {
    $mapped['speakers_brand'] = $asset->brand;
    $mapped['speakers_model'] = $asset->model;
    $mapped['speakers_pno']   = $asset->property_number;
}
```

When a user has both a speaker and earphones, both assets hit this same branch and write to the same three keys. The last asset processed wins. The first asset's data is silently lost.

### Fix Design

Split into two independent `elseif` branches:

```php
} elseif (str_contains($cat, 'speaker')) {
    $mapped['speakers_brand'] = $asset->brand;
    $mapped['speakers_model'] = $asset->model;
    $mapped['speakers_pno']   = $asset->property_number;

} elseif (str_contains($cat, 'earphone')) {
    $mapped['earphone_brand'] = $asset->brand;
    $mapped['earphone_model'] = $asset->model;
    $mapped['earphone_pno']   = $asset->property_number;
}
```

### Order Matters

`str_contains($cat, 'earphone')` must be checked before (or separated from) `str_contains($cat, 'speaker')` because some category values could theoretically contain both substrings (e.g., `'speaker/earphone combo'`). With two independent `elseif` branches, the first match wins per asset — which is the correct behavior since each asset maps to exactly one category.

### Model Compatibility

`PreventiveMaintenance::$fillable` already contains:
- `earphone_brand`
- `earphone_model`
- `earphone_brand_model` (legacy combined field — can remain unused)

`earphone_pno` is NOT in `$fillable`. It must be added.

### Schema Verification

```sql
-- Verify earphone_pno column exists
SHOW COLUMNS FROM preventive_maintenance LIKE 'earphone_pno';
```

If the column doesn't exist (only `earphone_brand_model` is present in the DB but `earphone_brand`/`earphone_model` are separate), a migration is needed. Based on the model's `$fillable`, the separate fields exist. The implementation task must verify this before deploying.

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do.*

### Property 1: Auto-Generated PM Completion Updates All Active User Assets

For any auto-generated PM request that transitions to `Completed`, every `InventoryAsset` with `status = Active` and `assigned_to_user = request.user_id` SHALL have `last_pm_date` set to a non-null value after the update call completes.

**Validates: Requirements 1.1, 1.4**

### Property 2: Manual PM Completion Path Is Unchanged

For any manual PM request (non-null `linked_asset_id`) that transitions to `Completed`, exactly the one asset matching `linked_asset_id` SHALL be updated — no other assets SHALL be modified.

**Validates: Requirements 1.5**

### Property 3: Division Filter Restricts Generated Users

For any PM schedule with a non-null `division_filter`, the set of user IDs returned by `getEligibleUsers()` SHALL be a subset of users whose active assets belong to the filtered division. No users from other divisions SHALL be included.

**Validates: Requirements 2.1, 2.2**

### Property 4: Speaker and Earphone Mapping Are Independent

For any user asset list containing both a speaker asset and an earphone asset, `mapUserAssetsToPMForm()` SHALL return a map containing all six keys (`speakers_brand`, `speakers_model`, `speakers_pno`, `earphone_brand`, `earphone_model`, `earphone_pno`) with each key holding the data from its respective asset type.

**Validates: Requirements 3.1, 3.2, 3.3**
