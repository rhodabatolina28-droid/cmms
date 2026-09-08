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
15. **Execution order: D5b → D5c → D5a → D5d → D6** — the public-disk security exposure is fixed
    BEFORE any new PDF auto-copy feature is built
16. **Archive PDFs reuse the EXISTING form templates** — `ArchiveTicketPdfAction` renders the same
    `pdf/ict-form` / `pdf/maintenance-form` blades the Download button uses (no duplicate template
    to maintain); archive trigger is `DB::afterCommit` + one-per-ticket guard; and Blade `@php`
    blocks must use **closures, never named functions** (named functions fatally collide when one
    process generates multiple PDFs — the D6 backfill proved it)

## 7. Git Checkpoints
- v1 implementation: inline `Request.php::booted()` + `total_downtime` column (no tag; superseded by this doc)
- Overhaul: X1-X4 to be committed per phase with tests, then pushed as a single squash to `origin/develop`
- D4 (high-official priority): DESIGNED — position backfill + `config/priority.php` (awaiting execution)
- D5 storage reorg: D5b/D5c private-disk migration + D5a CSM auto-PDF polish chain + D5d `csm:generate-pdfs` backfill — ✅ committed (`3a940d3` latest of chain)
- **D6 ticket auto-archive: ✅ committed `0b791b8`** — `archive_pdf_path` column, afterCommit trigger sa completion, `tickets:generate-archive-pdfs` backfill (34/0), sigImg closure fix, 4 feature tests pass
