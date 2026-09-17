# Asset Downtime Tracking — Implementation & Overhaul Plan

> **Status: ✅ COMPLETE (Sept 8, 2026).** All four phases shipped test-first:
> `2a588e6` X1 (sign fix + bundled-PM loop fix + PM/ICT split) → `4c10a01` X3 (terminal-status window close + is_downtime) → `77537aa`/`8817c5c` X2 (`downtime:repair` cleanup — live data verified: 0 negative durations, 0 negative asset totals, DELL XPS8940 -17,303 → +17,535 ICT) → X4 (asset-profile ICT/PM breakdown line).
> **Locked decisions (Gov-Option-B, FINAL):** `total_downtime` = ICT/repair breakdown ONLY (SIRA); `total_pm_downtime` = PM servicing ONLY (Servicio — scheduled, NOT failure downtime; does NOT inflate the failure total). Bundled PM credits ALL of the user's assets. Credit follows ticket TYPE, not terminal status.

---

## 1. How Downtime Works Today (v1 — implemented)

### 1.1 Where the logic lives
NOT in controllers (the original plan assumed that). It is **inline in `Request.php::booted()`**
(model `updating` handler, ~lines 260-350), so EVERY status transition goes through it —
from any Action, controller, or queue job. There are **no mass updates** (`Request::where()->update()`)
anywhere in the codebase, so no path bypasses the model events. ✓

### 1.2 The rules (v1)
| Event | What happens |
|---|---|
| Status → `Ongoing` | `downtime_start = now()` (once; skipped if already set) |
| Status → `Completed` | `downtime_end = now()`; `downtime_duration = now()->diffInMinutes(downtime_start)`; asset `total_downtime += duration` |
| Anything else (Pending, Awaiting Parts, Cancelled…) | downtime untouched |

### 1.3 Where it is displayed
- **Asset Profile → Repair & Maintenance History** (`inventory/detail.blade.php` L648-697):
  - Summary line: `Total Downtime: {{ $asset->formatted_downtime }}` (from `InventoryAsset::formatted_downtime`)
  - Per-ticket badge inside every timeline item: `Downtime: X (start → end | Ongoing)` — works for BOTH PM and ICT tickets ✓ (keep as-is)
- Request model accessors: `formatted_downtime_duration` ("2d 4h 30m"), `is_downtime` (bool)

### 1.4 Schema
```sql
requests:          downtime_start TIMESTAMP NULL · downtime_end TIMESTAMP NULL · downtime_duration INT NULL (minutes)
inventory_assets:  total_downtime INT DEFAULT 0 (minutes, combined)
```


---

## 2. Discovered Bugs (verified against live data, Sept 2026)

### 🐛 B1 — Carbon 3 SIGN BUG (critical — the reason every duration is ≤ 0)
Carbon 3.11.4 (Laravel 13) made `diffInMinutes()` **signed**. Current code:
```php
// Request.php L340 — WRONG in Carbon 3:
$duration = now()->diffInMinutes($request->downtime_start);   // past argument → NEGATIVE
```
**Live-data proof (before fix):**
```
requests:      min_duration = -17,303 · max_duration = 0 · negative_count = 11 of 22
assets:        11 assets with NEGATIVE total_downtime (DELL XPS8940 = -17,303 min ≈ -12 days!)
```
`increment('total_downtime', -X)` = DECREMENT → asset totals went negative.

**Fix (X1):**
```php
$duration = (int) abs($request->downtime_start->diffInMinutes(now()));
// downtime_start->diffInMinutes(now()) → past→future = positive in Carbon 3
// abs() + (int) cast = version-proof against any Carbon behaviour change
```

### 🐛 B2 — Bundled-PM loop: only the FIRST asset ever gets credit
The downtime-completion block sits **inside** `foreach ($assetsToUpdate as $asset)`. On the first
iteration `downtime_end` gets written, so every later iteration is skipped by
`if ($request->downtime_start && !$request->downtime_end)`.

**Fix (X1):** move the window-close + duration computation **outside the loop** (compute once per ticket),
then credit **each asset** in `$assetsToUpdate` (locked decision: bundled PM = all N assets were genuinely
unavailable).


### ⚠️ G1 — Window never closes on Cancelled / Rejected / Referred-External
Only `Completed` closes the window. A ticket that goes Ongoing → Cancelled leaves an **open window**:
no `downtime_end`, no duration, no asset credit — permanent data loss for that outage.

**Fix (X3):** treat `Cancelled / Rejected / Referred - External` like Completed for window-closing
(close + compute + credit the correct bucket). Awaiting Parts / Awaiting Signature keep the window
open (asset is still down — correct).

### ⚠️ G2 — Pending time is not counted (accepted limitation — documented, NOT fixed)
Downtime starts at Ongoing. Days spent in Pending (user waiting, asset unusable) are not captured.
The deep review flags this; for now we keep "downtime = repair time" as the definition.
Revisit together with Phase D3 (SLA), where response-time metrics belong.

### ⚠️ G3 — `is_downtime` accessor inconsistency
`getIsDowntimeAttribute()` requires `status === 'Ongoing'`, so the "currently down" indicator hides
while status is `Awaiting Parts` even though the asset is still broken and the window is still open.

**Fix (X3):**
```php
public function getIsDowntimeAttribute(): bool
{
    return $this->downtime_start !== null && $this->downtime_end === null;
}
```

---

## 3. The New Logic (X1 — what we are building)

### 3.1 Design: Combined total + PM breakdown (locked decision)
```
inventory_assets:
├── total_downtime       (existing, stays) = COMBINED (ICT + PM)   → "Total Downtime" display
└── total_pm_downtime    (NEW migration)   = PM portion only       → breakdown

ICT/Repair downtime = DERIVED = total_downtime − total_pm_downtime   (no extra column)
```

### 3.2 Credit rules (per ticket, on window close)
```
PM ticket completed:
├── total_downtime      += duration     (still counts toward the total ✓)
└── total_pm_downtime   += duration     (own bucket ✓)

ICT / Repair ticket completed:
└── total_downtime      += duration     (total only)

Bundled auto-generated PM (covers ALL assets of the user):
└── EVERY asset in $assetsToUpdate gets the credit (locked decision — all were down)
```

### 3.3 New model code (`InventoryAsset.php`)
```php
// cast
'total_pm_downtime' => 'integer',

// accessors (appended)
public function getFormattedPmDowntimeAttribute(): string   // "1h 30m"
public function getFailureDowntimeAttribute(): string        // derived: total − PM → "1d 8h"
```

### 3.4 Migration
```php
// add_total_pm_downtime_to_inventory_assets
Schema::table('inventory_assets', function (Blueprint $table) {
    $table->integer('total_pm_downtime')->default(0)->after('total_downtime'); // minutes, PM-only
});
```

### 3.5 Display (X4) — `inventory/detail.blade.php` downtime-summary block
```
Total Downtime: 2d 4h 30m
📉 ICT/Repair: 1d 8h   ·   🔧 PM: 20h 30m
```
Per-ticket badges below stay as-is (already correct UI) — only their VALUES become positive.

### 3.6 How to read the numbers (the practical point)
```
Asset A:  ICT 40h · PM 2h   → 🔴 chronically failing — replacement candidate
Asset B:  ICT 2h  · PM 6h   → 🟢 healthy — only scheduled servicing
(v1 showed both as "42h combined" — indistinguishable)
```


---

## 4. Execution Phases (test-first per phase — no phase moves until green)

| Phase | Scope | Gate |
|---|---|---|
| **X1** | Sign fix + loop fix + bundled-PM all-assets credit + `total_pm_downtime` migration + accessors + split credit logic | Feature tests: PM ticket raises BOTH columns; ICT raises total only; bundled PM credits every asset; all durations positive |
| **X2** | **DB backup first** (`storage/ux_backup/cmms_pre_downtime_fix_YYYYMMDD.sql`) → artisan command: `abs()` all negative `downtime_duration` rows; recompute BOTH asset columns per asset from its tickets (sum by type, with abs) | Verification query: 0 negative durations; 0 negative asset totals; spot-check DELL XPS8940 |
| **X3** | G1 window-closing on Cancelled/Rejected/Referred (credit by type) + G3 `is_downtime` accessor | Tests: cancelled Ongoing ticket closes window + credits asset; Awaiting Parts keeps window open + is_downtime true |
| **X4** | Breakdown line in Repair & Maintenance History summary (Total + ICT/PM split) | Manual check on asset profile; syntax + full suite |

### X2 cleanup command sketch
```
php artisan downtime:repair
  1. UPDATE requests SET downtime_duration = ABS(downtime_duration) WHERE downtime_duration < 0;
  2. For each inventory_assets row:
       total_downtime    = SUM(ABS(duration)) of ALL closed downtime windows (any type)
       total_pm_downtime = SUM(ABS(duration)) of closed windows where type = 'Preventive Maintenance'
  3. Report before/after table (assets touched, minutes corrected)
```

---

## 5. Out of Scope (future phases — do NOT mix into X1-X4)
- **D3 SLA-lite — ⏸️ DEFERRED (Sept 11 2026 — desisyon ng may-ari, naka-justify sa live data):**
  P1-P4 priority triage ay HINDI idagdag. Rasyonale: (1) kumpleto na ang D4 high-official priority
  (officials-first + URGENT + queue jump + age chips) — sapat para sa tunay na daloy; (2) 14
  completed ICT lang sa buong kasaysayan, lahat maliliit — WALANG buong-opisina na outage event;
  (3) ang P1/P3 badge ay madoble lang sa URGENT badge. Kaya: WALANG priority column, WALANG
  setter dialog, WALANG P2/P4. **Babalikan lang kapag:** may naganap na buong-opisina na outage
  (kahit beses lang) · humingi ang pamamahala ng response/resolution metrics · lumagpas na sa
  ~50-100 ICT/buwan. Kapag bumalik: ICT-only, IT-side triage + parts-pause (ang Awaiting Parts/
  Awaiting Signature/Referred-External ay hindi binibilang laban sa IT — response = assigned_at,
  resolution = completed_at).
- **D2-e (service window) — ✅ DONE (Sept 9)** — isang Scheduled PM ay Overdue lampas sumanda sa >3 WORKING days (weekends excluded); no-skip naka-lock ng tests (tingnan ang D2.6)

### D2 — Ticket Aging — ✅ DONE (Sept 9 2026 · execution log sa D2.6, kabilang ang D2-e service window) <!-- ang "NEXT" ay nailipat na sa D3 -->

**Layunin:** palitan ang hardcoded 7-day Overdue rule (`ListPmTasksAction.php:54`) ng unified,
bucket-based aging na nakikita sa LAHAT ng ticket views — para mas actionable at pare-pareho.

#### D2.1 Aging buckets (locked design)
```
🟢 Fresh    0–24h        · 🟡 Aging     1–3d
🟠 Getting old  3–7d     · 🔴 Overdue   7d+
```
- Unified accessor sa `Request` model: `age_in_hours` + `aging_bucket` (color + label)
- Age basis: `created_at` para sa pending tickets; tuloy-tuloy hanggang completion

#### D2.2 Saan makikita (SCOPE — pare-pareho sa lahat ng views)
| View | Ano ang ipapakita | Notes |
|---|---|---|
| Maintenance Calendar | age bucket badge sa bawat event — **BOTH ICT at PM** | user request |
| ICT lists (IT/SA/Admin) | age bucket sa row | ✅ may usable pattern na |
| **PM Work Orders (SA + IT)** | **age column/indicator — bago ito, dagdag mula sa user** | `pm-schedules/orders.blade.php` + `ListPmWorkOrdersAction` |
| PM Tasks | bucket-based ang papalit sa `diffInDays(now()) > 7` | ang original target |

#### D2.3 PM service window (locked rules)
- **Window: 3 WORKING DAYS** — hindi binibilang ang **Saturday at Sunday**
- **NO-SKIP rule:** hindi lalaktaw ang cycle sa susunod na division hangga't hindi natatapos ang
  kasalukuyang division na PM — kailangan matapos bago mag-move (walang skip, walang carry-over
  na automatic na pagpasok sa susunod na division habang may bukas pa)
- Kung lumagpas sa window ang isang division: nananatili siyang focus division (🔴 Overdue) —
  ang `next_scheduled_at` hindi bumabago hangga't hindi tapos

#### D2.4 ⚠️ Related bug — Consent/auto-advance hindi kumpleto (ROOT CAUSE FOUND → Finding-1 gate FIXED)
> "nag run ako ng PM pero hanggang 2 divisions lang ako, sa iba hindi pa na-trigger"
- **Root cause (verified sa live data + tests):** `checkAndAdvance()` eligibility gate
  (L282-286) ay **hindi gumagamit ng parehong filters ng generation** — binibilang
  nito LAHAT ng Active-asset users sa division, samantalang ang generation ay may
  `asset_categories` filter + actor-branch scope. Kahit isang excluded user
  (hal. Printer asset habang Laptop-only ang schedule, o kapanahon sa ibang branch)
  ay sapat nang i-stall/permanently i-block ang division advance.
- **✅ FIXED (test-first):** ang gate ay gumagamit na ngayon ng parehong filters —
  `resolveActor()` branch scope + `asset_categories` whereIn — kasabay ng
  branch filter sa completed-count. Tests: `PMFlowTest::
  test_excluded_category_assets_do_not_block_division_advance` +
  `test_assets_in_other_branch_do_not_block_division_advance` (RED → GREEN, 17/17 suite pass).
- **Tandaan:** hindi ito ang "hanggang 2 divisions" — ang live data ay tama pala
  (COA/RID/FMD tapos na, VAD in-progress pa). Ang bug ay matutuloy lang sana
  kapag automatic na + may category/branch mismatch. D2.3 no-skip rule ay
  safe nang i-implement.

#### D2.5 Order of work (test-first per phase)
1. **D2-a:** `age_in_hours` + `aging_bucket` accessors sa `Request` + tests
2. **D2-b:** Calendar (ICT + PM events) badge + ICT lists badge
3. **D2-c:** PM Work Orders (SA + IT) age column
4. **D2-d:** Palitan ang PM Tasks 7-day rule ng buckets
5. **D2-e:** Service window (3 working days, no weekends) + no-skip enforcement — **kasunod ng
   pag-ayos sa D2.4 advance bug**

#### D2.6 EXECUTION LOG — ✅ D2 DONE (Sept 9 2026)
Test-first lahat; full suite green pagkatapos ng bawat phase.

