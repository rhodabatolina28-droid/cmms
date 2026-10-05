# UX Defect Fix Plan — Mobile & Desktop (October 2026)

> **Created:** 2026-10-02
> **Branch:** `develop` (base commit `78daf0b`)
> **Scope:** 7 reported defects — concentrated on **mobile**, mayroon ding desktop/layout side-effects.
> **Rule:** bawat phase = **isang hiwalay na commit** (madaling i-rollback via `git revert <hash>`), may verification bago i-push.

---

## 1. Defect Register (7 bugs, lahat may napatunayang root cause)

| # | Bug (reported) | Root cause (verified) | Sakop |
|---|---|---|---|
| **1** | Parts & Consumables — hindi gumagana ang actions (`⋯`) sa mobile | Ang `⋯` menu ay nasa **loob ng horizontally-scrollable table** (`.table-wrap-parts { overflow-x: auto }` L284, `.parts-card { overflow: hidden }` L9) kaya **na-clip** ang dropdown; sabay ang `#dropdownBackdrop` (fixed, `z-index: 999`, L887) ay nasa ibabaw ng menu → ang tap ay tumatama sa backdrop → `closeAllDropdowns()`. Desktop: `.actions-dropdown { display: none }` (L99) → **mobile-only** ang sakit | Mobile |
| **2** | Physical Count —(a) hindi nawawala ang na-count na asset; (b) bumabalik sa taas pagkatapos mag-mark | (a) `ShowPhysicalCountAction` ay ipinapasa ang **lahat** ng assets/groups kahit `Ongoing`; disabled lang ang buttons ng counted — may `$pending` na pero hindi ginamit sa render. (b) `markAsset()` / `markMany()` ay **`location.reload()`** → nawawala ang scroll position, profile card, at "Other Assets of X" | Mobile + Desktop |
| **3** | ICT form — walang auto-fill ng na-scan na asset; Cam/Scan button hindi gumagana | (a) `@vite(['resources/js/qr-scanner.js'])` ay nag-build ng **0-byte** file (`public/build/assets/qr-scanner-BvRk9kiK.js` = 0 bytes) → `window.AssetScanner` **undefined** → `new AssetScanner()` ay **nag-throw** → hindi na-abot ang `scanBtn.addEventListener`. (b) `CreateIctFormAction` L44-46 ay **nag-aalis** ng `asset_id` kapag wala sa `$myAssets` (naka-`whereNotIn(['For Repair','For Disposal','Scrapped'])` L27/L36) → kaya kung For Repair na ang asset, `$preselectedAssetId = null` | Mobile (button hidden ≥768px) |
| **4** | "Register New Personnel" — hindi makita ang **Create Account** button (mobile) | Ang `<form id="addPersonnelForm">` (partial `_personnel_modals.blade.php` L7) ay anak ng `.modal-card` (L3) **pero hindi flex** → sa mobile global CSS (`.modal-card { display:flex; flex-direction:column; max-height:90vh }` `_phone-portrait.css` L206-213) ang `.modal-body` + `.modal-footer` (L77-80) ay **lumalampas** sa `max-height` at **kinakain ng `overflow:hidden`** | Mobile (+ desktop kapag matangkad ang content) |
| **5** | Super-admin dashboard — MTBF/MTTR cards overflow / siksik | `.analytics-title` (L708, L745) ay may `flex-wrap: nowrap` + `margin-left:auto` month `<select>`; ang value rows ay `space-between` na may **nowrap chips** → lumalampas sa box | Mobile + Desktop |
| **6** | PM Work Orders — blank ang "Assigned To"; wala ang stats cards | (a) **LIVE DB evidence:** `pm_schedules` id=1 focus = `CONCILIATION AND MEDIATION DIVISION`, `assigned_it_id = NULL`; auto-PM: **36 total, 6 unassigned — lahat CMD, status = Scheduled**. Ang `GeneratePMScheduleService` L350-353 ay **ni-null** ang `assigned_it_id` sa cycle advance, habang `AssignPMScheduleITAction` L44-50 ay nag-u-update **lang** kapag `office = current_focus_division`. (b) Ang 5 stats cards ay nasa IT PM Tasks lang | Mobile + Desktop |
| **7** | "Remove all icons — text only" sa mga na-scan / per-role cards | Maraming `<i class="fa-solid …">` pa sa scan flow at iba pang list pages | Lahat |
| **8** *(added 2026-10-05)* | Physical Count — **hindi na makapag-scan pagkatapos makarami** ("rate limiting ata") | `ThrottleRequests` (Laravel 13.8) ay nag-e-key ng authenticated request gamit ang **`sha1(user_id)` lang** — `$prefix` ay `''` sa `throttle:30,1` at **walang route sa key** → **lahat ng ~60 `throttle:*` routes ay IISANG 30/min bucket kada user**. Bawat scan = 1 POST `/search` + 1 POST `/mark`; ang `Mark all Present (10)` ay +10 sunod-sunod → **429 sa loob ng ilang segundo**. Silent pa ang JS: `searchAsset` ay `if (!data.success) return;` (walang mensahe), ang `markMany` ay nahuhulog sa `else { skipped++ }` ("already counted" ang maling summary) at patuloy pang pinapadalhan lahat, at ang `markAsset` ay naiiwang `...`/disabled ang button. **Live DB evidence:** pinakamalaking group = **10 assets**; log: `ThrottleRequests->handle(..., '30', '1')` stack traces | Mobile (live scan sessions) |

