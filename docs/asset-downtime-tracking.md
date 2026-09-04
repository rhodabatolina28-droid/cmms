# Asset Downtime Tracking — Implementation & Overhaul Plan

> **Status:** v1 implemented but **BROKEN** (Carbon 3 sign bug — all recorded durations ≤ 0).
> **Overhaul approved (Sept 2026):** X1 math fix + PM/ICT split → X2 data cleanup → X3 gap closure → X4 display.
> **Locked decisions:** PM downtime counts toward the combined total AND gets its own bucket. Bundled PM credits ALL of the user's assets.

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
- **D2 Ticket Aging** — unified age accessor + buckets (🟢 0-24h · 🟡 1-3d · 🟠 3-7d · 🔴 7d+) to replace
  the hardcoded 7-day Overdue rule in PM Tasks
- **D3 SLA-lite** — priority (P1-P4) usage, response/resolution targets, breach badges, MTTR/MTBF,
  availability %; requires aging (D2) and accurate downtime (this doc) first
- Note: no priority values exist in the system yet (`CMMS_DEEP_REVIEW_SEPT2026.md` #17: SLA = 0/10)

### D4 — High-Official Immediate Priority (ICT) — DESIGNED, awaiting execution

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

#### D4.2 Keyword config (NEW `config/priority.php`)
```php
return [
    'high_official_keywords' => [
        'Executive Director', 'Deputy Executive Director', 'Director IV', 'Director III',
        'OIC-Director', 'Director',   // full title phrases — HINDI generic words
    ],
];
```
**Guardrail 1 — full-phrase matching, hindi substring:** ginagamit ang buong plantilla title
("Director IV"), hindi malayang salita. Kaya ligtas ang "Director's Secretary" at "Programmer"
dahil hindi sila eksaktong tugma sa listahan. Ang Super Admin ang naglilista — ang position text
ay kontrolado, kaya finite ang mga title.

#### D4.3 Helper (User model)
```php
public function getIsHighOfficialAttribute(): bool
{
    $position = mb_strtolower(trim((string) $this->position));
    if ($position === '') return false;
    foreach (config('priority.high_official_keywords', []) as $kw) {
        if (mb_strpos($position, mb_strtolower($kw)) !== false) return true;
    }
    return false;
}
```

#### D4.4 Queue-jump — saan ipapasok (verified sites)
| Site | Kasalukuyang ordering | D4 change |
|---|---|---|
| `ItDashboardAction` L46-61 (IT dashboard widget) | orderByRaw CASE by status → updated_at desc, limit 6 | **Officials-first**: dagdag na lead CASE (may LEFT JOIN sa users): `official → 0, iba → 1` bago ang status CASE |
| `ListIctRequestsAction` (ICT requests list, paginate 20) | orderBy created_at desc (6 variants) | Parehong officials-first lead ordering, para consistent sa lahat ng list views |

**Ordering rule (locked):** Officials muna (newest first), tapos ang lahat ng regular tickets
ng may status CASE flow. Hindi hinahayaan ang Ongoing na regular na mawala sa flow — pero ang
bagong official ticket ang lalabas sa pinaka-taas ng queue.

#### D4.5 UI badge
⚡ **High Official** chip (amber) sa ticket card/row ng IT queue at ICT lists — kita agad kung bakit
nasa taas ang ticket.

#### D4.6 🚨 RISK NA NAHULI SA DEEPVIEW — self-service position editing
Ang `ProfileController` ay **hayaan ang USER na i-edit ang SARILING position** (self-service form,
`profile/index.blade.php` L272). Ibig sabihin: **kahit sino pwedeng mag-type ng "Director" para
lumaktaw sa queue!**

**Guardrail 2 (required bago i-rollout ang D4):** gawing **read-only** ang position field sa
self-service Profile; ang position ay i-e-edit **lang** ng Super Admin (User Management) at
Department Admin (Personnel Management modals — existing na). Ang self-inflation ay hindi na posible.

#### D4.7 Backfill plan
1. User magbibigay ng **official list** (pangalan + eksaktong posisyon)
2. Super Admin i-fi-fill sa User Management (54 users ang empty ngayon)
3. Verify: tinker check — `User::whereNotNull('position')` count + isHighOfficial spot-check

#### D4.8 Execution phases (pagkatapos ng X1-X4; test-first)
| Phase | Scope | Gate |
|---|---|---|
| **D4a** | `config/priority.php` + `is_high_official` accessor + position read-only sa Profile | Unit test: accessor matches "Director IV" ✓, rejects "Programmer" ✓, rejects empty ✓ |
| **D4b** | Queue-jump ordering sa ItDashboardAction + ListIctRequestsAction + ⚡ badge | Feature test: official ticket lumalabas sa taas ng regular queue |
| **D4c** | Backfill positions (official list ng user) | Manual verify sa queue |

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

## 7. Git Checkpoints
- v1 implementation: inline `Request.php::booted()` + `total_downtime` column (no tag; superseded by this doc)
- Overhaul: X1-X4 to be committed per phase with tests, then pushed as a single squash to `origin/develop`