| Phase | Scope | Commit | Tests |
|---|---|---|---|
| **D2-a** | `age_in_minutes` + `aging_bucket` + `age_display` + `is_aging_overdue` + `should_show_age` accessors (`Request`), Carbon3-proof `max(0)` clamp, exact boundaries 1440/4320/10080 | `5050937` | `TicketAgingTest` 5/5 |
| **D2-b/F6** | PM Tasks overdue → `is_aging_overdue` accessor (isang source-of-truth, tanggalin ang duplicate `diffInDays > 7` rule sa action + blade); bucket-colored age chip sa ilalim ng request # | `dd8d178` | PMFlow regression green |
| **D2-c** | Age chips sa LAHAT ng ticket lists (ICT/Admin/PM main/SA Master via JSON) + **F1 fix** (Master List user eager-load — naayos ang URGENT badge) + **F2 fix** (ICT list status-filter column index) | `b8d3267` | SupplyQueue/HighOfficial regression |
| **D2-d** | Calendar aging — ICT + PM grouped events may `age_bucket`/`age_display`, day-cell border tint, detail-card badges, per-ticket ages sa PM tickets | `362d7bc` | `PMCalendarTest` + `TicketAgingTest` 31/31 |
| **D2-fix** | Calendar Overdue counter + URGENT badge + unfinished-first (mga follow-up) | `ba4a009` `f7b00f3` `68fedeb` `bc21a6d` `9b8ef54` | `UnfinishedFirstTest` 10/10 · full suite **297/297** |

**Mga follow-up fixes (post-D2-d, user-requested):**
1. **URGENT badge gating** (`ba4a009`) — bagong `is_active_ticket` shared accessor (PENDING/ONGOING/
   SCHEDULED/AWAITING-*/REFERRED) ang nagpapagana sa BAWANG `should_show_age` at bagong
   `is_urgent_visible`. Ang URGENT badge ay hindi na lumalabas sa Completed/Cancelled/Rejected
   (history ≠ alarm) — 4 na view locations; `HighOfficialQueueTest` +1 test, 20/20 green.
2. **Calendar Overdue counter kasama na ang PM at ICT** (`f7b00f3`) — noon: PM schedule-level
   Overdue/FAILED rows lang ang binibilang, kaya ang 7d+ na aktibong ICT ticket ay hindi
   napapansin. Ngayon: `status = 'Overdue'` **O** `age_bucket = 'red'` (7d+ aktibong ticket,
   both types). JS `recomputeSummaryFromEvents` mirror din. `PMCalendarTest` +1 test.
3. **Unfinished-first ordering** (`68fedeb`) — bagong `Request::scopeUnfinishedFirst()`:
   Pending/Ongoing/Scheduled/Awaiting/Referred **umaangat** sa taas, Completed/Cancelled/Rejected
   **bumababa** sa dulo (hindi na matabunan ang naiwang trabaho ng bagong-tapos na). Naka-apply sa:
   `ListIctRequestsAction` (lahat ng role branches) · `GetRequestsDataAction` (Master List) ·
   `AdminDashboardAction` (Division Recent) · `SuperAdminDashboardAction` (Recent Office Requests).
4. **URGENT leads the unfinished group** (`bc21a6d`) — dinagdag `officialsFirst()` pagkatapos ng
   `unfinishedFirst()` sa Master List + SA/Admin dashboard Recents (nasa ICT lists na simula noon),
   kaya ang high-official ticket ay nananatiling priority sa loob ng aktibong group. Final order:
   ⚡URGENT-unfinished → regular-unfinished → terminal. Fixes: qualified `requests.*` columns
   (ambiguous-id bug sa join), clone ang Admin widget query (only_full_group_by collision).
5. **Calendar + Work Orders: age hidden sa terminal tickets** (`bc21a6d`) — ang PM per-ticket
   `age_bucket`/`age_display` sa calendar ay hindi noon na-gated ng `should_show_age` (may "8d 4h"
   kahit Completed). Ngayon null na (F5 rule). `PMCalendarTest::test_completed_pm_ticket_hides_age...`
   +1 test. Sabay dinagdag ang age fields sa `GetOrdersDataAction` (PM Work Orders payload).
6. **PM Work Orders age badge = ICT pattern** (`9b8ef54`) — sa una ay ginawa kong hiwalay na
   "Age" column sa `orders.blade.php`, pero consistent pala ang ilalagay sa **ilalim ng request #**
   (gaya ng ICT lists) — tanggalin ang extra column, badge → sariling cell under the order number.

**D2-e — PM Service Window — ✅ DONE (Sept 9 2026)**
- **Rule:** a Scheduled PM is **Overdue** once it has sat for MORE than 3 WORKING days
  (Mon-Fri; **weekends excluded**). `Request::PM_SERVICE_WINDOW_WORKING_DAYS = 3`.
- `Request::workingDaysBetween(from, to)` — count of working days after `from` up to and
  including `to` (Carbon `isWeekend()` skip; loop capped 3660 as failsafe).
- `is_aging_overdue` redefined: `Scheduled && workingDaysBetween(created_at, now) > 3`
  (replaces the old 7-calendar-day red-bucket rule — the 🎨 age-chip buckets stay as-is,
  they are display-only; the OVERDUE flag is the service-window alarm).
- **PM Work Orders:** `GetOrdersDataAction` now returns `overdue` (server-computed);
  `orders.blade.php` client-side `diffDays > 7` rule REMOVED — row highlight/count can
  never disagree with the badge.
- **No-skip stays locked:** `checkAndAdvance()` blocks on ANY unfinished request, so an
  overdue division keeps focus until its PMs are done (`PMFlowTest::
  test_overdue_unfinished_division_does_not_advance`).
- Tests: `TicketAgingTest::test_is_aging_overdue_uses_working_day_window` +
  `test_working_days_between_skips_weekends` + `PMFlowTest::test_overdue_unfinished_division_does_not_advance`
  + `UnfinishedFirstTest::test_pm_work_orders_carry_overdue_flag` — full suite green.

### D4 — High-Official Immediate Priority (ICT) — ✅ COMPLETE (D4a + D4c + D4b done · D4d backfill: user data entry)

**Note:** D5 (Private disk), D6 (Auto-archive PDFs), D7 (PR/Count archives) — **✅ TAPOS NA** (Sept 7-8,
commits nasa D5.8/D6.6/D7.9 execution logs sa ibaba), at **D2 Ticket Aging — ✅ TAPOS NA** (Sept 9, log sa
D2.6). Ang natitirang feature work: **D2-e (service window) → D3**. **Sa unahan ng lahat: DAILY**
— ang D3 ay nangangailangan ng priority values na wala pa sa system (tingnan ang D3 note sa Section 5).

**Rule:** kapag nag-file ng ICT request ang high official (Director, ED, OIC), ang ticket niya ay
**una sa IT queue** kahit huli siyang nagpasa — "immediate" ang treatment.

#### D4.1 Detection — position keyword matching (locked decision: NO new column)
```
users.position (EXISTING column) → keyword match (case-insensitive) → HIGH OFFICIAL
```
**DB reality check (Sept 2026):**
- `role` column = SYSTEM role lang (user=44, admin=10, super_admin=2, it=2) — ang Director ay `user` lang, walang rank info
- `position` column: **54 sa 58 users ang null/empty (93%)** — 4 lang ang may laman
- Kaya: **position backfill ang susi** — ibibigay ng user ang official list (pangalan + posisyon), i-fi-fill sa User Management

#### D4.2 Keyword config (`config/priority.php` — ✅ IMPLEMENTED, commit 202197e)
```php
// ACTUAL (lock decision: standardized short keywords — case-insensitive substring):
'high_official_keywords' => [
    'executive director',   // OIC-ED IV, OIC Deputy ED IV, Deputy ED IV
    'director ii',          // Director II, Technical / Internal Services
    'chief',                // Chief / OIC Chief of the six divisions
    'state auditor',        // State Auditor III (COA)
],
```
**Guardrail 1 — full-phrase matching, hindi generic words:** "Director's Secretary" at
"Programmer" ay ligtas (test-verified). Live-data check (Sept 2026): 4 users na may position
(COMPUTER PROGRAMMER I, IT Manager, ADMINISTRATOR IV, ADMIN) — lahat regular, 0 false positives.

#### D4.3 Helper (User model — ✅ IMPLEMENTED)
`getIsHighOfficialAttribute()` (str_contains-based, null-safe) + bonus `scopeHighOfficials()`
para sa D4b queue-jump query. Tests: `HighOfficialTest` — 12 official titles ✓, 6 regular/empty ✓.

#### D4.4 Queue-jump — ✅ DONE (commit pending D4b)
**implemented:** `Request::scopeOfficialsFirst()` — LEFT JOIN sa users + lead CASE
(official → 0, regular → 1) gamit ang keywords mula sa config; `select(requests.*)` para
hindi ma-clobber ang attributes. Naka-apply sa:
- `ItDashboardAction` — assigned widget (bago ang status CASE ordering)
- `ListIctRequestsAction` — lahat ng role branches (it/admin/super_admin/fallback)

#### D4.5 UI badge — ✅ DONE (red URGENT chip, user-approved design)
Text na **"URGENT"** (uppercase, 10px bold, letter-spacing) sa soft-red chip
(`#fef2f2` bg · `#b91c1c` text · `#fecaca` border) — walang icon, walang dot.
Naka-apply sa 4 na views: IT Dashboard widget · ICT list (requests/index) ·
Division Admin list (admin/requests) · SuperAdmin list (JS-driven).

#### D4.6 🚨 Guardrail 2 — self-service position editing — ✅ DONE (commit 202197e)
Position field sa self-service Profile ay **read-only** (disabled input) at **hindi na kinukuha**
ng `ProfileController::update()`. Test-verified: user na nag-attempt mag-self-inflate
(`Programmer I` → `OIC-Executive Director IV`) ay nanatiling `Programmer I`.
Position ay i-e-edit **lang** sa User Management / Personnel Management.

#### D4.7 Position input + backfill — ✅ D4c DONE (commit d23d37c)
Imbis na libreng listahan mula sa user, ang position ay naging **dropdown sa Create/Edit System
Account** na may cascade: Department → Division → Position (options mula sa
`config/priority.php::position_catalog`):
- **Always visible:** OIC-Executive Director IV · OIC Deputy ED IV · Deputy ED IV
- **Per Department:** Director II, Technical Services / Director II, Internal Services
- **Per Division:** `Chief, {CODE}` + `OIC, Chief, {CODE}` (RID/CMD/VAD/WRED/AD/FMD) · `State Auditor III` (COA)
- **"— None / Not set —"** default (hindi alam ang position = still creatable, settable anytime)
- **"— Other / Not Listed —"** → free text para sa mga regular positions (hal. Computer Programmer I)
  — hindi tumutugma sa keywords, kaya hindi official
- **Edit modal prefill:** hindi nasa catalog ang stored position → auto-"Other" + pre-filled
  (walang nawawalang data); `get_user` endpoint ngayon ay nagbabalik ng `position`
- Bug fixed sa rollout: ang cascade filter ay tumatakbo na kahit walang nakaselect pa (ang early
  return sana ay nag-stuck sa executive-only options)

**Natitirang backfill step (manual data entry, walang code):** i-edit ang bawat official account
sa User Management gamit ang bagong dropdown.

#### D4.8 Execution phases — status
| Phase | Scope | Status |
|---|---|---|
| **D4a** | `config/priority.php` + `is_high_official` accessor + position read-only sa Profile | ✅ commit 202197e — `HighOfficialTest` 4/4 |
| **D4c** | Position dropdown sa Create/Edit System Account (cascade + None/Other fallback + prefill) | ✅ commit d23d37c — `PositionDropdownTest` 5/5 |
| **D4b** | Queue-jump ordering (ItDashboardAction + ListIctRequestsAction) + red URGENT badge sa 4 views | ✅ commit 99e0e65 — `HighOfficialQueueTest` 3/3 + 28 regression green |
| **D4b+** | URGENT badge gated sa active statuses (`is_urgent_visible`) · URGENT leads unfinished group sa Master List + SA/Admin Recents | ✅ `ba4a009` `bc21a6d` — `HighOfficialQueueTest` 4/4 · `UnfinishedFirstTest` 10/10 |
| **D4d** | Backfill ng positions (manual, gamit ang D4c dropdowns) | ⏳ user data entry — gate: `Officials total` > 0 sa live tinker check |

---

## 5a. D5 — Storage Reorganization + CSM Auto-PDF — DESIGNED, awaiting execution

> **Konteksto:** lahat ng uploads ay dapat may kanya-kanyang organized storage, at ang CSM survey ay
> dapat may awtomatikong naka-save na PDF copy pagkatapos ma-submit (walang download button,
> walang manual na aksyon — awtomatikong mai-imbak). Ang mga signature files ngayon ay **test data
> lamang** — kaya walang maselang migration na kailangan; matatayo natin nang tama ang storage
> mula sa simula.

### D5.0 Storage audit findings (Sept 2026 — naberipika sa disk at DB)
| Upload type | Kasalukuyang lokasyon | Estado |
|---|---|---|
| Signatures (bago, base64→PNG) | `storage/app/public/signatures/` | ❌ **1,085 files, FLAT** + **PUBLIC disk** (may direktang URL!) |
| Signatures (legacy) | `public/signatures/` | ❌ 223 files, deretso sa public, hindi dumadaan sa Storage |
| Asset attachments | `storage/app/public/asset-attachments/{assetId}/` | ✅ organized, pero **public disk** (sensitibong docs!) |
| PR attachments (mga resibo/proof!) | `storage/app/public/pr-attachments/{prId}/` | ✅ organized, pero **public disk** (sensitibong financial!) |
| `public/csm/` | **HINDI uploads pala** — mga static na UI asset (csm_survey.css/js, banner PNG) | housekeeping lang |
| `csm_surveys` table | **WALANG file columns** — mga sagot lang (cc1-3, sqd1-9, suggestions) | walang nakaimbak na kopya ng form |

**Bakit mali ang public disk:** ang `/storage/signatures/technician_Juan_123.png` ay direktang
maa-access ng kahit sinong may URL — **walang auth check**. Ang mga pirma at resibo ay
personal/sensitibong data.