---

## 2. Phase Overview

| Phase | Nilalaman | Risk | Backend touch? |
|---|---|---|---|
| **0** | Pre-flight baseline (test run, DB before-values, branch check) | — | Wala |
| **1** | Personnel modal footer · MTBF/MTTR responsive · Parts mobile actions · scan-page icons | Mababa (front-end only) | Wala |
| **2** | Physical Count: i-hide ang counted + in-place mark (walang reload) + icons + mobile polish | Katamtaman | May (1 Action + JS) |
| **3** | QR/Cam button fix (0-byte bundle) + ICT auto-fill ng na-scan | Katamtaman | May (1 Action + JS build) |
| **4** | PM Work Orders: assignment back-fill + hindi na mag-reset + stats cards | Katamtaman | May (2 Actions + Service) |

**Bawal galawin sa lahat ng phase:** print/PDF/archive logic, at ang mga date-dependent na existing test.

---

## 3. PHASE 0 — Pre-flight (walang code change)

1. `php artisan test` → i-record ang baseline (**476 passed / 3 pre-existing date-dependent failures**: `CsmMonthlyReportTest` ×2, `PMCalendarTest` ×1).
2. Read-only DB snapshot para sa before/after ng Bug 6:
   - total auto-generated PM requests, bilang ng `assigned_to IS NULL` (target fix: 6 → 0 pagkatapos i-assign).
3. Kumpirmahin: branch `develop`, working tree clean (`git status --porcelain` = walang output).

---

## 4. PHASE 1 — Front-end only (walang backend risk)

**Target commit:** `fix(ux): phase 1 — personnel modal footer, KPI responsive, parts mobile actions, scan icons`

### 1a. Personnel modal footer (Bug 4)
**Files:** `resources/views/admin/personnel/index.blade.php` (CSS), `resources/views/partials/admin/_personnel_modals.blade.php` (markup reference)

**Dagdag sa `@section('styles')` ng `admin/personnel/index.blade.php`:**
```css
/* Ipagpatuloy ang flex chain hanggang sa footer (para hindi ma-clip) */
.modal-card { display: flex; flex-direction: column; max-height: 90vh; }
.modal-card > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; min-width: 0; }
.modal-card > form > .modal-body,
.modal-card > .modal-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.modal-card > form > .modal-footer,
.modal-card > .modal-footer { flex: 0 0 auto; }
.modal-overlay { overflow-y: auto; }
```
Sinasaklaw nito ang **dalawang modal**: `#addPersonnelModal` (may `<form>`, L2-83) at `#personnelModal` (direkta ang body, L86+).

**Audit (pareho ang one-line rule kung kailangan):**
- `resources/views/partials/super-admin/_user_modals.blade.php` (modal-card L3/L87, form L7/L104)
- `resources/views/super-admin/users/index.blade.php` (may nang-existing fixes na: rule sa L211-215, `.modal-body` L307)
- `resources/views/admin/requests/index.blade.php` (`.modal-card` L139-147 na may `overflow:hidden`)

### 1b. MTBF/MTTR responsive (Bug 5)
**File:** `resources/views/dashboard/super-admin.blade.php`

| Ano | Pagbabago |
|---|---|
| `.analytics-title` (L708, L745) | Alisin ang `flex-wrap: nowrap` → `flex-wrap: wrap; row-gap: 8px;` |
| Month `<select>` (L714-721) | ≤900px → sariling linya, `width: 100%`, `min-height: 44px` |
| Diff chips (L730-732, L760-762) | `white-space: normal` (payagang mag-wrap) |
| Value rows (L723-740, L753-767) | `flex-wrap: wrap; gap: 6px` |
| `.analytics-box` (L374) | `min-width: 0` (safety laban sa overflow) |
| Charts | Panatilihin ang 260px `chart-box-trend` + `maintainAspectRatio: false` |

**Verify sa:** 1440 / 1224 / 900 / 767 / 430 px (walang horizontal overflow sa `document.documentElement.scrollWidth`).

### 1c. Parts & Consumables mobile actions (Bug 1, pinaka-kritikal)
**File:** `resources/views/inventory/parts.blade.php`

