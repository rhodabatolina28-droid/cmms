# Implementation Plan: PM Auto-Generation Bugfixes

## Overview

Three surgical changes across two files. No migrations required for Bugs #1 and #3. Bug #7 requires verifying one column exists before deploying. Implementation order: #7 (smallest) → #3 (contained) → #1 (most critical).

---

## Tasks

- [ ] 1. Fix Bug #7 — Split speaker/earphone mapping into separate branches
  - [ ] 1.1 Verify `earphone_pno` column exists in the `preventive_maintenance` table
    - Run `SHOW COLUMNS FROM preventive_maintenance LIKE 'earphone%';` in MySQL
    - If `earphone_pno` is missing, create a migration: `php artisan make:migration add_earphone_pno_to_preventive_maintenance`
    - Migration should add: `$table->string('earphone_pno')->nullable()->after('earphone_model');`
    - If the column already exists, skip the migration
    - _Requirements: 3.5_

  - [ ] 1.2 Split the combined `speaker || earphone` branch in `mapUserAssetsToPMForm()`
    - File: `app/Services/GeneratePMScheduleService.php`
    - Locate the `elseif (str_contains($cat, 'speaker') || str_contains($cat, 'earphone'))` block
    - Replace with two independent `elseif` branches:
      - First branch: `str_contains($cat, 'speaker')` → maps to `speakers_brand/model/pno`
      - Second branch: `str_contains($cat, 'earphone')` → maps to `earphone_brand/model/pno`
    - Ensure earphone branch uses `earphone_brand`, `earphone_model`, `earphone_pno` (not the legacy `earphone_brand_model` field)
    - _Requirements: 3.1, 3.2, 3.3, 3.4_

  - [ ] 1.3 Add `earphone_pno` to `PreventiveMaintenance::$fillable` if not already present
    - File: `app/Models/PreventiveMaintenance.php`
    - Check that `earphone_pno` is in the `$fillable` array
    - Add it after `earphone_model` if missing
    - _Requirements: 3.5_

  - [ ]* 1.4 Write unit test for `mapUserAssetsToPMForm()` — speaker/earphone separation
    - Test: user has both speaker asset and earphone asset → both sets of fields populated
    - Test: user has only speaker → earphone fields absent/null
    - Test: user has only earphone → speaker fields absent/null
    - **Property 4: Speaker and Earphone Mapping Are Independent**
    - **Validates: Requirements 3.1, 3.2, 3.3**

- [ ] 2. Fix Bug #3 — Apply `division_filter` in `getEligibleUsers()` and `getNextEligibleDivision()`
  - [ ] 2.1 Extract `applyDivisionFilter()` private helper method in `GeneratePMScheduleService`
    - File: `app/Services/GeneratePMScheduleService.php`
    - Add private method `applyDivisionFilter(\Illuminate\Database\Eloquent\Builder $query, PMSchedule $schedule): void`
    - Copy the `$divisionMappings` array from `getQueueStatus()` into this helper
    - Apply `WHERE (office LIKE '%kw%' OR department LIKE '%kw%')` for each keyword
    - Method should be a no-op when `$schedule->division_filter` is null/empty
    - _Requirements: 2.3, 2.4_

  - [ ] 2.2 Call `applyDivisionFilter()` inside `getEligibleUsers()`
    - File: `app/Services/GeneratePMScheduleService.php`
    - Insert the call after the `asset_categories` filter and before `$assets = $query->get()`
    - Remove (or keep as comment) the existing note: `// NOTE: Division filter is NOT applied here`
    - _Requirements: 2.1, 2.2_

  - [ ] 2.3 Call `applyDivisionFilter()` inside `getNextEligibleDivision()`
    - File: `app/Services/GeneratePMScheduleService.php`
    - Same placement: after `asset_categories` filter, before `$assets = $query->get()`
    - This prevents cycle-advance from spilling into divisions outside the filter
    - _Requirements: 2.5_

  - [ ]* 2.4 Write unit test for `division_filter` enforcement
    - Test: schedule with `division_filter = 'RID'` → only users in RID-matching assets returned
    - Test: schedule with `division_filter = null` → all divisions included (no regression)
    - **Property 3: Division Filter Restricts Generated Users**
    - **Validates: Requirements 2.1, 2.2**

- [ ] 3. Checkpoint — Verify Bugs #7 and #3 in isolation
  - Ensure all tests pass, ask the user if questions arise.
  - Manually verify: generate PM for a schedule with `division_filter` set — confirm only that division gets work orders
  - Manually verify: check `preventive_maintenance` records for a user with both speaker and earphone assets — confirm both field sets are populated

- [ ] 4. Fix Bug #1 — Update `last_pm_date` for auto-generated PM completion
  - [ ] 4.1 Extract `resolveNextPmDate()` private helper in `MaintenanceController`
    - File: `app/Http/Controllers/MaintenanceController.php`
    - Add private method `resolveNextPmDate(RequestModel $trackingRequest): string`
    - Logic: if `pm_schedule_id` is set and schedule is active → `$schedule->calculateNextDate()`, else `now()->addMonths(3)->toDateString()`
    - _Requirements: 1.2, 1.3_

  - [ ] 4.2 Refactor the completion block in `MaintenanceController::update()`
    - File: `app/Http/Controllers/MaintenanceController.php`
    - Locate the block: `if ($newStatus === RequestModel::STATUS_COMPLETED && $trackingRequest->linked_asset_id)`
    - Change condition to: `if ($newStatus === RequestModel::STATUS_COMPLETED)`
    - Inside, add two branches:
      - **Branch A (manual PM):** `if ($trackingRequest->linked_asset_id)` — keep existing single-asset logic, replace inline `calculateNextDate()` call with `$this->resolveNextPmDate($trackingRequest)`
      - **Branch B (auto-generated PM):** `elseif ($trackingRequest->is_auto_generated && $trackingRequest->user_id)` — query all `InventoryAsset` where `assigned_to_user = user_id` AND `status = 'Active'`, update `last_pm_date` and `next_pm_due_date` on each
    - _Requirements: 1.1, 1.4, 1.5, 1.6_

  - [ ]* 4.3 Write unit test for auto-generated PM completion asset update
    - Test: completing an auto-generated PM updates `last_pm_date` on all active assets for the user
    - Test: completing a manual PM updates only the one linked asset (regression guard)
    - Test: completing an auto-generated PM with no active assets for the user does not throw
    - **Property 1: Auto-Generated PM Completion Updates All Active User Assets**
    - **Property 2: Manual PM Completion Path Is Unchanged**
    - **Validates: Requirements 1.1, 1.4, 1.5**

- [ ] 5. Final checkpoint — Full regression pass
  - Ensure all tests pass, ask the user if questions arise.
  - Verify complete PM cycle flow: Generate → Assign → Start → Complete → check `last_pm_date` updated on assets
  - Verify `checkAndAdvance()` still fires correctly after completion (it runs after `DB::commit()` and is unaffected by these changes)
  - Verify `division_filter = null` schedules still generate across all divisions normally

---

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster fix
- Bug #7 is the smallest change (2 lines split into 6) — do it first to build confidence
- Bug #3 is self-contained — the helper method keeps the fix DRY across two call sites
- Bug #1 is the most impactful — it touches the critical completion path inside a transaction
- All three changes are backward-compatible: null `division_filter`, manual PMs, and cycles without earphone assets all behave identically to today
- Column `earphone_pno` should be verified in DB before deploying Bug #7 fix — if missing, migration must run first