### D5.1 Target architecture: PRIVATE disk + authed serving (naka-lock na desisyon)
```
PRIVATE DISK (storage/app/private — 'local' disk, mayroon na: root = storage/app/private)
── WALANG direktang URL, walang /storage/ leak

📄 Mga PDF COPY (naka-organisa ayon sa BUWAN-TAON — "filing cabinet" ayon sa buwan):
├── csm-copies/{year}/{MonthName}/CSM-{requestNumber}.pdf
├── ict-pdfs/{year}/{MonthName}/ICT-{requestNumber}.pdf
└── pm-pdfs/{year}/{MonthName}/PM-{requestNumber}.pdf
    Halimbawa: csm-copies/2026/September/CSM-REQ-NCR-RCMB-2026-0005.pdf

🏷️ Mga ticket-scoped file (kada ID — mabilis mahanap ayon sa ticket):
├── signatures/{requestId}/...          ← mga pirma (sensitibo)
├── asset-attachments/{assetId}/...     ← mga asset docs/photos
└── pr-attachments/{prId}/...           ← mga resibo/proof of purchase

PUBLIC DISK: mga static na UI asset na lang (csm_banner.png, logo, css/js)

PAGKAKITA (halimbawa — pirma):
  <img src="/tickets/{id}/signature/technician">
      → SignatureController (auth middleware)
          ├─ naka-login? ✓
          ├─ pwede bang makita ang ticket na ito? (parehong policy sa ticket view)
          └─ ✓ → stream inline (hindi bilang hiwalay na download) · ✗ → 403
```
**Bonus:** mawawala ang DomPDF whitelist problem (`$allowed = realpath(...signatures)`) — ang PDF
generation ay magbabasa nang direkta mula sa private path (`Storage::path()`), walang URL, walang whitelist.

### D5.1a ★ PRIORITY: i-fix muna ang public-disk exposure (D5b/D5c) bago ang mga bagong PDF feature
Ang mga signature/resibo na nakatira sa public disk ay **live security exposure ngayon** (direct URL,
walang auth). Ang private-disk switch (D5b) + test-file cleanup (D5c) ay **unahin** — ang mga PDF
auto-copy features (D5a/D6) ay masusunod. Ang mga test signature files (1,085 + 223) ay hindi tunay
na data, kaya walang maselang migration: archive/clean lang.

### D5.1b ★ RECORD-DATE RULE (deepview refinement — pumipigil sa wrong-folder bug)
Ang folder month ay dapat galing sa **petsa ng RECORD**, hindi sa `now()` ng generation time:
| Type | Galing ng month/year folder |
|---|---|
| CSM | `csm_surveys.created_at` (petsa ng pagsusumite) |
| ICT / PM | `requests.completed_at` (petsa ng pagkumpleto — may column na) |

**Bakit:** kung pumalya ang PDF generation noong Sept 30 at na-retry sa Oct 3, dapat **September**
pa rin ang folder (September ticket iyon!). Bonus: ang retry ay nagiging **idempotent** — parehong
path = overwrite, walang mga duplicate PDF.

**Naberipika na ligtas:** `app.timezone = Asia/Manila` (live check: now() = PST) — folder month ay
palaging tamang PH month, walang UTC shift bug. Ang mga request number ay filename-safe
(`PM-NCR-RCMB-2026-0027` — mga gitling lang). `Storage::put()` ay awtomatikong gumagawa ng nested dirs.


### D5.2 CSM auto-PDF (walang download button — awtomatikong naka-imbak)
```
End user [I-submit] ang CSM survey
  └─ StoreCsmSurveyAction:
     1. DB::transaction (mga sagot + lockForUpdate) — WALANG binago ✓
     2. PAGKATAPOS NG COMMIT (hindi sa loob!): subukang gawin ang PDF
        └─ Pdf::loadView('pdf.csm-form', mga sagot + respondent + request number)
           → i-save sa: csm-copies/{year}/{MonthName}/CSM-{requestNumber}.pdf (PRIVATE disk; month/year = record date, see D5.1b)
        └─ I-update ang csm_surveys.pdf_path (BAGONG nullable column)
     3. try-catch: KUNG PUMALYA ANG PDF → naka-save pa rin ang mga sagot, null ang pdf_path,
        may log + retry command — HINDI KAILANMAN hahadakan ng PDF ang pagsusumite
```
**Bakit pagkatapos ng commit:** ang `RequirePendingSurvey` middleware ay nag-bl-block sa buong
app kapag may pending survey — kung pumalya ang PDF sa loob ng transaction, **makakulong ang user
sa survey loop**. Non-blocking = ligtas. (FINDING 1 ng deepview)

**Pag-view:** `[👁 Tingnan ang Kopya]` — authed route (`csm.pdf.show`) → inline PDF. Mga lugar:
user dashboard (sariling mga completed ticket) + CSM records view (Super Admin).

### D5.3 Signature private switch
1. `RequestHelpers::saveSignature()` → `Storage::disk('private')` + path `signatures/{requestId}/...`
2. **BAGONG authed route:** `GET /tickets/{request}/signature/{field}` → `SignatureController@show`
   (papalit sa lahat ng `/storage/{{ path }}` na `<img>` srcs — ict-form sections L203/280/338,
   ict form L324, maintenance sections L38/L15)
3. `pdf/ict-form.blade.php` L203 + `pdf/maintenance-form.blade.php` L86 whitelist → **tanggalin**;
   palitan ng direktang `Storage::disk('private')->get()` read sa PDF action (ipasa ang bytes/image data sa view)
4. Mga legacy test files (1,085 + 223) → **i-archive/i-delete** (test data lang — walang reconciliation)
5. Mga test DB signature paths (3+ PM rows) → i-reset o iwanan (mga test ticket naman)

### D5.4 Attachments → private (mga resibo = sensitibo)
- `UploadAssetAttachmentAction` + `UploadPrAttachmentAction` → private disk, parehong folder scheme
- Bagong authed attachment serving routes (papalit sa mga `/storage/` URL sa asset detail + PR pages)
- `PurchaseRequestController::download()` (L391) → palitan ang disk sa 'private' (may policy check na)

### D5.5 CSM static assets (housekeeping)
- `public/csm/` → mananatiling static (hindi uploads) — pero ilalipat sa `public/images/csm/` at
  aayusin ang 5 `asset()` reference sa `csm/form.blade.php` (L12-14, L27) para malinis ang root

### D5.6 Configuration (gDrive-ready)
```php
// config/cmms.php (BAGO)
'csm_copy_disk' => env('CSM_COPY_DISK', 'local'),   // o 'private' — lokal ngayon
// BALANG-ARAW GDRIVE: magdagdag lang ng 'gdrive' disk sa filesystems.php
// + palitan ang env value — WALANG code change sa Action (naka-abstract sa Storage API)
```

### D5.7 Execution phases

> **★ ORDER LOCKED (user decision):** **D5b + D5c FIRST** — the public-disk exposure is a live
> security issue (direct URLs, no auth). Fix private switch + cleanup BEFORE building new PDF features.
>
> (Superseded note: the earlier month/year UI-filter plan was DROPPED — month-year folder
> organization in storage replaces it, per user decision.)

| Phase | Saklaw | Gate |
|---|---|---|
| **D5a** | `pdf_path` column + post-commit auto-PDF + `pdf/csm-form` view + authed View Copy route | Test: submission → may PDF sa tamang month folder (record-date rule, D5.1b); failed gen → naka-save pa rin ang survey; nagbubukas nang inline ang view route |
| **D5b** | `saveSignature` → private + `{requestId}` scheme + authed signature route + blade src updates + tanggalin ang DomPDF whitelist | Test: bagong pirma ay nasa private path; hindi na gumagana ang lumang `/storage/signatures/...` URL; ICT/PM PDF ay may pirma pa rin |
| **D5c** | Test-file cleanup (1,085 + 223 archive) + mga attachment sa private + authed attachment routes + CSM static asset relocation | Beripikahin: walang natirang sensitibong file sa public disk; gumagana ang lahat ng view flows |
| **D5d** | Backfill: gumawa ng PDF copy para sa 3 lumang CSM survey | Tinker verify: lahat ng survey ay may pdf_path |

### D5.8 EXECUTION LOG (naudagawa — tapos na ang D5b + D5c)

> **D5b — ✅ COMPLETE (`264156b`, Sept 7, 2026)** — Signatures → PRIVATE disk + authed serving
> **D5c — ✅ COMPLETE (`c10c9f1`, Sept 7, 2026)** — Attachments → PRIVATE + test-file archive

#### D5b implementation details
| # | Gawain | Files |
|---|---|---|
| 1 | `RequestHelpers::saveSignature()` → `Storage::disk('local')` + month-year path `signatures/{year}/{MonthName}/...` | `app/Support/RequestHelpers.php` |
| 2 | Lahat ng 7 action files na nag-delete ng signature files → `'local'` (public→private) | `app/Actions/ICT/*` (5), `app/Actions/Maintenance/*` (2) |
| 3 | **Bagong authed route** `GET /tickets/{ticket}/signature/{field}` → `SignatureController@show` — may role middleware (`user,it,admin,supply_officer,super_admin`) + ticket policy check (`viewIct`/`viewMaintenance`) + field whitelist (404 kung mali) + path-traversal guard (`str_starts_with('signatures/')`) + `Storage::disk('local')->get()` | `app/Http/Controllers/Tickets/SignatureController.php` (NEW) + `routes/web.php` |
| 4 | 6 na blade img locations → `route('tickets.signature.show', ...)` | `partials/ict/_ict_form_sections.blade.php` (3), `requests/ict/form.blade.php` (1), `partials/maintenance/_technician_section.blade.php` (1), `_end_user_section.blade.php` (1) |
| 5 | DomPDF whitelist → `app/private/` (na-direct na) | `resources/views/pdf/ict-form.blade.php` L201-203, `pdf/maintenance-form.blade.php` L84-86 |
| 6 | **🚨 MAJOR DISCOVERY + FIX:** ang Laravel `local` disk ay may `'serve' => true` — nagrerehistro ng **unauthenticated `GET|PUT /storage/{path}` routes** (na-serve ang private files WITHOUT auth, at pwede pang mag-upload!). → **`serve => false`** | `config/filesystems.php` |

**Test gate D5b:** `SignatureAccessTest` 6 passed (10 assertions) — private write + month folder, non-image → null, owner 200, stranger 403, invalid field 404, **`storage.local` route WALA na**.

#### D5c implementation details
| # | Gawain | Files |
|---|---|---|
| 1 | Lahat ng attachment disk → **`'local'`** (private ang default na) | `UploadAssetAttachmentAction`, `UploadPrAttachmentAction` (store), `DeleteAssetAttachmentAction`, `DownloadAssetAttachmentAction`, `PurchaseRequestController` L391/L411, `models/AssetAttachment.php` (accessor) |
| 2 | **Data migration:** asset-attachments (9) + pr-attachments (7) = 16 files COPIED public→private (naka-verify bago tanggalin ang originals) | storage |
| 3 | **Test-file archive** → `storage/d5c_archive_20260907/` | 1,085 public `signatures/` + 223 legacy `public/signatures/` = **1,308 files**, inalis ang originals |
| 4 | Final: **public disk = `.gitignore` LANG** (1 file); private disk = 133 files | verified |

**Test gate D5c:** SignatureAccessTest 6 ✓ · RequisitionTicketContext 33 ✓ · SupplyQueueSearchTest 20 ✓

**⚠️ Tandaan (Option A na-inatanggap):** ang 20 lumang PM records na may old `signatures/maint_tech_...` flat paths ay **hindi na magpapakita ng pirma** (files na naka-archive). Test data lang sila. Lahat ng bagong pirma mula ngayon = 100% private + authed.

**✅ Ang binago para sa ADMIN/USER experience:** WALANG observable na pagbabago sa forms/PDFs ng mga bagong tickets — ang nagbago lang ay ang pinto (direct URL wala na; lahat dumadaan sa authed route na may parehong ticket access policy). Ang end-user/admin na may access sa ticket → nakikita pa rin nang pareho ang pirma.

#### D5a implementation details (COMPLETE — `8757695`, `4dcf91a`, Sept 7, 2026)
| # | Gawain | Files |
|---|---|---|
| 1 | Migration: `csm_surveys.pdf_path` (nullable) | `2026_09_07_000001_add_pdf_path_to_csm_surveys.php` |
| 2 | **PDF view** — faithful 1:1 replica ng web CSM form: NCMB logo sa gilid (SVG base64), THANK YOU + description, **walang consent notice** (system-only), horizontal profile table, CC1-3 na may SVG ✓, SQD table na may **face icons** (base64 PNG: strongly-disagree → strongly-agree) + N/A column, suggestions bilang **fill-lines** (hindi box), **walang END OF FORM footer**, walang `*` asterisks, **1 A4 page** | `resources/views/pdf/csm-form.blade.php` |
| 3 | **Auto-PDF sa submit** — POST-COMMIT (non-blocking try-catch), private disk, month-year folders, record-date rule (`created_at`), filename `CSM-{requestNumber}.pdf` | `app/Actions/Csm/StoreCsmSurveyAction.php` |
| 4 | **Email** — nullable column + validation (`nullable|email`) + fillable + PDF display | migration `000002`, `StoreCsmSurveyRequest`, `CsmSurvey` model, PDF view |
| 5 | **SVG checkmarks** — ang Arial core font ay WALANG ✓ glyph sa DomPDF (kaya blank ang mga marka) → ginawa silang **inline SVG images** (base64, pareho ng teknik ng NCMB logo). TEXT ay mananatiling **Arial** | PDF view (`$checkSvg`, `.cb-img`, `.chk-img`) |
| 6 | **CC1 strict-comparison bug** — `'2' === 2` (int key) → LAGING FALSE. Fix: `(string)$val` cast (pareho ng CC2/CC3) | PDF view |
| 7 | **`&nbsp;` literal** — ang Blade `{{ }}` ay nag-e-escape ng `'&nbsp;'` fallback → literal text. Fix: empty string (fill-line ay may border pa rin) | PDF view |
| 8 | **Profile layout** — walang `*` asterisks; Office + Service Availed = **full-width rows** (hindi na-compress ang mahabang division names); Client Type = "Government" (internal-use default, sadya) | PDF view |
| 9 | INSTRUCTIONS blocks (CC + SQD) — **ibalik** (mahalaga, per user) | PDF view |

**Test gate D5a:** `CsmArchivePdfTest` 2 passed (6 assertions) — submission → PDF sa tamang month folder (private, record-date rule); failed gen → naka-save pa rin ang survey. Render verified: **1 A4 page**, 10 embedded images (logo + 5 face icons + SVG checkmarks), email + CC1 ✓ + walang `&nbsp;` literal.

**⚠️ Tandaan:** ang mga **stored PDFs** na ginawa bago ang mga fixes na ito ay may lumang render (walang email/✓/atbp.). Ang mga **bagong submissions** ay gagawa ng bagong render nang tama. Para sa mga lumang survey, i-backfill gamit ang D5d.
---

## 5b. D6 — Auto-Archived PDFs (ICT + PM) — ✅ EXECUTED & COMMITTED (`0b791b8`)

**Rule:** kapag **na-Complete** ang ICT o PM ticket, awtomatikong gagawa ng FINAL archived PDF
(kumpleto: mga pirma, diagnosis, action taken, mga petsa) — naka-imbak sa private disk, naka-organisa
ayon sa buwan-taon. Walang manual na aksyon — awtomatikong mai-imbak, kagaya ng CSM.