- `toggleDropdown(event, btn)` (L1050) → **i-portal ang menu sa `document.body`**: `position: fixed`, coords mula `btn.getBoundingClientRect()`, **clamp** sa viewport, **flip pataas** kapag kulang ang espasyo sa ibaba, `z-index: 1300` (sa taas ng backdrop 999).
- `closeAllDropdowns()` (L1061) → ibalik ang menu sa row at linisin ang inline styles/classes.
- ≤768px → gawing **bottom sheet**: full-width, naka-pinned sa ibaba, items ≥52px, `max-height: 60vh; overflow-y: auto` — malaking touch target.
- Panatilihin ang `#dropdownBackdrop` (L887) para sa click-away.

### 1d. Scan-page icons → text only (Bug 7, bahagi)
**Files:** `resources/views/scan/asset-info.blade.php`, `resources/views/scan/scan-preview.blade.php`, `resources/views/scan/notice.blade.php`

| File | Linyang aalisin ang `<i>` |
|---|---|
| `asset-info.blade.php` | 133 `fa-arrow-left` · 138 `fa-qrcode` · 189 `fa-calendar-check` · 273 `fa-ticket` · 291 `fa-layer-group` · 307 `fa-clock-rotate-left` · 327 `fa-screwdriver-wrench` · 332 `fa-eye` · 336 `fa-house` |
| `scan-preview.blade.php` | 40 `fa-qrcode` · 56 `fa-check-double` · 60 `fa-triangle-exclamation` · 77 `fa-chevron-right` |
| `notice.blade.php` | 22 `.icon-wrap` + `$icon` · 25 `fa-house` |

Panatilihin ang layout/spacing; **teksto lang**. (Ang `notice.blade.php` `.icon-wrap` CSS sa L13 ay iaalis din dahil walang laman na.)

### Phase 1 verification
1. Headless footer/menu measurement: `FOOTER VISIBLE: true` at `MENU VISIBLE: true` sa 1224×805, 684×505, 430×780.
2. Click-test ng `⋯`: nagbubukas at tumatakbo ang Edit / Stock In / Stock Out / History / Units.
3. `php artisan test` → walang **bagong** failure (baseline pa rin).
4. Screenshots (temp sa `storage/`, lilinisin bago ang commit).

### ✅ Phase 1 RESULT — DONE & VERIFIED (2026-10-02)

| Item | Ebidensya (headless Chrome, tunay na pages) |
|---|---|
| **1a** Personnel modal footer | `visible: true` · `hitIsButton: true` (na-tap ang button, hindi natatakpan) · `cardRect [20,20,480,555]` · `footRect [20,422,480,555]` = **kumpleto sa loob ng card** · `formDisplay: flex` · `formMinH: 0px` · `bodyScrollable: true` |
| **1b** MTBF/MTTR | `noHorizontalScroll: true` · lahat ng 4 `.analytics-box`: `overflow: false` (sw == cw) · `titleWrap: "wrap"` · `valueRowWrap: "wrap"` · month `<select>`: `420 × 44px` |
| **1c** Parts dropdown | `position: fixed` · `zIndex: 1300` · `parentTag: BODY` (portaled) · `rect [10,339,490,617]` · `inViewport: true` · `hitInsideMenu: true` (nasa ibabaw ng backdrop 999) · `itemH: 52px` · `clipAncestor: table-wrap-parts/auto` |
| **1d** Scan pages | `asset-info` → `faElements: 0`, `faLink: 0` · `scan-preview` → `faElements: 0`, `faLink: 0`, `h1: "Asset Scanned"` |
| **Regression** | `php artisan test` → **3 failed, 476 passed (1825 assertions)** = eksaktong baseline (3 pre-existing date-dependent) |

**Bonus fix na nadiskubre habang nag-verify:** mixed line-endings (CRLF+LF) sa `scan/scan-preview.blade.php` at `scan/notice.blade.php` → na-normalize sa LF (walang spurious diff).

**Notes para sa susunod na phase:**
- Ang `_personnel_modals.blade.php` na `<form>` ay direktang anak ng `.modal-card` → isang CSS rule ang sumasaklaw sa **dalawang** modal (`#addPersonnelModal`, `#personnelModal`).
- Ang `admin/requests/index.blade.php` ay may dead `.modal-card` CSS (walang modal markup) → **walang aksyon**.
- Ang SA user modals (`_user_modals.blade.php`) ay sakop na ng naunang fix sa `super-admin/users/index.blade.php` (L211-215).
- Ang `⋯` dropdown sa Parts ay **mobile-only** (`@media max-width:768px`) kaya bottom-sheet ang tamang pattern.
- Ang `$icon` na variable sa `ScanController` ay inalis na (hindi na ginagamit ng views).

---

## 4b. PHASE 1b — Post-scan (QR) pages mobile UX — DONE (2026-10-02)

