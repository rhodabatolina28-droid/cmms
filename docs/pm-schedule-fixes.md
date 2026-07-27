# PM Schedule & UX Fixes — Implementation Log

## Overview
This document tracks all fixes and improvements made to the PM Schedule module and related UX issues. Each entry includes the problem, root cause, solution, and verification steps.

---

## Fix 1: Division Spelling (EMPLOYMENT → ENHANCEMENT)

### Problem
PM work orders for **WORKPLACE RELATIONS ENHANCEMENT DIVISION** were not appearing in the PM Schedules dashboard table. The header showed:
```
Work Orders — WORKPLACE RELATIONS AND EMPLOYMENT DIVISION
```
But no orders appeared because the division name in `pm_schedules.current_focus_division` was misspelled as **"WORKPLACE RELATIONS AND EMPLOYMENT DIVISION"** while the actual `requests.office` field contained the correct spelling **"WORKPLACE RELATIONS ENHANCEMENT DIVISION"**.

### Root Cause
The `inventory_assets` table had **36 assets** with the wrong spelling "EMPLOYMENT" and **13 assets** with the correct spelling "ENHANCEMENT". When PM was generated, the division name came from `$asset->office` — variants of the spelling caused mismatches.

### Files Modified
- **Database** — Direct UPDATE queries (no migration needed)

### SQL Executed
```sql
-- Fix inventory_assets (36 records)
UPDATE inventory_assets 
SET office = 'WORKPLACE RELATIONS ENHANCEMENT DIVISION' 
WHERE office = 'WORKPLACE RELATIONS AND EMPLOYMENT DIVISION';

-- Fix pm_schedules (1 record)
UPDATE pm_schedules 
SET current_focus_division = 'WORKPLACE RELATIONS ENHANCEMENT DIVISION' 
WHERE current_focus_division = 'WORKPLACE RELATIONS AND EMPLOYMENT DIVISION';
```

### Verification
- ✅ `inventory_assets` — 49 assets with correct spelling, 0 with wrong spelling
- ✅ `pm_schedules` — 1 record with correct spelling
- ✅ `requests` — 4 auto-generated PM records with correct spelling

---

## Fix 2: End User Printed Name Auto-Fill

### Problem
When opening a PM form (via PM Work Orders → View/Start), the **"Printed Name"** field under End User Signature was blank instead of auto-filling with the end user's full name.

### Root Cause
The `maintenanceFormViewData()` method in `MaintenanceController.php` was not passing the `$endUser` variable to the view. The form template (`form.blade.php` line 273) expected:
```php
value="{{ $maintenance->end_user_printed_name ?? ($endUser->full_name ?? '') }}"
```
But `$endUser` was never set, so it always fell back to empty string.

### Files Modified
- `app/Http/Controllers/MaintenanceController.php`

### Changes Made
1. **`maintenanceFormViewData()`** — Added `$endUser = User::find($requestorId)` and included `'endUser' => $endUser` in the view data array
2. **`create()`** — Added `'endUser' => $user` for new PM forms

### Verification
- ✅ When opening an existing PM ticket, the Printed Name field now auto-fills with the end user's full name
- ✅ When creating a new PM, the field auto-fills with the current user's name

---

## Fix 3: Disposal Notification — Supply Officer Only

### Problem
When IT or Super Admin flags an asset **"For Disposal"** during a PM, the notification was sent to **ALL 10 admin users** — including admins from CMD, FMD, WRED, OED, VAD, RID, COA — who have nothing to do with inventory/disposal management.

### Root Cause
The notification query in `MaintenanceController.php` was:
```php
$admins = \App\Models\User::where('role', 'admin')->get();
```
This returned all users with `role = 'admin'` regardless of their `can_supply` flag.

### Solution
Only **Supply Officers / Administrative Division Admins** (those with `can_supply = true`) should receive disposal notifications.

### Files Modified
- `app/Http/Controllers/MaintenanceController.php`

### Changes Made
```php
// BEFORE (wrong - notifies all admins)
$admins = \App\Models\User::where('role', 'admin')->get();

// AFTER (correct - only supply-capable users)
$admins = \App\Models\User::where('can_supply', true)
    ->where('is_active', true)
    ->get();
```

### Users Who Will Receive Disposal Notifications
| ID | Name | Role | Office | can_supply |
|----|------|------|-------|:----------:|
| 4 | Jazel E. Batolina | admin | ADMINISTRATIVE DIVISION | ✅ |
| 34 | Jazel Portes | admin | ADMINISTRATIVE DIVISION | ✅ |

### Email Notification
Both users will receive **in-app notification** AND **email notification** (because `Notification.php` only skips email for `super_admin` role).

### Note
ICT Request Controller already had the correct filter (`where('can_supply', true)`) — only PM was broken.

---

## Fix 4: Null Region for User Accounts

### Problem
The **User Management** page showed only **22 users** instead of **42 users** for the Super Admin.

### Root Cause
The `usersData()` method in `SuperAdminController.php` filters users by `region`:
```php
$baseQuery = User::query()
    ->when($actor->region, fn ($q) => $q->where('region', $actor->region))
    ->when($actor->branch, fn ($q) => $q->where('branch', $actor->branch));
```
The Super Admin has `region = 'NCR'`, but **20 users** had `region = NULL`. They were excluded from the query.

### SQL Executed
```sql
UPDATE users SET region = 'NCR' WHERE region IS NULL;
```

### Verification
- ✅ All 42 users now have `region = 'NCR'`
- ✅ User Management page shows all 42 users