### D6.1 Bakit completion-only (hindi bawat pag-update)
Ang ICT/PM form ay **nagbabago habang buhay ng ticket** (mga update ng technician, mga pirma, repair
recommendation). Kung bawat pag-update ay may sariling PDF → daan-daang duplicate na bersyon, walang
"opisyal" na kopya. **Isang final archived PDF kada ticket** = malinis na opisyal na record. Ang
on-demand na Download PDF button ang bahala sa mga interim state (mayroon na, walang pagbabago).

### D6.2 Trigger point (isang lugar lang — sakop ang lahat ng completion paths)
```
Request.php::booted() status sync — doon na nangyayari ang:
  ✓ downtime close (X1)   ✓ asset restore   ✓ CSM gating
  + BAGO: auto-archive PDF (post-commit, try-catch, non-blocking — pareho ng pattern ng D5a)
```

### D6.3 Storage + DB (month-year folders, record-date rule)
```
ict-pdfs/{year}/{Month}/ICT-{requestNumber}.pdf   ← ang buwan ay galing sa requests.completed_at (D5.1b)
pm-pdfs/{year}/{Month}/PM-{requestNumber}.pdf
DB: requests.archive_pdf_path (BAGONG nullable column — iisang column para sa LAHAT ng uri ng ticket)
```

### D6.4 Pag-view
`[👁 View Archived Copy]` sa mga completed ticket — authed inline route (pareho ng CSM View Copy).
Ang mga on-demand na `ict.pdf` / `maintenance.pdf` route — **walang pagbabago**, para sa mga ongoing tickets.

### D6.5 Mga yugto ng pagpapatupad (pagkatapos ng D5a; test-first)
| Phase | Saklaw | Gate |
|---|---|---|
| **D6a** | `archive_pdf_path` column + post-commit auto-archive sa `booted()` completion path + View Archived Copy route | Test: pagkumpleto → may PDF sa tamang month folder (record-date rule); nabigong gen → tapos pa rin ang ticket (retry command) |
| **D6b** | Backfill: gumawa ng archived PDF para sa mga umiiral na completed ICT/PM ticket | Tinker verify: lahat ng completed ay may archive_pdf_path |

**Opsyonal (pag-aprobahan pa):** PR Delivery Confirmation ay maaari ring i-auto-archive sa `received`
— parehong mekanismo, `purchase_requests.archive_pdf_path`. Hindi pa napagdesisyunan.

### D6.6 EXECUTION LOG — ✅ tapos na (commit `0b791b8`, Sept 8 2026)

**Ano ang nai-deliver:**

| Component | File | Detalye |
|---|---|---|
| **DB column** | `2026_09_07_000004_add_archive_pdf_path_to_requests.php` | `requests.archive_pdf_path` nullable — iisang column para sa LAHAT ng ticket types (ICT/PM/REQ) |
| **Archive action** | `app/Actions/Ticket/ArchiveTicketPdfAction.php` | `generate(Request): ?string` — renders the EXISTING `pdf/ict-form` o `pdf/maintenance-form` blade (walang bagong template), saves sa private disk, returns relative path. **Idempotent** — same path = overwrite, walang doble |
| **Auto-trigger** | `app/Models/Request.php::booted()` updated event | `status` nagbago → `STATUS_COMPLETED` **AT** wala pang `archive_pdf_path` → `DB::afterCommit(...)` → `ArchiveTicketPdfAction::generate($request->fresh())`. Try/catch → `Log::warning` (hindi kailanman babagsak ang completion flow; retry command ang backup) |
| **Backfill command** | `app/Console/Commands/GenerateTicketArchivePdfs.php` | `php artisan tickets:generate-archive-pdfs` — nilalagyan ng PDF ang lahat ng completed na walang archive. Record-date rule (D5.1b): folder month = `completed_at`, hindi `now()` |
| **Tests** | `tests/Feature/TicketArchivePdfTest.php` | 4 tests, LAHAT PASS: ICT→`ict-pdfs/` (5 asr.), PM→`pm-pdfs/` (4 asr.), non-completed→null, idempotency |

**Storage layout (verified live):**
```
storage/app/private/
├── ict-pdfs/2026/September/ICT-2026-0004.pdf      ← buwan galing sa completed_at
└── pm-pdfs/2026/September/PM-2026-0001.pdf        ← PM at ICT MAGKAHIWALAY talaga
```

**Backfill result:** `34 archived / 0 missing` — lahat ng completed tickets may PDF na.

**Lesson learned sa build (nakuha sa totoong failure):** ang named function declaration
(`function sigImg()` sa loob ng Blade `@php` block) ay **cannot redeclare** error kapag
maraming PDF ang gine-generate sa isang PHP process — kaya bumabagsak ang backfill command
pagkatapos ng unang file. Fix: ginawang **closures** (`$sigImg = function(...)`) ang
`ict-form.blade.php` at `maintenance-form.blade.php`. Ito rin ang dahilan kung bakit pumasa ang
first-run pero namatay ang batch — hindi bug sa data, bug sa pattern.

**Paano gumagana ngayon (end-to-end):**
```
ICT/PM ticket → technician completes → status = completed (DB transaction commit)
   → afterCommit: ArchiveTicketPdfAction.generate()
      → renders existing form blade (kasama ang lahat ng pirma, diagnosis, actions, petsa)
      → saves: {ict|pm}-pdfs/{year}/{Month}/{requestNumber}.pdf (private disk)
      → requests.archive_pdf_path = relative path
   → (kung pumalya: Log::warning; ticket TAPOS pa rin; ayusin via tickets:generate-archive-pdfs)
```

---

## 5c. D7 — Huling mga Arkibo: PR Delivery Confirmation + Physical Count Report — DISENYO

### D7.0 Kumpletong imbentaryo (deep scan, Sept 8 2026) — ano ang NA-SA-TABI na vs KULANG

**✅ May archived PDF na (private disk):**
| Document | Folder | Trigger |
|---|---|---|
| ICT ticket form | `ict-pdfs/{year}/{Month}/` | status → completed (D6) |
| PM ticket form | `pm-pdfs/{year}/{Month}/` | status → completed (D6) |
| REQ requisition form | `ict-pdfs/` (same flow) | status → completed (D6) |
| CSM survey copy | `csm-copies/{year}/{Month}/` | survey submission (D5a) |

**✅ Naka-store as-is (ang file mismo ang record — walang PDF na kailangan):**
`signatures/` (D5b) · `asset-attachments/` (D5c) · `pr-attachments/` = proof of purchase (D5c) ·
`inventory-imports/` (16 CSVs) · `parts-imports/` (92 CSVs) — lahat private, lahat nasa DB ang path.

**🔴 GAP 1 — PR Delivery Confirmation: STREAM-ONLY.**
`DownloadDeliveryConfirmationPdfAction` ay `response($pdf->output())` lang — **inist-stream sa
browser, WALANG save sa disk**. Ito ang opisyal na receiving report (per-piece serial + property
numbers + destinations + pirma) — permanent record sa gobyerno, pero walang nakatagong kopya.

**🟡 GAP 2 — Physical Count report: on-demand lang.**
May `printReport`/`export` pero walang permanent archive. Ang completed count session = **Annual
Physical Inventory Report (COA document)** — dapat may archived copy sa session completion.

**🟢 OK lang bilang ganito (hindi archival):** audit logs (data table), Excel exports (working
reports), QR stickers (utility), finalized-PR-form (pre-delivery — optional follow-up lang).

### D7a — PR Delivery Confirmation auto-archive (mirror ng D6)
```
PR → STATUS_DELIVERED (delivery recorded; HINDI ang legacy 'received' status)
   → PurchaseRequest::booted() updated event
   → DB::afterCommit → ArchiveDeliveryConfirmationPdfAction::generate($pr)
      → renders EXISTING pdf.delivery-confirmation blade (serials + properties + pirma)
      → saves: pr-pdfs/{year}/{Month}/{pr_number}.pdf  (private disk)
        buwan galing sa delivered_at (record-date rule D5.1b)
      → purchase_requests.archive_pdf_path (bagong nullable column)
   → guard: one archive per PR; try/catch → Log::warning; retry command ang backup
Backfill: php artisan prs:generate-archive-pdfs (10 delivered PRs sa kasalukuyan)
```

### D7b — Physical Count report auto-archive
```
Count session → completed
   → CompletePhysicalCountAction (or model event) → afterCommit archive
   → renders NEW pdf/physical-count-report blade (mirror ng print report view)
   → saves: count-pdfs/{year}/COUNT-{sessionId}.pdf   ← YEARLY folder (annual inventory)
   → physical_counts.report_pdf_path (bagong nullable column)
```

### D7 mga yugto (test-first, isang commit kada phase)
| Phase | Saklaw | Gate |
|---|---|---|
| **D7a-1** | Migration (`purchase_requests.archive_pdf_path`) + `ArchiveDeliveryConfirmationPdfAction` + booted trigger | Test: delivered PR → PDF sa tamang month folder; non-delivered → null; idempotent |
| **D7a-2** | Backfill command + i-run sa 10 delivered PRs | Tinker: 0 delivered na walang archive |
| **D7b-1** | Migration (`physical_counts.report_pdf_path`) + report blade + archive action | Test: completed session → PDF sa tamang year folder |
| **D7b-2** | Backfill command + i-run | Tinker: 0 completed sessions na walang archive |

### D7.9 EXECUTION LOG — ✅ D7a + D7b tapos na (commit `34442e7`, Sept 8 2026)

| Component | File | Detalye |
|---|---|---|
| **PR archive action** | `app/Actions/PurchaseRequest/ArchiveDeliveryConfirmationPdfAction.php` | Renders the EXISTING `pdf.delivery-confirmation` blade (per-piece serial/property numbers); `pr-pdfs/{year}/{Month}/{pr_number}.pdf`; month from `delivered_at` (record-date rule); idempotent |
| **PR trigger** | `PurchaseRequest::booted()` | `status` → `delivered` (wasChanged guard + `!archive_pdf_path`) → `DB::afterCommit` → try/catch `Log::warning`. Legacy `received` status HINDI na-archive |
| **PR backfill** | `php artisan prs:generate-archive-pdfs` | `--force` option; **10/10 archived, 0 failed, 0 missing** |
| **Count report action** | `app/Actions/PhysicalCount/ArchiveCountReportAction.php` | NEW `pdf/physical-count-report` blade (custodian-grouped, Present/Missing/Damaged/Not counted, summary tota). `count-pdfs/{year}/COUNT-{id}.pdf` — **YEARLY** folders (annual inventory); year from `completed_at` |
| **Count trigger** | `CompletePhysicalCountAction` | afterCommit + try/catch `Log::warning`; **instance method** (trait `BuildsCustodianGroups`) |
| **Count backfill** | `php artisan counts:generate-archive-pdfs` | **5/5 archived** (sessions 1-5), 0 missing |
| **Tests** | `PrDeliveryArchiveTest.php` (3) + `CountReportArchiveTest.php` (3) | Lahat PASS; regressions: 48 PR + 12 PhysicalCount PASS |

**Storage (verified live):**
```
storage/app/private/
├── pr-pdfs/2026/August/PR-2026-0001.pdf ...  (10 files, month = delivered_at)
├── count-pdfs/2026/COUNT-1.pdf ...          (5 files, year = completed_at)
```

### D7c — ✅ EXECUTED & COMMITTED (Sept 8 2026, `4e9fc2c`)

**Feedback: REUSE ang existing PR print design** (hindi gumawa ng bagong design). Ang DomPDF
`pdf.pr-form` blade ay **eksaktong mirror ng `.prd-sheet` PRINT version** ng
`show.blade.php` (ang `@media print` styles: walang outer card border/radius/padding — plain
A4 sheet), kasama ang markup na `.prd-title`, `.a60-hdr` field grid (Entity Name / Fund Cluster /
Office / PR No. / Date / RCC), `.prd-table` item grid na may blank padding rows + TOTAL row,
`.prd-purpose`, at `.prd-signs` (Requested/Approved signature table, side-by-side).

| Component | File | Detalye |
|---|---|---|
| **DB column** | `2026_09_08_000003_add_pr_form_pdf_path_to_purchase_requests.php` | `purchase_requests.pr_form_pdf_path` — HIWALAY sa `archive_pdf_path` (isang PR = DALAWANG documents: ang PR form sa `finalized`, ang Delivery Confirmation sa `delivered`) |
| **PDF blade** | `resources/views/pdf/pr-form.blade.php` | DomPDF reuse ng existing `.prd-sheet` print design (walang outer card box — plain A4) |
| **Archive action** | `app/Actions/PurchaseRequest/ArchivePrFormPdfAction.php` | Renders `pdf.pr-form` → `pr-forms/{year}/{Month}/{pr_number}.pdf` (private disk); month from `finalized_at` (record-date rule); idempotent; accepted statuses = `finalized` AT `delivered` (backfill-friendly) |
| **Trigger** | `PurchaseRequest::booted()` (second guard) | `status` → `finalized` → afterCommit → try/catch `Log::warning`. Hindi nag-sagal sa D7a delivery archive (hiwalay columns + guards) |
| **Backfill** | `php artisan prs:generate-archive-form-pdfs` | **13/13 archived, 0 failed** (may bagong delivered PR 2026-0013); `pr-forms/2026/August/...` + `September/...` |
| **Tests** | `PrFormArchiveTest.php` (3) | Lahat PASS: finalized→correct folder, submitted→null, idempotent; **48 PR regression PASS** |

**Storage (verified live):**
```
storage/app/private/
├── pr-pdfs/2026/{August,September}/PR-*.pdf      ← D7a Delivery Confirmation (11)
└── pr-forms/2026/{August,September}/PR-*.pdf     ← D7c PR Form mismo (13)
```

---

## 6. Key Design Decisions (locked, Sept 2026)
1. **PM counts toward the combined total** AND gets its own bucket — one "Total Downtime" line, never two competing totals
2. **ICT downtime is derived** (total − PM), not a third column
3. **Bundled PM credits ALL of the user's assets** — every asset was genuinely unavailable
4. **Cancelled/Rejected/Referred close the window and credit the asset** — the asset really was down
5. **`abs()` + `(int)` cast everywhere** — Carbon-version-proof
6. **DB backup before any data-mutation command** (lesson learned from the pre-restore incident)
7. **High-official detection = position keyword matching** (no `is_high_official` column) — position
   backfill + `config/priority.php` full-phrase keyword list; DB audit showed `position` 93% empty
   and `role` is system-role only, so neither can identify rank as-is
8. **Position becomes admin-managed only** — self-service Profile position field goes read-only
   BEFORE D4 rolls out (otherwise any user can self-inflate to "Director" and jump the queue)