Ang mga standalone na pahina pagkatapos mag-scan ng QR (`scan/asset-info`, `scan/scan-preview`, `scan/notice`) ay nakikita ng **lahat ng roles** (IT → `asset-info`, user → `scan-preview`, hindi-assigned/out-of-scope → `notice`).

| Item | Bago | Ngayon |
|---|---|---|
| Back action (`asset-info`) | **Doble**: maliit na text link sa itaas (walang touch target) + button sa ibaba | **Isang** 44px touch chip sa itaas (full-width sa mobile) |
| Back action (`scan-preview`) | **WALA** — walang paraan pabalik sa dashboard | Bagong 44px chip → `route('dashboard.user')` |
| Blank space sa ibaba | `body { min-height: 100vh }` + malaking footer padding → sayang na blangkong espasyo | Inalis ang `min-height: 100vh`; compact footer → **`blankBelow: 0`** |
| Footer lines (`scan-preview`) | **2** footer notes sa loob ng card | **1** compact na linya sa labas ng card |
| Touch targets | 48px | **48-52px**, full-width sa mobile |
| Breakpoint | `480px` (hindi tumatama sa 500px-wide na layout) | **`768px`** (tugma sa global mobile breakpoint ng app) |
| Icons | may icons noon | text-only (`faElements: 0`) |

**Ebidensya (headless Chrome sa tunay na Blade output, `innerWidth: 500`):** `backLink h=44` · `backToDashboardCount=1` (walang doble) · `footer h=29 / padBottom 10px` · `blankBelow=0` · `bodyMinH=0px` · `scan-preview CTA 52×444` · `notice button 48×380 (full-width)` · `faElements=0`, `faLinks=0`.

**Sinadya na HINDI binago:**
- Ang **ICT Repair Ticket link** (`#display_number`) — napatunayang gumagana (dinadala sa ticket page). Wala pong "View Ticket" button na naalis: tiningnan ang **buong git history** (mula `d5f8ae2` initial commit) at link lang talaga ang nasa scan page noon. Ang button na may label na `Process` / `View Ticket` ay nasa **`requests/ict/ticket.blade.php:195`** (ibang page), hindi sa scan page.
- Ang **focus-division gating ng "Conduct PM"** (`ScanController` L91-104: `$showPmActions` = tugma ang division ng may-ari ng asset sa `current_focus_division`) — business rule ng PM cycle, hindi inalis. Kapag hindi tugma, ang PM panel ay nagpapakita ng **last PM history** (informational) sa halip na ang action button.

---

## 4c. PHASE 1c — Parts modals + My Assets mobile UX — DONE (2026-10-02)

| Item | Bago | Ngayon |
|---|---|---|
| Parts modal-box sa mobile (Edit Part · Stock In · Stock Out · History · Units) | `width: 95-96vw`, halos edge-to-edge; hindi nasusunod ang padding | `max-width: 430px` + overlay padding → may malinaw na side margins; full-height flex chain (box → form → body scroll + foot pinned) kaya laging maabot ang Save/Cancel |
| Parts × close button | Global `button { width: 100% }` rule ay **nag-stretch sa ×** — sumasakop sa modal header · desktop rule walang fixed size | Naka-pin: `parts` 34px desktop / 36px mobile; `assets` 40px mobile · + systemic exclusion ng **17 close-button classes** sa `layouts/app.blade.php` global rule |
| Units table sa loob ng modal | Pinipilit sa `min-width: 720px` → horizontal-scroll sa maliit na modal | Rule na-scoped lang sa main registry table (`.table-wrap-parts`); wrap nang maayos sa loob ng modal |
| Stock Out serial picker | `44px` min-height rule tumatama pati sa `<input type="checkbox">` → nag-wrap ang serial row sa 2 linya | Checkbox excluded → `18px` eksakto; serial = **1 linya + ellipsis** gaya ng desktop |
| My Assets (`profile/assets`, lahat ng roles na may assigned assets) | Overlay walang scroll, walang max-height → na-clip ang modal sa maikling screen; × naka-stretch sa 100% | Scrollable overlay + `max-height: calc(100dvh - 20px)`; × naka-pin sa 40px |
| Inventory detail (Upload/Scrap) + Physical Count scanner modal + User dashboard modal | Walang scroll / `overflow: hidden` → nawawala ang action buttons sa maikling screen | Max-height + scroll sa overlay at box; stacked full-width footer buttons ≤480px |

**Files (6):** `inventory/parts.blade.php` · `inventory/detail.blade.php` · `inventory/physical-count-show.blade.php` · `profile/assets.blade.php` (My Assets) · `dashboard/user.blade.php` · `layouts/app.blade.php` (systemic 17-class close-button exclusion).

**Ebidensya (headless Chrome, tunay na Blade output):** parts → `zeroOverflow: true` sa open modals · serial single-line ellipsis · checkbox 18px; dynamic close-button test sa 17 classes → `pageStretched: []` (**walang naka-stretch**); `php artisan view:cache` OK.

