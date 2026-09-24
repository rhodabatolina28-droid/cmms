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

## 5a. D5 — Storage Reorganization + CSM Auto-PDF — ✅ EXECUTED (D5a–D5d; tingnan ang §5.8 execution log sa ibaba)

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

## 5c. D7 — Huling mga Arkibo: PR Delivery Confirmation + Physical Count Report — ✅ EXECUTED (D7a/D7b `34442e7` · D7c `4e9fc2c`; tingnan ang §D7.9 execution log)

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
- D4 (high-official priority): ✅ EXECUTED — `config/priority.php` + position dropdown + queue-jump + URGENT badge (`202197e` → `bc21a6d`); natitira lang ang **D4d** na mano-manong backfill ng positions (user data entry, walang code)
- D5 storage reorg: D5b/D5c private-disk migration + D5a CSM auto-PDF polish chain + D5d `csm:generate-pdfs` backfill — ✅ committed (`3a940d3` latest of chain)
- **D6 ticket auto-archive: ✅ committed `0b791b8`** — `archive_pdf_path` column, afterCommit trigger sa completion, `tickets:generate-archive-pdfs` backfill (34/0), sigImg closure fix, 4 feature tests pass
- **D2 Ticket Aging: ✅ DONE (Sept 9 2026)** — chain `5050937` → `dd8d178` → `b8d3267` → `362d7bc` → `ba4a009` → `f7b00f3` → `68fedeb` → `bc21a6d` → `9b8ef54`. Accessors + chips sa lahat ng lists + calendar aging + unfinished-first + URGENT-in-unfinished + terminal-age hidden + Work Orders badge. Full suite **297/297 green**.
- **D8 Master List Category Column: ✅ committed (Sept 10-11 2026)** — category column + filter + Type column sa 3 Master Lists + IT Parts/Components category removal + Type alignment fix (dedicated td classes, left+baseline sa lahat ng roles)
- **D3 SLA-lite (P1-P4): ⏸️ DEFERRED (Sept 11 2026)** — hindi idagdag (rationale sa Section 5); babalikan lang sa mga trigger doon
- **D9 KPI Dashboard: ✅ EXECUTED (Sept 11–12 2026)** — final set = **MTTR + MTBF** lang (D9-rev: tinanggal ang SLA% · P1% · Parts Usage), pamilyang stat-card-premium, walang bagong table/column (placement + alignment rules sa Section 9); nasundan ng D9.20–D9.34c (ICT form UX, dashboard polish, CSM stats/card/monthly/alert/weekly)

---

## 8. D8 — Master List Category Column + IT Parts/Components Removal (Sept 2026) — ✅ DONE (Sept 11 2026)

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

## 9. D9 — Maintenance KPI Dashboard (MTTR + MTBF) — ✅ EXECUTED (Sept 11–22 2026: D9.1 → D9.34c; tingnan ang §9.5 at ang CSM changelog sa dulo)

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

- **D9.24 Date-Based Service Request Numbers: TAPOS (Sept 17 2026)** — (1) **Desisyon:** pinalitan ang request number format mula sa `{PREFIX}-{REGION}-{BRANCH}-{YYYY}-{NNNN}` (hal. `REQ-NCR-RCMB-2026-0028`) tungong **date-based** na `{PREFIX}-{YYYY}-{MM}-{DD}-{NNNN}` (hal. `REQ-2026-09-17-0001`). Ang "SERVICE REQUEST NO" field sa ICT form at PDF ay direktang kumukuha ng numerong ito (via `service_request_no`), kaya agad na makikita ang bagong format. (2) **Bakit hyphen at hindi slash:** ang unang plano ay `REQ-2026/09/16/0001`, ngunit natuklasan sa deep review na ang request number ay ginagamit bilang **filename at storage path** sa 6 na lugar — `ArchiveTicketPdfAction` (`Storage::put` -> `ict-pdfs/YYYY/Month/ARCH-{number}.pdf`), `StoreCsmSurveyAction` (`Storage::put` -> `csm-copies/...`), at 4 na `Content-Disposition` filenames (ICT PDF, ICT disposal tag, PM PDF, PM disposal tag). Ang `/` ay bawal sa Windows filenames at path separator sa Linux -> gagawa ng sub-directories at sirang download names. Ang hyphen din ang ISO 8601 convention (`YYYY-MM-DD`) at tugma sa NAP/ISO 15489 record-control practice (filename-safe, retrievable). (3) **Files:** `app/Support/RequestHelpers.php` (generator), `app/Models/Request.php` (bagong `parseRequestNumber()` + dual-format accessors), bagong `tests/Feature/ServiceRequestNumberFormatTest.php`. (4) **Eksaktong pagbabago:** (a) generator — `$datePart = now()->format('Y-m-d')`, search prefix `"{$prefix}-{$datePart}"`, advisory lock `request_number_{PREFIX}_{Y_m_d}` (per prefix, per araw), araw-araw na reset ng counter; (b) **critical fix:** ang `explode('-')` display accessors ay masisira ng bagong format (`$parts[3]` ay magiging `'16'` -> `ICT-16-0001`), kaya idinagdag ang `parseRequestNumber()` na dual-format aware (detects new format via `ctype_digit($parts[1]) && strlen($parts[1]) === 4`) — legacy `REQ-NCR-RCMB-2026-0042` -> `ICT-2026-0042` pa rin, bago `REQ-2026-09-16-0001` -> `ICT-2026-09-16-0001`; (c) ang `full_display_number` para sa bagong format ay kumukuha ng region/branch sa **model columns** (`$this->region` + `getBranchCode($this->branch)`) dahil wala na sila sa numero -> `ICT-NCR-RCMB-2026-09-16-0003`. (5) **Hindi ginagalaw:** lumang numero sa DB (audit trail, walang backfill — sabay na gumagana ang mixed formats), `requests.request_number varchar(255) NOT NULL UNIQUE` (kasya; 19 chars ang bago vs 22), ang 6 na filename/path spots (hyphen-safe), `NotificationController::resolveTargetUrl` regex `/REQ-[A-Z0-9-]+/` (tumutugma pa rin dahil sakop ang digits at hyphen), requisition `PR-` numbering, at ang PM generation logic. (6) **Beripikasyon:** `php -l` OK lahat; bagong focused test **12 passed / 16 assertions** (ICT format, PM prefix, same-day increment 0001->0002, independent ICT/PM counters, legacy numerong hindi humaharang sa bagong sequence, display number new/legacy/PM, full display kasama at walang region/branch); **full suite: 2 failed / 339 passed (1312 assertions)** — pareho pa rin ang 2 pre-existing failures (`PurchaseRequestTest` badge cap, `TicketCategoryFilterTest` master list), **0 bagong failure** (+12 tests kumpara sa 327-passed baseline). (7) **Revision (Sept 17 2026):** ang unang bersyon ay **araw-araw** nagre-reset (daily). Binago ayon sa requirement: **tuloy-tuloy na ang sequence sa buong taon** — hinahanap ang huling numero sa buong taon (`LIKE 'REQ-2026-%'`) at nagre-reset lang kapag bagong taon (hal. `REQ-2026-09-17-0001` -> `REQ-2026-09-18-0002` -> `REQ-2027-01-01-0001`), at ang advisory lock ay per prefix per taon na (`request_number_REQ_2026`). Dagdag na 2 regression tests: `test_sequence_continues_across_days_within_the_same_year` at `test_sequence_restarts_in_a_new_year` (12 passed / 15 assertions; full suite 2 failed / 339 passed, 0 bagong failure). **(8) Revision 2 (Sept 17 2026) — FINAL:** ibinalik ang **araw-araw (daily) na reset**. Bawat araw ay nagsisimula sa `0001` (`REQ-2026-09-17-0001`, at sa susunod na araw `REQ-2026-09-18-0001`), at ang advisory lock ay per prefix per araw (`request_number_REQ_2026_09_17`); ang petsa/taon ay nasa loob pa rin ng numero. Ang 2 regression test ay pinalitan ng `test_sequence_restarts_on_a_new_day` (ang nakaraang araw na `0007` ay hindi humaharang -> `0001`, at `0002` sa parehong araw) at `test_previous_year_numbers_do_not_block_todays_sequence`. Beripikasyon pagkatapos ng revert: focused **12 passed / 16 assertions**; full suite **2 failed / 339 passed (1312 assertions)** — pareho pa rin ang 2 pre-existing failures, **0 bagong failure**. **(9) **Rationale (FINAL, Sept 17 2026):** ang daily reset ay sadyang pinili (hindi kapabayaan). Ang service requests ay **internal operational records** na ginagamit sa araw-araw na queue tracking, kaya bawat araw ay bagong sequence (`REQ-2026-09-17-0001`, `PM-2026-09-17-0001`) at **hiwalay ang counter ng ICT at PM** (magkaibang prefix at advisory lock: `request_number_REQ_2026_09_17` vs `request_number_PM_2026_09_17`). Nasa numero ang buong petsa, kaya nananatiling **unique, hindi na-reuse, at traceable** (NAP / ISO 15489). Ang mga **external government forms** ay may sariling yearly series: `PR-{YEAR}-NNNN` (procurement, `CreatePurchaseRequestAction:139`) at `PAR-{YEAR}-NNNN` (property, `ParNumberService:12`) — ibang document class, kaya ibang cadence. Buong inventory ng mga numero: (a) auto-generated — `request_number` (ICT/PM, **daily**), `pr_number` (yearly), `par_number` (yearly); (b) kopya ng request number — `repair_requests.service_request_no`, `preventive_maintenance.form_no`; (c) manu-manong input — `serial_number`, `property_number`, `property_no`, `employee_no`, `article_serial_no`, `transfer_receipt_no`, `wmr_no`.
- **D9.25 Test Suite: ZERO Failures (2 pre-existing fixed): TAPOS (Sept 17 2026)** — (1) **TicketCategoryFilterTest** (master list render): ang SA assertion ay `assertSee('<th>Type</th>', false)` — eksaktong string match, pero ang SA view (super-admin/requests/index.blade.php:418) ay may inline style: `<th style="width: 9%...">Type</th>` — nandoon naman ang Type column, sobra-strict lang ang assertion. Fix: relaxed sa `assertSee('Type</th>', false)` (line 167). Result: 4 passed / 25 assertions. (2) **PurchaseRequestTest** (badge_counts_all_unread_but_list_limited_to_ten): luma na ang assertion — ang NotificationController ay sadyang binago (limit 10 → default 50 + `?limit=` param + infinite scroll; badge = true unread total). Fix: updated test — 15 created = 15 returned sa default, at explicit `?limit=10` ay nagre-return ng 10 habang 15 pa rin ang count. Kasama ring tinanggal ang mojibake char sa comment (line 1240). Result: PASSED. (3) **Full suite: 341 passed / 1315 assertions / 0 failed** — unang beses na fully green ang suite mula nang maitala ang mga pre-existing failures (baseline noong D9.17: 312 passed / 2 failed). Tests-only na changes — walang app/production code na binago.

