# Requirements Document: PM Auto-Generation Bugfixes

## Introduction

Three confirmed bugs exist in the PM (Preventive Maintenance) module that affect data accuracy, division filtering behavior, and asset form field mapping. This document defines the exact requirements for fixing all three bugs without introducing regressions to the existing cycle management, division advance, and form generation logic.

---

## Glossary

- **Auto-generated PM**: A PM work order created by `GeneratePMScheduleService::generate()`, having `is_auto_generated = true`, `linked_asset_id = null`, and `asset_id = 0`.
- **Manual PM**: A PM work order created by an end user or IT personnel through the form UI, having a non-null `linked_asset_id`.
- **Bundled PM**: Synonym for auto-generated PM; one work order per user covering all assigned assets.
- **TrackingRequest**: A row in the `requests` table (`App\Models\Request`) that links to a `preventive_maintenance` row via `detail_id`.
- **InventoryAsset**: A row in `inventory_assets` representing one physical asset assigned to a user (`assigned_to_user`).
- **PMSchedule**: A schedule record in `pm_schedules` that drives generation frequency, division ordering, and cycle tracking.
- **division_filter**: A nullable string column on `pm_schedules` that the admin sets to restrict generation to one division.
- **mapUserAssetsToPMForm**: The private method in `GeneratePMScheduleService` that maps asset data onto PM form fields.
- **speakers_* / earphone_***: Separate field groups in `preventive_maintenance` for speaker and earphone assets.

---

## Requirements

### Requirement 1 — Bug #1: Update `last_pm_date` for Auto-Generated PMs on Completion

**User Story:** As an ICT Super Admin, I want `last_pm_date` and `next_pm_due_date` to be updated on every asset when an auto-generated PM is completed, so that the inventory accurately reflects when each asset was last serviced and when it is next due.

#### Acceptance Criteria

1. WHEN an auto-generated PM request transitions to `Completed` status, THE `MaintenanceController` SHALL update `last_pm_date = now()` and `next_pm_due_date = calculateNextDate()` on every `InventoryAsset` assigned to `$trackingRequest->user_id`.
2. WHEN an auto-generated PM request transitions to `Completed` status AND the associated `PMSchedule` record is active, THE `MaintenanceController` SHALL compute `next_pm_due_date` using `$schedule->calculateNextDate()`.
3. WHEN an auto-generated PM request transitions to `Completed` status AND no active `PMSchedule` is found, THE `MaintenanceController` SHALL set `next_pm_due_date = now()->addMonths(3)` as a safe fallback.
4. WHEN updating assets for an auto-generated PM, THE `MaintenanceController` SHALL only update assets whose `assigned_to_user` matches the request's `user_id` AND whose `status` is `Active`.
5. WHEN a manual PM (non-null `linked_asset_id`) transitions to `Completed`, THE `MaintenanceController` SHALL continue using the existing single-asset update path unchanged.
6. THE `MaintenanceController` SHALL preserve the existing transaction boundary so that asset date updates and status changes either both commit or both roll back.

---

### Requirement 2 — Bug #3: Apply `division_filter` During PM Generation

**User Story:** As an ICT Super Admin, I want the `division_filter` setting on a PM schedule to actually restrict which division gets PM work orders generated, so that the system only generates PMs for the configured division instead of all divisions.

#### Acceptance Criteria

1. WHEN `$schedule->division_filter` is not null or empty AND `GeneratePMScheduleService::getEligibleUsers()` is called, THE `GeneratePMScheduleService` SHALL restrict eligible users to those whose `InventoryAsset->office` or `InventoryAsset->department` matches the filter.
2. WHEN `$schedule->division_filter` is null or empty, THE `GeneratePMScheduleService` SHALL include all divisions in eligible user selection, preserving current behavior.
3. WHEN `division_filter` is applied in `getEligibleUsers()`, THE `GeneratePMScheduleService` SHALL use the same `$divisionMappings` keyword-expansion logic already present in `getQueueStatus()` to translate short codes (e.g., `'RID'`) into full division name patterns.
4. WHEN `division_filter` is applied, THE `GeneratePMScheduleService` SHALL apply the filter on the asset query using `WHERE (office LIKE '%keyword%' OR department LIKE '%keyword%')` for each expanded keyword.
5. WHEN `getNextEligibleDivision()` is called AND `$schedule->division_filter` is set, THE `GeneratePMScheduleService` SHALL apply the same division filter to the asset query inside `getNextEligibleDivision()` so that cycle advance does not spill into other divisions.

---

### Requirement 3 — Bug #7: Separate Speaker and Earphone Asset Mapping

**User Story:** As an ICT technician, I want PM forms generated for users who have both a speaker and earphones to correctly populate separate fields for each device, so that the PM form is accurate and neither asset is silently overwritten by the other.

#### Acceptance Criteria

1. WHEN a user's asset list contains an asset whose category contains `'earphone'`, THE `GeneratePMScheduleService` SHALL map that asset to `earphone_brand`, `earphone_model`, and `earphone_pno` fields instead of `speakers_brand`, `speakers_model`, and `speakers_pno`.
2. WHEN a user's asset list contains an asset whose category contains `'speaker'` (and not `'earphone'`), THE `GeneratePMScheduleService` SHALL continue mapping that asset to `speakers_brand`, `speakers_model`, and `speakers_pno`.
3. WHEN a user's asset list contains both a speaker and an earphone asset, THE `GeneratePMScheduleService` SHALL populate all six fields (`speakers_brand`, `speakers_model`, `speakers_pno`, `earphone_brand`, `earphone_model`, `earphone_pno`) independently without either overwriting the other.
4. THE `mapUserAssetsToPMForm` method SHALL split the existing `str_contains($cat, 'speaker') || str_contains($cat, 'earphone')` branch into two independent `elseif` branches — one for speaker, one for earphone.
5. WHEN `earphone_brand`, `earphone_model`, or `earphone_pno` are populated by the mapping, THE `PreventiveMaintenance` model SHALL accept and persist these values (all three fields already exist in `$fillable`).