> **Paliwanag sa ScanFlowTest:** kapag tumatakbo nang **mag-isa** (`--filter=Scan`, 5 tests), nag-fail ito sa `Data truncated for column 'status'` — enum mismatch ito sa mismong test fixture (`status='Serviceable'`, galing sa commit `a07ef39`), **hindi dulot ng Phase 1/1b/1c** (pawang CSS-only). Sa **buong suite** pumapasa ito: **3 failed / 476 passed (1825 assertions)** = eksaktong baseline (3 pre-existing date-dependent: `CsmMonthlyReportTest` ×2, `PMCalendarTest` ×1).

---

## 5. PHASE 2 — Physical Count (cohesive change: backend + frontend)

**Target commit:** `fix(physical-count): hide counted assets, in-place mark without reload, text-only actions`

**Files:** `app/Actions/PhysicalCount/ShowPhysicalCountAction.php`, `resources/views/inventory/physical-count-show.blade.php`, (kung kailangan) `app/Actions/PhysicalCount/MarkPhysicalCountAssetAction.php`

| Item | Pagbabago |
|---|---|
| **2a** I-hide ang na-count | Sa `ShowPhysicalCountAction`: kapag `$session->status === 'Ongoing'` → i-filter ang `$groups` sa **custodian na may pending lang**, at sa loob ng group → **pending assets lang** (gamitin ang `$pending`). Panatilihin ang `$summary` (all-assets) para sa stats bar. **Hindi** gagalawin ang Print/Archive/Export. Kapag `Completed` → ipakita lahat (report view) |
| **2b** Huwag agad mag-close | `markAsset()` / `markMany()`: **in-place update** ng row (pill → Operational/Non-Operational, i-disable ang buttons), i-update ang group counters + stats bar + `COUNTED_IDS`; alisin ang row kung nasa pending-only mode. **Alisin ang `location.reload()`**. Manatiling bukas ang profile card para tuloy-tuloy sa iba pang asset ng custodian |
| **2c** Icons + mobile polish | Alisin ang icons sa listahan (kasama ang ✅/❌ emoji sa mark buttons kung meron) → teksto lang; panatilihin ang 44-46px touch targets; ayusin ang `.stats-bar` (2×2 sa mobile); full-width ang "Scan QR"/"Complete Session" |

**Verification:**
1. Bagong feature tests: (i) `Ongoing` → naka-hide ang counted rows at kumpleto ang summary; (ii) `Completed` → buo ang listahan; (iii) mark endpoint hindi nabago ang behavior.
2. Live: i-mark ang isang asset → **hindi** bumabalik sa taas, nag-update ang row, nanatili ang card.

**RESULT (2026-10-02):** ✅ **DONE & VERIFIED**
- **2a — Nakatago ang counted:** `ShowPhysicalCountAction` — kapag `Ongoing` → custodian groups na may pending lang, at pending assets lang sa loob; `Completed` → buo (report view); `$summary` (all-assets) buo pa rin para sa stats bar.
- **2b — Walang reload:** tinanggal ang lahat ng `location.reload()` sa `markAsset()`/`markMany()`; in-place DOM update (`pcBumpStats`, `pcRemoveRows`, `pcSyncGroup`, `pcSyncSearchGroupHeader`, `pcSyncScanCard`, `pcSyncEmptyState`, `pcUpdateCountedNote`) + scroll compensation (hindi tumatalon pataas ang listahan).
- **Scan card v2:** custodian ang header (`Custodian <pangalan>`) + sunod-sunod na compact na asset blocks (una ang na-scan, may "Scanned" badge); isang linya lang ang `.scan-meta` (`SN · PAR · Property · Category · Status`); `Mark all Present (N)`; dedupe sa `seenIds`/`pendingIds`; walang icon/emoji, walang accent line, walang tall detail grid. **SN rule:** nasa `.scan-meta` lang ang SN (katabi ng PAR) at hindi ito ipinapakita kapag wala (walang "N/A") — inalis ang inline `SN:` sa tabi ng pangalan sa scan card; ang normal na search rows ay `search-sn` pa rin. **Badge dedupe:** inalis ang "Already Counted" (`.already-counted-sm`) sa **tatlong** lugar (scan card, custodian group rows, search-result rows) + ang dead CSS nito — ang `.counted-text` sa actions area ang tanging label ng counted.
- **Auto-close + sunod na scan (follow-up fix):** bagong `pcMaybeCloseScanCard()` — kapag ubos na ang pending sa scanned card (katapusan ng "Mark all Present" o ng huling individual mark), awtomatikong nagsasara ito at **binubuksan muli ang camera** (kung available) para deretso sa susunod na scan; kung walang camera → ibinabalik ang view sa "Scan QR". Isinasara rin ang dating card sa `openScanner()` (`pcHideScanCard`). **Matibay laban sa lumang state:** ang 422 na `"Asset already counted as X"` ay ina-adopt na counted (`pcAdoptCounted`) — kaya kahit LUMA na ang `COUNTED_IDS` ng nakabukas na pahina (na-mark na sa ibang device/tab), walang buttons na maiiwan at nagsasara pa rin ang card; nawala rin ang maling "Failed" error sa single mark.
- **Verification:** headless Chrome (500px + 1440px): `alreadyCountedInCard 0`, `inlineSnBesideName 0`, `faInCard 0`, `countedRows 1` + `countedRowOnlyLabel true`, walang overflow, `markAllText "Mark all Present (2)"`, `cardClosedAfterMarkAll`/`scannerReopenedAfterMarkAll`/`cardClosedAfterSingleMark`/`scannerReopenedAfterSingleMark` = `true`, `statsBumpedBy2 true`, at ang live-case: `cardClosedAfterAlreadyCounted`/`skippedAdoptedAsCounted`/`statsBumpedForAlreadyCounted`/`isMarkingReset` = `true`. `php artisan test --filter PhysicalCount` = **12/12 passed**; buong suite = **3 failed / 476 passed** (pre-existing baseline, walang bagong failure).
- **Iterations (live Taglish feedback, 2026-10-02):** custodian header → sequential asset blocks → "Mark all Present (N)" → "Scanned" badge → compact na isang-linyang meta → walang icons/emoji → badge dedupe → SN placement → walang reload → **auto-close + deretso sa susunod na scan** (dulo).
- **Cleanup:** naalis lahat ng temp artifacts pagkatapos ng verification (headless probe `storage/framework/pc-probe.php`, `public/__pc-probe.html`, `probe-500.html`, `probe-1440.html`, test logs) — malinis ang working tree bago ang commit.
- ⚠️ **Paalala sa live testing:** inline JS ng pahina ang binago → kailangan ng **isang page refresh** sa device (pull-to-refresh/close tab) bago masubukan ang bagong behavior; hindi ito nakikita sa dating nakabukas na pahina.