9. **Officials-first ordering** — officials (newest first) at the top of IT queue; regular tickets
   keep the status-based flow below; Ongoing regular work is not displaced, only queue entry order changes
10. **ALL sensitive uploads move to the PRIVATE disk** (signatures, CSM copies, asset + PR attachments)
    — served only through authed controller routes with the same access policy as the parent ticket;
    public disk keeps only static UI assets. No `/storage/` direct URLs for sensitive files.
11. **CSM PDF is auto-generated AFTER the DB transaction commits** — never inside it, never blocking
    the survey submission (PDF failure = null pdf_path + retry command; the `RequirePendingSurvey`
    middleware means a failed submission would trap the user in a loop)
12. **Storage is disk-abstracted from day one** — CSM copy disk via config (`csm_copy_disk`), so a
    future Google Drive switch is an env change, not a code change
13. **PDF copies are organized by MONTH-YEAR folders in storage** (`{type}-pdfs/{year}/{MonthName}/`),
    not by requestId and not by UI filters (the month/year UI-filter plan was dropped) — the storage
    itself becomes the filing cabinet ("CSM ng September 2026" = buksan lang ang folder)
14. **Folder month = RECORD date, never generation `now()`** (CSM → `created_at`; ICT/PM →
    `completed_at`) — makes retries land in the correct folder and idempotent (same path = overwrite);
    verified safe: `app.timezone = Asia/Manila`, request numbers are filename-safe
15. **Execution order: D5b → D5c → D5a → D5d → D6 → D7a → D7b** — the public-disk security exposure is fixed
    BEFORE any new PDF auto-copy feature is built
16. **Archive PDFs reuse the EXISTING form templates** — `ArchiveTicketPdfAction` renders the same
    `pdf/ict-form` / `pdf/maintenance-form` blades the Download button uses (no duplicate template
    to maintain); archive trigger is `DB::afterCommit` + one-per-ticket guard; and Blade `@php`
    blocks must use **closures, never named functions** (named functions fatally collide when one
    process generates multiple PDFs — the D6 backfill proved it)
17. **PR archive trigger = `delivered` (CURRENT status), never the legacy `received`** — the
    Delivery Confirmation needs actual receipt data (serials/property per piece) which only exists
    after delivery recording; Physical Count archive uses **YEARLY folders** (`count-pdfs/{year}/`)
    because the document is the annual inventory, not a monthly filing
18. **Unfinished-first ordering** — Pending/Ongoing/waiting tickets FLOAT above
    Completed/Cancelled/Rejected in every list + dashboard Recent; unfinished work must never be
    buried under freshly-Completed rows. High officials still lead WITHIN the unfinished group
    (`unfinishedFirst()` → `officialsFirst()` → `created_at desc`).
19. **URGENT badge is an alarm, not a tag** — shared `is_active_ticket` accessor gates it (and the
    age chip): a Completed/Cancelled/Rejected ticket is history, so the red badge disappears even if
    the requester is a Director.
20. **Calendar "Overdue" = PM schedule-level Overdue + ANY 7d+ ACTIVE ticket (PM or ICT)** — a single
    number, both types; the age accessor is the single source of truth.

## 7. Git Checkpoints
- v1 implementation: inline `Request.php::booted()` + `total_downtime` column (no tag; superseded by this doc)
- Overhaul: X1-X4 to be committed per phase with tests, then pushed as a single squash to `origin/develop`
- D4 (high-official priority): DESIGNED — position backfill + `config/priority.php` (awaiting execution)
- D5 storage reorg: D5b/D5c private-disk migration + D5a CSM auto-PDF polish chain + D5d `csm:generate-pdfs` backfill — ✅ committed (`3a940d3` latest of chain)
- **D6 ticket auto-archive: ✅ committed `0b791b8`** — `archive_pdf_path` column, afterCommit trigger sa completion, `tickets:generate-archive-pdfs` backfill (34/0), sigImg closure fix, 4 feature tests pass
- **D2 Ticket Aging: ✅ DONE (Sept 9 2026)** — chain `5050937` → `dd8d178` → `b8d3267` → `362d7bc` → `ba4a009` → `f7b00f3` → `68fedeb` → `bc21a6d` → `9b8ef54`. Accessors + chips sa lahat ng lists + calendar aging + unfinished-first + URGENT-in-unfinished + terminal-age hidden + Work Orders badge. Full suite **297/297 green**.
- **D8 Master List Category Column: ✅ committed (Sept 10-11 2026)** — category column + filter + Type column sa 3 Master Lists + IT Parts/Components category removal + Type alignment fix (dedicated td classes, left+baseline sa lahat ng roles)
- **D3 SLA-lite (P1-P4): ⏸️ DEFERRED (Sept 11 2026)** — hindi idagdag (rationale sa Section 5); babalikan lang sa mga trigger doon
- **D9 KPI Dashboard: PLANO (Sept 11 2026)** — 3 cards (MTTR · P1% · Parts Usage), walang bagong table/column, placement + alignment rules sa Section 9

---

## 8. D8 — Master List Category Column + IT Parts/Components Removal (Sept 2026) — GAGAWIN

### D8.1 Ano at bakit
- **Category column + filter sa 3 Master Lists** (IT, Admin Division, Super-Admin) — dedicated slim
  **"Type"** text column (walang icon — gov registry convention: COA/DICT templates ay labeled columns,
  hindi badges-in-cell) + `All Categories ▾` dropdown sa filter ribbon (tabi ng All Divisions/Departments).
- Ground: 8 fixed categories mula sa Register Asset modal (`Desktop · Laptop · Monitor · Printer/Scanner ·
  Peripherals · Network/Server · Others` pagkatapos ng D8.2 removal), 406 live assets walang out-of-list value.
- **Kasabay: alisin ang "IT Parts / Components" asset category** — may dedikadong Parts/Consumables module na
  (`inventory/parts.blade.php` + PartsStockUnits), doon na ang mga parts. Zero live data ang category
  (0 assets, 0 requests) kaya pure front-end removal, walang migration.

### D8.2 IT Parts / Components removal map (11 puntos, 3 files)
| File | Linya | Ano |
|---|---|---|
| `inventory/partials/_modal_asset.blade.php` | L27 | `<option value="IT Parts / Components">` sa category select |
| `inventory/partials/_modal_asset.blade.php` | L492-521 | Buong itPartsSection quick-fill block (Part Type + Capacity/Specs) |
| `inventory/index.blade.php` | L460-463 | CSS `.it-parts-box/-title/-grid/-part-label` — ✅ TINANGGAL |
| `inventory/index.blade.php` | L572 | Responsive `.it-parts-grid` rule — ✅ TINANGGAL |
| `inventory/index.blade.php` | L772-774 | Inline `itPartType` change listener — ✅ TINANGGAL |
| `resources/js/inventory.js` | L419 | `itPartsSection` declaration |
| `resources/js/inventory.js` | L427 | hide line sa `toggleSpecsForm()` |
| `resources/js/inventory.js` | L440-442 | show block kapag category = "IT Parts / Components" |
| `resources/js/inventory.js` | L446-453 | `itPartTypeChange()` function (may mojibake — regex via PowerShell) |
| `resources/js/inventory.js` | L1091 | `window.itPartTypeChange = itPartTypeChange;` export |

**Verified safe:** server validation ay free-string (`required|string|max:100`, walang `in:` list) ·
Parts module ay hiwalay (sariling category field) · 0 backend refs sa `app/`, `config/`, `tests/`.

### D8.3 Category column + filter plan (test-first)
1. **Tests muna** — `TicketCategoryFilterTest`: eager-loaded category sa row data; SA/Admin
   server-side `category` filter + `filtered_stats` kasama ang `hasFilters` hook
2. **Actions** — `ListIctRequestsAction` + `GetRequestsDataAction`: `with(['linkedAsset:asset_id,category'])`
   + `requests.linked_asset_id` sa explicit select list + `whereHas('linkedAsset', category)`
3. **IT blade** — slim **Type** column (text only: `$req->linkedAsset?->category ?? '—'`) + ribbon dropdown +
   JS `data-category` match + **`cells[4]` → `cells[5]` shift sa `filterRequests()`** (may F2 comment na
   sensitive ang indices — itetest ito)
4. **SA/Admin blades** — same column + `params.set('category', ...)` sa server-side `loadRequests()`
5. Verify (grep 0 refs + lint + build + suite) → commit kada phase

### D8.4 Execution log
- **D8.2 IT Parts / Components removal: ✅ TAPOS (Sept 11 2026)** — lahat ng 11 puntos tinanggal
  (dropdown option + quick-fill block + 5 toggleSpecsForm/itPartTypeChange JS points + CSS block +
  responsive rule + inline listener + window export). Codebase grep = **0 refs** (incl. rebuilt
  minified `inventory-*.js` = 0) · blade lint clean · `npm run build` OK (manifest fresh) ·
  Inventory suite **15/15 green** (87 assertions). Kasama sa commit ang D8 doc.
- **D8.3 Category column + filter: ✅ TAPOS (Sept 11 2026)** — test-first (`TicketCategoryFilterTest`,
  4 tests / 22 assertions): JSON filter + eager load + render assertions. Edits:
  `ListIctRequestsAction` (+`linkedAsset:asset_id,category` eager load) · `GetRequestsDataAction`
  (+category `whereHas` filter, +`linked_asset_id` sa select, +`filled('category')` sa `hasFilters`,
  +eager load) · 3 Master List blades (IT/admin/SA): slim **Type** text column (walang icon —
  `linkedAsset?->category ?? —`), `All Categories ▾` dropdown sa ribbon, `data-category` row attribute
  (IT/admin), JS filter match, SA `params.set('category')` + stats `isFiltered` + listener,
  colpans 7→8/8→9, **IT `filterRequests()` cells[4]→cells[5] shift** (F2 comment updated).
  Learned: ang editor-em-dash insert ay naging 3-char mojibake — i-replace ng proper U+2014.
  Full suite **305/305 green** (1189 assertions, 46s).
- **D8.3a Alignment fix: ✅ TAPOS (Sept 11 2026)** — ang Type values ay misaligned: header
  `TYPE` = `text-align: left` pero values = `center`, at neighbors = `baseline` (top) pero
  Type = `middle` → lumalagap sa baba sa tall rows. Fix: dedicated type classes — IT
  `td-type` (existing, unused) · admin `ad-td-type` (existing, unused) · SA `sa-td-type`
  (bago: `font-weight:700; font-size:12px; color:#475569`) — lahat **left + baseline**,
  pantay sa header at katabing cells sa lahat ng roles. Na-lock sa tests (`assertSee('td-type')`).

---

## 9. D9 — Maintenance KPI Dashboard (MTTR + MTBF) — PLANO (Sept 11 2026, naka-revise)

### D9.1 Ano at bakit
- **2 KPI cards** (naka-revise Sept 11 2026): **MTTR** (mean time TO REPAIR = avg downtime_duration, buwanang, sa araw — ISO 55000 standard, kaparehong data ng asset profile) · **MTBF** (mean time between failures = araw sa buwan ÷ bilang ng completed ICT na may downtime window; failure = breakdown, hindi request-only; 0 failures → "No failures this month"). **SLA% dapat wala muna** — naka-align sa D3 deferral (walang target, kulang pa ang data, walang management demand).
- Tugma sa deep review scorecard: #9 Reporting & Analytics = 4/10 — ang pinakahina na may
  **handang datos na ngayon** (X1-X4 downtime split + D8 category column).
- **WALANG bagong table/column** — purong pagbubuod mula sa umiiral na requests table
  (downtime_duration, downtime_start, status) — ang downtime data na naayos (X1-X4) ang sususustainan ng MTTR at MTBF.

### D9.2 Mga patakarang pagkakaayos (na-verify laban sa mga kasalukuyang dashboard)
| Patakaran | Dahilan (na-verify sa mga blade) |
|---|---|
| **WALANG dobleng numero** — ang Pending/Ongoing/Completed/Total/Overdue counts ay HINDI uulitin sa KPI | Ang live stat cards (stat-card-premium) sa lahat ng 3 dashboard ay mayroon na nito — doble lang kung uulitin |
| **Pwesto: pagitan ng stat cards at analytics grid** | Reading order: LIVE (ngayon) → BUWAN (trend) → charts (malalim) |
| **Parehong pamilya ng disenyo** — cards = stat-card-premium, header = analytics-title + icon-blue | Isang visual language, walang dayuhan |
| **Chart.js ay naka-load na** (SA dashboard L600 CDN) | Ang mga chart sa Phase 2 ay WALANG bagong library — gawing katulad ng mga kulay: #0038A8 + #93c5fd |



- ### D9.3 Ang 2 cards (mock-up na naka-lock, naka-revise)
```
+----------------------------------------------+
|  Maintenance KPI - Monthly  [Set 2026 v]    |
|                                              |
|  [MTTR days]          [MTBF days]            |
|     2.9                   14.0               |
|   v 1.0 faster         ^ 3.2 shorter        |
|   than last month      than last month       |
|                        (red = pangminandaan) |
+----------------------------------------------+
```
| Card | Formula | Source | Alignment |
|---|---|---|---|
| MTTR | avg(downtime_duration)/1440 ng completed ICT na may downtime sa buwan (1 decimal) | requests.downtime_duration (X1-X4) | ISO 55000 "time to restore" = kaparehong numero ng asset profile downtime |
| MTBF | araw sa buwan ÷ count ng failures (completed ICT na may downtime_duration; 0 -> null, "No failures this month") | requests.downtime_duration | Failure = breakdown (downtime window), hindi request-only — tumutugma sa "downtime = ICT lang" locked decision |
### D9.4 Mga yugto (test-first)
1. **D9.1** — KpiController (o method sa kasalukuyang Dashboard action) + isang aggregated na buwanang query + tests (tumpak na mga numero sa kilalang datos)
2. **D9.2 (rev)** — 2 cards (MTTR + MTBF) sa SA dashboard + buwanang dropdown (6-buwan backfill) + render tests
3. **D9.3** — 2 cards sa IT + Admin dashboards (na-scope ayon sa tungkulin) + mga tests
4. **D9.4** — dokumentasyon + buong suite + commit kada phase
- **Phase 2 (kalaunan):** 6-buwan trend charts (nakaload na Chart.js, walang bagong library) +
  hiwalay na buong report view na may buwanang talahanayan + PDF/CSV.

### D9.5 Log ng pagpapatupad