- **D9.26 ICT Form Section Cleanup: TAPOS (Sept 17 2026)** — (1) **SERVICE REQUEST NO + RID merged into one field**: label ay `SERVICE REQUEST NO: RID -` at ang input ay puro request number (hal. `REQ-2026-09-17-0001`) — ang DB ay nananatiling pure number (`service_request_no` UNIQUE column walang prefix), `rid` column ay `'RID'` na via hidden input (nililinis ang lumang junk values (8545, 58455...) sa susunod na save). Parehong web form (`_ict_form_sections.blade.php`) at PDF (`pdf/ict-form.blade.php` — tinanggal ang hiwalay na RID row). (2) **DATE RECEIVED autofill** — kapag empty at admin/IT ang nag-e-edit, default = ngayong araw (`fmtDate(... ?? now())`); view mode ng lumang ticket na walang date ay blangko pa rin. (3) **END-USER label**: `INITIAL DIAGNOSIS:` → `DESCRIPTION OF REPAIR REQUEST:` (consistent na sa PDF na "Description of Repair Request:" na pala; ang IT-section "Initial Diagnosis:" ay hindi ginagalaw — tama para sa IT). (4) **REPAIR COST (₱) field removed** sa web form (Section 5/After Repair) — walang DB migration; **data-loss fix**: ang `cost` mapping fallback sa `TechnicianUpdateIctTicketAction` at `SignIctAcceptanceAction` ay `null` → `$repairRequest->cost ?? null` (para hindi mabura ang naka-save na cost kapag nag-save na walang field; create/resubmit ay null pa rin — new cycle). Orphan `.ict-cost-input` CSS din ang tinanggal. (5) **`ACTION TAKEN / RECOMMENDATION:` (IT section, `it_remarks`) renamed to `REMARKS:`** — tugma sa PDF label; ang SERVICE PROVIDER section na `actionTaken` label ay hindi ginagalaw (tama para sa SP flow, PDF "Action Taken / Recommendation:"). (6) **Section headers**: tinanggal ang black left border (4px) at black vertical `::before` bar sa `.section-header` (base + responsive CSS) — gray box + section title ang natira; PM form ay walang `.section-header` usage kaya hindi apektado. (7) **Beripikasyon**: `php -l` clean · focused test 15/15 (51 assertions) · **full suite 341 passed / 1315 assertions / 0 failed** · zero JS/test references sa mga binago na field ids.
- **D9.27 Repair Type: 5 Checkboxes → 2 Clean Dropdowns: TAPOS (Sept 17 2026)** — (1) **Desisyon**: ang 5 checkboxes (INTERNAL REPAIR / EXTERNAL REPAIR / REFERRED TO SERVICE PROVIDER / WITHIN WARRANTY / BEYOND WARRANTY) ay may **dalawang dimension pala** — repair mode (magkakatapat) at warranty (magkakatapat din) — kaya **2 dropdowns** ang ginawa (TYPE + WARRANTY), hindi isang flat dropdown (sisirain nito ang pagpili ng mode + warranty nang sabay, na kailangan ng lumang data). (2) **UI/UX**: flex row na may `TYPE` at `WARRANTY` inline labels, "— Select type —" placeholder, `ucfirst` display ("Internal repair"), reusable `.ict-type-selects` CSS (gap 12px, min-width 200px, wrap sa mobile). (3) **Zero backend/DB change**: 2 hidden `name="repairType[]"` inputs ang sync ng JS (value = dropdown value; **disabled kapag empty** para hindi mapasama sa submission) — ang sasabmit pa rin ay JSON array format; `json_encode($data['repairType'])` mapping hindi binago; **prefill** mula sa JSON (mode = unang tugma sa 3 modes, warranty = unang tugma sa 2 warranty options) kaya tama ang lumang tickets (multi-value data ay napipick ang una, malilinis sa susunod na save). (4) **SP trigger rewire**: palit ang `.repair-type-cb` checkbox listener sa dropdown `change` — kapag naging "REFERRED TO SERVICE PROVIDER" ang TYPE dropdown, lalabas ang banner + ma-a-activate ang SERVICE PROVIDER section (pareho ng logic, `keepActive` intact). (5) **PDF — walang gagalawin** (checkbox render mula sa JSON, format unchanged). (6) **Orphan CSS cleanup**: `.checkbox-group.compact-checkbox` (ict-form/_controls.css, mobile-responsive/_base.css at _phone-portrait.css) — walang natirang blade user (maintenance ay `checkbox-group-minimal`, ibang klase); `.checkbox-label`/`.radio-label` rules ay naiwan (ginagamit pa rin ng ICT radios at pm-schedules). (7) **Beripikasyon**: view:clear ✓ · focused test 15/15 ✓ · **full suite 341 passed / 1315 assertions / 0 failed** ✓ · zero test references sa repairType (walang nasira).
- **D9.28 ICT Form Label Cleanup + RECEIVED BY Alignment: TAPOS (Sept 17 2026)** — (1) **CAPS normalization**: lahat ng hindi-CAPS labels ay ginawang CAPS para konsistente — "RECEIVED by:" → "RECEIVED BY:", "Initial Diagnosis:" → "INITIAL DIAGNOSIS:", "Repair Type:" → "REPAIR TYPE:", "Technician/IT Personnel/End-User Signature over Printed Name:" → CAPS, at lahat ng "Date:" → "DATE:" (web form + sections). **PROTEKTADO (hindi ginagalaw)**: "Privacy Notice & Waiver:" (`_privacy_notice.blade.php` — `<strong>` element, hindi label) at ang acknowledgement sentence ("I hereby acknowledge...") — buong-buo ayon sa instruction. (2) **Required asterisk visual removed**: burado ang `.required::after` CSS rule (content " *") sa `ict-form/_base.css` — ang `class="required"` at validation ay **mananatili** (display-only ang tinanggal). (3) **RECEIVED BY alignment fix**: ang MIDDLE NAME input ay dati placeholder-only na hiwalay sa ilalim (hindi pantay ang box sa LAST/FIRST) — ginawang **ikatlong column sa parehong row** na may sariling "MIDDLE NAME" inline-label (kagaya ng NAME OF TECHNICIAN section sa SP). (4) **Walang kulay/logic/DB na binago** — text + 1 CSS rule + 1 markup restructure lang. (5) **Beripikasyon**: view:clear ✓ · focused test 15/15 (51 assertions) ✓ · zero natirang non-CAPS labels (maliban sa 2 protektado) ✓.
- **D9.30 Dashboard CSM Snapshot: Isama ang PM sa CSM stats: TAPOS (Sept 17 2026)** — (1) **Bug:** ang CSM Satisfaction card ng SA dashboard (SuperAdminDashboardAction) ay may where('requests.type','ICT') filter — ang PM surveys (2 sa DB) ay hindi binibilang, kaya '14/14 responded · 100%' ang ipinapakita kahit 16 ang totoong surveys. Tama ang average math (sqd1–9 Likert 5..1, mean over answered) pero kulang ang coverage. (2) **Fix (Option A):** tinanggal ang type filter — kasama na ang lahat ng request types (ICT + PM); pinalitan ang completedIctCount ng completedRequestCount (lahat ng completed, branch-scoped) para consistent ang numerator/denominator; dinagdagan ang surveys query ng division_admin_review_status='Approved' (hardening laban sa >100% rate); in-update ang 2 view refs sa dashboard/super-admin.blade.php. (3) **Resulta (live):** 16/34 responded · 47% · average 4.9/5 — ang 34 ay 14 completed ICT + 20 completed auto-generated PMs (lahat ay is_auto_generated=1), kaya totoo ang 47% dahil bihira sumagot ng survey ang end users ng scheduled PMs. Kung gusto namang i-exclude ang auto-generated PMs sa denominator, quick follow-up lang. (4) **Beripikasyon:** php -l ok · full suite 341 passed / 0 failed (1315 assertions) · live dashboard render HTTP 200, card shows 4.9/5 at 16/34 · 47%. Files: SuperAdminDashboardAction.php, dashboard/super-admin.blade.php.
- **D9.31a CSM Stats Foundation (Phase 1): TAPOS (Sept 17 2026)** — (1) Bagong `app/Services/CsmStatsService.php` = single source of truth ng CSM math: `scoreFor()` (case-insensitive scoring ng ARTA labels — ligtas sa legacy "Neither Agree Nor Disagree" na malaking N), `severeCount()/isSevere()` (SD ≥3/9 = SEVERE_SD_THRESHOLD=3), `averageForSurveys()` (N/A at unknown excluded sa average), `perColumnAverages()`, `weakestColumn()`, `SQD_LABELS` (offset: DB sqd1 = form "SDQ0"), `optionLabels()/validationLabels()`. (2) Refactor ng `SuperAdminDashboardAction` para gamitin ang service — output unchanged (verified live: 3.9 · 22/34 · 65%). (3) `StoreCsmSurveyRequest`: mas mahigpit na validation — `in:` ang 5 ARTA labels + `N/A` (ang N/A ay valid pero hindi isko-score, excluded sa average — nahuli ito dahil nag-fail ang CsmArchivePdfTest: may N/A checkbox pala bawat SQD row sa form). (4) Beripikasyon: focused 13/13 (41 assertions) · full suite 352 passed/1355 assertions/0 failed · live dashboard numbers unchanged. Commit `5469343`.
- **D9.31b/c Dashboard CSM Card (Phase 2): TAPOS (Sept 17 2026)** — (1) **D9.31b** (`189cfd2`): trend chip + overdue PM chip BESIDE the number (hindi 4th line sa ibaba — ang 6 tiles ay iisang grid row ayon sa D9.9); padding override `.stats-grid > .stat-card-premium { padding: clamp(12px,1.1vw,16px) !important }` laban sa 24px padding ng built admin bundle; measured via headless-Chrome harness: rowStretch=0px sa 6 breakpoints. (2) **D9.31c** (`6a60d95`, user decisions): tinanggal ang bare "▲1.9" chip (walang unit/reference — nakakalito) at ang "Weakest: SDQ2" line (siksik sa ~200px tile) — pinalitan ng **ARTA/CSC descriptive bands** (`ARTA_BANDS`: 4.21–5.00 Very Satisfied … 1.00–1.80 Very Dissatisfied): score colored by band + sub line "Satisfied · 67% satisfied · 24/36"; **CRITICAL FIX: SATISFACTION_COLUMN sqd8 → sqd1** (ang sqd1 = form SDQ0 "I am satisfied with the service" — ang tunay na overall-satisfaction question; ang sqd8 = SDQ7 online support lang pala); trend lumipat sa hover tooltip na kumpleto ("Lower than Aug 2026: 5.0 → 4.0") at gumagamit ng **this-month average** hindi overall snapshot (bug na nahuli ng test — inayos via `csmCurrentAvg`); trend hidden hangga't both months < MIN_TREND_SAMPLE=3. (3) Dalawang stale duplicate test files (lumang stat-chip API) pinalitan ng isang `CsmDashboardCardTest` (8 tests: band rendering, color class, tooltip show/hide, empty state, band boundaries, SDQ0 semantics, trendDirection flat). Beripikasyon: 500 error (stale compiled view) ayos via view:clear · full suite 360 passed/1378 assertions/0 failed · live render 200 OK. Commits `189cfd2`, `6a60d95`.
- **D9.32 CSM Monthly Summary Report (Phase 3 — susunod gawin): TAPOS (Sept 18 2026, commit `35dc070`)** — (1) **Layunin:** isang PDF, dalawang gamit — ARTA/CSC compliance (page 1) + internal improvement review (page 2). Mga user decision: **PLAIN LANGUAGE** (hindi technical — buong tanong, walang SDQ codes, "Rating" hindi "Mean", pangungusap na comparison), **walang comments section, WALANG ticket numbers (printable docs = aggregate lang dahil matatrace ang respondent sa ticket), walang signature block, 2 pages lang**. (2) **Page 1 (opisyal, ARTA format):** letterhead same style ng csm-form PDF (NCMB navy #0f2a6b); plain-terms summary box (respondents, overall rating + ARTA band, % satisfied, response rate); per-question table sa FORM ORDER (SDQ0–SDQ8): counts bawat scale point (5/4/3/2/1, N/A excluded), average, interpretation; weakest row highlighted; scale legend. (3) **Page 2 (internal, aggregate lang):** "What we need to improve" (lowest-rated question + ilan ang disagree + comparison sa nakaraang buwan + simpleng mungkahi), "The good news" (top 2), respondent profile (Male/Female lang). (4) **Delivery:** `csm:monthly-report {month?}` command (default current month; any month on-demand; no-data month = gagawa pa rin na "no responses recorded" para buo ang annual record); auto tuwing ika-1 ng buwan 7:10AM para sa NAKARAANG buwan (1 schedule line sa routes/console.php); save sa `storage/app/csm-reports/{Y-m}/` (private disk); 🔔 bell + 📧 **EMAIL** sa SA pagkatapos gumawa (summary + download link — user decision: hindi lang bell, may email din). (5) **CSM email exception** sa `Notification::booted()` L42: dagdag na `&& !str_starts_with($type, 'CSM')` — pinapayagan ang email sa SA para sa CSM types (safe sa flood: bundled by design, ~2–4 emails/buwan). (6) **1 SA-only download route** `/csm/reports/{month}/download` (pattern ng `ict.pdf`). (7) **Planned files:** `app/Services/CsmMonthlyReportService.php` (gagamit ng CsmStatsService), `resources/views/pdf/csm-monthly-report.blade.php`, `app/Console/Commands/CsmMonthlyReport.php`, `tests/Feature/CsmMonthlyReportTest.php`.
- **D9.32 IMPLEMENTATION NOTES: TAPOS (Sept 18 2026, commit `35dc070`)** — (1) **Implemented per locked plan:** (a) `CsmStatsService` + `SQD_QUESTIONS` const (9 buong tanong, eksakto mula sa form) + `questionFor()` helper; (b) bagong `CsmMonthlyReportService::build($month)` — per-question counts bawat scale point (5/4/3/2/1, N/A excluded), averages + ARTA bands, MoM (overallPrev + prevAverage per column), weakest/second-weakest, good news (top 2), respondent profile (Male/Female), response rate (completed_at windowed sa buwan, Approved semantics same sa dashboard); (c) 2-page plain-language PDF (`pdf/csm-monthly-report.blade.php`, same NCMB letterhead style ng csm-form): page 1 = plain-terms box + per-question table (buong tanong, walang SDQ codes) + overall row + weakest row highlighted amber; page 2 = "What we need to improve" (lowest-rated + % disagree + went down/up sentence + generic action) + second-lowest + good news + who answered — **walang comments/tickets/signatures** per locked decisions; (d) `csm:monthly-report {month?}` — default = previous month kapag run sa ika-1 (scheduled), current month kung hindi; writes sa **storage/app/private/csm-reports/{Y-m}/** (TANDAAN: ang 'local' disk root ay storage/app/private sa Laravel 13 — hindi storage/app); (e) notification bawat SA: bell + **email** (log preview verified: "Your CSM Monthly Report for September 2026 is ready. Overall: 3.9/5 (Satisfied) · 21 response(s) · 71% satisfied · lowest-rated: ...") + download URL; (f) `Notification::booted()` — CSM* email exception (verified: CSM type = email lumalabas, non-CSM type = in-app lang pa rin); (g) SA-only route `/csm/reports/{year}/{month}/download` (verified live: user role → 302, SA → 200 application/pdf) na may on-demand generation; (h) schedule `csm:monthly-report` monthlyOn(1, 07:10) sa restored scheduler (BUG-SCHED-1). (2) **Live result (Sept data):** 21 respondents · overall 3.9 (Satisfied) · 71% satisfied · 33 completed · 64% response rate · M19/F2 · weakest SDQ2 @ 3.9 — PDF 15.4KB generated. (3) **Bugs na nakuha habang ginagawa:** duplicate `@endif` sa blade (syntax error, fixed); float-vs-int assertion (responseRate 100.0 — `round()` ay laging float); local-disk root discovery. (4) **Tests:** bagong `CsmMonthlyReportTest` — 6 tests/35 assertions (scale-point counts, weakest+MoM, empty month, command PDF+notification+email, route guard, CSM-only email exception) · **full suite 366 passed/1413 assertions/0 failed**.
- **D9.33 Real-Time Severe Alert + D9.34 Weekly Digest (Phases 4–5): PLAN LOCKED (Sept 17 2026)** — (1) **D9.33 real-time:** trigger per SINGLE survey — SD sa ≥3/9 tanong = severe; **hindi per batch** (kahit 1 lang sumagot buong araw, kapag grabe = agad; 1–2 tanong lang ang negative = walang agad na alert, sa weekly/monthly lang makikita); bell + email na may **listahan ng binagsak na tanong** (buong text ng lahat ng Disagree/SD answers — ang tanong ay hindi personal data, hindi makakilala ng respondent); **daily bundle: max 1 alert/araw** (dedup via notifications-table query, type-based, walang migration); hook sa `StoreCsmSurveyAction` pagkatapos ng DB commit. (2) **D9.34 weekly digest (Lunes 7:05AM, csm:weekly-check):** overall rule **ARTA-aligned** (pinalit sa dating arbitrary <4.0): pumasok sa Neutral band o mas mababa (≤3.40) **o** drop ≥0.3 vs nakaraang linggo, may **≥5 surveys guard**; **per-question BIG WARNING**: ≥3 kliyenteng Disagree sa iisang tanong sa linggo **o** question avg ≤2.60, may ≥3 responses guard — mag-fi-fire kahit maayos ang overall; 💚 recovery note (balik sa band pataas) + 🏆 milestone (4 sunod na linggong ≥4.5); dedup 1/linggo. (3) **Alignment principle:** lahat ng layers (dashboard card, real-time alert, weekly digest, monthly PDF) ay gumagamit ng IISANG `CsmStatsService` math at IISANG confidentiality rule — aggregate lang sa kahit anong notification/report, walang pangalan/ticket/comments; email ay English. (4) **Phase 6 cleanup:** irehistro sa `routes/console.php` ang 3 naulilang commands (pm:send-reminders 6AM, parts low-stock check 7AM, assets:verify-set-integrity 8AM — PATUNAY na hindi tumatakbo: `schedule:list` ay 1 entry lang noon at 0 notifications sa DB; Laravel 13 scheduling ay sa routes/console.php hindi sa app/Console/Kernel.php na dead code); i-verify ang schedule:work/Task Scheduler sa production.
- **DEFERRED (hindi gagawin sa scope na ito):** CSM web reports page (Option B — PDF muna, page pwedeng sundan); ticket numbers/pangalan/client comments sa kahit anong report o notification; technician visibility sa scores (kailanman); DB migrations (zero schema change); signature block sa PDF.
- **BUG-SCHED-1 Dead Scheduler — 3 commands na kailanman hindi tumatakbo: TAPOS (Sept 18 2026)** — (1) **Ang issue:** ang `app/Console/Kernel.php` ay may `schedule()` method na may 4 schedules (GenerateScheduledPM 2AM, SendPMDueReminders 6AM, CheckPartsLowStock 7AM, VerifyAssetSetIntegrity 8AM) — PERO ang Laravel 13 (modern structure) ay hindi na nagbabasa ng Kernel.php: ang `bootstrap/app.php` L11 ay naka-register LANG sa `routes/console.php` (commands source), kaya ang buong `schedule()` method ng Kernel ay **dead code** — hindi kailanman tinatawag. (2) **Patunay (live):** `php artisan schedule:list` ay may 1 entry lang (`pm:generate-scheduled` midnight — ito ang nasa routes/console.php kaya gumagana); DB evidence: **0** low-stock notifications at **0** reminder notifications sa buong history. (3) **Epekto:** walang awtomatikong PM due reminders (6AM), walang low-stock alerts (7AM), walang daily asset-set integrity check (8AM) — tahimik na hindi gumagana, walang crash. Ang GenerateScheduledPM 2AM entry ay dead duplicate lang ng gumaganang routes/console.php entry. (4) **Fix plan:** (a) i-verify ang `$signature` ng 3 commands; (b) i-add sa `routes/console.php`: `Schedule::command('pm:send-reminders')->dailyAt('06:00')`, `parts:check-low-stock` 7AM, `assets:verify-set-integrity` 8AM; (c) tanggalin/i-mark deprecated ang patay na Kernel.php schedule method; (d) `schedule:list` dapat ipakita lahat; (e) **manual test run** ng bawat command (unang beses pa lang silang tatakbo — i-verify na walang notification spam / may guard). (5) **Runner caveat (user decision: manual muna dahil walang server pa):** ang registration ay hindi sapat kung walang runner — sa local, manual command run o `php artisan schedule:work` muna; kapag may server na, **isang cron line** lang: `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` — at LAHAT ng nasa routes/console.php ay awtomatikong gagana. (6) **FIX SHIPPED (commit `30220e2`, Sept 18 2026):** 3 schedules naka-register sa routes/console.php (inventory:verify-asset-sets — hindi assets:verify-set-integrity ang tunay na signature), Kernel.php **burado** (zero references sa buong codebase), `schedule:list` = 4 entries lahat may Next Due, manual first runs verified (integrity: 0 violations read-only · low-stock: combined summary, 4 notifs, may --dry-run option · PM reminders: per-branch summary, 4 emails, local = log mailer), full suite 360 passed/0 failed.
- **BUGFIX-CSM-CMD-1 Invalid Carbon Method in CsmMonthlyReport Command: TAPOS (Sept 21 2026)** — (1) **Ang Issue:** Sa `app/Console/Commands/CsmMonthlyReport.php` L77, ang date resolver ay tumawag ng `$today->isDayOfMonth(1)`. Ang `isDayOfMonth()` ay hindi umiiral sa `Carbon`/`CarbonImmutable` ng Laravel 13, nagdudulot ng `BadMethodCallException: Method Illuminate\Support\Carbon::isDayOfMonth does not exist.` kapag pinatakbo ang command. (2) **Fix:** Pinalitan ng native Carbon property check: `$today->day === 1`. Na-verify na tumatakbo ang scheduled at on-demand month resolution nang walang error.
- **D9.32h CSM Monthly Summary Report PDF Refinement: TAPOS (Sept 21 2026)** — (1) **1-Page A4 Portrait Enforcement:** Na-recalibrate ang margins at paddings (`@page { size: A4 portrait; margin: 9mm 11mm 8mm 11mm; }`) para maiwasan ang DomPDF right-side clipping at matiyak na 100% sakto sa 1 page nang walang overflow. (2) **Single-row Metadata Bar:** Ginawang iisang row ang Reporting Period, Date Generated, at Coverage (Whole Office - ICT Unit) gamit ang table layout na may `white-space: nowrap`. (3) **Symmetrical Insight Cards:** Pinalitan ang dating uneven at unstyled text cards ng pantay na 50%/50% side-by-side modules para sa "Top Performing Dimensions" at "Area Needing Improvement". Nilagyan ng rank pill badges (`#1`, `#2`, `LOW`, `2ND`), official ARTA dimension names, aligned rating scores, at favorable/disagree percentages. (4) **Full-Width Demographic Profile:** Inilipat ang Respondent Profile bilang malinis na horizontal anchor card sa ilalim bago mag-footer na may breakdown ng bilang at percentage. (5) **Font Metric & Template Fixes:** Pinalitan ang DomPDF-unsupported character (`&rarr;` → `to`) at tinanggal ang duplicate footer block sa ilalim ng Blade template. (6) **Centralized ARTA Dimensions:** Idinagdag ang `CsmStatsService::SQD_DIMENSIONS` at `dimensionFor()` helper; tinanggal ang inline lookup array sa Blade template para 100% dynamic at sumusunod sa single source of truth pattern.
- **D5b CSM Survey Form PDF Header Alignment: TAPOS (Sept 21 2026)** — (1) Pinalitan ang acronym na `NCMB` sa header ng `resources/views/pdf/csm-form.blade.php` ng buong opisyal na ahensya: **`NATIONAL CONCILIATION AND MEDIATION BOARD`**. (2) Inayos ang typography (`font-size: 16px; font-weight: bold; color: #0f2a6b; letter-spacing: 0.5px; line-height: 1.15;`) para pareho sa letterhead ng Monthly Summary Report. (3) Na-verify na nananatiling eksaktong 1 page A4 portrait ang survey copy. (4) **Tests:** 28 tests / 109 assertions sa buong CSM test suite = 100% passed.
- **D9.33 CSM Real-Time Severe Alert (Phase 4): TAPOS (Sept 22 2026)** — (1) **Bagong `app/Services/CsmSevereAlertService.php`** (`NOTIFICATION_TYPE = 'CSM Severe Alert'`): `check(CsmSurvey $survey): bool` — tatawagin ang `CsmStatsService::isSevere()` (Strongly Disagree sa ≥3/9 SQD, existing math mula D9.31a); **dedup 1 alert/araw** via query sa mismong notifications table (`where('type', ...)->whereDate('created_at', today())->exists()`) — **walang migration, walang bagong table**; kapag severe at first sa araw: bell + **email** sa bawat `super_admin` via `Notification::send` (ang CSM* email exception sa `Notification::booted()` mula D9.32 ay sakop na nito — walang bagong code doon; email count sa lokal: log preview). (2) **Message format (English, plain language, aggregate-only):** "Severe CSM alert: X of 9 answers were Strongly Disagree. Failed questions: 1) "buong tanong text" — Strongly Disagree · 2) ..." — buong tanong text via `CsmStatsService::questionFor()` (hindi SDQ codes), kasama ang lahat ng **Disagree at Strongly Disagree** answers, **walang pangalan/request number** (pareho ng confidentiality rule ng weekly digest at monthly PDF); URL = `dashboard.super-admin`. (3) **Hook sa `StoreCsmSurveyAction`** pagkatapos ng DB commit + archival PDF block: `if (isset($survey) && $survey) { app(CsmSevereAlertService::class)->check($survey); }` — non-blocking by contract (sariling try/catch + `Log::warning` sa service; hindi kailanman ma-a-istorbo ang survey submission). (4) **Tests: `tests/Feature/CsmSevereAlertTest.php` 5 tests/19 assertions** — severe fires sa lahat ng SA + 2 email queued; non-severe (2 SD lang) silent + `Mail::assertNothingQueued`; dedup 1/araw (2 severe surveys, isang alert lang); next-day refire (rolled `created_at` ng notification kahapon); aggregate-only (walang full_name at request number sa message). **Gotcha na-nadiskubre:** ang `created_at` ay HINDI nasa `$fillable` ng `Notification` — ang mass `update(['created_at' => ...])` ay silent-drop; property-assignment (`$n->created_at = ...; $n->save()`) ang gumagana (gaya ng pattern sa ibang tests). (5) **Beripikasyon:** focused 5/5 ✓ · **full suite 372 passed / 1438 assertions / 0 failed** ✓ · walang bagong schedule (event-driven — walang linya sa `routes/console.php`).
- **D9.34 CSM Weekly Digest (Phase 5): TAPOS (Sept 22 2026)** — (1) **Bagong `app/Services/CsmWeeklyDigestService.php`** — `build(Carbon $anchor)` sa window na Monday 00:00–Sunday 23:59 ng linggo ng $anchor (same `whereBetween('created_at', ...)` selection semantics ng monthly report; lahat ng math ay via `CsmStatsService`): (a) **Overall watch (ARTA-aligned, pinalit sa dating arbitrary <4.0):** linggong average ≤3.40 (Neutral band pababa) **o** drop ≥0.3 vs nakaraang linggo — may **MIN_WEEK_SAMPLE = 5** guard para ang ilang sagot lang ay hindi umiiyak ng lobo; (b) **Per-question BIG WARNING:** ≥3 kliyenteng Disagree/SD sa iisang tanong sa linggo **o** question average ≤2.60 — may **MIN_QUESTION_SAMPLE = 3** guard; **gumaganap kahit maayos ang overall**; (c) **💚 Recovery note:** nakaraang linggo ay watch/low band, ngayong linggo ay good band na (balik sa band pataas); (d) **🏆 Milestone:** 4 sunod na linggo (kabilang ang reported week) na lahat ≥4.5 average. (2) **Bagong `app/Console/Commands/CsmWeeklyCheck.php`** — `csm:weekly-check {week?}` (default: nakaraang lingwo kapag Lunes umaga); **dedup 1/lingwo** via notifications-table query (`type` + `whereDate('created_at', today())` — walang migration); walang-laman na linggo = skip na may warning (walang notification); message = headline ("CSM weekly digest for Sep 14 - Sep 20, 2026: 7 response(s), overall 4.1/5 (Satisfied).") + bawat flag na pinaghihiwalay ng " · ": overall watch / drop sentence, per-question warnings na may buong tanong text at bilang ng sumasang-ayon, recovery, milestone, o "No action needed this week." kapag malinis; bell + email sa bawat SA (CSM* exception), URL = `dashboard.super-admin`. (3) **Schedule sa `routes/console.php`:** `Schedule::command('csm:weekly-check')->weeklyOn(1, '07:05')` — Lunes 7:05AM, bago pa ang 7:10AM monthly report; `schedule:list` = verified (Next Due: 5 days from now). (4) **Tests: `tests/Feature/CsmWeeklyDigestTest.php` 7 tests/29 assertions** — overall ≤3.40 flags (5 surveys all-Neither → 3.0, walang per-question warning); drop ≥0.3 flags kahit good band (5.0 → 4.0); 5-survey guard (4 all-SD = walang overall alarm PERO per-question warning pa rin); per-question warning habang healthy ang overall (3 sa 5 nag-Disagree sa sqd2, overall ~4.8); milestone 4 sunod na linggo ≥4.5; recovery Neutral → Very Satisfied (peroneo ang milestone); command sends + dedup same-day rerun + 1 email queued. (5) **Beripikasyon:** focused 7/7 ✓ · **full suite 379 passed / 1467 assertions / 0 failed** ✓ · WALANG bagong code sa `Notification.php` (CSM* exception mula D9.32 ang sumasakop sa 'CSM Weekly Digest').
- **D9.34b Weekly Digest: Email Only + Short Message: TAPOS (Sept 22 2026)** — (1) **User decision pagkatapos ng live demo:** ang weekly digest ay **EMAIL LANG — walang bell** ("ang haba ng message siguro mas okay email nalang to tapos maikli lang"), dahil ang digest ay lingguang babasahin, hindi agad-agad na abala (ibá ang severe alert — mananatiling bell + email dahil urgent ito). (2) **`CsmWeeklyCheck` command rewrite:** (a) **Direct email** via `Mail::to($admin->email)->queue(new SystemNotificationMail(full_name, 'CSM Weekly Digest', $message, 'N/A', $url, branch, region))` — **bypass ang `Notification::send`** para WALANG bell row sa notifications table; (b) ginaya ang mga safety rule ng `Notification::booted()`: local env = `RequestNotificationService::logLocalEmailPreview` (readable preview sa laravel.log), production = skip ang alias emails (`str_contains '+`'); (c) **dedup lumipat sa Cache** (`Cache::put('csm-weekly-digest-' . today()->toDateString(), true, now()->endOfDay())`) — dahil wala nang bell row na pagbabatayan; isang digest kada araw (Lunes lang ang schedule kaya isang kada linggo). (3) **Short message rule:** buo nang hindi kina-list ang lahat ng per-question warnings — kapag **1** ang warning, sasabihin kung anong tanong at ilan ang sumang-ayon; kapag **≥2**, isang linya lang: "**N of 9 questions flagged** (worst: \"<buong tanong ng pinakamababang average>\" — X disagreed, avg Y/5)" — ang buong detalye ay sa dashboard. Live result sa totoong data (Sep 14–20, 6 puro-SD surveys): *"CSM weekly digest for Sep 14 - Sep 20, 2026: 6 response(s), overall 1/5 (Very Dissatisfied). Overall is low (1/5, Very Dissatisfied) — please review. Down 4.0 from last week (5 to 1). 9 of 9 questions flagged (worst: 'The office followed the transaction's requirements...' — 6 disagreed, avg 1/5)."* — ~430 characters mula ~2,000. (4) **Test update** (`CsmWeeklyDigestTest`): `Cache::flush()` sa test start (dedup state ay cache na, hindi DB — hindi dapat mag-retain sa pagitan ng suite runs); command test ngayon ay nagsisiguro **0** bell rows + **1** queued email na may maikling message + deduped rerun (still 1). (5) **Beripikasyon:** focused 7/7 (28 assertions) ✓ · **full suite 379 passed / 1466 assertions / 0 failed** ✓ · 2 lumang demo CSM Weekly Digest bell rows sa dev DB ay inalis (email-only na ang design). Commit `b52cf54` = orihinal na D9.34; ang pagbabagong ito ay hiwalay na commit.


- **D9.34c Weekly Digest: Bell Row Binalik + Bell Look Cleanup (Maikling Message Pa Rin): TAPOS (Sept 22 2026)** — (1) **User decision pagkatapos ng live demo:** *"may bell dapat na nakapag send na ng email ganon dapat"* — **binalik ang bell row** para sa weekly digest (ang D9.34b ay email-only), dahil kapag may email na ipinadala ay dapat may katumbas na entry sa 🔔 bell dropdown para makita agad sa system. (2) **`CsmWeeklyCheck` (revert ng delivery ng D9.34b):** (a) `Notification::send($admin->id, null, 'CSM Weekly Digest', $message, route('dashboard.super-admin'))` ulit — **bell + email** (sakop ng CSM* exception sa `Notification::booted()`; walang bagong code doon); (b) **dedup bumalik sa notifications table** (`where('type','CSM Weekly Digest')->whereDate('created_at', today())`) — walang migration (tinanggal ang Cache-based dedup ng D9.34b); (c) **mananatili ang MAIKLING message** (rule ng D9.34b): headline + hanggang 2–3 flag sentence; kapag ≥2 warnings = *"N of 9 questions flagged (worst: \"…\" — X disagreed, avg Y/5)"* (~430 chars mula ~2,000); (d) walang-laman na linggo = warning lang, walang notification. (3) **Bell look cleanup:** (a) **tinanggal ang vertical line** — desktop 4px `::before` bar (`resources/css/admin/_ui.css`) at **mobile 3px `border-left: 3px solid #0038A8 !important`** (`resources/css/mobile-responsive/_phone-portrait.css`); redundant ito dahil puro-unread naman ang laman ng dropdown — **ang bughaw na tuldok na lang ang unread marker** (nananatili ang unread tint); (b) **override sa layout** (`resources/views/layouts/app.blade.php`): `.notif-item.unread::before { display:none; }` at `.notif-item.unread { border-left:none !important; }` — kailangan ito dahil **hindi naka-rebuild ang Vite assets** (public/build = Sept 17) at ang inline `<style>` (L19–184) ay dumarating pagkatapos ng `@vite` link (L17) kaya nananalo ito sa cascade kahit `!important` pa ang mobile rule; (c) **contextual link label:** *"Open Ticket"* kapag may `request_number` (ticket notifications) at *"View Details"* kapag wala (CSM digest/alert/monthly at iba pang system notices), tooltip = "Click to view details". (4) **Bagong regression test: `tests/Feature/NotificationBellStyleTest.php`** (1 test / 7 assertions) — sinusuri ang **totoong rendered SA dashboard HTML**: naroroon ang dalawang override, **nauna ang `/build/assets/` link** kaysa sa override (cascade order), at **malinis ang source CSS modules** (para hindi na muling bumalik ang linya kapag `npm run build`). (5) **Live demo sa dev DB:** inalis ang **48 duplicate `CSM Monthly Report` bells** (galing sa paulit-ulit na PDF regen ng D9.32) — pinanatili ang pinakabago kada SA; inalis ang lumang 2 digest rows at pinatakbo ulit ang `csm:weekly-check 2026-09-14` → **2 sariwang UNREAD bells** (#568 `batolina@gmail.com`, #569 `test.superadmin@cmms.test` @ 10:16) + email preview sa `laravel.log`; same-day rerun = *"already sent today"* (deduped). (6) **Beripikasyon:** `CsmWeeklyDigestTest` **7 passed / 30 assertions** ✓ · `NotificationBellStyleTest` **1 passed / 7 assertions** ✓ · **full suite 380 passed / 1475 assertions / 0 failed** ✓ · `php artisan serve` HTTP 200 ✓.

- **Phase 6 — Production Scheduler/Mail Runbook (CSM Roadmap, huling yugto): ✅ TAPOS bilang DOKUMENTO (Sept 22 2026)** — (1) **Bagong `docs/PRODUCTION_DEPLOY_CHECKLIST.md`** (~375 linya, 10 seksyon) = ang go-live runbook na dati ay nagkalat lang sa mga changelog entry: server requirements (PHP 8.3+, `dom/fileinfo/gd/intl/mbstring/openssl/pdo_mysql/zip`, MySQL, document root = `public/`, Node para sa build) · **ang 6 na scheduled command** (`pm:generate-scheduled` 00:00 · `pm:send-reminders` 06:00 · `parts:check-low-stock` 07:00 · `inventory:verify-asset-sets` 08:00 · `csm:weekly-check` Lunes 07:05 · `csm:monthly-report` ika-1 07:10) · **ang isang cron line** (`* * * * * cd /path && php artisan schedule:run`) kasama ang Windows Task Scheduler na katumbas (inline `.bat` na nasa md lang — **walang bagong file sa repo**, ops-side ang mga script) at `schedule:work` para sa local · **Mail+Queue** (`Notification::booted()` L114-120: kapag `MAIL_MAILER=smtp` ay `send()` na agad, kung `log/array` ay `queue()` — kaya sa dev ay may **409 pending jobs** na live na ebidensya ng "bakit walang email" na senaryo sa production) · deployment steps (maintenance mode → git pull → `composer install --no-dev` → **`npm run build`** [hindi naka-track sa git ang `public/build`] → `.env` → `migrate --force` → private-disk permissions → `optimize` → `up`) · storage/backup/retention (ang `storage/app/private` ay **legal records** — pirmas, PDFs, survey copies; ang `*.pdf` ay nasa `.gitignore`) · go-live verification (7 agad-agad + 7 smoke test + unang gabi) · 10 patibong (pinaka-mahalaga: **walang dedup ang `csm:monthly-report`** — huwag patakbuhin muli sa parehong araw; deduped naman ang weekly/severe via notifications table) · rollback procedure. (2) **Live verification (Sept 22 2026, dev machine):** server 200 ✓ · `/up` 200 ✓ · `schedule:list` **6/6** ✓ · `php artisan about` = local/Debug OFF/Asia-Manila/mysql/database ✓ · `schedule:run -v` = "No scheduled commands are ready to run" ✓ · `parts:check-low-stock --dry-run` = "4 low, 4 critical" na **walang ipinadala** ✓ · **dedup live test:** muling patakbo ng `csm:weekly-check 2026-09-14` → *"already sent today — deduped (1 per week)"*, bilang ng rows **hindi nagbago** ✓ · CSM bells sa DB: #560/#561 Monthly (Sept 21) · #562/#563 Severe (09:11) · #568/#569 Weekly Digest (10:16) ✓. (3) **Walang code na binago** — purong dokumento: main doc 1000 → 1002 linya (changelog + 6 heading/checkpoint corrections: §5a D5, §5c D7, §8 D8, §9 D9, Git Checkpoints D4 at D9 ay "✅ EXECUTED" na) + **bagong file** `docs/PRODUCTION_DEPLOY_CHECKLIST.md` (375 linya). **Ang natitirang gawain ng Phase 6 ay ops-side lang at nangyayari kapag may production server na:** isang cron/Task Scheduler entry, `.env` values, `npm run build`, at ang "unang gabi" na verification sa §7.

- **D9.35 User Dashboard Quick Actions: Isang Card + Admin-style Blue sa Hover/Click (Sept 22 2026)** — (1) **User feedback:** *"un sa quick action ng user is parang na sa labas dapat na sa iisang card sila tapos un pag na click or na ano ng arrow (mouse pointer) dapat magaya katulad sa admin."* (2) **Problema (na-verify sa code):** ang *Quick Actions* label at ang 2 button ay **nakalutang lang sa page background** (walang card), at ang `.action-button-premium:hover` ay **`border-color` lang** ang binabago — **nananatiling puti** ang loob, kaya walang blue feedback kagaya ng admin (`btn-action-premium:hover { background:#0038A8; color:#fff }`). Ang admin naman ay may `.queue-panel` card pa (D9.22). (3) **Fix — isang view lang, `resources/views/dashboard/user.blade.php`:** (a) **`.queue-panel` card** — eksaktong kopya ng admin (bg `#fff`, border `rgba(0,0,0,.05)`, radius `12px`, padding `16px`, shadow, `margin-bottom:20px`); ang label + 2 button ay nasa **loob na ng iisang card**; (b) **blue sa hover/click/focus** — `.action-button-premium:hover, :active, :focus-visible { background:#0038A8; color:#fff; border-color:#0038A8; transform:translateY(-2px) }` — **dagdag ang `:active`/`:focus-visible`** (sa admin ay `:hover` lang) para gumana rin sa **touch/phone** at keyboard; (c) **readability habang blue** — `.action-title` → puti, `.action-subtitle` → `rgba(255,255,255,.85)`, count chip → `rgba(255,255,255,.2)` bg + puting text; (d) **gated state hindi nagbabago** — `.action-restricted` (pula, `pointer-events:none`) at dagdag na `.action-restricted:hover/:active { background:#fff5f5 !important; transform:none !important }` para walang blue kahit mapindot; (e) **walang icons** (user decision: *"remove mo un icon"*). (4) **Kasama sa parehong batch (admin):** tinanggal ang `<i>` icons sa **3 button ng Management Tools** (`fa-list-check`, `fa-boxes-stacked`, `fa-users-gear`) — text-only na ang "Manage Requests / Inventory & Assets / Manage Personnel"; nananatili ang `.btn-action-premium:hover i` rule (dead na ngayon, hindi nakakasama, at balik agad kapag may icon muli). (5) **Test-first — bagong `tests/Feature/UserDashboardQuickActionsTest.php` (4 tests / 21 assertions; unang patakbo = 3 failed, tamang RED):** structural na DOM check na ang `ribbon-label` + **2 `a.action-button-premium`** ay **loob ng iisang `queue-panel`**; regex sa `.action-button-premium:hover, :active, :focus-visible { background: #0038A8`; readability (subtitle `rgba(255,255,255,0.85)`) at chip-inversion rules; gated state = 1 `action-restricted`, **0 `<a>` sa card**, at rose-hover rule; at links (`ict.create`, `profile.assets`) + "3 active items assigned". **Natutunan:** ang page-wide `assertStringNotContainsString(route('ict.create'))` ay **mali** — nasa **sidebar** din ang parehong link, kaya ang assertion ay ini-scope sa card via `DOMXPath` (`//div[contains(@class,'queue-panel')]`). (6) **Walang migration, walang bagong route, walang `npm run build`** — inline sa `@section('styles')` ang lahat ng CSS (kagaya ng admin), kaya Ctrl+F5 lang ay lilitaw agad ang bagong itsura. (7) **Beripikasyon (Sept 22 2026):** `view:clear` ✓ · focused `UserDashboardQuickActionsTest` **4/4 (21 assertions)** ✓ · admin dashboard render `UnfinishedFirstTest` **11/11** ✓ (walang nasira sa pagtanggal ng icons) · **full suite 384 passed / 1496 assertions / 0 failed** (380 baseline + 4 bago) ✓ · zero temp files.

- **D9.36 Super Admin Management Tools: Blue sa Hover/Click (Sept 22 2026)** — (1) **User request:** *"move tayo sa super admin Management Tools gawain mo din ng blue un"* — ipagpatuloy ang D9.35 consistency drive (user dashboard) sa **super admin** dashboard. (2) **Problema (na-verify):** ang SA Management Tools panel ay nasa card na at icon-free na, **pero ang `.mgmt-tool-link:hover` ay light grey lang** (`background:#f8fafc; border-color:#e2e8f0`) — **walang blue feedback**, hindi kagaya ng admin (`btn-action-premium:hover → #0038A8`) o ng bagong user dashboard. (3) **Fix — CSS lang sa `resources/views/dashboard/super-admin.blade.php` (`@section('styles')`):** (a) default state ng link ay ginawang admin-like — `background:#f8fafc`, `border:1px solid #e2e8f0`, `border-radius:10px` (dati: transparent border + 8px), `margin-bottom:6px`; (b) **blue sa hover/click/focus** — `.mgmt-tool-link:hover, :active, :focus-visible { background:#0038A8; border-color:#0038A8; transform:translateY(-2px); box-shadow:0 4px 10px rgba(0,56,168,.25) }`; (c) **readability habang blue** — `.mgmt-tool-title` → `#fff`, `.mgmt-tool-desc` → `rgba(255,255,255,.85)` (nananalo sa base rules dahil mas mataas ang specificity ng descendant selector, kahit nasa unahan ng file ang bagong rules). (4) **Walang markup na binago** — 4 na link (`Master List`, `Manage Users`, `PM Schedules`, `Maintenance Calendar`) at ang card wrapper ay ganoon pa rin; **CSS lang** + `:active`/`:focus-visible` (para sa touch at keyboard). (5) **Test-first — bagong `tests/Feature/SuperAdminManagementToolsTest.php` (2 tests / 8 assertions; unang patakbo = 1 failed = tamang RED):** structural na `DOMXPath` check na ang heading na "Management Tools" ay iisa at ang **4 `a.mgmt-tool-link`** ay nasa loob ng parehong card (may label-order assertion na `Master List → Manage Users → PM Schedules → Maintenance Calendar`); at regex sa blue rule + title/desc inversion. (6) **Beripikasyon (Sept 22 2026):** `view:clear` ✓ · focused **2/2 (8 assertions)** ✓ · full suite **386 passed / 1504 assertions / 0 failed** (384 + 2 bago) ✓. **Walang migration, walang route, walang `npm run build`** — inline CSS, Ctrl+F5 lang.


- **D9.37 QR Batch Sticker Print (Supply Officer): Double-Toggle Selection Bug + Status Filter Fix: TAPOS (Sept 22 2026)** — (1) **Ang bug (na-report ng user sa QR part ng supply officer):** sa `inventory/qr-batch.blade.php`, ang document-level `click` handler (row toggle, L583-588) ay **hindi nag-e-exclude ng checkbox clicks** — kaya sa bawat pag-click ng checkbox sa row ay may dalawang magkatunggang toggle: (a) ang row handler (`toggleRow` → `toggleById(id, !cb.checked)` + manual `cb.checked = !cb.checked`) at (b) ang **native checkbox toggle** (default action ng click) + `change` handler (`toggleById(id, cb.checked)`). Ang dalawang ito ay nagkansela — **check-then-uncheck agad**, ang Set ay bumabalik sa empty, ang count ay nananatiling "0 selected", at ang **"Print Selected" button ay hindi kailanman nag-e-enable** → hindi magagamit ang buong batch QR print. (2) **Fix:** guard clause sa click handler — `if (e.target.closest('input[type="checkbox"]')) return;` — ang checkbox ay hawak na ng native toggle + `change` handler, ang row-click toggle ay para lang sa totoong row clicks. (3) **Bonus fix #1 — status filter vs totoong DB:** ang filter ay may option na **"Defective" (0 assets sa DB — walang silbi)** at **kulang ang "Under Maintenance" (9 totoong assets — invisible sa filter)**. Verified sa live DB: Active 334 · Spare 48 · For Repair 15 · Under Maintenance 9. Pinalitan ang "Defective" ng "Under Maintenance". (4) **Bonus fix #2 — duplicate markup:** burado ang nakadobleng `<div class="table-container">` (L381-382). (5) **Test-first:** bagong `tests/Feature/QrBatchSelectionTest.php` (1 test / 9 assertions) — unang patakbo **FAILED** sa guard assertion (tamang RED), pagkatapos ng fix ay **GREEN**: sinusuri ang rendered HTML ng `/inventory/qr-batch` bilang `supply_officer` — naroon ang guard clause, ang 4 totoong status options (Active/Spare/For Repair/**Under Maintenance**), **WALA** ang "Defective", at naroon ang print button + sticker URL. **Natutunan:** ang `{{ route('x') }}` ay nage-evaluate sa rendered HTML — i-assert ang resulting URL (`/inventory/qr-sticker/`), hindi ang blade source. (6) **Beripikasyon:** focused test GREEN ✓ · full suite 387 passed / 1513 assertions / 0 failed ✓ · live `/inventory/qr-batch` HTTP 200 ✓ · backend (route/controller/sticker SVG) ay beripikadong maayos na simula una — front-end JS lang ang sira. Files: `resources/views/inventory/qr-batch.blade.php`, `tests/Feature/QrBatchSelectionTest.php`.

- **D9.38 PM Ticket na "ICT Support Request" ang Concern/Subject: AYOS NA (Sept 22 2026)** — (1) **Bug (na-report ng user):** sa Recent Activity / Recent Requests tables, ang mga PM (Preventive Maintenance) ticket ay "ICT Support Request" ang nakalagay sa Concern/Subject kahit hindi ICT. (2) **Root cause (2 layers):** (a) **Data** — ang `GeneratePMScheduleService::generate()` ay gumagawa ng `RequestModel::create()` na **WALANG `description`** (verified: **27/27 PM tickets** sa dev DB = NULL description); (b) **Display** — kapag NULL ang description, may hard-coded fallback na `'ICT Support Request'` sa `dashboard/user.blade.php:357`, `dashboard/admin.blade.php:370`, at `'Technical Support Request'` sa `dashboard/it.blade.php:364` — lahat ICT-worded, kahit PM ang ticket. (3) **Fix sa source (data):** ang generation service ay naglalagay na ng makabuluhang description: `"Scheduled Preventive Maintenance — {schedule_name} ({focus_division})"` (hal. *"Scheduled Preventive Maintenance — PMS (VOLUNTARY ARBITRATION DIVISION)"*). (4) **Fix sa display (single source of truth):** bagong `getConcernLabelAttribute()` accessor sa `App\Models\Request` — description passthrough kung may laman; kung wala, **type-aware fallback**: PM → `"Preventive Maintenance"`, iba → `"ICT Support Request"`. Ginamit sa 4 views: `dashboard/user` (L357), `dashboard/admin` (L370), `dashboard/it` (L364), at `requests/index` (L270, dati "N/A"). (5) **Backfill sa 27 existing PM tickets** (one-time script, precedent: D9.20-P3): description mula sa linked `pm_schedules.schedule_name` + `current_focus_division`; **27 → 0 remaining NULL**. (6) **Test-first:** bagong `tests/Feature/PmConcernLabelTest.php` (**4 tests / 14 assertions**; unang patakbo = 3 failed = tamang RED): (a) accessor type-aware (PM NULL → "Preventive Maintenance", ICT NULL → "ICT Support Request", may description → passthrough); (b) auto-generated PM ticket ay may hindi-NULL na description na may schedule name; (c) user dashboard ay hindi nagla-label ng PM bilang ICT; (d) admin dashboard ICT fallback ay bu pa rin (ICT-only table). **Natutunan sa test #3:** ang assertion na `assertStringNotContainsString('ICT Support Request')` sa buong page ay over-assertion kung may sadyang NULL-description na ICT row sa test data — dapat may totoong description ang ICT row para ang tanging posibleng pinagmulan ng text ay ang PM fallback. (7) **Beripikasyon:** focused **4/4 (14 assertions)** GREEN ✓ · full suite **391 passed / 1527 assertions / 0 failed** (387 + 4) ✓. Files: `app/Models/Request.php`, `app/Services/GeneratePMScheduleService.php`, 4 blades, 1 test.

- **D9.39 Notification Bell na "From: <sarili mo>" — AYOS NA (Sept 22 2026)** — (1) **Bug (na-report ng user):** *"tama ba 'yan… nag-submit ng ICT pero siya rin ang FROM"* — sa notification bell, ang mga self-addressed notification (status update ng sariling ticket) ay nagpapakita ng **"From: <requestor>"** — ang requestor mismo ang recipient, kaya mukhang nag-notify ang sarili mo sa sarili mo. (2) **Root cause:** walang `from`/`actor` column ang `notifications` table (`user_id`, `request_id`, `type`, `message`, `url`, `is_read` lang) — ang `NotificationController::getNotifications()` ay nag-derive ng sender mula sa `request->user->full_name` (ang **requestor**). Tamang-tama ito para sa mga notification na para sa SA/IT ("New ICT Repair from JUAN…", "Job Order Assigned"), PERO mali para sa mga self-addressed ("Request Updated", "Request Completed", "Request Rejected", "PM Scheduled") — doon, ang actor ay ang IT/admin/system, hindi ang requestor. (3) **Fix (display-side, isang punto):** kapag self-addressed ang notification (`request->user_id === user_id`), tinatawag ang bagong `deriveSelfNotificationSender()`: (a) kung may pangalang aktor sa message — `/IT personnel\s+(.+?)(?=\s+(?:has|will|is|was)\b|\.(?:\s|$)|$)/i` — mula sa *"… is now Ongoing. IT personnel Marites Santos-Reyes has been assigned…"* → **"Marites Santos-Reyes"** (may whitespace-normalize + verb-guard laban sa *"IT personnel has updated…"* na walang pangalan); (b) kung wala → **"System"** (tamang label para sa auto-generated PM scheduling at status updates na walang actor). Ang admin-directed notifications ay **hindi nabago** (requestor pa rin ang From — tama). (4) **Test-first:** bagong `tests/Feature/NotificationFromLabelTest.php` (**3 tests / 9 assertions**; unang patakbo = 2 failed = tamang RED): (a) self-addressed assignment → sender = ang IT personnel sa message, hindi ang requestor; (b) self-addressed na walang aktor → "System"; (c) admin-directed → requestor pa rin (guard). **Natutunan:** ang lazy regex capture `(.+?)(?=\s+(?:has|…))` ay tumitigil sa unang verb boundary — kinakailangan ang verb-guard dahil ang *"IT personnel has updated…"* (walang pangalan) ay magpapadagdag ng buong sentence bilang kandidatong pangalan. (5) **Beripikasyon:** focused **3/3 (9 assertions)** GREEN ✓ · full suite **394 passed / 1536 assertions / 0 failed** (391 + 3) ✓ · walang migration, walang schema change (display-side lang ang fix). Files: `app/Http/Controllers/NotificationController.php`, `tests/Feature/NotificationFromLabelTest.php`.


- **D9.40 Pagination sa Lahat ng Listahan (Manage Requests / ICT Repair Requests / Personnel / PM): AYOS NA (Sept 23 2026)** — (1) **Bug (na-report ng user):** *"don sa may USER at ADMIN un sa may part ng Manage Requests at un sa ICT Repair Requests bakit nag-iba un mga ... <p class="text-sm text-gray-700 leading-5 dark:text-gray-600">Showing 1 to 20 of 22 results</p>"* — ang pagination bar ay lumitaw na hindi naka-style (plain text + plain boxes) sa halip na maging bahagi ng UI. (2) **Root cause (2 layers, kumpirmado laban sa aktwal na markup):** (a) **data threshold** — ang paginator ay lumalabas lang kapag lumagpas sa 20 rows ang listahan (`hasPages()`), kaya dati (<= 20) ay *"maayos"* — nang lumagpas na ang data (ICT user **22**, personnel **58**, PM **27**, requisitions 17) ay saka unang beses lumitaw ang bar; (b) **legacy CSS na para sa ibang markup** — ang `layouts/app.blade.php` ay may block na **"CSS for Pagination Fix (Final Clean)"** na isinulat para sa Bootstrap-style paginator (`ul/li`, `.active span`, `li[aria-current="page"]`), pero ang Laravel 11 ay nag-render ng **default Tailwind paginator** (div/span). Ang blanket na `nav[role="navigation"] a, nav[role="navigation"] span { padding: 8px 14px !important; border: 1px solid #dee2e6 !important; background: white !important; }` ay sumalo sa `<span class="font-medium">1</span>` / `20` / `22` ng *"Showing X to Y of Z results"* -> **naging kahon-buttons ang mga numero sa loob ng pangungusap**; ang current page ay hindi kailanman na-highlight (kailangan ng `li`); ang `nav[role="navigation"] > div:first-child { display: none !important; }` ay **sapilitang nagtago ng mobile prev/next block sa lahat ng screen size**; at ang buong button-group wrapper ay nagkaroon din ng sariling border (kahon-sa-loob-ng-kahon). (3) **Buong audit per role (deepview):** 10 bare `->links()` sa 9 blades — `requests/index` (user + it — ICT Repair Requests) · `admin/requests/index` (admin + supply_officer — Manage Requests) · `admin/personnel/index` (admin + super_admin — 58 users) · `requests/maintenance/index` (LAHAT ng roles) · `requests/maintenance/scheduled` at `pm-tasks` (it/admin/SA) · `inventory/physical-count` (admin) · `pm-schedules/index` (super_admin) · `requisitions/it-index` (2 tabs — it/admin/SA). Hindi kasama: super-admin AJAX paginations at supply workspace `vendor.pagination.parts` (may sariling styled UI na). (4) **Fix:** (a) **bagong shared view** `resources/views/vendor/pagination/cmms.blade.php` — *"Showing X to Y of Z results"* summary + windowed page numbers (**current +/- 2** + ellipsis + first/last, gaya ng pattern ng `parts.blade.php` at ng JS paginators ng SA/parts) + Prev/Next na may disabled state, div/span based na may sariling classes (`cmms-pag*`) at **walang `role` attribute** ang `<nav>` para hindi ito maabot ng legacy selectors; (b) **layout:** pinalitan ang sirang block ng `.cmms-pag` styles (inline sa layout — **hindi kailangan ng Vite rebuild** dahil hindi na-rebuild ang `public/build`, precedent D9.34c) at itinapon ang dead rules; (c) **10 `->links()`** -> `->links('vendor.pagination.cmms')`; (d) tinanggal ang dead pagination CSS sa `admin/personnel/index.blade.php` (rules para sa `span.current`/`svg`/`.disabled` na walang katumbas na markup); (e) EOL normalized sa LF ayon sa `.gitattributes` (`* text=auto eol=lf`). (5) **Tests (test-first):** bagong `tests/Feature/PaginationStyleTest.php` — (a) user na may 22 sariling ICT sa `/requests/ict`: may `cmms-pag__info`, eksaktong *"Showing 1 to 20 of 22 results"*, **wala** ang raw Tailwind markup (`text-sm text-gray-700 leading-5 dark:text-gray-600`) at **wala** ang legacy blanket selector; (b) admin sa Manage Requests: pareho; (c) personnel (21 users): pinatutunayan na shared ang view; (d) **windowing sa paglaki ng data:** 200 rows sa page 5 ng 10 pages -> **7 page numbers lang** + **2 ellipsis** + maabot ang page 10. **RED muna: 4 failed / 8 assertions** -> **GREEN: 5 passed / 24 assertions**; **full suite 399 passed / 1560 assertions / 0 failed** (baseline 394 + 5 bago). (6) **Nakadokumentong limitasyon:** ang filter/search ng mga listahang ito ay **client-side** (nasa naka-load na 20 rows lang) — kapag > 20 ang data, ang nasa ibang page ay hindi nakikita ng filter; ang server-side filtering ay hiwalay na phase (ang pagination ay tama na ang *total* kaya nakikita ang tunay na bilang). (8) **Hardening (para hindi na bumalik sa dati kapag dumadami ang data):** `Paginator::defaultView('vendor.pagination.cmms')` sa `AppServiceProvider::boot()` -- kaya kahit ang bagong list page na gagamit ng bare `{{ $x->links() }}` ay awtomatikong naka-style na (hindi na pwedeng bumalik sa raw Tailwind paginator); test #5 sa `PaginationStyleTest` ay nag-a-assert ng `Paginator::$defaultView` at na ang bare `links()` ay nagre-render ng `cmms-pag`. (7) **Rollback point:** `9ba3c28` (`git reset --hard 9ba3c28`).
- **D9.41 Server-Side Filtering ng Listahan (ICT Repair Requests / Manage Requests): AYOS NA (Sept 23 2026)** — (1) **Bug (na-report ng user):** *"dito mahihirapan un admin system... need natin to ma fix"* — ang filter ribbon (search / status / category) ay **client-side JS** lang: nagtatago lang ng rows sa loob ng **20 na naka-load**, kaya kapag lumagpas na sa 20 ang listahan (ICT user 22, admin scoped, personnel 58) ang mga tugma sa page 2+ ay **hindi nakikita**. (2) **Root cause:** ang `requests/index.blade.php` at `admin/requests/index.blade.php` ay may `function filterRequests()` na `row.style.display` lang ang ginagawa (walang query param), at ang `ListIctRequestsAction` ay `paginate(20)` **nang walang filter at walang `withQueryString()`**. (3) **Fix (server-side):** (a) **`ListIctRequestsAction`** — bagong `filters()` + `applyFilters()`: `q` (request_number + description; para sa admin/supply/super_admin ay kasama ang requestor_name, office, at requestor full name), `status` (whitelist: Pending/Ongoing/Completed/Rejected), `category` (via `whereHas('linkedAsset')` — tugma sa 7 totoong DB categories: Peripherals 128, Printer/Scanner 87, Monitor 69, Desktop 59, Network/Server 37, Laptop 24, Others 2); inilapat sa **lahat ng 5 role branches** bago ang pagination at may **`->withQueryString()`** sa lahat ng 5 `paginate(20)` — kaya nananatili ang filter sa page links; (b) **mga blade:** naging **GET form** ang ribbon (`name="q"`, `name="status"`, `name="category"`, Filter button + Reset na lumalabas lang kapag may filter, `onchange="this.form.submit()"` sa mga select) at **tinanggal ang client-side `filterRequests()` JS** — ang server na ang single source of truth. (4) **Bug na nahuli ng test (agapan):** dahil ang `officialsFirst()` ay may `left join users as official_users`, ang hindi table-qualified na `office`/`branch` sa `whereHas('user')` (admin/supply at super_admin scope) ay naging **`SQLSTATE[23000] 1052 Column 'office' in EXISTS subquery is ambiguous`** nang maidagdag ang office sa search OR-group — kaya **lahat ng scope columns ay qualified na** (`users.branch`, `users.office`, `users.full_name`, `requests.request_number/description/requestor_name/office`). (5) **Pagination view (count feedback):** ang `cmms.blade.php` ay nagre-render na ng *"Showing X to Y of Z results"* **kapag may rows** (kahit 1 page) at ang windowed page numbers lang ang lumalabas kapag >1 page — kaya may **agarang feedback** ang filter (hal. *"Showing 1 to 3 of 3 results"*). (6) **Tests (test-first):** bagong `tests/Feature/ServerSideFilterTest.php` (4 tests) — (a) user: 25 ICT (20 "Printer jammed" Ongoing + 5 "Monitor flicker" Pending) -> walang filter `of 25 results`; `?q=Monitor` -> `of 5 results` at wala ang Printer rows; `?status=Pending` -> 5; `?q=Monitor&status=Ongoing` -> empty state; (b) admin: 22 scoped ICT -> `of 22 results`, `?q=Santos` (requestor) -> `of 3 results` at si Maria Santos lang ang naka-render (ang admin table ay walang Description column kaya requestor/office ang hinahanap); (c) **filter nananatili sa page links** (`?q=Printer` -> may `q=Printer&page=2` na link); (d) GET form at wala nang `row.style.display`/`filterRequests` sa HTML. **RED muna: 4 failed / 8 assertions** -> **GREEN: 4 passed / 31 assertions**; **full suite 403 passed / 1591 assertions / 0 failed** (baseline 399 + 4 bago). (7) **Tandaan sa fixtures:** ang `RequirePendingSurvey` middleware ay nagre-redirect (302) kapag ang `user` ay may Completed ticket na walang CSM survey — kaya ang filter tests ay gumagamit ng Pending/Ongoing fixtures (valid pa rin ang Completed sa whitelist). (8) **Nakadokumentong susunod (kung kakailanganin):** ang ibang listahan (Personnel, Maintenance, Physical Count, PM Schedules) ay may sariling filter/search na hiwalay na imbestigahan; ang ICT + Manage Requests (ang pinaka-mabigat na listahan ng admin) ay tapos na. (9) **Rollback point:** `548d2ad`.
- **D9.41b Live Search + ID-Search Fix sa Filter Ribbon (Admin at User): AYOS NA (Sept 23 2026)** — (1) **Bug (na-report ng user):** *"wait sa may admin at sa user hindi sya working kahit tama un input ko"* at *"pag type ko palang nag lalabas na un mga possible"* — (a) ang bagong GET form ay kailangan pang pindutin ang **Filter** button o Enter (ang dating client-side `keyup` ay agarang nagtatago ng rows), kaya parang *"hindi gumagana"*; (b) **ang pinaka-malaking sanhi:** ang ID na nakikita sa page (`display_number`, hal. **ICT-2026-0028**) ay **hindi DB column** kundi **accessor** na galing sa `request_number` (**REQ-NCR-RCMB-2026-0028**) — kaya kahit "tama" ang input (kinopya mula sa screen) ay **hindi tumutugma** sa LIKE search -> 0 results. (2) **Fix:** (a) **live search** — debounce na **450ms** sa `input` event (`input.form.submit()`) at pagkatapos ng reload ay **autofocus + caret sa dulo** (`setSelectionRange`) para tuloy-tuloy ang pag-type; (b) **tinanggal ang Filter button** (kasama ang CSS nito) sa admin at user — hindi na kailangan; nananatili ang Reset link; (c) **ID-aware search** sa `ListIctRequestsAction::applyFilters()`: kapag ID-like ang input (walang space, may dash o puro numero) ay hinahanap din ang **bawat numeric group** ng `request_number` (hal. `ICT-2026-0028` -> `%2026%` AND `%0028%`) — kaya tumutugma na ang display ID, ang DB number, at ang numero lang; hindi apektado ang text search (may space) kaya walang false positives. (3) **Live verification laban sa totoong dev data (action-level, per role):** admin — `ICT-2026-0006` -> 1, `REQ-NCR-RCMB-2026-0006` -> 1, `-2026-0006` -> 1, requestor `Mike Fortes` -> 1, `status=Ongoing` -> 1; user (22 ICT) — `ICT-2026-09-17-0001` -> 1, `REQ-2026-09-17-0001` -> 1, `status=Ongoing` -> 6; IT (5 assigned) — `ICT-2026-0028` -> 1, `REQ-NCR-RCMB-2026-0028` -> 1, `ICT-2026` -> 5. (4) **Tests:** 3 bagong test sa `ServerSideFilterTest` (live search + walang Filter button sa user at admin; ID na nasa screen) — **RED: 1 failed / 6 passed** -> **GREEN: 7 passed / 46 assertions**; **full suite 406 passed / 1606 assertions / 0 failed** (baseline 403 + 3 bago). (5) **Rollback point:** `2c16cf4`.

- **D9.42 Per-Region Service Request Numbering (ICT + PM) + Reset Removal — PLANO/PHASED (Sept 23 2026)** — (1) **Goal:** palitan ang legacy `REQ-NCR-RCMB-2026-0006` / D9.24 `REQ-2026-09-17-0001` ng **per-region daily format**: **`ICT-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}`** / **`PM-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}`** (hal. `ICT-NCR-MAINOFFICE-2026-09-23-0001`) — bawat rehiyon/branch ay **sarili ang sequence** (gov't rule: hiwalay ang bawat office), araw-araw bumabalik sa `0001`. Kasama: **tanggalin ang Reset button** sa filter ribbons (user + admin). (2) **Deepview findings (6 risks):** (a) **3 generation path** — `CreateIctTicketAction` (may Auth user), `GeneratePMScheduleService::generate()` (maaaring console/scheduler — **walang Auth**, kailangang explicit ang region/branch), `CreateMaintenanceTicketAction` (Auth); iba-iba ang source ng region/branch per path. (b) `display_number` accessor ≠ DB `request_number` (screen `ICT-2026-0006` vs DB `REQ-NCR-RCMB-2026-0006`) — ok na ang ID-aware search (D9.41b) pero ipapasa ang analyzer/`parseRequestNumber` sa bagong 7-segment form. (c) **Mirror columns** na UNIQUE at naka-display sa form/PDF: `repair_requests.service_request_no` + `preventive_maintenances.service_request_no` — iu-update **kasabay** (`requests.detail_id` ang link). (d) Wala pang DB unique index sa `requests.request_number` — **idadagdag** (per-table UNIQUE na existing sa mirrors ay sapat na doon; ida-verify ang main table). (e) May mga test na naka-assert sa lumang format (`ServiceRequestNumberFormatTest`, `REQ-2026`/`REQ-NCR` sa ~5 tests) — i-uupdate kasabay ng display fallback. (f) Lahat ng dev DB data ay test data (**safe ang full renumbering**, inaprubahan ng user); hindi hinahawakan ang stored PDF/archive paths (timestamp-based, walang numero) kaya walang file renames. (3) **PHASES (bawat phase = isang commit, may rollback point):** **PHASE 1 — Reset removal + generator v2:** (i) alisin ang Reset `<a>` + `@if` + `.btn-filter-reset`/`.ad-filter-reset` CSS sa `requests/index.blade.php` at `admin/requests/index.blade.php` (magiging 0 lilit ang Reset; pa-reset na lang = paki-clear ang search box o piliin ang "All Status"/"All Categories"); (ii) bagong format sa `RequestHelpers::generateRequestNumber()` — prefix `REQ-` → `ICT-`/`PM-` (per type), idagdag ang `{REGION}-{BRANCH}-` (galing sa explicit `$region/$branch` params, **hindi** sa `Auth::()` — para gumana sa console path), daily counter unchanged (`LIKE '{prefix}-{region}-{branch}-{Y-m-d}-%'` → MAX+1), advisory lock key i-extend ng region/branch; (iii) lahat ng 3 call-site ay magpapasa ng region/branch (ICT: ticket data; PM scheduled: schedule/user region + explicit fallback; PM manual: Auth user). **PHASE 2 — Backfill migration command:** bagong `php artisan requests:renumber {--dry-run} {--region=} {--branch=}` — bawat row: bagong `request_number` (per region/branch/created-date sequence, sunod sa `created_at` para stable ang order), i-update ang **mirror** (`service_request_no` kung tugma ang luma), collision check bago ang write (kapag may banggaan = i-skip + i-report, hindi basta isinulat), **dry-run default** (walang isinusulat hangga't walang `--force`/confirmation flag); kasama ang soft-deleted rows (i-parallel check). **PHASE 3 — Display + templates + unique index + tests:** (i) `display_number`/`parseRequestNumber` sa `Request.php` — kilalanin ang 7-segment form (region/branch na may posibleng dash? **HINDI — gagawing walang dash ang region/branch code** via `preg_replace`/whitelist para hindi masira ang `explode('-')` parsing — i-confirm sa Phase 1 kung paano ito iu-encode: piliin ang **hyphen-free region/branch token**, hal. `NCR`/`NCRMAIN`, o i-escape ang dash bilang `--`); (ii) notification/PDF message templates na nagre-refer ng lumang numero; (iii) unique index migration (`requests.request_number`); (iv) update `ServiceRequestNumberFormatTest` + dependent tests (`REQ-2026`, `REQ-NCR` patterns). **PHASE 4 — Full suite + live verification + MD changelog:** buong suite (baseline **406 passed / 1606 assertions**), live check sa dev data (bago/lumang numero, mirrors, PDF/form display, search),更新 ang MD + `PRODUCTION_DEPLOY_CHECKLIST.md` kung may deploy step. (4) **Open question na ise-serialize sa Phase 1:** kung paano i-encode ang `branch` na may dash/space (hal. "Main Office" → token) — gagamit ng whitelist map katulad ng `getBranchCode()` at **hyphen-free** output lahat. (5) **Rollback:** kada phase ay hiwalay na commit — `git revert <phase-sha>`; Phase 2 backfill ay may `--dry-run` muna at ii-scan ang mirror bago ang write. (6) **Status: PLANO LANG — nakasulat bago ang execution.**


- **D9.42 Live verification laban sa dev DB (read-only, Sept 23 2026)** — (1) Bagong numero sa totoong data path: `ICT-NCR-RESE-2026-09-23-0001`, `ICT-RII-BATU-2026-09-23-0001`, `ICT-RVII-CEBU-2026-09-23-0001`, `PM-RXI-DAVA-2026-09-23-0001` — iba-iba ang rehiyon ay **hindi nagkokonsumo ng isa't isang sequence** (lahat `0001`). (2) **Kumpirmadong latent ang branch-code bug:** ang **bawat** row ng `requests` at **lahat ng 58 user** sa dev DB ay `region=NCR` / `branch=RCMB` lamang — kaya kahit ang lumang `str_contains` loop ay `NCR`/`RCMB` lang ang nababasa nito, at ang pagkakamali sa `REGION I`/`REGION II` ay **hindi kailanman lumabas** (naayos na ngayon: exact match muna, tapos longest keyword). (3) Display parity sa totoong rows: `REQ-NCR-RCMB-2026-0028` → screen `ICT-2026-0028`, buo `ICT-NCR-RCMB-2026-0028`; `REQ-2026-09-17-0001` → `ICT-2026-09-17-0001` / `ICT-NCR-RCMB-2026-09-17-0001`; `REQ-2026-09-23-0001` → `ICT-2026-09-23-0001` / `ICT-NCR-RCMB-2026-09-23-0001` — **tuloy ang pag-render ng lahat ng lumang numero**. (4) 0 sa 59 rows ang 7-segment pa lamang — suporta ito sa **Phase 2 backfill** na susunod.

- **D9.42 Phase 2 — `requests:renumber` backfill command — TAPOS (Sept 23 2026)** — (1) **Bagong file:** `app/Console/Commands/RenumberServiceRequests.php`; signature: `php artisan requests:renumber {--region=} {--branch=} {--dry-run} {--skip-orphans} {--force}`. **DRY-RUN ang DEFAULT** (walang `--force` = walang naiisulat; ang `--dry-run` ay laging nananalo kahit pinagsabay ang `--force`). (2) **Algoritmo = kaparehong-kapareho ng generator** para walang drift: pinapangkat ang rows sa `(prefix, getBranchCode(region), getBranchCode(branch), date(created_at))`, inaayos by `created_at, id`, at binibigyan ng `{PREFIX}-{REGION}-{BRANCH}-{date}-{NNNN}` simula `0001` kada araw — kaya ang unang araw ng bawat office ay laging `0001`. **Idempotent by construction:** ang row na nasa tamang format na ay `unchanged` (hindi ginagalaw), at ang pangalawang pagtakbo ay **0 row ang babaguhin** (may test). (3) **Mirror sync — 4 na number columns sa buong schema** (verified via `information_schema`): `requests.request_number`, `repair_requests.service_request_no`, `preventive_maintenance.form_no`, `preventive_maintenance.service_request_no`. Ang mirror ay **sinusundan lamang kung hawak pa rin nito ang lumang numero** (`value === old`); ang **banyagang halaga ay HINDI hinahawakan** at ini-uulat bilang `LEFT AS IS (...)` — hal. ang `preventive_maintenance.service_request_no` na may `REF-EXTERNAL-2026-0042` (may test). (4) **All-or-nothing write:** ang buong batch ay nasa isang `DB::transaction`, at ang bawat target ay **huling-huling ni-re-check** (`whereKeyNot($id)->exists()`) bago isulat; kapag may **collision** (may ibang request na may ganoon nang numero — hal. filtered-out na row o ibang `created_at` day) → **exit code 1 at WALANG isinulat kahit isang row** (hindi partial), dahil **tatlo sa apat na columns ay UNIQUE**. May `AuditLog::log('Renumber Service Requests', ...)` sa dulo ng write. Ang `--region`/`--branch` ay tumatanggap ng **code o buong pangalan** (`--region=RII` = `--region="REGION II"`, parehong `getBranchCode()` rule ng generator).
- **D9.42 Phase 2 — mga natuklasan at beripikasyon (Sept 23 2026)** — (5) **Mga natuklasan sa pre-flight laban sa totoong dev DB (read-only, `information_schema`) na kailangang itama sa plano:** (a) **MALI ang Phase 3(iii) sa plano** — **MAY UNIQUE index na** ang `requests.request_number` (**`requests_request_number_unique`**), kaya **hindi na kailangan** ang pagdaragdag ng index; may UNIQUE din ang `repair_requests.service_request_no` (`repair_requests_service_request_no_unique`) at ang `preventive_maintenance.form_no` (`preventive_maintenance_form_no_unique`). Ang **`preventive_maintenance.service_request_no` lang ang walang unique**. (b) **Mali rin ang table name sa plano** — ang totoong table ay **`preventive_maintenance` (SINGULAR)**, hindi `preventive_maintenances`. (c) **BUG na nahuli ng unang dry-run:** ang **`detail_id` ay type-scoped** — ang ICT rows ay tumuturo sa `repair_requests`, ang PM rows sa `preventive_maintenance`, at **nag-o-overlap ang mga id** (1..27); ang unang bersyon ay naghahanap sa **dalawang** table kaya ang ICT #1 ay nag-uulat ng `preventive_maintenance.form_no = PM-NCR-RCMB-2026-0001` (at kabaligtaran para sa PM) — **naayos** (type-aware lookup; may test na ang banyagang halaga ay `LEFT AS IS`). Hindi ito makikita sa unit test kung walang overlap sa id, kaya nahanap lang sa totoong data. (d) Ang **27/27 PM rows** ay `preventive_maintenance.service_request_no = NULL` habang ang **`form_no` ang sumusunod sa ticket** — kaya ang `form_no` ang totoong PM mirror. (e) **2 row sa dev ang walang `detail_id`** (`ZZTMP-CSM-CARD-001/002`, *"TEMP layout verification ticket"*, gawa ng layout QA) → ito ang dahilan ng **`--skip-orphans`**; mahalagang semantic: ang ini-skip na row ay **nagre-release ng slot nito**, kaya ang totoong ticket ng araw na iyon ay kumukuha ng **`0001`** at **walang gap** (may test sa **dalawang direksyon** — with at without the flag). (f) Ang **`requests.type` ay `ENUM('ICT','Preventive Maintenance')`** — kaya ang "unsupported type" guard sa command ay **hindi maaabot ngayon** (defensive lang para hindi tahimik na sumali ang bagong type sa ICT series; walang test dahil imposible via DB). (6) **Tests:** bagong **`tests/Feature/RequestRenumberCommandTest.php` — 12 tests, 37 assertions, lahat passed** — dry-run na walang isinusulat; force renumber na daily sequence; **araw-araw na `0001` restart**; ICT mirror (`repair_requests.service_request_no`) sync; PM `form_no` sync **habang hindi ginagalaw ang banyagang `service_request_no`**; **idempotency** (2nd run = 0 babaguhin); **collision → exit 1 at all-or-nothing** (walang naisulat kahit isang row, hindi naagaw ang numero); `--region` filter (ang ibang rehiyon ay hindi ginalaw); **soft-deleted rows** ay nire-renumber pero **hindi binubuhay**; `--skip-orphans`; at **tumutuloy ang live generator** pagkatapos ng backfill (`...-0001` → bagong ticket ay `...-0002`, **hindi nag-restart**). (7) **Dry-run laban sa totoong dev data:** **59 rows, 59 ang ire-renumber, 0 collisions, 0 skipped** — hal. `#1 REQ-NCR-RCMB-2026-0001 → ICT-NCR-RCMB-2026-08-20-0001`, `#7 PM-NCR-RCMB-2026-0001 → PM-NCR-RCMB-2026-09-01-0001`, `#56 REQ-2026-09-17-0001 → ICT-NCR-RCMB-2026-09-17-0001`, `#59 REQ-2026-09-23-0001 → ICT-NCR-RCMB-2026-09-23-0001`; **lahat ng ICT** ay `repair_requests.service_request_no: sync` at **lahat ng PM** ay `preventive_maintenance.form_no: sync` (`service_request_no: LEFT AS IS (empty)`). **Ang `--force` ay HINDI pa pinatakbo** sa dev DB — hinihintay ang go-ahead (Phase 4). (8) **Beripikasyon ng suite:** `php -l` malinis; **buong suite = 429 passed / 1679 assertions (54.79s, 0 failure)** = baseline na `417/1642` **+12 tests / +37 assertions** (eksaktong tugma sa bagong file, kaya **0 regression**).
- **D9.42 Phase 3(ii) — notification/email number lookup — TAPOS (Sept 23 2026)** — (1) **Production-impacting BUG na nahanap at naayos:** ang notification bell (`NotificationController::getNotifications`) at ang click-router (`NotificationController::resolveTargetUrl`) ay **`/REQ-[A-Z0-9-]+/` lamang** ang tinatanggap. Sa bagong format ay **walang match** → (a) `request_number` = **NULL** sa bell, at (b) ang pag-click ay **bumabagsak sa generic ticket LIST** kaysa buksan ang ticket. **Empirikal na beripikasyon** (probe script): ang lumang regex ay `NO MATCH` sa lahat ng `ICT-…`/`PM-…` na mensahe, samantalang ang bago ay tumatama — at **`REQ-…` legacy/D9.24 ay tumatama pa rin sa bago** (backward-compatible). (2) **Bagong `RequestHelpers::extractRequestNumber(?string $message): ?string`** — single source of truth para sa lookup na ito (ipinagbabawal ang pag-re-inline ng regex). Kinikilala ang **lahat** ng format na kailanman isinulat ng system: D9.42 `ICT-…`/`PM-…`, D9.24 `REQ-2026-09-17-0001`, at legacy `REQ-NCR-RCMB-2026-0001`. Ang pattern ay `\b(?:ICT|PM|REQ)-[A-Z0-9]+(?:-[A-Z0-9]+)*` — **bawat segment ay kailangang may alphanumeric**, kaya (a) **hindi nilulunok ang trailing separator** (`"…-0001 - please review"` → `…-0001`); (b) **hindi naaangkin ang `PR-…`** (procurement, sariling `prNumber()` helper) at ang mga salitang nagtatapos sa prefix token (`CONFLICT-123` → NULL). (3) **Tatlong wiring:** (a) `getNotifications` — `$reqNum` mula sa helper, at ang `PR-` regex ay **fallback** na lang; (b) `resolveTargetUrl` — helper din; (c) **`Notification::send` (email path)** — ang `$requestNumber` ay `request relation → prNumber() → extractRequestNumber() → 'N/A'`; dati ay `relation → prNumber() → 'N/A'` lamang, kaya ang **ICT/PM notification na walang `request_id` ay `'N/A'` ang numero sa email** (may **64 sa 525** notification sa dev DB na `request_id = NULL`). (4) **PDF blades — WALANG pagbabago, at ito ang tama:** ang `resources/views/pdf/ict-form.blade.php:392` (`$rr->service_request_no ?? $request->display_number ?? $request->request_number`) at ang `pdf/maintenance-form.blade.php:107` (`$pm->form_no ?? …`) ay **nagbabasa mula sa mirror column** na **nila-sync na ng Phase 2**, kaya awtomatikong tama ang nakalimbag na numero; ang manual PM form blade ay `$maintenance->form_no` din — **walang hardcoded na legacy format kahit saan** sa PDF/form blades. (5) **Tests:** bagong **`tests/Feature/NotificationRequestNumberMatchTest.php` — 16 tests / 42 assertions, lahat passed** — bell number para sa bagong ICT at PM na mensahe; legacy + D9.24 na pagbasa; `request_number = NULL` kapag walang numero; `PR-` ay hindi naaangkin; trailing separator; **pag-click ay bumubukas ng ticket** (ICT user → `ict.edit`, admin → `ict.show`, PM → `maintenance.show`, legacy → `ict.edit`); fallback sa list kapag walang katugmang ticket; `PR-` notification → `requisitions.index`; at direktang helper coverage (apat na format, trailing separator, unrelated references, `null`/blank input); **at ang stored na `url` ay laging nananalo** kaysa sa extraction (fallback lang ito).
- **D9.42 Phase 4 — live end-to-end beripikasyon + deploy checklist — TAPOS (Sept 23 2026)** — (1) **Backup muna:** `mysqldump` → `storage/ux_backup/cmms_pre_d942_renumber_20260923.sql` (ipinag-uutos ng §6; walang dump = huwag ituloy). (2) **Dry run:** **59 rows, 59 ire-renumber, 0 collisions, 0 skipped** — lahat ng ICT ay `repair_requests.service_request_no: sync`, lahat ng PM ay `preventive_maintenance.form_no: sync`. (3) **`--force` run:** **59 request number + 57 mirror value** ang naisulat, at may **`AuditLog #540`** (*"D9.42 Phase 2 backfill: 59 request number(s) and 57 mirror value(s) moved to the per-region daily format"*). (4) **Idempotency sa totoong DB:** ang pangalawang `--force` ay **0 request number** ang isinulat — kumpirmadong idempotent. (5) **Verification script (read-only, 8 checks):** `total=59, non-conforming=0`; `duplicates=0`; `type/prefix mismatch=0`; **12 groups, lahat `1..N` walang gap** (bawat araw ay bumalik sa `0001`); ICT mirror `linked=30, mismatch=0`; PM mirror `linked=27, form_no mismatch=0` (`service_request_no NULL=27/27`); at **2 orphan rows** (`#57`/`#58`, ang mga `ZZTMP` na row). (6) **LIVE end-to-end sa production action path:** gumawa ng **totoong ICT ticket** gamit ang `CreateIctTicketAction` (hindi mock) para sa asset na **walang active na request** → **`ICT-NCR-RCMB-2026-09-23-0002`** (dahil ang `#59` ay naging `…-0001` sa backfill, **tumuloy sa `-0002` — hindi nag-restart**). **14/14 check PASS:** numero, `type=ICT`, prefix `ICT-`, `-NCR-RCMB-`, araw; **mirror `repair_requests.service_request_no`**; `display_number` (`ICT-2026-09-23-0002`) at `full_display_number` (buo); `created_at` araw; **`AuditLog`**; **notification `#579`** (*"New ICT Repair from MIKE FORTES (ICT-NCR-RCMB-2026-09-23-0002) — RESEARCH AND INFORMATION DIVISION. Please review and assign IT personnel."*) kasama ang **`extractRequestNumber(message)` = buong stored number**; at `duplicates=0` pagkatapos. **Hard-deleted** pagkatapos ang ticket, mirror, notification, at audit row (test data lang — inaprubahan ng user). (7) **PDF blades:** **walang pagbabago** — ang `pdf/*.blade.php` ay gumagamit ng `$repair->service_request_no` / `$pm->form_no` (ang mirror) at may fallback sa `$request->request_number`, kaya **tuloy ang pag-print** sa bagong format; ang ICT list ay `display_number` (hindi nagbago ang hugis). (8) **Scoping finding:** bawat role ay **branch-scoped** (user=own, it=assignments, admin/SO=branch+office, **super_admin=sariling branch** maging sa dashboard) at ang sequence ay unique per **(region, branch)** — kaya **konstante ang region+branch sa loob ng anumang screen** at **hindi kailanman magsasama** ang `ICT-2026-09-23-0001` ng NCR at ng RII. **Desisyon: hindi binago ang display** — nananatiling makitid ang ID column sa lahat ng 39 na screen. (9) **`docs/PRODUCTION_DEPLOY_CHECKLIST.md`:** bagong **§5a D9.42 one-time backfill** (backup → dry-run → `--force` → idempotency check → per-region split → `--skip-orphans`; **exit codes** 0/1/2; **bakit maintenance mode** — hindi nagbabahagi ng `GET_LOCK` sa live generator), bagong **§7 rows 15–22** (regex/duplicate/prefix/`0001..N`/mirror-parity/continuation/bell-text/audit), bagong **§8 caveat #11**, at **§10 cross-reference**. (10) **Limitsyon na dapat sabihin:** ang **PM path ay hindi pinatakbo end-to-end sa live DB** — ang isang live PM run ay bumubuo ng **buong batch** (sobrang invasive); ito ay sakop ng unit tests (`RequestNumberPerRegionTest`: manual PM + scheduled PM na **walang `Auth`**) at ng Phase 1 live generator check (`PM-RXI-DAVA-2026-09-23-0001`). (11) **Suite:** 445 passed / 1721 assertions matapos ang Phase 3(ii).





- **D9.42 Phase 1 + Phase 3 (Display/Tests) — TAPOS (Sept 23 2026)** — (1) **Phase 1(i) Reset removal:** tuloy ang commit `ac623b1` (user + admin filter ribbons, walang Reset; `.btn-filter-reset`/`.ad-filter-reset` CSS burado) kasama ang 3 bagong assertion sa `ServerSideFilterTest`. (2) **Phase 1(ii) generator v2 — `RequestHelpers::generateRequestNumber(string $type, ?User $actorUser = null, ?string $region = null, ?string $branch = null)`:** prefix `REQ`→**`ICT`**/`PM` (per type), ang bagong stored format ay **`{ICT|PM}-{REGION}-{BRANCH}-{YYYY}-{MM}-{DD}-{NNNN}`**. Resolution order ng region/branch: **explicit param → `$actorUser` → `Auth::user()`** (console path walang Auth kaya explicit ang pinapasa). Daily counter + advisory lock ay **per (prefix, region, branch, day)** — hiwalay ang sequence ng bawat office at bumabalik sa `0001` araw-araw. **Latent BUG fix sa `getBranchCode()`:** exact match muna bago ang keyword scan, at ang keyword map ay **ini-sort by longest-key** — dati ay insertion-order `str_contains` kaya ang `'REGION I'` ay nanalo bago ang `'REGION II'` at ang **lahat ng numbered region ay tahimik na nagiging `RI`** (hindi ito lumabas noon dahil single-region lang ang dev data); dinagdagan din ng `trim()` ang input. (3) **Phase 1(iii) 3 call-sites:** ICT store → `generateRequestNumber('ICT', $user)`; **scheduled PM** → region/branch ay **`$user?->region ?? $actor?->region`** na **pagkatapos** ma-load ang `$user` (para ang numero ay hinding-hindi makakalayo sa aktwal na columns, at gumagana kahit Auth-less na scheduler); **manual PM** → `generateRequestNumber('Preventive Maintenance', Auth::user())` (kaparehong user na pinagmumulan ng `region`/`branch` ng tracking row). (4) **Phase 3(i) display/parser:** `parseRequestNumber()` ay kumikilala na sa **7-segment per-region form** (region/branch ay hyphen-free codes galing `getBranchCode()`); `full_display_number` ay **inauna ang naka-embed na codes** kaysa sa columns (may column na punong rehiyon name, hal. `NATIONAL CAPITAL REGION`); ang `display_number` ay **hindi nagbago ang hugis** (`ICT-2026-09-23-0001`) kaya tuloy ang lahat ng screen at ang ID-aware search ng D9.41b; ang legacy `REQ-NCR-RCMB-2026-0001` at D9.24 `REQ-2026-09-17-0001` ay **parehong readable pa rin**. (5) **Phase 3(iv) tests:** bagong **`tests/Feature/RequestNumberPerRegionTest.php` (10 tests)** — format, per-region/branch na hiwalay na sequence sa parehong araw, branch-code bug (exact-then-longest), `SYS` fallback, screen vs full display, legacy + D9.24 na pag-render, ICT store kasama ang **`repair_requests.service_request_no` mirror**, manual PM Auth fallback, at scheduled PM na **walang `Auth::user()`**; in-update din ang `ServiceRequestNumberFormatTest` (mula `REQ-` patungong `ICT-NCR-RCMB-`). (6) **Beripikasyon (Sept 23 2026):** `php -l` malinis sa lahat ng binagong file; **buong suite = 417 passed / 1642 assertions (55.31s, walang failure)**. (7) **Natitira (sunod na phase):** Phase 2 `php artisan requests:renumber` backfill — **TAPOS, tingnan sa itaas**; Phase 3(ii) notification/email number lookup — **TAPOS, tingnan sa itaas**; ~~Phase 3(iii) UNIQUE index sa `requests.request_number`~~ → **hindi kailangan, may UNIQUE na pala** (`requests_request_number_unique` — natuklasan sa Phase 2 pre-flight); **Phase 4** — live end-to-end beripikasyon (kasama ang **`--force` backfill run** sa dev DB) + `PRODUCTION_DEPLOY_CHECKLIST.md` — **TAPOS, tingnan ang Phase 4 entry sa itaas**.


- **D9.42 Phase 3(v) — Display surfaces: short form ang nakikita, buo lang ang nasa DB — TAPOS (Sept 24 2026)** — (1) **Desisyon (mula sa user):** ang **DB ay nag-iingat ng buong numero** (`ICT-NCR-RCMB-2026-09-23-0001` / `PM-NCR-RCMB-2026-09-04-0008`) para sa traceability (NAP/ISO 15489), uniqueness at future per-region reporting, pero **lahat ng screen, form, PDF at bell** ay nagpapakita ng **short form** (`ICT-2026-09-23-0001` / `PM-2026-09-04-0008`). (2) **Bagong `App\Models\Request::shortNumber(?string $number): string`** — ang **iisang** lugar na nagpapaliit ng naka-store na numero; **`static`** kaya nagagamit kahit walang model instance (bell payload, mirror `service_request_no` sa ICT form/PDF, at `form_no` ng PM). (3) **Bagong `Request::looksLikeTicketNumber()` guard — mahalagang desisyon:** ang `parseRequestNumber()` ay may legacy fallback branch na **lumilikha ng taon/numero para sa KAHIT ANONG dashed string** (hal. `PR-2026-0016` → `PR-2026-001`, `CONFLICT-123` → basura). Kaya ang **hindi ticket** (`PR-…` procurement, `PAR-…` property, `CONFLICT-…`, `ZZTMP-CSM-CARD-001` scratch rows) ay **hindi pinapaliit**; ang **`JO-…` job order** (5-segment na hugis, gaya ng `JO-NCR-RCMB-2026-0001`) ay **tuloy pa ring pinapaliit**. **Nahuli ito ng regression test:** ang unang (prefix whitelist) bersyon ay eksklusibo sa `ICT|PM|REQ` kaya **bumagsak ang `SupplyQueueSearchTest::test_tickets_tab_includes_pm_generated_job_orders`** — ang shape-based guard ang tamang solusyon (malawak sa prefix, mahigpit sa hugis: `PREFIX-YYYY-MM-DD-NNNN`, `PREFIX-REGION-BRANCH-YYYY-MM-DD-NNNN`, `PREFIX-REGION-BRANCH-YYYY-NNNN`; prefix = 2–5 letra). (4) **`display_number` ay idinagdag sa `Request::$appends`** — kaya kasama na ito sa **AJAX payload** ng Super Admin Master List (`super-admin/requests/index.blade.php` ay `${req.display_number || req.request_number}`) at ng **PM Work Orders** (`pm-schedules/orders.blade.php`) — walang hiwalay na column na kailangang i-sync, at ang mga narrow `select()` ay safe dahil walang numero = walang imbento (`PmConcernLabelTest`/`TicketCategoryFilterTest` ay pumasa). (5) **Mga surface na in-update (short form na):** ICT form (`SERVICE REQUEST NO:` — **tinanggal ang `RID -` prefix**; ang nakikitang box ay **readonly**, at ang **buong numero ay dinadaan sa hidden field** kaya hindi ma-de-demote sa save), ICT PDF, PM form (`Form No.` readonly display; **tinanggal ang `name="form_no"`** dahil **server-side** ito sine-set ng `CreateMaintenanceTicketAction` + `ResolveMaintenanceDetailAction` at **wala sa validation rules** ng `StoreMaintenanceRequest`/`UpdateMaintenanceRequest`), PM PDF, disposal tag, inventory detail, parts **stock-out picker**, at ang **notification bell** (`NotificationController::getNotifications` → `$n->request->display_number`). (6) **Beripikasyon (Sept 24 2026):** mapping table laban sa **12 tunay na sample** — `ICT-NCR-RCMB-2026-09-23-0001`→`ICT-2026-09-23-0001`, `PM-RXI-DAVA-2026-09-04-0008`→`PM-2026-09-04-0008`, `REQ-2026-09-17-0001`→`ICT-2026-09-17-0001`, `REQ-NCR-RCMB-2026-0028`→`ICT-2026-0028`, `JO-NCR-RCMB-2026-0001`→`JO-2026-0001`; at `PR-2026-0016`, `PAR-2026-0007`, `CONFLICT-123`, `ZZTMP-CSM-CARD-001`, empty at null → **hindi ginalaw**; `php -l` malinis; focused `SupplyQueueSearchTest` **20 passed (74 assertions)**; **buong suite = 445 passed / 1721 assertions (75.76s, 0 failure)**.
- **D9.42 Phase 3(vi) — Render-boundary normalizer: short form kahit buo ang naka-store sa message/email/audit/flash — TAPOS (Sept 24 2026)** — (1) **Iniulat na leak:** bumubulusok pa rin ang **buong numero sa loob mismo ng mensahe** ng bell, hal. *"IT has completed the repair for your ICT request **ICT-NCR-RCMB-2026-09-24-0001**…"* — dahil ~40 composer (Actions/Services/Http) ang nag-i-interpolate ng `request_number` sa `message` **pagkatapos ng creation time**, at ang `message` ay historical text na naka-store na (hindi display string). (2) **Desisyon: render-boundary fix — hindi 40 manual edit at hindi row rewrite.** Bagong **`App\Models\Request::shortenNumbersInText(?string $text): string`**: regex na naghahanap ng dashed tokens (`\b[A-Z]{2,6}-[A-Z0-9]+(?:-[A-Z0-9]+)*`), tapos bawat match ay dadaan sa **`shortNumber()`** na may **`looksLikeTicketNumber()` guard** → kaya **idempotent** (pangalawang pass walang babaguhin) at hindi ginalaw ang dayuhang identifier (`PR-2026-0016`, `PAR-2026-0007`, `ISO-15489`, `CONFLICT-123`, `SN-ABC123`). (3) **Kung saan inilapat (mga render/data boundary lamang):** **(a) bell** — `NotificationController::getNotifications`: `message` **at** `request_number` (badge sa `app.blade.php` `notif-req-badge`) ay short na, habang ang **`resolveTargetUrl()` ay nagbabasa pa rin sa RAW DB message** kaya pareho pa rin ang click routing (`ict.edit` / `maintenance.show` / PR list); **(b) email** — `SystemNotificationMail::build()`: subject (`#…`) at body; ginawa sa **build time (hindi constructor)** kaya sakop pa rin ang mga **nagawa nang naka-queue na mail bago ang change**; **(c) audit** — `GetAuditLogsDataAction`: `details` sa JSON ay short na (ang DB row ay buo pa rin para sa ISO 15489 traceability; ang server-side search ay `LIKE` pa rin sa stored full text); **(d) flash messages** — `StoreCsmSurveyAction` ("complete the survey for request …") at `RequirePendingSurvey` ("… satisfaction survey for …"), dumaan sa `shortNumber()`. (4) **Na-scan at hindi na kailangang baguhin:** walang ibang notification archive view (bell lang); `inventory/detail.blade.php` parts history ay `display_number` na ang gamit; ang ICT hidden field `serviceRequestNo` ay **sinadyang buo** (papasok sa DB, hindi display); walang kahit isang row na in-edit sa `notifications`, `audit_log` o `requests`. (5) **Tests:** 3 bagong test sa `NotificationRequestNumberMatchTest` — `the bell message text prints the short number` (may katunayan na **buo pa rin ang naka-store sa DB**), `the email subject and body print the short number`, `shorten numbers in text leaves foreign identifiers alone` (may idempotency + null/empty); at **5 dating assertion** ay in-update sa display-payload expectations (legacy `REQ-NCR-RCMB-2026-0001` → `ICT-2026-0001`, `REQ-2026-09-17-0001` → `ICT-2026-09-17-0001`; PR number ay hindi ginalaw). **`NotificationRequestNumberMatchTest` = 19 passed (57 assertions); buong suite = 448 passed / 1736 assertions (59.86s, 0 failure)**.

- **BUG-NOTIF-FROM-2 / D9.42 Phase 3(vii) — Bell "From" (pangalan sa tabi ng icon) = ang ACTOR na nag-perform ng aksyon, hindi ang ticket requestor — TAPOS (Sept 24 2026)** — (1) **Iniulat ng user:** mali ang pangalan sa bell dropdown (`From: <strong>${n.sender}</strong>`, `layouts/app.blade.php:543`): hal. kapag nag-assign ang System Admin ng IT → ang nakikita ng IT ay ang END-USER na nag-submit ng ticket; kapag ang IT/System Admin ang nag-request ng parts → ang nakikita rin ng supply ay ang user na nag-submit. Dapat ang **aktwal na gumawa** (admin na nag-assign / IT-SysAdmin na nag-request) ang nasa From. (2) **Root cause:** idine-derive ang `sender` palagi mula sa `request->user` (ticket REQUESTOR) + message-text regex — **walang record kung sino ang gumawa ng aksyon** (walang actor column ang `notifications` table; ~44 na `Notification::send()` call sites). (3) **Fix — bagong `sender_id` column** (`2026_09_24_000001_add_sender_id_to_notifications_table`, nullable FK → users, `nullOnDelete`): **`Notification::send(…, $url = null, $senderId = false)`** — default `false` = **auto-detect ang `Auth::id()`** → isang beses lang na fix na sumasaklaw sa LAHAT ng web call site nang walang44 na hiwalay na edit (assign, review, resubmit, technician update, requisition/PR, forward, acceptance sign, quick status, atbp.); explicit `int` = override; explicit `null` = system notice. Console/CSM commands ay walang Auth → `NULL` naturally; ang **`CsmSevereAlertService`** ay nagpapasa ng explicit `null` (may naka-sign-in na respondent habang nag-fire, pero hindi siya ang actor). (4) **Bell resolution order (`getNotifications`):** kapag may `sender_id` → pangalan ng **actor** (eager-load `sender` relation); kapag **actor == recipient** → `deriveSelfNotificationSender()` pa rin (hindi puwedeng "From: yourself" — BUG-NOTIF-FROM-1); kapag **NULL** (legacy rows/system) → dating heuristics (requestor → message regex → self-guard) — kaya lumaing data ay may fallback pa rin. Ang `resolveTargetUrl()`/message routing ay hindi ginalaw. (5) **Tests — `NotificationFromLabelTest` +4 (7 total):** assign → From = admin (**hindi** requestor); parts request → From = IT (**hindi** ticket requestor); submit → From = submitting user; actor == recipient → "System". (6) **Hindi binago** ang lumang notification rows (`sender_id NULL` = fallback behavior).

- **D9.42 follow-up — Legacy retention & conditional purge (files + code) — FILE PURGE NAKAGAWA (Sept 24 2026); CODE REMOVAL NAKATALI SA (#4)** — (1) **Bakit may entry na ito:** pagkatapos ng Phase 3(vii), sinurvey ang lahat ng natitirang "legacy" bago mag-move-on sa susunod na gawain — ang desisyon ng user ay **i-record muna ang plano sa docs** at ipatutupad kapag "okay na" (handa na ang prod/real data), habang ngayon ay **walang tinatanggal** (survey result: malinis ang `git status`, walang stash, walang `_d942_*`/test logs — na-delete na ang mga iyon). Ang mahalagang distinsyon ng entry na ito: ang **legacy DATA** (row sa DB — nawawala sa DB refresh) ay HIWALAY sa **legacy CODE** (hindi aapektuhan ng DB refresh — ang prod ang gumagamit nito). (2) **IPATUTUPAD PAG HANDA — FILE purge (gitignored lahat → ZERO git/test/prod impact):** **(a)** `storage/d5c_archive_20260907/` — **1,308 files, 3.29MB** (`legacy_public_signatures/` 223 + `public_signatures/` 1,085; `.gitignore:36`) — tinanggal na ang originals sa live paths noong D5C (`public/signatures` at `storage/app/public/signatures` ay wala na), orphan/test data lang ayon sa docs na ito mismo (*"i-archive/i-delete — test data lang, walang reconciliation"*); **(b)** `build.log` + `builderr.log` (root; `*.log` gitignore; Aug 24 pa huling na-write, walang tumutukoy). (3) **KEEP NGAYON — legacy CODE na PROD-SERVING (hindi test-only; ang dev DB refresh ay HINDI naglilinis ng prod data):** **(a)** sender-fallback sa `NotificationController::getNotifications()` (`sender_id NULL` → requestor → message-regex → self-guard) — tatlong dahilan: (i) kapag na-deploy ang `add_sender_id` migration sa prod, **lahat ng kasalukuyang prod notifications ay magiging `sender_id NULL`** at ito ang magpapakita ng tamang label sa mga unread lumang rows sa bell; (ii) **tuloy-tuloy ang NULL sender kahit pagkatapos ng refresh** — ang `CsmSevereAlertService` (explicit `null`) at ang console/PM scheduler (walang `Auth`) ay system notices na dito pa rin dumadaan; (iii) pag tanggal = blanko/`"null"` ang From ng system notices at **babagsak ang ≥2 existing tests** (`admin directed notification still shows the requestor`, `self addressed status notification shows the acting it person`); **(b)** `deriveSelfNotificationSender()` — **perpetual**, hindi legacy (gumagamit din ito ng bagong `actor == recipient` path; ito ang BUG-NOTIF-FROM-1 guard); **(c)** `REQ-` branch ng `RequestHelpers::extractRequestNumber()` — dev = **0 `REQ-` rows** (62/62 renumbered na) pero **hindi pa renumbered ang prod** (nasa `PRODUCTION_DEPLOY_CHECKLIST` pa lang) kaya dito umaasa ang bell badge at click-routing (`resolveTargetUrl`) ng lumang prod notifications; **(d)** `storage/ux_backup/*.sql` dumps (`.gitignore:37`; kasama ang `cmms_pre_d942_renumber_20260923.sql`) — **rollback insurance** ng sarili ninyong rule: *"BACKUP MUNA — walang dump = huwag ituloy"*. (4) **MGA KONDISYON bago alisin ang legacy CODE (hiwalay na task + test update — HINDI kasama sa file purge):** **(a)** deployed na ang `2026_09_24_000001_add_sender_id_to_notifications_table` sa prod; **(b)** na-renumber na ang prod (D9.42 renumber, may backup muna) at kumpirmadong walang `REQ-` na lumalabas sa prod rows/messages; **(c)** may desisyon na ang lumang unread NULL-sender rows ay okay nang mag-show ng `System` (o na-backfill na ang `sender_id`) — **saka pa lang** i-a-adjust ang `NotificationFromLabelTest` (2 tests) at ang legacy case ng `NotificationRequestNumberMatchTest`, at saka pa lang tatanggalin ang fallback/requestor/`REQ-` branches. (5) **DB REFRESH findings (dev) — para sa real-data prep:** kaya ng system na mabuhay mula sa wala — ang **452 tests ay `migrate:fresh` bawat patakbo** (`RefreshDatabase`) kaya kumpirmadong kaya ng migrations na buuin ang buong schema mula sa 0; `DatabaseSeeder` = 5 fixed accounts (superadmin/test.superadmin/it/admin/user, password = `password`) + `User::factory(10)` = **15 users** (hindi niya tinatawag ang `PartsStockSeeder`); **WALANG seeder** para sa requests (**62**), notifications (**556**), `inventory_assets` (**406**), requisitions/PR/PM schedules → kapag nag-refresh: **users-only ang app** at mawawala ang lahat ng data rows (hindi aapektuhan ang storage files — PDF/signatures/attachments — ang code, o ang test DB). **Bago mag-refresh:** `mysqldump` → `storage/ux_backup/` (iiral ang rule na walang dump = huwag ituloy). **Pagkatapos ng refresh:** `sender_id`-populated na agad ang mga bagong notifications (auto `Auth::id()` sa `send()`) kaya ang fallback ay ang mga system/console notices na lang ang disiplinadong dumadaan, at ang lumang heuristics ay hindi na distinct sa dev. (6) **Katayuan: FILE PURGE — NAKAGAWA (Sept 24 2026); CODE REMOVAL — HINDI PA.** **[Execution record]** kaagad pagkatapos ng plan na ito: tinanggal ang **`storage/d5c_archive_20260907/` (1,308 files, 3.14MB)** at ang **`build.log` + `builderr.log`** — pagkatapos: lahat `Test-Path = False`, **`git status` = malinis** (ignored files lang, walang git effect), **HTTP 200** pa rin ang `/login`, at **`storage/ux_backup/` = 19 files buo pa** (hindi hinawakan). **Hindi** hinawakan ang legacy code (#3a–c), ang `ux_backup` dumps, at **kahit anong DB row** — zero code change kaya walang test na kailangang i-run muli. Ang natitira sa plan ay ang **code removal (#3a/c)** na nakatali sa mga kondisyon sa **(#4)** — hindi ipatutupad bago ang prod deploy/renumber verification.

- **BUG-NOTIF-LINK / D9.42 follow-up — Notification click destination: parts/PM-batch family → tamang workspace (hindi na ICT form) — TAPOS (Sept 24 2026)** — (1) **Iniulat ng user:** kapag ni-click ng IT/System Admin ang parts notification nila (o ng Supply ang bagong parts requisition), napupunta sa **ICT form** imbis na sa parts requisition page — at i-check din daw ang iba. (2) **Root cause (`NotificationController::resolveTargetUrl`):** ang **request-linked branch** (`request_id` → `ict.edit`/`ict.show`) ay **nauna sa lahat**, at may `request_id` ang bawat parts notification (parent ticket) → nahihijack papunta sa ticket kahit nasa ilalim na pala ang `str_contains($type,'Parts') → requisitions.index` (**dead code** para sa may-request rows — hindi ito naaabot kailanman). (3) **Audit ng lahat ng 22 dev-DB notification types — 4 na bug class:** **(a)** parts family → ICT form: `Parts Requisition` (39 rows → Supply), `Parts Request — Approve/Issue/Reject/Issued` (39 → IT/SA), `Parts Request Rejected` (auto-reject → IT); **(b)** `PM Batch Generated` (21 → IT/admin/supply) → `pm-schedules.index` na **`role:super_admin`-only = 403-redirect** para sa IT/admin/supply (at para sa admin/supply ay may **pangalawang 403** sa loob ng `ListMaintenanceRequestsAction`: *"managed by IT personnel and System Admin only"*); **(c)** ang **email "View Details" button** (`Notification::booted`) ay muling itinatayo ang parehong maling ICT URL; **(d)** `FixStuckRequisitionIssues`: `$requisition->ticket?->id ?? $pr->id` — **PR-id na ipinapasa bilang `request_id`** (magkaibang table → maling ticket ang nalilikha); **(e)** bell label (`layouts/app.blade.php` L547) = *"Open Ticket"* kahit hindi ticket ang destination. (4) **Bagong precedence (iisang source of truth):** stored `url` > **parts family** (role-aware) > **PM batch** (role-aware) > **linked ticket** > **message-number lookup** > **PR regex** > fallbacks — nasa tatlong static helper ng `Notification` model: `isPartsFamilyType()`, `partsFamilyUrlFor()`, `pmBatchUrlFor()` — ginagamit ng **pareho** ng bell controller at ng email `booted()`. Destinations: Supply → `requisitions.index` (queue view), IT/SA requester → `requisitions.index?tab=history`; PM batch: SA → `pm-schedules.index`, IT → `pm.tasks`, admin/supply/user → sariling dashboard (`dashboardRouteName()`) — lahat **beripikadong `assertOk`** (role middleware + action guards). Bell label ngayon: *"Open Requisitions"* / *"Open PR"* / *"Open Ticket"* ayon sa type. (5) **Test-first:** bagong `tests/Feature/NotificationDestinationUrlTest.php` — **12 tests / 30 assertions** (unang patakbo = **8 failed / 4 passed** = tamang RED; ang 4 guards = `Job Order Assigned`→`ict.edit`, `for Review`→`ict.show`, stored-url wins, PM-batch SA); focused run = **63 passed / 201 assertions**; **full suite = 464 passed / 1,778 assertions / 0 failed** (452 + 12). (6) **Audited-OK, HINDI binago:** `PR Submitted/Finalized/Delivered` (explicit url o PR-regex → `purchase_requests.show`), `Asset Tagged for Disposal` (ticket form = print-tag entry point ✓), `Parts Low Stock Alert` (dating `requisitions.index` = pareho pa rin ngayon), `Job Order Assigned`/`for Review`/`Request *`/`Ready for Signature`/`PM Task Created`/`PM Scheduled` (ticket form ✓), `CSM *` (explicit url ✓). **Walang DB row na hinawakan** at hindi naapektuhan ang BUG-NOTIF-FROM-2 sender logic (magkapareho pa rin ng legacy fallback ang mga lumang `sender_id NULL` rows).

- **D9.43 ICT Form: "TO BE FILLED-UP BY SERVICE PROVIDER" section — hidden maliban kung REFERRED ang REPAIR TYPE (lahat ng role; web form lang, hindi PDF) — TAPOS (Sept 24 2026)** — (1) **Iniulat:** *"mag move naman tayo sa TO BE FILLED-UP BY SERVICE PROVIDER — dapat i-hide ito sa system sa form (hindi sa PDF); kapag ang REPAIR TYPE ay 'Referred to Service Provider' lang ang lalabas, kapag hindi pinili naka-hide, both roles para maayos"* + follow-up: *"both roles to ha, dapat sa user din."* (2) **Root cause:** sa `partials/ict/_ict_form_sections.blade.php` (dating L131+) ay **laging naka-render** ang buong section sa loob ng `@if($isUpdate)` — ang tulong toggle noon ay (a) `disabled-section` class (grayed + "RESTRICTED" overlay, `display` pa rin; gated sa `$spSectionActive || !$isAdmin || $isView`) at (b) enable/disable ng inputs sa JS `syncSpState()` — **walang tunay na visibility toggle**, kaya kitang-kita ito sa lahat ng role kahit Internal/External repair ang pili. (3) **Fix (Option A — STRICT ayon sa desisyon ng user):** (a) **blade** — bagong wrapper `<div id="serviceProviderSectionWrap" class="{{ $spSectionVisible ? '' : 'hidden' }}">` na bumabalot sa SP section-header + section; `$spSectionVisible = $isReferredToSp` lang — **kahit may naka-save nang SP data** (company name/service date/pullout via `RequestHelpers::isServiceProviderSectionInUse`) basta hindi referred ang type ay hidden (data ay safe sa DB at lumalabas pa rin sa PDF); (b) **JS** (`partials/ict/_ict_scripts.blade.php`) — sa `syncSpState()` idinagdag ang `spWrap.classList.toggle('hidden', !referred)` (eksaktong kundisyon ng orange banner → **live reveal** kapag pumili/pagpalit ng TYPE dropdown) + **guarded init call** `if (repairTypeMode && !repairTypeMode.disabled) syncSpState()` — hindi tumatakbo kapag view-only/non-admin (baka ma-un-disable nito ang SP inputs na hindi sa kanila; ligtas ang change-event route kasi disabled ang dropdown sa view mode); (c) **hindi ginalaw:** enablement logic (`keepActive` + `disabled-section` role-gating — magkaiba ang visibility sa enablement: hidden man ang section, enabled pa rin ang inputs kapag may luma nang data kaya tuloy ang submission), `mapLegacyData` na `array_filter($val !== null)` (absent/disabled inputs → hindi mabubura ang naka-save), **PDF `pdf/ict-form.blade.php` — 0 change** (opisyal na naka-print ang section doon), backend/DB/validation — 0 change (walang `required` sa SP fields), maintenance/PM form — walang SP section, untouched. (4) **Roles:** type-driven ang visibility para sa **lahat ng tatlo** — admin, IT, at end user; role-gated pa rin ang editability gaya dati (admin/IT edit, view-mode restricted). (5) **TDD:** bagong `tests/Feature/IctSpSectionVisibilityTest.php` (4 tests) laban sa `GET /requests/ict/{id}/edit` — admin/IT/end-user × internal vs referred + strict-data edge case. **RED 4 failed (8 assertions)** → nadebug: mali ang unang `assertDontSee('TO BE FILLED-UP BY SERVICE PROVIDER')` — **kahit naka-`display:none` ay nasa HTML pa rin ang text** (CSS hide, hindi server-side omit) → pinalitan ng **wrapper-class contract**: `assertSee('<div id="serviceProviderSectionWrap" class="hidden"', false)` (hidden) vs `class=""` + `assertSee(header)` (visible) + presence ng `.hidden { display: none; }` rule → **GREEN 4 passed / 18 assertions**. (6) **Beripikasyon:** focused test 4/4 ✓ · `view:clear` ✓ · **full suite 468 passed / 1,796 assertions / 0 failed** (baseline 464 + 4 na bago; **0 regressions**) · walang Vite-managed file na binago (inline blade lang) kaya walang `npm run build`. Files: `resources/views/partials/ict/_ict_form_sections.blade.php`, `resources/views/partials/ict/_ict_scripts.blade.php`, `tests/Feature/IctSpSectionVisibilityTest.php` (NEW), `docs/asset-downtime-tracking.md`.