---

## 5.5 PHASE 2.5 (hotfix, 2026-10-05) — Scan rate-limit (Bug 8)

**Target commit:** `3b9f213` — `fix(physical-count): scope scan throttle routes per-endpoint and surface 429 in UI`

**Trigger:** live report — *"un rate limiting ata kaya pag nakarami na ko ng scan hindi ako makapag scan."*

### Root cause (verified sa vendor code, hindi lang hula)

```php
// vendor/laravel/framework/.../ThrottleRequests.php (Laravel 13.8)
'key' => $prefix . $this->resolveRequestSignature($request)  // prefix = '' sa `throttle:30,1`
resolveRequestSignature() → sha1($user->getAuthIdentifier())  // ← WALANG route sa key
```

1. **Shared counter:** lahat ng ~60 `throttle:*` routes sa `routes/web.php` ay **iisang 30/min bucket kada user** (key = `sha1(user_id)` lang). Proof (red test): 35 POST `/search` → ang `notifications/read-all` (iba-ibang route) ay **429 na agad**.
2. **Burst math:** bawat QR scan = 1 POST `/search` (user assets) + 1 POST `/mark`; ang `Mark all Present` ay **+10 sunod-sunod** (live DB: pinakamalaking custodian group = **10 assets**) → 429 sa loob ng ilang segundo. Red test: **429 sa mark request #31**.
3. **Silent ang JS** (kaya "bigla na lang ayaw"): `searchAsset` → `if (!data.success) return;` (walang mensahe); `markMany` → ang 429 ay nahuhulog sa `else { skipped++ }` → maling "already counted" summary **at patuloy pang pinapadalhan lahat ng natitira**; `markAsset` → generic "Failed" at **naiiwang `...`/disabled ang button**. Ang laravel.log ay may 18 `ThrottleRequests` stack-trace frame (`handle(..., '30', '1')`).

### Fix

| Layer | Pagbabago |
|---|---|
| **Routes** (`routes/web.php` L235-245) | Route-scoped prefixes + burst-safe limits: `search` → **`throttle:120,1,pc-search`**, `mark` → **`throttle:300,1,pc-mark`**, `store` → `throttle:30,1,pc-store`, `complete` → `throttle:30,1,pc-complete`. Sariwa ang bawat bucket (hindi na nagbabahagi sa ~60 iba pang routes). |
| **JS helpers** (`physical-count-show.blade.php`) | `pcRetryAfter(res)` (binabasa ang `Retry-After` header, default 30s) + `pcTooManyMsg(res)` |
| **`searchAsset`** | 429 → inline mensahe sa search results ("Masyadong mabilis — maghintay ng ~Ns") |
| **`markMany`** | 429 → **hinto ang loop** (hindi na pinapadalhan lahat ng natitira), hiwalay na warning summary: ilan ang na-mark / ilan ang hindi naipadala + retry seconds. Hindi na nagpapakita ng maling "already counted". |
| **`markAsset`** | 429 → warning dialog + **ibalik ang button label/state** (dati ay naiiwing `...`); pati ang generic-failure at catch branches → binabalik na rin ang button |
| **scanner `onScan`** | 429 sa user-assets search → warning dialog, **itutuloy pa rin** ang pagpapakita ng na-scan na asset |