- **D9.6 Dashboard logic fixes: TAPOS (Sept 12 2026, commit `d7035f1`)** — (1) **Overdue Tickets** card: Pending/Ongoing LANG (same `$userRequests` scope ng stat cards) — hindi na lumalampas sa Pending+Ongoing; dati 8 vs 4 dahil kasama sa raw query ang Scheduled PM/Awaiting/Referred. Aging Scheduled PM = D2 chips pa rin sa Master List. (2) **Active Assets card**: tinanggal ang "4 under repair" subnote — ang Under Repair ay hiwalay na enum states (For Repair / Under Maintenance) na may sariling doughnut slice (mutually exclusive na ang 354+48+4+0=406). (3) **MTBF indicator**: kapag bumaba ang MTBF vs nakaraang buwan = RED (line graph, gradient, chip, at ▼ delta text); tama na rin ang arrows (improved = ▲ green, worsened = ▼ red). Tests: KpiDashboardTest 7/7 + UnfinishedFirstTest 11/11 + full suite **312/312** (1233 assertions).
- **D9.1+D9.2 ✅ TAPOS (Sept 11 2026)** — test-first (KpiDashboardTest 2 passed / 15 assertions): GetMaintenanceKpiAction (6-buwan window; MTTR via abs diffInHours — Carbon 3 signed trap; P1 share via is_high_official accessor; Parts OUT movements; kpi_month GET param, default current) + wire-in sa SuperAdminDashboardAction compact + SA blade KPI section (analytics-box family, buwanang dropdown GET form, trend chips green/red, FK-safe test data: parts_stock parent row bago ang movements). Rollback: git revert ng D9 commit.
- **D9-rev: MTTR + MTBF lang (Sept 11 2026)** — SLA% dapat wala muna (naka-align sa D3 deferral) · tanggal ang P1% at Parts Usage · MTTR naka-redefine = avg(downtime_duration)/1440 (live 2.9 araw — ISO 55000 "time to restore", consistent sa asset profile) · MTBF = araw sa buwan ÷ failures na may downtime_duration (live 14/14 ✓). Deep-reviewed: failure = breakdown (downtime), hindi request-only; dalawang card ay gumagamit ng sariling downtime data (X1-X4). Susunod: D9.3 IT + Admin (role-scoped).
- **D9-rev ✅ EXECUTED (Sept 11 2026)** — test-first 3 passed / 14 assertions: action = downtime-based failure set (completed ICT + whereNotNull downtime_duration), MTTR = avg downtime /1440 (ISO 55000), MTBF = daysInMonth/count (null kung walang failures); blade = 2 cards (MTTR avg time to restore + MTBF No failures this month state), tanggal lahat ang P1%/Parts Usage refs (0 natira); tests verified: MTTR 1.5/prev 3.0, request-only ticket nag-exclude sa failures, zero-failure = null.
- **D9-polish (Sept 11 2026)** — UX: buwanang dropdown inilagay sa header row (hindi na stand-alone); cards = stat-card-premium family (kapareho ng Total/Pending/Overdue — white, border, hover lift, stat-bg-icon); MTBF icon = fa-infinity; text polish: subtitle How long a failed asset stays down, and how often breakdowns occur · card captions Avg. time to restore a failed asset · Time between breakdowns · No failures this month = green (#10b981). Verify: 3/14 green.
- **D9-charts ✅ TAPOS (Sept 11 2026)** — 2 mini trend charts (MTTR blue + MTBF green) sa existing analytics grid (kasama ng Request Volume/Asset Status — 4 boxes). Censored handling (stats-aligned): no-breakdown months = HOLLOW marker at y=month-days (tooltip No breakdowns MTBF ≥ N days), completed_count distinguishes vs no-observation (gap). Action: +trend series + completed_count + censored flag. Test-first: 4 passed / 29 assertions (kasama ang trend marks censored months).

- **D9.7 Dashboard consolidation: TAPOS (Sept 13 2026, commit `2f34fec`)** — (1) **Overdue PM visibility**: ang Overdue Tickets card ay may amber subtext na (e.g. `7 PM overdue`) gamit ang bagong `stats['overdue_pms']` (D2-e rule: Scheduled PM lampas 3 working days via `is_aging_overdue`, same pattern ng `ListPmTasksAction`; hidden kung 0). Dati invisible ang 7 Scheduled PM sa dashboard. (2) **CSM Satisfaction card**: value = `csmAverage`/5.0 + `14/14 completed ICT responded · 100%` (retains ang lumang Service Quality widget info); **tinanggal ang redundant Service Quality (CSM) widget** sa right column. (3) **Uniform plain-language naming**: MTTR → **Avg. Downtime** · MTBF → **Days Between Failures** (card labels + graph titles + JS legends/tooltips/empty-state); acronyms nananatili sa subtitles (mean time to repair (MTTR)…). Warranty Alerts card = **hindi idinagdag** (0 sa 406 assets may warranty_expiration). Tests: KpiDashboardTest 9/9 + full suite **314/314** (1246 assertions).

- **D9.8 Graph line improvements + Baseline/Censored handling: TAPOS (Sept 13 2026, commits `2011d8e` + baseline commit)** — (1) **Lines**: 2.5px → **3px** + `borderCapStyle: round` (pareho sa MTTR/MTBF); (2) **MTBF censored segments = DASHED** (`[5,5]`) papunta/paglabang censored month — statistically honest dahil "≥ X days" (lower bound) ang value, hindi eksakto; dati solid = misleading; (3) **Censored point border color fix**: dati hardcoded green kahit red ang worsened state — gumagamit na ng `kpiMtbfColor`; (4) **Hover crosshair plugin** (`kpiHoverLine`): patayong dashed guide line sa hinahover na buwan, shared ng dalawang charts; (5) **Baseline reference line** (`kpiBaselinePlugin`): dashed horizontal sa **mean ng valid na buwan** (MTTR: may breakdown; MTBF: **hindi censored** — lower bound ang censored, bias kung isasama) na may `avg X.Xd` label; `suggestedMax` para laging visible; walang valid month = walang baseline. Tests: KpiDashboardTest 9/9 (52 assertions).

- **D9.10 Stepped interval line for MTTR/MTBF + Full 6-month timeline: TAPOS (Sept 15 2026, commit `3813de6`)** — (1) Pinalitan ang slanted straight line (`tension: 0`) ng **`stepped: 'middle'`** para sa MTTR at MTBF. Bawat buwan ay may sariling interval plateau sa halip na matarik na diagonal stick sa dulong kanan ng card. (2) **6-month X-axis visibility**: `autoSkip: false` gamit ang compact labels (`Apr '26`, `May '26`, ..., `Sep '26`) — hindi na nawawala ang September sa dulo. (3) Tooltip null-safety checks sa unobserved months. Tests: KpiDashboardTest 9/9 (52 assertions).

- **D9.10b Polish KPI trend charts: TAPOS (Sept 15 2026, commit `234d089`)** — (1) **Baseline pill badges**: Pinalitan ang raw text ng rounded white pill badges na may 1px colored border para sa `avg 1.6d` at `avg 16.6d` — malinaw at hindi nasasapawan ng gridlines o step lines. (2) **Dynamic MTBF header icon**: Ang `<i class="fa-solid fa-arrow-trend-down">` ay nagiging red din kapag lumala ang failure interval (`kpiMtbfWorsened`), 100% cohesive sa red chip at line. Tests: KpiDashboardTest 9/9 (52 assertions).

- **D9.11 Dashboard space optimization (All-in-one KPI widgets): TAPOS (Sept 15 2026, commit `0e61352`)** — (1) Tinanggal ang hiwalay na higanteng `Maintenance KPI` box na kumakain ng ~200px na vertical space; (2) Inilipat ang malalaking numero (`3.1 days` at `2.3 days`), comparison deltas, subtitiles, at ang buwanang dropdown (`Maintenance KPI: [September 2026 ▾]`) sa mismong **header ng MTTR at MTBF chart cards** sa Analytics Grid bilang modernong all-in-one KPI widgets. (3) Umaakyat pataas ang analytics grid at recent tickets table, kaya mas mabilis basahin nang hindi nag-i-scroll. Tests: KpiDashboardTest 9/9 (52 assertions).

- **D9.12 Super Admin Dashboard Cleanup & Icon-Free Layout: TAPOS (Sept 15 2026, commits `d8dd761`, `0965212`, `b2eabf0`)** — (1) **Chart dimensions restored**: Ibinalik ang `Request Volume by Office` at `Asset Status Overview` sa orihinal na 280px height at standard grid gap para hindi siksikan ang visual aesthetics; (2) **Operations Overview retained & cleaned**: Ibinalik mula sa pansamantalang "System Activity" patungong "Operations Overview", pero inalis ang lahat ng circular/square icon badges (`fa-clock-rotate-left`, `fa-triangle-exclamation`, `fa-hourglass-half`, `fa-star`) para sa minimalist at cohesive na reading flow; (3) **Management Tools icon-free**: Tinanggal ang lahat ng colored square icon containers at chevrons sa Management Tools (`Master List`, `Manage Users`, `PM Schedules`, `Maintenance Calendar`), pinalitan ng malinis na semantic CSS (`.mgmt-tool-link`, `.mgmt-tool-title`, `.mgmt-tool-desc`) nang walang inline JS mouseover handlers; (4) **CSM typography default**: Inalis ang brown/blue text color override sa CSM Satisfaction score — ginawang default slate-800 (`#1e293b`); (5) Inayos ang HTML balance at tinanggal ang redundant extra closing `<div>` tag.

- **D9.13 Global Notification System Overhaul (Scrolling, Auto-Resolving URLs & Click-to-Open): TAPOS (Sept 15 2026, commits `0d6fb51`, `63b8dc5`)** — (1) **20+ Notifications Scrolling Fix**: Na-diagnose ang ugat kung bakit 10 items lang ang nakikita sa notification dropdown kahit 20+ ang bilang sa red bell badge — hardcoded ang `->limit(10)` sa `NotificationController::getNotifications()`. Tinaasan ang default limit patungong `50` at sinuportahan ang offset pagination kasama ang frontend infinite scroll sa `#notifContent` (na may modern custom scrollbar) para seamless na ma-scroll ang 20 hanggang 100+ notifications nang walang putol. (2) **Auto-Resolving Target Ticket URLs**: 98% ng notifications sa database (463 sa 472) ay may `null` na URL kaya hindi naki-click dati. Nagpatupad ng `resolveTargetUrl()` helper na kusa at garantisadong nagre-resolve ng tamang destination route base sa `request_id`, tracking regex (`REQ-...`), PR regex (`PR-...`), o type (ICT tickets → `/requests/ict/{id}`, Maintenance tickets → `/requests/maintenance/{id}`, Requisitions/Parts → `/requisitions`, Purchase Requests → `/purchase-requests/{id}`). (3) **Rich Notification Cards**: Idinagdag sa `buildNotifItemHtml()` ang malinaw na **tracking number badge** (`.notif-req-badge`), **sender/requestor name** (`From: ...`), at **relative time display** (`time_ago`). (4) **Direct Click-to-Open Flow**: Ang buong notification card ay clickable na ngayon na may blue hover indicator at `Open Ticket ↗` link — pag-click, kusa itong namarkahang read at agad binubuksan ang mismong ticket, habang ang inline *Mark as read* link ay nananatili para sa pag-dismiss nang hindi lumilipat ng page.

- **D9.14 Master List of Requests Table De-Cramping & Layout Overhaul: TAPOS (Sept 15 2026, commit `3bd9c2a`)** — (1) **De-cramped Desktop Layout**: Idinagdag ang `min-width: 1180px; width: 100%;` sa `.gov-table-premium` kasama ang customized smooth scrollbar sa `.sa-table-wrap` (`-webkit-overflow-scrolling: touch;`) upang maiwasan ang pagsisiksikan ng 9 na column sa standard desktop at laptop viewports. (2) **Column Proportioning**: Bawat `<th>` ay binigyan ng malinaw na proportional width at min-width (`Request ID: 21% / 190px`, `Type: 9% / 100px`, `Office: 17% / 160px`, `Requestor: 13% / 130px`, `Assigned IT: 13% / 130px`, `Date Requested: 10% / 120px`, `Completed At: 10% / 120px`, `Status: 8% / 95px`, `Action: 8% / 90px`). (3) **Vertical Bloat Elimination**: Tinanggal ang duplicate na Office/Division subtitle sa ilalim ng Requestor column dahil mayroon nang sariling dedicated column ang Office / Division. (4) **Stacked Date & Time Layout**: Pinalitan ang mahabang single-line date format (`Sep 15, 2026 | 02:30 PM`) ng stacked typography gamit ang `.sa-date-primary` (bold date sa taas) at `.sa-date-sub` (subtle time sa ibaba), na nagpaluwag sa horizontal cell width. (5) **Badges & Tooltips**: Binalot ang asset type sa malinis na `.sa-type-category` badge at nilagyan ng hover tooltip ang ticket description upang manatiling single-line na may ellipsis nang hindi nakakabawas sa detalye.

- **D9.15 Admin Dashboard Polish & Mobile Isolation: TAPOS (Sept 15 2026, commit `ffff5cd`)** — (1) **Recent Requests Table Enrichment**: Pinalawak ang table mula sa 3 column patungong 6 na komprehensibong column: Ticket # (may request type), Concern / Subject (may tooltip), Requestor & Office, Date Requested, Status, at Action. (2) **Stat Cards Cleanup**: Tinanggal ang `.stat-card-premium::before` colored edge stripes at pinanatili ang standard slate `#1e293b` font color nang walang loud o distracting na mga kulay. (3) **Desktop vs Mobile Layout Isolation**: Nananatiling 100% natural at fluid ang desktop table nang walang pilit na horizontal scrollbar; ang `min-width: 650px` at `white-space: nowrap` ay naka-isolate nang mahigpit sa loob ng `@media screen and (max-width: 767px)` para sa mobile swipe.

- **D9.16 User Dashboard Overhaul & Equipment Visibility: TAPOS (Sept 15 2026)** — (1) **N+1 Prevention**: Nag-eager-load ng `assignedTo` at `csmSurvey` sa `UserDashboardAction.php` para sa mabilis na rendering. (2) **Stat Cards Cleanup**: Tinanggal ang colored left-border stripes (`.stat-card-premium::before`); pinanatili ang banayad na watermark icon at neutral na slate typography. (3) **Quick Actions Clean & Focused**: Inalis ang survey alert banner, tinanggal ang lahat ng circular icon containers sa buttons, at tinanggal ang obsolete na Preventive Maintenance action (ICT-scheduled na ngayon). (4) **My Assigned Equipment**: Nagdagdag ng direct shortcut patungong `/my-assets` (`route('profile.assets')`) na nagpapakita ng eksaktong bilang ng aktibong kagamitan na nakatalaga sa user. (5) **Recent Activity Table**: Ginawang 6 na column na nagpapakita ng Ticket #, Concern / Subject, Assigned IT (technician o Unassigned), Date Submitted, Status, at Action (Rate Us kung completed at walang survey, view icon). (6) **Responsive Design**: Natural 2-column layout sa desktop (`minmax(0, 1fr) 340px`), at mobile-only horizontal scroll sa ilalim ng `767px`.

- **D9.17 IT Dashboard Workbench Overhaul: TAPOS (Sept 15 2026)** — (1) **Stat Cards Consistency**: Tinanggal ang `.stat-card-premium::before` colored stripes at inalis ang colored text classes (`stat-value-amber`, `stat-value-dark-amber`) upang maging pantay-pantay ang malinis na slate `#1e293b` number styling. (2) **Assigned Job Orders Table**: Pinalitan ang dating loose list rows ng isang structured at propesyonal na table na naglalaman ng: Ticket # (may ICT / PM badge at Urgent badge kung High Official), Concern / Subject (may tooltip), Requestor & Office, Date Submitted, Status pill, at Action button (*Diagnose*, *Parts Status*, *Continue*, *Update*). (3) **For Completion Reminder Panel**: Pinanatili ang kanang panel para sa mga job order na nangangailangan ng Section 5 / IT technician completion signature. (4) **Responsive Workbench**: Fluid 2-column grid (`minmax(0, 1fr) 320px`) sa desktop, at naka-isolate ang `min-width: 650px` table horizontal scroll para sa mobile phone viewports (`max-width: 767px`).


- **D9.18 Login Logo Revert + Sitewide Favicon Coverage: TAPOS (Sept 15 2026)** — (1) **Login Logo Revert**: Ibinabalik ang orihinal na styling ng logo sa login page — 100px width, walang `drop-shadow`, plain hover transition, 28px logo-container margin. Ang bagong `drop-shadow` (blue-tinted) ang nagre-reveal ng **white rectangle background** ng `ncmb-logo.png` (hindi transparent ang PNG) kaya hindi ito nagbe-blend sa puting login card — sa pagtanggal ng shadow, invisible na ulit ang white bg. Hindi ginagalaw ang mismong PNG file (verified sa `git status`). (2) **Login Favicon Restored**: Ibinabalik ang `<link rel="icon" type="image/svg+xml" href="{{ asset('images/ncmb-logo.svg') }}">` sa login page. (3) **Sitewide Favicon Audit (100+ blade files, isa-isa)**: Napatunayang covered ng `layouts.app` ang lahat ng naka-`@extends` na pages (dashboards, PM schedules, ICT/maintenance tickets, inventory, requisitions, purchase requests, profile, admin/super-admin, audit logs) — pero **8 standalone full-HTML pages ang walang favicon**: `components/form-layout.blade.php` (isang fix, 2 form ang apektado: ICT request + PM/maintenance request), 4 error pages (403/404/419/500), at 3 scan pages (`asset-info`, `notice`, `scan-preview`). Nagdagdag ng kaparehong favicon link sa lahat ng 8. (4) **Sadyang hindi ginagalaw** ang `pdf/*` (7 DomPDF views), `emails/default`, at 3 print views (`physical-count-print`, `qr-batch`, `qr-sticker`) — hindi na-rerender sa browser tab. (5) **Verification**: rescan ng buong `resources/views` = **0 user-facing pages na walang favicon**; live HTTP test sa non-existent URL = 404 error page rendered na may icon link sa HTML.

- **D9.19 Super Admin → System Admin Display Rename: TAPOS (Sept 16 2026)** — (1) **Desisyon**: Hindi tinuloy ang ROLE_SIMPLIFICATION_PLAN (mananatili ang Division Admin role); ang mga admin sa RID (Research and Information Division) ang gagamit ng system admin role. **Label-only rename** — ang role value sa DB/code ay mananatiling `super_admin` (walang DB migration, zero permission risk). (2) **Saklaw**: 70 files, 150 line changes — display text lang na may space ("Super Admin"/"super admin"/"SUPER ADMIN") → "System Admin": user-facing strings sa app/ (abort/JSON 403 messages, notification texts, command descriptions + comments), blade/JS (sidebar, user modals, page titles, confirm dialogs), `config/roles.php` label → `'System Admin (RID)'`, `config/priority.php` comment, routes/web.php comments. (3) **Hindi ginagalaw**: role value `super_admin`, class names (SuperAdminController, SuperAdminDashboardAction, atbp.), directory `views/super-admin/`, URLs `/super-admin/...`, route name `dashboard.super-admin`, JS identifiers (`CMMS_IS_SUPER_ADMIN_VIEW`), test fixtures. (4) **Verification**: `git grep -i 'super admin'` sa app/resources/config/routes = **0 hits**; identifiers intact (super_admin 93 files, SuperAdmin 37 files — unchanged); full test suite **312/314 passed** — ang 2 failures ay **pre-existing at hindi related sa rename** (`PurchaseRequestTest::badge_counts_all_unread_but_list_limited_to_ten` notification list cap 15-vs-10; `TicketCategoryFilterTest::master_list_views_render_type_column_and_category_filter`) — pinatunayan via `git stash` na bumabagsak din sila sa pre-rename code (parehong assertion).
- **D9.20 ICT Requests: Diretso sa System Admin (tinanggal ang Division Admin approval): ✅ TAPOS (Sept 16 2026)**
  - **(1) DESISYON** — Tinanggal ang Approve/Reject ng per-division admin para sa **ICT requests**: ang bagong ICT ticket ay **awtomatikong naka-`division_admin_review_status = 'Approved'`** at **diretso sa System Admin**; ang SA na ang nag-a-approve/reject at nag-a-assign ng IT. Ang Division Admin ay **VIEW-ONLY** na (ang "Manage Requests" module ay naging monitoring list na lang ng sariling division).
  - **(2) LUMANG DALOY (bago ang D9.20)** — End User → `POST /requests/ict` (`division_admin_review_status = NULL`) → Division Admin (office-scoped) ang nagre-review via `POST /requests/ict/{id}/review` → **Approve** = `'Approved'` + `RequestNotificationService::notifySuperAdminOfForwardedRequest()`; **Reject** = `status = Rejected` + notify ang requestor → pagkatapos ma-approve, saka pa lang lalabas sa SA (lahat ng SA query ay may `where('division_admin_review_status', 'Approved')`) → SA ang nag-a-assign ng IT (`RequestPolicy::assignTicket` = super_admin only).
  - **(3) BAGONG DALOY (D9.20)** — End User → `POST /requests/ict` (**auto** `'Approved'` + `reviewed_at = now()`) → **notification diretso sa SA** (in-app + email) → lalabas **agad** sa SA master list/dashboard (dahil `'Approved'` na — **ZERO changes** sa `ListIctRequestsAction`, `GetRequestsDataAction`, `SuperAdminDashboardAction`) → SA: Assign IT, o **Reject** (via review panel) → Reject = `status = Rejected` + notify ang requestor → Resubmit: **auto-`'Approved'` ulit** + notify SA ulit.
  - **(4) BAKIT AUTO-APPROVE (hindi pagtanggal ng gate)** — Lahat ng downstream gate (`RequestPolicy::assignTicket`, SA list/dashboard filters, KPI queries, `unfinishedFirst` scopes) ay naghihintay ng `'Approved'`. Sa auto-approve, **hindi na kailangang galawin ang mga ito** → pinakamababang risk, **walang DB migration** (ire-reuse ang existing review columns).
  - **(5) SAKLAW NG DIVISION ADMIN (hindi nagbabago)** — Role, Personnel Management, Inventory/Supply (`can_supply`), at ang Manage Requests = **view-only monitoring**: ang admin list (`ListIctRequestsAction:40-50`) ay `type=ICT` + `branch` + `office` filter lamang — **walang review/status filter** → nakikita pa rin nila ang lahat ng tickets ng division nila sa lahat ng stage (Pending → Ongoing → Awaiting Parts/Signature → Completed). Sa bagong flow ay wala nang NULL-review ticket, kaya **natural na nawawala ang review panel nila**.
  - **(6) HINDI KASAMA / BABALA** — (a) **PM requests**: hindi kasama (naka-bypass na sa SA noon pa sa `CreateMaintenanceTicketAction`) — hindi gagalawin ang PM flow. (b) **`isDivisionAdmin()`**: **HUWAG GALAWIN** — kung isasama ang `super_admin` dito, papasok ang SA sa office-match path ng **6 na lugar** (`RequestPolicy:159`, `RequisitionPolicy:50`, `ictFormFlags:344`, `maintenanceFormFlags:393`, `canAdminQuickUpdateStatus:457`, `ReviewIctTicketAction:25`) at **LILIIT ang scope niya** mula branch-wide patungong RID-only. Additive (`|| isSuperAdmin()`) lang ang gagamitin sa review gate. (c) DB schema, audit log category, at `RequestScope` (dead code) — hindi gagalawin.
  - **(7) MGA PHASE** — Phase-by-phase para ma-test kada hakbang. **Kada phase:** `php -l` sa lahat ng edited files → focused test run → **full test suite** → git commit (para madaling i-rollback kung may issue).
    - **P0 — MD documentation (phase tracker na ito): ✅ TAPOS** — kumpletong task list, files, tests, at verification criteria.
    - **P1 — SA approve/reject path (prerequisite): ✅ TAPOS** — *Bakit:* bago palitan ang daloy, kailangang makapag-approve/reject na ang SA, kung hindi ay mai-stuck ang tickets. *Files:* `routes/web.php` (L84), `app/Actions/ICT/ReviewIctTicketAction.php`, `app/Models/Notification.php` (L40). *Eksaktong pagbabago:* (a) route middleware `role:admin` → `role:admin,super_admin` (kung hindi, 403 agad sa middleware bago pa umabot sa action); (b) `ReviewIctTicketAction`: guard union `!$admin->isDivisionAdmin() && !$admin->isSuperAdmin()`, **branch-scope para sa SA**, **laktawan ang "already reviewed" 422 kapag SA** (ito ang kanilang Reject/Approve path para sa auto-approved tickets), **skip self-notify**, role-aware rejection copy, audit category `'System Admin Review'` kapag SA; (c) `Notification.php`: email whitelist — huwag laktawan ang `super_admin` kapag `str_ends_with((string)$notification->type, 'for Review')` (ngayon ay in-app lang ang SA dahil sa anti-flood guard). *Tests:* `tests/Feature/IctDirectToSystemAdminTest.php` part 1 — SA approve ng NULL-review ticket, SA reject, 403 para sa non-admin, walang self-notify. *Commit:* `feat(ict): allow System Admin to approve/reject ICT requests (D9.20 phase 1)`. **Resulta (Sept 16 2026):** 7 bagong tests sa `tests/Feature/IctDirectToSystemAdminTest.php` — **7 passed, 18 assertions**; full suite **2 failed / 319 passed (1263 assertions)** — pareho pa rin ang 2 pre-existing failures (`PurchaseRequestTest`, `TicketCategoryFilterTest`), **0 bagong failure** (+7 tests kumpara sa 312-passed baseline). Nadiskubre at naayos din ang latent bug sa `ReviewIctTicketAction`: `$validated['notes']` → `$validated['notes'] ?? null` (nag-e-error kapag hindi pinadala ang optional na `notes`; hindi na-trigger mula sa UI dahil laging may value ang JS).
    - **P2 — Diretso sa SA (auto-approve + notification): ✅ TAPOS** — *Bakit:* ito ang puso ng pagbabago — tinatanggal ang review step at dinadala ang ticket agad sa SA. *Files:* `app/Actions/ICT/CreateIctTicketAction.php`, `app/Actions/ICT/ResubmitIctTicketAction.php`, `app/Services/RequestNotificationService.php`. *Eksaktong pagbabago:* (a) sa `RequestModel::create()` — idagdag ang `'division_admin_review_status' => 'Approved'` at `'reviewed_at' => now()`; (b) palitan ang `notifyAdminsOfNewRequest()` ng **bagong** `notifySystemAdminOfNewIctRequest()` (gagayahin ang PM pattern: `cascadeSuperAdminsForUser()` + **self-skip** + type na may `'for Review'` suffix para tumugma sa email whitelist + mensaheng *"Please review and assign IT personnel"*); (c) Resubmit: `'Approved'` ulit sa halip na NULL + notify SA. Ang lumang `notifyAdminsOfNewRequest()` ay mananatili (deprecated, walang tatawag). *Tests:* part 2 — bagong ICT ay `'Approved'` + may `reviewed_at`; **SA** ang na-notify; **wala** sa division admins; resubmit ay `'Approved'` ulit. *Commit:* `feat(ict): route new ICT requests directly to System Admin (D9.20 phase 2)`. **Resulta (Sept 16 2026):** 3 bagong tests (total **10** sa file) — **10 passed, 30 assertions**; full suite **2 failed / 322 passed (1275 assertions)** — pareho pa rin ang 2 pre-existing failures, **0 bagong failure** (+3 tests kumpara sa P1 na 319 passed). **Dalawang latent bug na natuklasan ng P2 tests:** (a) **Sira ang buong resubmit flow** — ang `ResubmitIctTicketAction` ay may `'remarks' => null` sa `requests` update, ngunit **walang `remarks` column** ang `requests` table (verified via `php artisan db:table requests` → 30 columns, wala talaga; at walang migration na nagdagdag/nag-drop nito) → **500 error sa lahat ng resubmit**; **naayos sa P2** (tinanggal ang invalid na write). (b) **Parehong bug class** sa `QuickUpdateStatusAction:44` (`'remarks' => $validated['remarks']` sa `requests`) → sira rin ang quick-status endpoint; **iaayos sa P3** kasama ng view-only hardening.
    - **P3 — View-only hardening + Backfill: ✅ TAPOS** — *Bakit:* para tuluyang maging view-only ang division admin at **walang mai-stuck** na lumang ticket. *Files:* `app/Support/RequestHelpers.php` (L455-484), one-time backfill run. *Eksaktong pagbabago:* (a) `canAdminQuickUpdateStatus`: regular division admin → `false`; **Supply Officer + SA** lang ang may quick-status action (defense-in-depth — ang endpoint `POST /admin/requests/update-status` ay walang frontend caller, ngunit server-side guard pa rin); (b) one-time backfill: lahat ng existing `division_admin_review_status = NULL` na tickets → `'Approved'` (may precedent: `RepairBrokenPMRecordsAction`). (c) **bug fix (nahanap sa P2 tests):** tanggalin ang `'remarks' => $validated['remarks']` sa `QuickUpdateStatusAction:44` — hindi umiiral na column sa `requests` table → 500 error tuwing gagamitin ang quick status; kasama ang test na nagpapatunay gumagana ito para sa Supply Officer / SA. *Tests:* part 3 — division admin blocked, SO allowed, backfill test. *Verification:* `NULL` count bago/pagkatapos = **0**. *Commit:* `refactor(ict): make Division Admin view-only and backfill pending reviews (D9.20 phase 3)`. **Resulta (Sept 16 2026):** 3 bagong tests (total **13** sa file) — **13 passed, 38 assertions**; full suite **2 failed / 325 passed (1283 assertions)** — pareho pa rin ang 2 pre-existing, **0 bagong failure** (+3 kumpara sa P2 na 322 passed). **Backfill verification (script check sa dev DB):** ICT = **20 tickets, 0 NULL** review status (20 Approved); PM = **27 tickets, 0 NULL** → **HINDI KAILANGAN ang backfill** sa environment na ito (para sa ibang environment bago mag-deploy, patakbuhin ang parehong count check). **Behavior change:** ang regular division admin ay **403** na (hindi 422) kapag ginamit ang quick-status endpoint — role gate na mismo ang humaharang (hindi na pinapayagan ang role). **Bug fix (b) — natapos:** natanggal ang invalid na `'remarks'` write → **gumagana na ang quick status** para sa Supply Officer at System Admin (napatunayan ng test na may kasamang `remarks` sa payload).**
    - **P4 — UI copy cleanup: ✅ TAPOS** — *Bakit:* iwasan ang malabo/maling label sa ticket page pagkatapos ng flow change. *Files:* `resources/views/requests/ict/form.blade.php`, `resources/views/partials/ict/_ict_scripts.blade.php`. *Eksaktong pagbabago:* (a) review panel label/copy → **"System Admin Review"** + *"Please review this request before assigning IT personnel"*; (b) status box: **huwag magpakita ng pekeng "APPROVED by Division Admin"** sa lahat ng ticket — ipakita lang kapag tunay na may review record; (c) confirm dialog: generic (*"Approve this request?"* / *"Reject this request?"*). *Tests:* part 4 — render assertions (SA vs division admin). *Commit:* `chore(ict): role-aware review copy in ICT form (D9.20 phase 4)`. **Resulta (Sept 16 2026):** dagdag sa saklaw: bagong flag na `canReviewAsSystemAdmin` sa `ictFormFlags` (`RequestHelpers.php`); **SA panel = Reject-only** (nakatago ang "Approve & Forward" dahil auto-`'Approved'` na ang bagong tickets); status box gate: ipakita lang kapag `status === 'Rejected'` o may `reviewed_by_admin_id` — **wala nang pekeng "APPROVED"** sa auto-approved tickets; label → "Review Status"; confirm dialog → generic. **Bug fix sa blade:** ang `fmtDate()` helper (`form.blade.php:3`) ay walang `function_exists` guard → **fatal error** kapag dalawang test ang nag-render ng parehong view sa iisang process; nilagyan ng guard + cleared compiled view cache. 2 bagong render tests → **15 passed, 47 assertions** sa test file.
    - **P5 — Final: ✅ TAPOS** — I-update ang mga status sa itaas (⏳ → ✅) + isulat ang resulta ng tests, commit ng docs, at **push** sa `origin/develop`. **FINAL RESULTA (Sept 16 2026):** full test suite **327 passed / 2 failed (1292 assertions)** — ang 2 ay ang **parehong pre-existing failures** (baseline bago ang D9.20: 312/314), **0 bagong failure**; 15 bagong tests sa `IctDirectToSystemAdminTest.php` (lahat passed). Kumpleto na ang bagong daloy: ICT requests → auto-'Approved' → diretso sa System Admin (in-app + email) → SA ang nag-a-approve/reject at nag-a-assign ng IT; Division Admin = view-only sa Manage Requests.
  - **(8) BASELINE AT RISK** — Full suite bago magsimula: **312/314 passed**; ang 2 failures ay **pre-existing** (`PurchaseRequestTest::badge_counts_all_unread_but_list_limited_to_ten`, `TicketCategoryFilterTest::master_list_views_render_type_column_and_category_filter`) — pinatunayan via `git stash` na bumabagsak din sa pre-change code. **Target kada phase: walang BAGONG failure** (hindi lalala sa 2).
  - **(9) ROLLBACK** — Kada phase ay hiwalay na commit, kaya ang isang `git revert <commit>` o `git checkout` ay sapat na para ibalik ang partikular na phase nang hindi naaapektuhan ang iba. Ang P3 backfill ay data change (NULL → 'Approved') — mababalik sa NULL sa partikular na tickets kung kinakailangan, ngunit tandaan na ang 'Approved' ay nagpapakita ng ticket sa SA (safe side) kaya hindi ito mapanganib.
  - **D9.21 ICT Form UX: Two-Step Review + Panel Cleanup: 🔵 GINAGAWA (Sept 16 2026)** — kasunod ng D9.20, polish ng ticket page base sa feedback: (1) **Two-step flow** — hindi na sabay nakikita ang System Admin Review at Assign IT (magulo kapag sabay): una ang **Review panel** (Approve + Reject), at **pagkatapos i-Approve ng SA** (sets `reviewed_by_admin_id`) saka lang lalabas ang **Assign IT Personnel** panel; blade gates sa `form.blade.php`: review panel = `Pending && !assigned_to && !reviewed_by_admin_id (SA)`, assign panel = `reviewed_by_admin_id` na at hindi self-assigned (kapag si SA mismo ang naka-assign, nakatago na rin). (2) **Tanggal ang "Assigned to:" chip** (`ict-assigned-chip`) at ang "Currently assigned:" text sa assign panel — dobleng impormasyon lang dahil nasa SA requests table na ang Assigned badge. (3) **Tanggal ang Review Status box** ("APPROVED on Sep 09…") sa form ayon sa desisyon — walang stamp sa form, nasa requests table/activity ang review trail. (4) **Compact UI ng review panel** (`partials/ict/_form-styles.blade.php`): light gray box (#f8fafc, 1px #e2e8f0, walang colored accent border — inalis ang blue left border), Approve = NCMB blue solid / Reject = white outline na may red, single-row layout na nag-st-stack sa mobile. **Status:** blade + CSS edits **TAPOS** (php -l ok; 14/15 tests passed); **PENDING:** i-update ang obsolete na `test_genuinely_reviewed_ticket_shows_its_review_status_box` (i-assert na wala na ang box) + bagong two-step test (`test_system_admin_reviews_first_then_assign_panel_appears_after_approval` — nakasulat na), full suite run, at commit (`refactor(ict): two-step review flow and form cleanup (D9.21)`).
- **D9.22 Admin Dashboard: Isang Card ang Management Tools + Division Info: TAPOS (Sept 16 2026)** — pagkatapos ng D9.21, dashboard polish naman base sa feedback. **(1) Bago:** ang **Management Tools** (Manage Requests / Inventory & Assets / Manage Personnel — vertical buttons pa rin) at ang **Division Info** (System Role + Department/Office sa info-box) ay magkasama na sa **iisang `queue-panel` card** sa right column — pantay ang tingin sa ibang panels (Needs IT Assignment, Supply Snapshot). **(2) Bakit:** dati ay nakakalat sila (bare label + buttons sa labas ng card, tapos hiwalay na info-box) — kalat tignan at hindi aligned sa card design ng dashboard. **(3) Files:** `resources/views/dashboard/admin.blade.php` lang (binabalutan ng `queue-panel` wrapper + `margin-top:16px` sa Division Info label); walang logic/route change, walang DB change. **(4) Iteration notes:** una ay inilipat ang tools sa loob ng Division Info box (mali ang pagkakaintindi, na-revert), pangalawa ay naging inline horizontal row (hindi rin tinanggap) — final ay isang card, vertical buttons, ayon sa request. **(5) Beripikasyon:** balanced closing tags, `view:clear` ok. Kasama sa pending commit kasama ng D9.21.
- **D9.23 SA Bug Fixes: Division Filter Crash + Silent Archive: TAPOS (Sept 16 2026)** — (1) **Bug A — Master List division filter** (`Failed to load requests` kapag may piniling division): sanhi ay SQL 1052 — ang `GetRequestsDataAction::execute()` ay gumagamit ng bare `where('office', ...)` habang ang `officialsFirst()` scope ay nag-join ng `users as official_users` (may sariling `office` column) → ambiguous. Fix: `where('requests.office', $division)`. Verified live sa tinker: ADMIN DIV = 1, RID = 23, zero SQL error. (2) **Bug B — ARCHIVE OLD LOGS na tahimik**: ang DB ay walang logs older than 1 year (0/494) kaya ang action ay nagre-return ng `back()->with('error')` pero walang flash display sa blade → mukhang hindi gumagana. Fix: (a) AJAX detection sa `ArchiveLogsAction` — JSON `success:false + message` kapag fetch; (b) blade JS — palit ng `form.submit()` sa fetch flow na may Swal loading, JSON error toast, at blob download na may `X-Archived-Count` header para sa success message; (c) fallback na `back()->with('error')` ay naiwan para sa no-JS. (3) **Files:** `app/Actions/SuperAdmin/GetRequestsDataAction.php`, `app/Actions/SuperAdmin/ArchiveLogsAction.php`, `resources/views/super-admin/audit-logs/index.blade.php`. (4) **Beripikasyon:** `php -l` ✓, live tinker filter test ✓, full suite — 2 pre-existing failures lang.

- **D9.24 Date-Based Service Request Numbers: TAPOS (Sept 17 2026)** — (1) **Desisyon:** pinalitan ang request number format mula sa `{PREFIX}-{REGION}-{BRANCH}-{YYYY}-{NNNN}` (hal. `REQ-NCR-RCMB-2026-0028`) tungong **date-based** na `{PREFIX}-{YYYY}-{MM}-{DD}-{NNNN}` (hal. `REQ-2026-09-17-0001`). Ang "SERVICE REQUEST NO" field sa ICT form at PDF ay direktang kumukuha ng numerong ito (via `service_request_no`), kaya agad na makikita ang bagong format. (2) **Bakit hyphen at hindi slash:** ang unang plano ay `REQ-2026/09/16/0001`, ngunit natuklasan sa deep review na ang request number ay ginagamit bilang **filename at storage path** sa 6 na lugar — `ArchiveTicketPdfAction` (`Storage::put` -> `ict-pdfs/YYYY/Month/ARCH-{number}.pdf`), `StoreCsmSurveyAction` (`Storage::put` -> `csm-copies/...`), at 4 na `Content-Disposition` filenames (ICT PDF, ICT disposal tag, PM PDF, PM disposal tag). Ang `/` ay bawal sa Windows filenames at path separator sa Linux -> gagawa ng sub-directories at sirang download names. Ang hyphen din ang ISO 8601 convention (`YYYY-MM-DD`) at tugma sa NAP/ISO 15489 record-control practice (filename-safe, retrievable). (3) **Files:** `app/Support/RequestHelpers.php` (generator), `app/Models/Request.php` (bagong `parseRequestNumber()` + dual-format accessors), bagong `tests/Feature/ServiceRequestNumberFormatTest.php`. (4) **Eksaktong pagbabago:** (a) generator — `$datePart = now()->format('Y-m-d')`, search prefix `"{$prefix}-{$datePart}"`, advisory lock `request_number_{PREFIX}_{Y_m_d}` (per prefix, per araw), araw-araw na reset ng counter; (b) **critical fix:** ang `explode('-')` display accessors ay masisira ng bagong format (`$parts[3]` ay magiging `'16'` -> `ICT-16-0001`), kaya idinagdag ang `parseRequestNumber()` na dual-format aware (detects new format via `ctype_digit($parts[1]) && strlen($parts[1]) === 4`) — legacy `REQ-NCR-RCMB-2026-0042` -> `ICT-2026-0042` pa rin, bago `REQ-2026-09-16-0001` -> `ICT-2026-09-16-0001`; (c) ang `full_display_number` para sa bagong format ay kumukuha ng region/branch sa **model columns** (`$this->region` + `getBranchCode($this->branch)`) dahil wala na sila sa numero -> `ICT-NCR-RCMB-2026-09-16-0003`. (5) **Hindi ginagalaw:** lumang numero sa DB (audit trail, walang backfill — sabay na gumagana ang mixed formats), `requests.request_number varchar(255) NOT NULL UNIQUE` (kasya; 19 chars ang bago vs 22), ang 6 na filename/path spots (hyphen-safe), `NotificationController::resolveTargetUrl` regex `/REQ-[A-Z0-9-]+/` (tumutugma pa rin dahil sakop ang digits at hyphen), requisition `PR-` numbering, at ang PM generation logic. (6) **Beripikasyon:** `php -l` OK lahat; bagong focused test **12 passed / 16 assertions** (ICT format, PM prefix, same-day increment 0001->0002, independent ICT/PM counters, legacy numerong hindi humaharang sa bagong sequence, display number new/legacy/PM, full display kasama at walang region/branch); **full suite: 2 failed / 339 passed (1312 assertions)** — pareho pa rin ang 2 pre-existing failures (`PurchaseRequestTest` badge cap, `TicketCategoryFilterTest` master list), **0 bagong failure** (+12 tests kumpara sa 327-passed baseline). (7) **Revision (Sept 17 2026):** ang unang bersyon ay **araw-araw** nagre-reset (daily). Binago ayon sa requirement: **tuloy-tuloy na ang sequence sa buong taon** — hinahanap ang huling numero sa buong taon (`LIKE 'REQ-2026-%'`) at nagre-reset lang kapag bagong taon (hal. `REQ-2026-09-17-0001` -> `REQ-2026-09-18-0002` -> `REQ-2027-01-01-0001`), at ang advisory lock ay per prefix per taon na (`request_number_REQ_2026`). Dagdag na 2 regression tests: `test_sequence_continues_across_days_within_the_same_year` at `test_sequence_restarts_in_a_new_year` (12 passed / 15 assertions; full suite 2 failed / 339 passed, 0 bagong failure). **(8) Revision 2 (Sept 17 2026) — FINAL:** ibinalik ang **araw-araw (daily) na reset**. Bawat araw ay nagsisimula sa `0001` (`REQ-2026-09-17-0001`, at sa susunod na araw `REQ-2026-09-18-0001`), at ang advisory lock ay per prefix per araw (`request_number_REQ_2026_09_17`); ang petsa/taon ay nasa loob pa rin ng numero. Ang 2 regression test ay pinalitan ng `test_sequence_restarts_on_a_new_day` (ang nakaraang araw na `0007` ay hindi humaharang -> `0001`, at `0002` sa parehong araw) at `test_previous_year_numbers_do_not_block_todays_sequence`. Beripikasyon pagkatapos ng revert: focused **12 passed / 16 assertions**; full suite **2 failed / 339 passed (1312 assertions)** — pareho pa rin ang 2 pre-existing failures, **0 bagong failure**.