---

## Fix 5: checkAndAdvance() — Prevent Early Division Advance

### Problem
When a division has **5 eligible users** but only **4 were generated** with PM requests, the `checkAndAdvance()` method would mark the division as complete and advance to the next division — leaving 1 user without a PM request.

### Root Cause
The original logic only checked:
1. Are there any **pending** (Scheduled/Ongoing/Awaiting Signature) requests? → If 0, proceed
2. Are there any **completed** requests? → If > 0, advance

It did **NOT** check if ALL eligible users (those with Active assets in that division) have been processed.

### Scenario
- WRED has 5 users with Active assets: IDs [19, 20, 21, 22, 23]
- Due to the division spelling issue (Fix 1), user 19 (Rica Manalo) had assets with the correct spelling while `current_focus_division` had the wrong spelling
- Only 4 users (20, 21, 22, 23) were generated
- All 4 were completed
- `checkAndAdvance()` saw 0 pending + 4 completed → **advanced prematurely**

### Files Modified
- `app/Services/GeneratePMScheduleService.php`

### Changes Made
Added a guard in `checkAndAdvance()` that compares total eligible users vs unique completed users:

```php
// Count eligible users (Active assets) in this division
$totalEligibleUsers = \App\Models\InventoryAsset::where('status', 'Active')
    ->whereNotNull('assigned_to_user')
    ->where('office', $focusDivision)
    ->distinct('assigned_to_user')
    ->count('assigned_to_user');

// Count unique users with Completed requests
$uniqueCompletedUsers = RequestModel::where('pm_schedule_id', $schedule->id)
    ->where('is_auto_generated', true)
    ->where('status', 'Completed')
    ->where('office', $focusDivision)
    ->when($activeCycle, fn($q) => $q->where('created_at', '>=', $activeCycle->started_at))
    ->distinct('user_id')
    ->count('user_id');

// If not all eligible users are completed, DON'T advance
if ($uniqueCompletedUsers < $totalEligibleUsers) {
    return [null, false];
}
```

### Verification
- ✅ If 5 eligible users exist but only 4 are completed → returns `[null, false]` (no advance)
- ✅ If all 5 eligible users are completed → advances normally
- ✅ Only counts users with **Active assets** (ignores users in the division who have no assets)

---

## Fix 6: Cleanup Test Data

### Problem
After testing, the database contained test PM and ICT records that needed to be removed for a clean testing environment.

### Deleted Records
| Table | Records Deleted |
|-------|:---------------:|
| `requests` (PM) | 25 |
| `requests` (ICT) | 7 |
| `preventive_maintenance` | 25 |
| `repair_requests` | 7 |
| `pm_cycles` | 1 |
| `pm_division_schedules` | 3 |
| `pm_schedule_history` | 4 |
| `notifications` (PM/ICT related) | 334 |
| `audit_logs` (Requests & Inventory) | 545 |
| `inventory_history` (PM/ICT/Disposal) | 189 |

### Preserved
- ✅ PM Schedule (PMS) — reset and ready for testing
- ✅ 705 notifications — non-PM/ICT related
- ✅ 148 audit logs — non-Requests/Inventory related
- ✅ 14 inventory history — non-PM/ICT/Disposal related

---

## Fix 7: PM Schedule Reset

### Problem
After fixing the division spelling, the existing PM cycle still had `current_focus_division` pointing to the old (wrong) division name, and the cycle was already advanced past WRED.

### SQL Executed
```sql
UPDATE pm_schedules 
SET current_focus_division = NULL, 
    current_cycle_id = NULL, 
    is_paused = false, 
    paused_at = NULL 
WHERE id = 1;
```

### What This Does
- Clears the current focus division so the next "Generate PM" starts fresh
- Clears the current cycle ID so a new cycle is created
- Unpauses the schedule

---

## Fix 8: User Count by Role (for Reference)

### Users with Active Assets
| Role | Count | Description |
|------|:-----:|-------------|
| user | 30 | End users across all divisions |
| admin | 10 | Division admins (including Supply Officers) |
| super_admin | 1 | Super Admin |
| it | 1 | IT Personnel |
| **Total** | **42** | |

### Note
All users with Active assets — including admins, super admin, and IT — are eligible for PM generation. The PM system generates requests based on asset assignment, not role.

---

## Pending Changes (To Be Implemented)

### 1. Assign Flow — No Auto-Ongoing
**Problem:** When Super Admin assigns IT (or self), the status automatically changes to "Ongoing" even though work hasn't started yet.

**Solution:**
1. `assignIt()` — Remove `$updates['status'] = STATUS_ONGOING`
2. After successful assign, redirect to PM Work Orders page
3. User clicks "Start" → becomes "Ongoing"

### 2. start() — Allow All Assigned Users
**Problem:** `start()` has `$user->role !== 'super_admin'` condition preventing Super Admin from starting PMs.

**Solution:** Change to check `assigned_to` instead of role:
```php
if ($status === SCHEDULED && (int)$trackingRequest->assigned_to === (int)$user->id) {
    $trackingRequest->update(['status' => ONGOING]);
}
```

### 3. IT PM Tasks — Assigned Only
**Problem:** IT user can see all unassigned PMs in their branch.

**Solution:** Remove `orWhereNull('assigned_to')` from `pmTasks()` query.

---

## Git Commits

| Commit | Message |
|--------|---------|
| `d32f3a4` | Fix: PM schedule division spelling, end user auto-fill, disposal notification, checkAndAdvance guard, null region |

## Branch: `develop`