### Verification

1. **TDD:** bagong `tests/Feature/PhysicalCountScanThrottleTest.php` — **RED muna (3/3 failed: shared counter 429, mark #31/429, walang prefixes)** → pagkatapos ng routes fix → **GREEN (3/3, 126 assertions)**.
2. `php artisan test --filter=PhysicalCount` → **15/15** (12 luma + 3 bago).
3. Full suite: **3 failed / 479 passed** — pareho lang ang 3 pre-existing date-dependent (`CsmMonthlyReportTest` ×2, `PMCalendarTest` ×1); +3 tests vs 476-baseline, **walang bagong failure**.
4. `php artisan route:list --json` → `physical-count.mark` middleware = `web, auth, active, require.survey, role:admin, throttle:300,1,pc-mark`.
5. Blade: `php -l` + `view:cache`/`view:clear` OK; inline JS pinalitan ng `node --check` → **JS SYNTAX OK**.

**Rollback:** `git revert <phase-2.5-hash>` (routes + blade + test).

⚠️ **Paalala:** muli na namang binago ang inline JS ng page → **isang page refresh** sa device bago i-test. Ang route change ay walang route-cache kaya effective agad sa server.

---

## 6. PHASE 3 — Scan/Camera + ICT auto-fill (end-user side)

**Target commit:** `fix(scan): rebuild qr-scanner bundle and preselected scanned asset on ICT form`

**Files:** `resources/js/qr-scanner.js`, `resources/views/partials/ict/_ict_scripts.blade.php`, `resources/views/requests/ict/form.blade.php`, `app/Actions/ICT/CreateIctFormAction.php`

| Item | Pagbabago |
|---|---|
| **3a** Cam button | Sa `resources/js/qr-scanner.js`: idagdag ang `if (typeof window !== 'undefined') { window.AssetScanner = AssetScanner; }` (para hindi ma-tree-shake ang bundle) + **`npm run build`** → tiyakin na `public/build/assets/qr-scanner-*.js` **> 0 bytes** at defined ang `window.AssetScanner` |
| **3b** Auto-fill | `CreateIctFormAction` L41-47: kung may `asset_id` at pag-aari ng user (validated gaya ng sa `linkedAssetValidationError`) → **i-push sa `$myAssets`** sa halip na gawing `null`; sa `form.blade.php` L279 → suportahan din ang `request('asset_id')` sa `selected`. Panatilihin ang JS path (URL param → `ictAutoFillFromAsset`) |
| **3c** Guard | Magdagdag ng smoke test/verification na hindi na-empty ang built bundle para hindi na maulit |

**Verification:** live mobile flow — scan-preview → "Report Repair" → **naka-preselect at auto-filled** ang asset; ang Scan button ay nagbubukas ng camera modal at nakaka-detect ng QR.

---

## 7. PHASE 4 — PM Work Orders (System Admin)

**Target commit:** `fix(pm-orders): back-fill IT assignment for scheduled auto-PMs and add stats cards`

**Files:** `app/Actions/PMSchedule/AssignPMScheduleITAction.php`, `app/Services/GeneratePMScheduleService.php`, `app/Actions/PMSchedule/GetOrdersDataAction.php`, `resources/views/pm-schedules/orders.blade.php`

| Item | Pagbabago |
|---|---|
| **4a** Back-fill ng assignment | `AssignPMScheduleITAction` L44-50: i-update ang **lahat ng `is_auto_generated` requests ng schedule na `status = Scheduled`** (hindi pa nasimulan) kahit anong division (hindi lang `current_focus_division`), + ang non-terminal ng focus division gaya ng dati. Ibalik ang `updated_count` sa toast |
| **4b** Hindi na mag-reset | `GeneratePMScheduleService` L350-353: huwag i-null ang `assigned_it_id` kung may bagong gawain pa; fallback sa L191 → kung `NULL`, ipakita ang **"Unassigned — needs assignee"** sa UI imbes na `--` |
| **4c** Inline assign | Maglagay ng assign control (dropdown ng IT + Save) sa PM Work Orders page para maisaayos agad sa isang lugar |
| **4d** Stats cards | `GetOrdersDataAction` → idagdag ang `stats` (Total / To Do / Ongoing / Completed / Overdue, gamit ang `is_aging_overdue` gaya ng `ListPmTasksAction`) — **additive** lang sa JSON; i-render ang 5 cards sa `orders.blade.php` (markup mula `pm-tasks.blade.php`), mobile-friendly (2 columns ≤767px → 2/2/1) |

**Verification:** (i) i-test na ang 6 na CMD "To Do" ay nagkakaroon ng assignee pagkatapos i-assign (DB before/after); (ii) feature test sa `AssignPMScheduleITAction`; (iii) tugma ang stats sa listahan sa bawat filter; (iv) mobile screenshots.

---

## 8. Cross-cutting Rules

- **Guardrails:** walang gagalawin sa print/PDF/archive; **additive** lang ang JSON changes; visual fix = CSS/JS only.
- **Rollback:** bawat phase = isang commit → `git revert <hash>` kung may aberya.
- **Verification toolkit:** headless Chrome measurement (VISIBLE flags + bounding boxes), screenshots sa `storage/` (temp, lilinisin), at `php artisan test` kada phase.
- **Cleanup:** iaalis ang lahat ng temp artifacts bago mag-final commit; isasama ang `public/build` kapag may JS/CSS entry change.
- **Walang DB migration** sa buong plano — pure bug fix/UX.

---

## 9. Open Decisions (kailangan ng kumpirmasyon sa tamang phase)

1. **Bug 7 scope** (Phase 1): kasama na ang **scan-flow pages**. Kung isasama rin ang **"My Assets"** at **lahat ng list-page cards**, sabihin bago magsimula ang Phase 1.
2. **Bug 6b/6c** (Phase 4): **sticky** ba ang IT assignment (hindi na ni-null sa cycle advance) o sapat na ang **inline assign sa PM Work Orders**?

---

## 10. Progress Log

| Petsa | Phase | Status |
|---|---|---|
| 2026-10-02 | Plan doc | ✅ Ginawa — `c5670ae` |
| 2026-10-02 | **Phase 1** — personnel modal footer · MTBF/MTTR responsive · Parts mobile actions · scan-page icons | ✅ **DONE & VERIFIED** — `996e4ca` |
| 2026-10-02 | Phase 1 docs (ebidensya + hashes) | ✅ — `94a8112` |
| 2026-10-02 | **Phase 1b** — post-scan (QR) pages mobile UX: isang back action, walang sayang na footer space | ✅ **DONE & VERIFIED** — `369edab` |
| 2026-10-02 | **Phase 1c** — Parts modals (Edit/Stock In/Out/History/Units) + My Assets mobile UX: fixed ×, scrollable modals, serial picker 1-linya | ✅ **DONE & VERIFIED** — `7d652ae` |
| 2026-10-02 | **Phase 2** — Physical Count: hide counted · walang reload · scan card v2 (custodian-ordered, compact) · auto-close + deretso sa susunod na scan | ✅ **DONE & VERIFIED** — `719045b` |
| 2026-10-05 | **Phase 2.5 (hotfix — Bug 8)** — scan rate-limit: route-scoped throttle prefixes (`pc-search` 120/min, `pc-mark` 300/min, `pc-store`/`pc-complete` 30/min) + 429 handling sa JS (search message, markMany stop + warning, markAsset button restore, scanner toast) | ✅ **DONE & VERIFIED** — `3b9f213` |
| — | Phase 3 — QR/Cam button (0-byte bundle) + ICT auto-fill | ⏳ Nakabinbin |
| — | Phase 4 — PM Work Orders (assignment back-fill + stats cards) | ⏳ Nakabinbin |

**Rollback:** `git revert 3b9f213` (scan throttle) · `git revert 719045b` (Phase 2) · `git revert 7d652ae` (Phase 1c) · `git revert 369edab` (Phase 1b) · `git revert 996e4ca` (Phase 1) · `git revert c5670ae` (docs).

---

## 11. Local testing (Cloudflare tunnel)

Ang tunnel URL ay **nagbabago tuwing i-restart** ang `cloudflared` (trycloudflare quick tunnel).

| Hakbang | Command / Location |
|---|---|
| Simulan ang web server | `php artisan serve` (port 8000) |
| Simulan ang tunnel | `Start-Process -FilePath 'tools\cloudflared.exe' -ArgumentList 'tunnel','--url','http://localhost:8000','--no-autoupdate' -NoNewWindow -RedirectStandardError 'storage\framework\tunnel.err'` |
| Kunin ang bagong URL | `Select-String -Path storage\framework\tunnel.err -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com'` |
| I-update ang `.env` | `APP_URL=<bagong URL>` |
| I-clear ang config cache | `php artisan config:clear` |
| **I-regenerate ang QR codes** | `App\Services\QrCodeService::regenerateForAll()` — kailangan ito dahil ang QR ay nag-e-encode ng `config('app.url') . '/r/{asset_id}'` (406 assets) |
| I-verify | `Invoke-WebRequest https://<url>/login` → 200 |

> ⚠️ **Tandaan:** kapag nag-restart ang tunnel at nagbago ang URL, laging `config:clear` + QR regenerate, kung hindi luma ang URL na naka-encode sa mga sticker.