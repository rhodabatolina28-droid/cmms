# UX Defect Fix Plan — Mobile & Desktop (October 2026)

> **Created:** 2026-10-02
> **Branch:** `develop` (base commit `78daf0b`)
> **Scope:** 7 reported defects — concentrated on **mobile**, mayroon ding desktop/layout side-effects.
> **Rule:** bawat phase = **isang hiwalay na commit** (madaling i-rollback via `git revert <hash>`), may verification bago i-push.

---

## 1. Defect Register (9 bugs, lahat may napatunayang root cause)

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
| **9** *(added 2026-10-06)* | PM Tasks (IT side) — (a) nakikita ng IT ang trabahong **hindi sa kanya**; (b) hindi pababa ang completed sa table | (a) `ListPmTasksAction` L30-39: `orWhereNull('assigned_to') AND branch` → **unassigned same-branch tickets** ay nasa personal queue ng IT; (b) L44: `orderBy('created_at','desc')` lang → kaka-**completeng** task (pinakabagong `created`) ay nasa **taas** pa rin — samantalang ang SA `GetOrdersDataAction` L44-47 ay nagsu-sort ng Scheduled→Ongoing→Awa­iting→Completed | IT side (mobile + desktop) |

---

## 2. Phase Overview

| Phase | Nilalaman | Risk | Backend touch? |
|---|---|---|---|
| **0** | Pre-flight baseline (test run, DB before-values, branch check) | — | Wala |
| **1** | Personnel modal footer · MTBF/MTTR responsive · Parts mobile actions · scan-page icons | Mababa (front-end only) | Wala |
| **2** | Physical Count: i-hide ang counted + in-place mark (walang reload) + icons + mobile polish | Katamtaman | May (1 Action + JS) |
| **3** | QR/Cam button fix (0-byte bundle) + ICT auto-fill ng na-scan | Katamtaman | May (1 Action + JS build) |
| **4** | PM Work Orders: assignment back-fill + hindi na mag-reset + stats cards | Katamtaman | May (2 Actions + Service) |
| **2.5** *(hotfix, 2026-10-05)* | Scan rate-limit (Bug 8): route-scoped throttle prefixes + 429 handling sa JS | Mababa (routes + JS) | May (routes lang, walang DB) |

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

> ⏸️ **PARKED (2026-10-06):** sinabi ng user na *"working na pala — need lang pala assign"* ang PM Work Orders (SA side) → itinigil muna ang **4a-4d**; nag-pivot ang session sa **PM Tasks IT-side fixes (Bug 9)**. Hindi pa naka-implement ang 4a-4d — buo pa rin ang plan na ito kapag ibinalik.

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
3. **Email sender/links (Phase E, §12):** `MAIL_FROM_ADDRESS` (ngayon = personal Gmail) at `APP_URL` (trycloudflare quick tunnel na nagbabago kada restart — §11) → kailangan ng **opisyal na internal URL + office email account** bago ang phase **E4**; hanggang ibinibigay = checklist placeholders lang.
4. **Phase E S4 (§12.9):** `PMAdminNotificationMail` = **dead code** (0 call sites — hindi kailanman na-send). **I-wire** sa totoong PM-assignment flow o **i-delete**? (preview ☠️)
5. **Phase E S7 (§12.9):** PR email details-box label — panatilihin ang **"Ticket No."** o palitan ng **"Reference No."**?

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
| 2026-10-05 | Phase 2.5 docs + cleanup + prod-readiness assessment (lahat ng temp probe/logs natanggal, tree malinis, `ffe67f7`) | ✅ — `df17756` + `ffe67f7` |
| 2026-10-05 | Tunnel/`APP_URL` rotation (trycloudflare expired) → `accommodation-numbers-acting-engineer.trycloudflare.com`, `config:clear` + **406 QR regenerated**, `/login` 200 | ✅ (hindi naka-commit — `.env` is gitignored, runbook §11) |
| 2026-10-06 | **Phase 3 (Bug 3)** — ICT form Cam/Scan + auto-fill: `qr-scanner.js` → `window.AssetScanner` (bundle 0 B → 1,318 B) · blade scanner init → `DOMContentLoaded` + tanggalin ang unmatched-`});` OLD HANDLER remnant (SyntaxError = binababa ng browser ang buong script block) · `CreateIctFormAction` keep/push ng user-owned `asset_id` (For Repair allowed, parity sa `linkedAssetValidationError`) · bagong `IctScanPrefillTest` | ✅ **DONE & VERIFIED** — `0abde01` |
| 2026-10-06 | **Phase 3b (scan flow)** — guest scan → login → **options page muna** (`/r/{id}`) sa halop na deretso sa ICT form: `AuthController` qr-redirect → `url('/r/'.id)` (dating `route('ict.create')` — pre-existing simula June 29, na-expose lang ng tunnel rotation logout) + 2 bagong tests | ✅ **DONE & VERIFIED** — `1d4a3e5` |
| 2026-10-06 | **DATE RECEIVED autofill** — default = **receipt date** (`created_at`, kailan pumasok ang request para sa system admin), HINDI ang araw na binuksan ang form (dating `now()` mula D9.26) sa `_ict_form_sections.blade.php` + bagong `IctDateReceivedAutofillTest` (3 tests; saved-value + view-blank locks) | ✅ **DONE & VERIFIED** — `2d1081d` |
| 2026-10-06 | **PM Tasks IT-side (Bug 9)** — (a) **assigned-to-me-only** visibility (tanggalin ang unassigned-in-branch na `orWhereNull` leak) · (b) table sort = Scheduled→Ongoing→Awaiting Signature→Completed bago `created_at desc` (parang SA PM Work Orders — **completed pababa na**) sa `ListPmTasksAction` + bagong `PmTasksItScopeTest` (3 tests) | ✅ **DONE & VERIFIED** — `4c81d14` |
| 2026-10-06 | **Icon sweep (Bug 7 continuation)** — text-only sa tinukoy ng user: (a) **scan PM status chip** (`asset-info.blade.php`) — tinanggal ang `⏳ ` bago "To Do"/"In Progress"; (b) **PM Schedules (SA)** — Work Orders title (`fa-clipboard-list`) · "View All Work Orders" (`fa-list`) · Schedules title (`fa-calendar-days`) · status lines (`fa-spinner`/`fa-check-circle`/`fa-hourglass-half`). **Hindi ginalaw:** functional buttons (Pause/Resume/Stop/Generate/Activate/Trash/View Calendar), swipe-hint + empty-state icons (cross-page patterns) | ✅ **DONE & VERIFIED** — `ecdaaaa` |
| 2026-10-06 | Tunnel/`APP_URL` rotation (lumang DNS expired) → `womens-cents-any-eyes.trycloudflare.com`, `config:clear` + **406 QR regenerated**, `/login` 200 + `/r/1` 302 | ✅ (hindi naka-commit — `.env` is gitignored, runbook §11) |
| 2026-10-07 | **M1 — Batch QR Sticker Print mobile** (nasira ng Phase A custodian grouping): ≤767px table → **stacked custodian cards** (CSS-only transform, iisang render path — group header = card header na may laging kitang *select* button, checkbox pinned sa kaliwa, `SN:`/`PAR:` labels + forced line 2 bago ang meta) · **sticky bottom bar** `#mobilePrintBar` (count + Print laging nasa thumb reach) · compact header (Back + Select All chips) · filters 2-in-row · tanggal ang swipe-horizontal hint · desktop table at print flow **hindi ginalaw** (1280×800 screenshot check) · bagong `QrBatchPrintTest` mobile test | ✅ **DONE & VERIFIED** — `b40956f` |
| 2026-10-07 | **M2 — Physical Count post-count action buttons (Export CSV / Print Report / Print by Custodian) mobile**: ≤677px grid `1fr 1fr` → **`1fr`** (tanggal ang orphan na "Print by Custodian" sa tabi ng blangkong cell + wrapped label) + **`width: 100%`** sa title row — root cause ng "bitin" na hindi full-width = `align-items: flex-start !important` ng global `_phone-portrait.css` (nag-o-oo-ride sa inline `stretch`) → shrink-to-fit na 166px lang; CDP computed-style probe: **166px → 280px** = full content width · bagong `PhysicalCountGroupTest` mobile test | ✅ **DONE & VERIFIED** — `2c9d4de` |
| 2026-10-07 | **Email notification rebrand + template refresh** — subjects `[NCMB CMMS]` → **`[NCMB]`** sa 3 mailables (`SystemNotificationMail`, `PMAdminNotificationMail`, `PMScheduledMail`) · template header **National Conciliation and Mediation Board** (+ `Official Notification` eyebrow; subtitle *Computerized Maintenance Management System* = **KEEP**; `<title>` default = agency full name) · `.env` `MAIL_FROM_NAME` `CMMS System Alert` → agency full name (gitignored; `PRODUCTION_DEPLOY_CHECKLIST` sample in-sync) · design: gray `#f1f5f9` backdrop + card shadow, app navy **`#0038A8`** (dating indigo `#1e3a8a`) sa top-bar/header/CTA/footer, uppercase details labels, status pill, Outlook `bgcolor` fallbacks + `color-scheme: light` metas · test-first: `NotificationRequestNumberMatchTest` RED→GREEN **19/19**, full suite **3F/506P** = baseline · headless-Edge render QA (preview screenshot = agency header + subtitle intact) | ✅ **DONE & VERIFIED** — `076e391` |
| 2026-10-08 | Tunnel/`APP_URL` rotation (patay ang `cloudflared` process → "hindi gumagana") → `favorites-insights-though-estimation.trycloudflare.com`, `config:clear` + **406 QR regenerated**, `/login` 200 + `/r/1` 302 | ✅ (hindi naka-commit — `.env` is gitignored, runbook §11) |
| 2026-10-08 | **Scan hub role sections (Supply Officer scope)** — sa `/r/{id}` (`scan/asset-info.blade.php`): gates sa **VIEWER role** (`Auth::user()`, hindi ang may-ari): "Preventive Maintenance" section + "Recent Service History" = **IT/System Admin lang** → supply/admin = **Other Assets na lang ang matira** · "Asset not assigned to any user" notice inilabas sa PM gate (label `Assignment`, all roles — Spare-scan explanation, lock ng `QrLifecycleTest`) · **View Full Inventory Profile** para sa `canProcessSupply()` → `inventory.detail` (parity sa System Admin; super_admin → `super_admin.inventory.detail`, same pattern as `ApiAssetProfileAction`) · `Conduct PM` blade-gated it/super_admin (defense-in-depth) · IT/System Admin view = **unchanged** · test-first: bagong `ScanHubRoleSectionsTest` RED→GREEN **3/3**, scan suite **15/15** (100 assertions: `ScanSetHubTest`+`QrLifecycleTest`+`ScanFlowTest`) · full suite **3F/510P** = 506 baseline + 3 new + 1 pre-existing UNCOMMITTED KPI test (stash-verified: pure HEAD = 3F/506P) | ✅ **DONE & VERIFIED** — `74755d3` |
| 2026-10-08 | **Scan hub ICT Repair Ticket = active tickets only** — sa `/r/{id}` (`ScanController`): ang "ICT Repair Ticket" panel ay **lilitaw lang kapag HINDI pa complete** ang ticket · pag terminal na (**Completed/Cancelled/Rejected**) → **wala na sa panel, lumilipat sa Recent Service History** (history query = walang status filter → kasama na talaga doon) · `ictTicket` query = `whereIn` **active statuses** (`Pending/Ongoing/Scheduled/Awaiting Parts/Awaiting Signature/Referred - External` — mirror ng `Request::isActiveTicketAttribute`: *"terminal = history, not alarms"*) · bonus: bagong terminal na ticket ay **hindi na sumasapaw** sa mas lumang open ticket (panel = laging latest **ACTIVE**) · supply/admin = walang binago (`ictTicket=null` sa branch nila) · test-first: bagong `ScanIctTicketSectionTest` RED **2F** → GREEN **2/2**, scan suite **17/17** (114 assertions) · full suite **3F/512P** (3F = pareho pa ring pre-existing `CsmMonthlyReportTest`×2 + `PMCalendarTest`×1; 512 = 506 + 1 uncommitted KPI + 3 + 2) | ✅ **DONE & VERIFIED** — `0548cc5` |
| 2026-10-08 | **Batch QR Sticker Print search bar (user report: "pag nag-type ng name or serial/PAR dapat may lumabas")** — 4 butas sa `qr-batch.blade.php`: (a) `matchSearch` walang **`assigned_to_name`** (custodian = pangunahing grouping ng page → pangalan ng tao = walang lalabas) at walang `property_number` (kulang sa server-side parity `GetInventoryAssetsAction`: item_name/serial/par/property) · (b) ang tanging wiring ay **inline `oninput`** na **blokado ng strict production CSP** (`script-src` nonce lang, walang `unsafe-inline` — `SecurityHeaders`) → patay sa prod, gumagana lang locally (`APP_ENV=local`) · (c) habang paged-load ang 406 assets, bawat page arrival = `renderTable(allAssets)` na **binabura ang aktibong search** · fix: `addEventListener('input')` sa nonce'd script (inline inalis) · `matchSearch` = server parity + custodian + `.trim()` · load completion → `filterTable()` (re-apply active search) · placeholder = "Search custodian/asset, serial, PAR..." · test-first: bagong `QrBatchSearchTest` RED **1F/1P** → GREEN **2/2** (18 assertions) · QR suite **13/13** + `node --check` OK · full suite **3F/514P** (pareho pa ring 3 pre-existing) | ✅ **DONE & VERIFIED** — `2c75901` |
| 2026-10-08 | **CSM survey silent feedback (user report: \"after mag-answer ng CSM walang lumabas\" + counter 84)** — DB probes: **hindi kailanman sira ang save** (87 rows, 3 latest ~1 min kasabay ng Request Completed emails, walang failed-submit logs, scoped = unscoped) — ang sira ay **feedback**: (a) `dashboard/user.blade.php`: `session('error')` at success na **hindi thank-you string** (duplicate-submit) = **wala talagang output** → fix: `#dashboardFlash` + `Swal.fire` (SweetAlert2 mula sa layout, error/success icon), **hindi kasama** ang thank-you path para walang double popup · (b) **branded `thankYouModal` pinanatili** ayon sa user preference (verified: `.modal-overlay` = `display:flex` → nagre-render talaga iyon; tinanggal muna bilang generic Swal, ibinalik pagkatapos ng review) · (c) `csm/form.blade.php` walang `$errors` block → validation bounce mukhang no-op; fix: `Please review your answers` + per-field list · test-first: bagong `CsmSubmissionFeedbackTest` RED **2F/1P** (save-lock passed = feedback ang bug) → GREEN **3/3** (20 assertions); CSM suite **41P + 2 pre-existing**; full suite **2F/518P** (2F = `CsmMonthlyReportTest`×2 pre-existing; 518 = 514 + 3 new + 1 KPI mula `5bd8ca2`) | ✅ **DONE & VERIFIED** — `bbee88a` · rollback: `git revert bbee88a` |
| 2026-10-09 | **PM form mobile UX — M1 (cascade consolidation)** — 3 competing mobile CSS layers (inline `@media` sa `form.blade` · `maint-form/_responsive.css` · global `_phone-portrait.css` PM block na panalo sa `!important`) → inalis ang 187-line PM block sa global file, ini-port ang mga nanalo sa module (floating-card chrome · 850px checklist scroll contract · touch inputs · 13px label floors), **namatay ang `min-width:500px` device-info side-scroll** · desktop 1280 = 100% walang pagbabago · test-first: bagong `PmFormMobileTest` RED **3F** → GREEN **3/3** | ✅ **DONE & VERIFIED** — `ac4e016` |
| 2026-10-09 | **PM form mobile UX — M2 (Tech/End User readability + full-width Device Info)** — (a) **device inputs ~235px lang** (intrinsic width) dahil ang PM-M1 ay `td` lang ang na-blockify; naayos = blockify ang buong chain (grid > own `tbody` > inner table > `tbody` > `tr` > `td`) → `desktopModel` 231px → **373px = container**; **`display` ay sinadyang HINDI `!important`** (regression na nahuli ng verification: ang `!important` ay binasag ang inline `display:none` ng JS → lumitaw ang nakatagong `.monitor-2-row`; `monitor-2`=none / `printer-2`=block muli = tama sa 2-printer na ticket) · (b) readability floors sa `@media≤767px`: section label/bar 11.52→**12.5px**, end-user backup note 11.2→**12.5px**, sig caption 9.6→**11.5px**, ≤390 bar 10.4→**12px** · `overflowX=false`, desktop = naka-media-scoped kaya walang binago · test-first: bagong test RED → GREEN **4/4** (17 assertions); full suite **2F/522P** (2F = pre-existing `CsmMonthlyReportTest`×2) | ✅ **DONE & VERIFIED** — `f6d4a98` |
| 2026-10-09 | **PM form mobile UX — M3 (checklist touch targets + sticky headers)** — (a) **checkbox hit area ~20px lang** (nagmukhang ~30px pero `transform: scale` lang 'yon — hindi lumalaki ang tunay na target; walang effect din ang `tr { min-height }`) → **real 28px box** (`width/height/min-*`), tanggal ang scale trick, `.check-cell` 56→**72px**, **whole-cell tap delegation** sa `_pm_scripts` (anumang tap sa loob ng cell = toggle; may `matchMedia(767px)` guard para **hindi maapektuhan ang desktop** text-selection) → row ≈ **45px**, effective target ≥44px (probe-verified: `cellTapToggle=true`) · (b) **sticky section headers** (`.section-label` + `.section-bar-minimal` `position:sticky top:0`) — kailangan ng `.bond-paper overflow-x: hidden → **clip**` (ang `hidden` ay gumagawa ng ancestor scrollport na tahimik na pumapatay ng sticky) · test-first: bagong test RED → GREEN **5/5** (24 assertions); full suite **2F/523P** (2F = pre-existing `CsmMonthlyReportTest`×2) | ✅ **DONE & VERIFIED** — `f3c39d9` |
| 2026-10-09 | **PM-LT — LAPTOP SPECS shift (user report: \\\"mali mali, dapat accurate\\\")** — root cause sa **CSV import**: ang `mapPmsLaptop` ay nag-de-destruct ng extra `$year` bago `$cpu` (\\\"8:Year 9:CPU\\\") pero ang totoong laptop sheet ay **walang Year column** → **+1 shift lahat ng spec**: `cpu`=RAM value, `ram`=GPU, `gpu`=HD-1, `hd2`=OS, `os`=Office, at `date_acquired`=null (CPU string ≠ date) — napatunayan ng read-only DB probes (23 laptop assets + pm rows 8–40; desktop imports = tama) · fix: destructuring na walang `$year` (`$cpu` = column 8), column 7 = ComputerName\\|Year na lenient sa `parseDate` · **data repair** (one-shot, signature-guarded na `cpu`→\\<n\\>GB at `os`→20xx): **23 assets + 22 PM rows** umikot pabalot pabalik isang slot, JSON backup = `storage/framework/pm_lt_repair_backup_20261009_061326.json` (gitignored); **ang totoong CPU value ay siniba ng lumaing `$year` slot sa import = HINDI na mababawi** → `cpu=NULL` ngayon (accurately unknown, kailangang i-type muli kung may original sheet) · test-first: bagong `InventoryCsvImportTest::test_pms_laptops_map_to_correct_columns` RED → GREEN **8/8** (53 assertions); full suite **2F/524P** (2F = pre-existing `CsmMonthlyReportTest`×2) · render probe (request 83 / pm 40): `ltRam=16GB DDR5 · ltGpu=NVIDIA RTX 4050 · ltOs=WIN 11 PRO · ltHd1=500GB SSD · ltOffice=2021` | ✅ **DONE & VERIFIED** — `0cb6226` |
| 2026-10-09 | **PM PDF letter-size fix** (separate sa defect register): `DownloadMaintenancePdfAction` legal→**letter** · `ArchiveTicketPdfAction` PM=letter / ICT=a4 · `maintenance-form.blade` letter @page metrics + conditional MON-2/PRINTER-2 `rowspan` (tanggal ang empty rowspan cell) + `Printer-2` label + stray `</td>`→`</tr>` + `page-break-inside:avoid` sa checklist · PDF tests **6/6** | ✅ — `f600293` |
| 2026-10-09 | **Phase E PLAN — internal email overhaul**: deep view (14 files, ~50 message sources; §12) + desisyon: **English concise internal ops** · `[NCMB]` prefix **KEEP** · **type strings immutable** · 6 phases E1–E6 (template → mailables → message bank → infra → tests → render QA/docs) | 📝 **PLAN LANG — walang code change pa** |
| 2026-10-09 | **Phase E visual preview + APPROVAL** — 7 scenarios × BEFORE/AFTER na-render mula sa **totoong** `emails/default.blade.php` (`public/__mail-preview/` + generator `storage/framework/mail_preview.php` — temp, hindi naka-commit) + 15 headless-Edge screenshots (`storage/framework/pv_*.png`); **na-approve ng user ang AFTER design** ("oo ganto") — copy per scenario §12.7, template deltas §12.8, findings §12.9; 2 bagong open decisions §9.4–9.5 | ✅ **APPROVED (visual)** — code execution (E1–E6) nakabinbin sa §9.3–9.5 |
| — | Phase 4 — PM Work Orders (assignment back-fill + stats cards) | ⏳ Nakabinbin |

**Rollback:** `git revert f600293` (PM PDF letter size + checklist fixes) · `git revert 0cb6226` (PM-LT laptop CSV column shift — code only; DB repair: `storage/framework/pm_lt_repair_backup_20261009_061326.json`) · `git revert f3c39d9` (PM-M3 touch targets + sticky headers) · `git revert f6d4a98` (PM-M2 device width + floors) · `git revert ac4e016` (PM-M1 cascade consolidation) · `git revert bbee88a` (CSM silent feedback) · `git revert 2c75901` (qr-batch search bar) · `git revert 0548cc5` (scan hub ICT active-only panel) · `git revert 74755d3` (scan hub role sections) · `git revert 076e391` (email rebrand + template refresh) · `git revert 2c9d4de` (M2 action buttons) · `git revert b40956f` (M1 qr-batch mobile cards) · `git revert ecdaaaa` (icon sweep) · `git revert 4c81d14` (PM Tasks IT-side) · `git revert 2d1081d` (DATE RECEIVED autofill) · `git revert 1d4a3e5` (Phase 3b scan flow) · `git revert 0abde01` (Phase 3) · `git revert 3b9f213` (scan throttle) · `git revert 719045b` (Phase 2) · `git revert 7d652ae` (Phase 1c) · `git revert 369edab` (Phase 1b) · `git revert 996e4ca` (Phase 1) · `git revert c5670ae` (docs).

### QR-scan status check (2026-10-05, updated 2026-10-06, base sa register sa itaas)

| QR-scan area | Bug/s | Status |
|---|---|---|
| **Physical Count scanning** (scan card, mark, auto-rescan, mark-all, rate-limit) | 2, 8 | ✅ **OK na** — Phases 2 + 2.5, tested 15/15 + full suite 479 passed |
| **Post-scan pages** (`scan/asset-info`, `scan-preview`, `notice`) | 7 (part) | ✅ OK — Phase 1b |
| **`/r/{id}` QR redirect + stickers** | — | ✅ OK — 406 regenerated sa kasalukuyang `APP_URL` |
| **ICT form Cam/Scan button + auto-fill** | **3** | ✅ **OK na** — Phase 3 (`0abde01`): bundle 1,318 B + `window.AssetScanner` defined · script-block SyntaxError inalis · owner preselect/autofill (5/5 tests; headless Chrome probe: modal opens, autofill OK, 0 JS errors) |

**Verdict (updated 2026-10-06):** ✅ **100% na ang QR-scan areas** — kasama na ngayon ang **ICT form Cam/Scan + auto-fill** (Phase 3, `0abde01`).

**Next:** **Phase 4** (PM Work Orders — assignment back-fill + stats cards).

### Phase 3 RESULT (2026-10-06, `0abde01`)

**Root causes (3, lahat na-reproduce bago mag-fix):**

1. **0-byte bundle** — walang `export`/`window` assignment sa `resources/js/qr-scanner.js` → tree-shake ng Rollup → `public/build/assets/qr-scanner-*.js` = 0 B → `new AssetScanner` throws bago ma-attach ang `scanBtn` listener.
2. **Script ordering** — `@vite` = deferred module, pero ang inline IIFE ay classic = tumatakbo **bago** mag-execute ang module → kailangan ng `DOMContentLoaded` deferral.
3. **SyntaxError sa blade + null-drop sa action** — ang "OLD HANDLER" remnant ay may commented-out opener pero buhay ang pares na `});` → **binababa ng browser ang BUONG `<script>` block** (kahit ang auto-select ay hindi tumatakbo). Dagdag: `CreateIctFormAction` nag-ze-null ng `?asset_id=` kapag na-filter ng status (`For Repair`) kahit pag-aari ng user → walang `<option>`/`ictAssetsMap` entry = walang auto-fill.

**Fixes:** `qr-scanner.js` +`window.AssetScanner` (side-effect = hindi ma-tree-shake) · blade: scanner init naka-`DOMContentLoaded` (+ typeof guard at readyState fallback) at inalis ang remnant · action: kapag ang asset ay wala lang sa dropdown dahil sa status filter → i-keep kung hindi naka-block (`For Disposal`/`Scrapped`/`Disposed` lang; **hindi** ang `For Repair`) at pag-aari (`assetAssignedToUser`) o branch-scope (IT/SA), at **i-push sa `$myAssets`**; kung foreign/blocked → `null` pa rin.

**Verification:**

| Hakbang | Resulta |
|---|---|
| RED (bago fix) | 2 failed / 3 passed — `null` vs `90002` + source walang `window.AssetScanner` |
| `node --check` HEAD vs working | HEAD = `SyntaxError: Unexpected token '}'` (exit 1) → working = exit 0 |
| `npm run build` | `qr-scanner-BRavi3bg.js` = **1,318 B** (dati 0 B), may `window.AssetScanner`, exit 0 |
| GREEN (pagkatapos fix) | **5/5 passed (18 assertions)** |
| Headless Chrome probe | `assetscanner=function`, click Scan → `modal=flex` + `Initializing camera...`, `select=90002`, `autofill=90002`, **0 JS errors** |
| Full suite | **3 failed / 484 passed** = baseline (3F/479P) + 5 bagong tests — walang bagong failure (3 = `CsmMonthlyReportTest` ×2 date-dependent + `PMCalendarTest` ×1, pre-existing) |

**Phase 3b addendum (`1d4a3e5`)** — pagkatapos ng tunnel-rotation logout, napansin na ang guest scan → login ay **deretso sa ICT form** (bypass ang options page). Pre-existing `d5f8ae2` (June 29) — `AuthController` qr-redirect → `route('ict.create')` — na-expose lang ng bagong domain. Fix: `url('/r/'.id)` (options page muna, tulad ng authed flow) + 2 tests. RED 1F/6P → **GREEN 7/7**.

**Observation (out of scope):** `public/build/assets/app-BvRk9kiK.js` ay 0.00 kB din (pre-existing, walang naire-report na epekto) — i-check sa susunod na phase kung naka-`@vite` ito sa mga layout.

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
| I-verify | `Invoke-WebRequest https://<url>/login` → 200 (kapag 302/exception ang PowerShell, alternatibo: `curl.exe -s -o NUL -w "%{http_code}" https://<url>/login`) |

> ⚠️ **Tandaan:** kapag nag-restart ang tunnel at nagbago ang URL, laging `config:clear` + QR regenerate, kung hindi luma ang URL na naka-encode sa mga sticker.

---

## 12. PHASE E — Internal Email System Overhaul (plan, 2026-10-09)

> Contexto: **internal-use na ang CMMS** — dapat tumugma ang buong email surface (template, subjects, ~50 message strings, sender/links) sa internal-ops identity. **Deep view tapos (2026-10-09); walang code change pa** sa planong ito.

### 12.0 Deep-view inventory (14 files, ~50 message sources)

| Layer | Katotohanan |
|---|---|
| Template | `resources/views/emails/default.blade.php` — **iisang shared template**: eyebrow *Official Notification*, details box (Ticket/Type/Status/Date), `View Details` CTA, footer = NCMB + DOLE + CONFIDENTIALITY NOTICE + "do not reply" |
| Mailables (3) | `SystemNotificationMail` · `PMScheduledMail` (may hardcoded message) · `PMAdminNotificationMail` — lahat ay nagre-render ng `emails.default`, subjects `[NCMB] {Type} - #{shortNo}` |
| Dispatch hub | `Notification::booted(created)` — ang ~**47 `Notification::send()` sites** (bell + auto-email) ay dito dumadaan; may super_admin *no-flood* rule, CSM exception, alias-skip, local log preview; `smtp` = send diretsa, iba = queue |
| Direct sends (2) | `GenerateScheduledPM` (SA failure alerts) · `SendPMDueReminders` (weekly summary) — parehong `\n`-laden ang message |
| Auth emails | **Wala** — login/logout views lang (walang password-reset mail) |
| Tests (6) | `CsmMonthlyReportTest` · `CsmWeeklyDigestTest` · `CsmSevereAlertTest` · `NotificationDestinationUrlTest` · `NotificationRequestNumberMatchTest` · `PurchaseRequestTest` |

### 12.1 Natuklasan (defects)

1. 🔴 **`APP_URL` = trycloudflare quick tunnel** → lahat ng email `View Details` links = temporary (namamatay kada restart, §11).
2. 🟠 **`MAIL_FROM_ADDRESS` = personal Gmail** (`rhodabatolina28@gmail.com`) — hindi pang-internal na sender.
3. 🔴 **Multi-line messages = run-on sa email** — walang `nl2br()` sa body (`{{ $notificationMessage }}`) pero `\n`-laden ang weekly summary + PM generation alerts.
4. 🟠 **Tone split** — template = public/agency style (*Official Notification* + confidentiality/DOLE boilerplate), mga message = internal ops instructions.
5. 🟠 **~50 message strings sa 15 files** — magkakaibang style, `strtoupper()` names, walang convention.

### 12.2 Mga desisyon (kinumpirma 2026-10-09)

- **Tono/wika:** **English — concise internal ops** (short, professional).
- **`[NCMB]` subject prefix** = **KEEP** (rebrand `076e391`, D9.42).
- **Notification `type` strings = IMMUTABLE** — may nakadepende: super_admin *"for Review"* gate, CSM prefix gate, `PM Scheduled` side-effect (`Notification.php` L145), parts-family detection, at 6 test files. **Message text lang ang babaguhin.**
- **In-app bell** = parehong message text → kasama sa revision (magpapakita ng bagong teksto rin).

### 12.3 Scope / phases (test-first, RED→GREEN kada phase)

| Phase | Dizon | Files |
|---|---|---|
| **E1** | Template redesign: eyebrow → **"CMMS Notification"** · body → `{!! nl2br(e($notificationMessage)) !!}` · footer → NCMB · CMMS line + "Automated CMMS message — do not reply." + **"For internal use only."** (tanggal ang CONFIDENTIALITY/DOLE boilerplate) · CTA → **"Open Ticket"** · greeting → `Hello {name},` · panatilihin ang header name/sub + details box + status pill | `emails/default.blade.php` |
| **E2** | Mailables: subjects → `[NCMB] {Type} · #{shortNo}` · hardcoded message ng `PMScheduledMail` → concise rewrite · consistency check ng `PMAdminNotificationMail` | 3 mailables |
| **E3** | Message bank (~50 strings): (1) 1 sentence, action-first · (2) tanggal ang `strtoupper()` sa names · (3) **laging may ticket/PR number** (requirement ng `extractRequestNumber`/`prNumber()` fallbacks) · (4) **hindi ang type strings** | `RequestNotificationService`(15) · `PMNotificationService`(3) · `PurchaseRequestNotificationService`(3) · ICT actions(9) · `UpdateMaintenanceTicketAction`(3) · `Request.php`(2) · `CheckLowStockAction` · `GeneratePMScheduleService` · CSM services(3) · `GenerateScheduledPM` · `SendPMDueReminders` |
| **E4** | Infra/deploy: **`APP_URL`** (tunay na internal URL) + **`MAIL_FROM_ADDRESS`** (office account) → ilagay sa `PRODUCTION_DEPLOY_CHECKLIST.md`; `.env` = gitignored → ibinibigay ng user (open item §9.3) | checklist doc + `.env` (hindi naka-commit) |
| **E5** | Tests: bagong `EmailTemplateInternalTest` (nl2br multi-line · footer walang CONFIDENTIALITY · may "For internal use only." · eyebrow text · subject format) · 6 existing email tests GREEN · full suite vs baseline **2F/524P** | `tests/Feature/` |
| **E6** | Render probe (sample data → HTML → screenshot bago/pagkatapos) · progress-log row + rollback line · commits + push `origin/develop` | — |

### 12.4 Commits (kada isa ay `git revert`-able)

`E1+E2` template+mailables → `E3` message bank (+ E5 tests sa parehong phase) → `E6` docs + push.

### 12.5 Verification toolkit (tulad ng mga naunang phase)

`php artisan test` (kada phase + full suite) · render probe sa `public/` (temp, lilinisin bago mag-commit) · `git diff` review bago bawat commit · docs row + rollback line pagkatapos.

### 12.6 Visual preview + approval (2026-10-09)

- **Deliverable:** `public/__mail-preview/index.html` — 7 scenarios × BEFORE/AFTER side-by-side, rendered mula sa **totoong** `emails/default.blade.php` (hindi mockup) gamit ang totoong `shortNumber()`/`shortenNumbersInText()` helpers (D9.42).
- **Generator:** `storage/framework/mail_preview.php` (temp, gitignored area, **hindi kino-commit**) — kaya i-regenerate kung may copy change: `php storage/framework/mail_preview.php`.
- **Bukas:** `http://127.0.0.1:8000/__mail-preview/` (o `file:///C:/laragon/www/CMMS/public/__mail-preview/index.html`).
- **Evidence:** 15 headless-Edge screenshots = `storage/framework/pv_*.png` (6) + `pv2_*.png` (10-1 [10 files]) — temp.
- **Verdict:** user **na-approve ang AFTER design** ("oo ganto") → E1–E6 proceed pagkatapos masagot ang §9.3–9.5.
- **Preview-only, OUT of E-scope:** ang link sa loob ng preview = placeholder (`cmms.internal.local`); ang totoong email link = §9.3 (`APP_URL`).

### 12.7 Approved copy — scenario by scenario (E3 message bank + E2 subjects)

> Legend: `{N}` = stored full number · `{S}` = `shortNumber({N})` (D9.42) · parehong structure ng details box/CTA/footer ang lahat (§12.8).

| # | Scenario | BEFORE (current, totoong strings) | AFTER (approved) |
|---|---|---|---|
| **S1** | ICT assigned/updated (requestor) | `Your ICT Repair request {N} is now Ongoing. IT personnel Juan Dela Cruz has been assigned to work on your ticket.` | `Your ICT Repair request {S} is assigned to Juan Dela Cruz. Open the ticket to track progress.` |
| **S2** | Rejected (may reason) | `Your ICT Repair request {N} was rejected. Reason: Duplicate ticket.` | `Your ICT Repair request {S} was rejected — Duplicate ticket. Submit a new ticket if the issue persists.` |
| **S3** | PM Scheduled (requestor) | `A workstation preventive maintenance (PM) has been scheduled for your equipment in {div}. Please coordinate with your ICT Unit for your schedule.` | `PM for your workstation in {div} is scheduled on {date}. Coordinate with your ICT Unit for your time slot.` |
| **S4** ☠️ | PM Task Assigned (admin) | `A new PM task has been assigned to you. Please check your dashboard.` (+ literal `TBD` sa Date field) | `PM task {S} is assigned to you. Conduct the PM and encode results before {date}.` (Date = totoong schedule, §12.9) |
| **S5** 🔴 | Weekly PM summary | `\n`-laden → **run-on** sa email (walang `nl2br`) | parehong structure + `nl2br` + ending: `Log in to the CMMS to conduct the remaining PMs.` |
| **S6** 🔴 | PM generation FAILED | `PM Generation FAILED — Action Required` + details na **run-on**; Type field = mahabang string | `PM generation FAILED for schedule "{name}".` + bawat field = sariling linya; Type = `PM Alert` |
| **S7** | PR submitted | `PR-2026-0015 submitted — 3 item(s), total ₱12,500.00. Awaiting your review.` | `PR-2026-0015 submitted — 3 items, ₱12,500.00 total. Awaiting your review.` |

**Subjects (E2) — lahat ng 7:**

| | BEFORE | AFTER |
|---|---|---|
| S1/S2 | `[NCMB] {Type} - #{S}` | `[NCMB] {Type} · #{S}` |
| S3/S4 | `[NCMB] … - #{N}` (**RAW** — hindi ashorener) | `[NCMB] … · #{S}` |
| S5/S6 | `[NCMB] … - #SYSTEM` | `[NCMB] … · #SYSTEM` |
| S7 | `[NCMB] PR Submitted - #PR-2026-0015` (redundant hash) | `[NCMB] PR Submitted · PR-2026-0015` (walang `#` para sa PR) |
| S6 Type field | `PM Generation FAILED — Action Required` | `PM Alert` (kapareho ng bell row type — **hindi** registered gate string, safe) |

### 12.8 Template deltas — E1 exact literals (`resources/views/emails/default.blade.php`)

| Line (approx) | BEFORE (exact literal) | AFTER |
|---|---|---|
| L190 | `<div class="header-eyebrow">Official Notification</div>` | `…>CMMS Notification</div>` |
| L198 | `<div class="greeting">Good day, {{ $recipientName }}!</div>` | `<div class="greeting">Hello {{ $recipientName }},</div>` |
| L199 | `<div class="message">{{ $notificationMessage }}</div>` | `<div class="message">{!! nl2br(e($notificationMessage)) !!}</div>` — **ang core run-on fix** (S5/S6) |
| L228 | `<a href="{{ $ticketUrl }}" …>View Details</a>` | `…>Open Ticket</a>` |
| L237 | `Department of Labor and Employment, Republic of the Philippines` | `Computerized Maintenance Management System` |
| L239 | `This is an automated notification. Please do not reply.` | `This is an automated CMMS message — do not reply to this email.` |
| L240 | `<strong>CONFIDENTIALITY NOTICE:</strong> This email and any files transmitted with it are confidential…` | `<strong>For internal use only.</strong>` |
| — | **KEEP:** header name (agency full name) + subtitle (*Computerized Maintenance Management System*), details box (Ticket/Type/Status/Date), status pill, `#0038A8` top-bar/CTA, gray backdrop, Outlook `bgcolor` fallbacks, `<title>` default (rebrand `076e391` — hindi babaguhin) | |

**Mailable deltas — E2 (`app/Mail/*.php`):**

| File | Change |
|---|---|
| `SystemNotificationMail` | subject: `" - #"` → `" · #"` (L55) — `shortNumber()` nasa lugar na ✓ |
| `PMScheduledMail` | subject: raw `{requestNumber}` → `shortNumber()`; hardcoded message → §12.7 S3 copy; **i-pass ang totoong `scheduleDate`** (§12.9.2) |
| `PMAdminNotificationMail` | subject: raw → `shortNumber()`; date fallback `'TBD'` → `null` (walang Date row kung unknown); **depende sa §9.4 (wire/delete)** |
| `Notification.php` L117-125 | `PMScheduledMail(…, null /* scheduleDate */, …)` → i-pass ang `$request` PM schedule date kung available (E2) |

### 12.9 Natuklasan sa preview (bagong findings/gates)

1. ☠️ **`PMAdminNotificationMail` = dead code** — 0 call sites sa buong codebase (search: `PMAdminNotificationMail(` = 0) → **§9.4** (wire o delete). Kung delete: kasama sa E2 + tanggalin ang S4 scenario.
2. 🔴 **S3 Date field = ngayon, hindi PM date** — `Notification.php` L121 ay nagpapasa ng `scheduleDate = null` → `PMScheduledMail` falls back sa `now()`. E2: ipasa ang totoong schedule date mula sa PM request/schedule row.
3. ⚠️ **S7 label** — "Ticket No." ang lumalabas para sa PR number → **§9.5** (keep vs "Reference No."). Kung "Reference No.": condition lang sa `type`/PR, additive sa E1.
4. ✅ **S6 Type change = safe** — ang `PM Generation FAILED — Action Required` ay lokal na ginawa sa `GenerateScheduledPM` L356-358 (hindi registered/immutable gate string; ang bell row type ay `PM Alert` na) → mapapalitan sa E2/E3.
5. **Subject quirks confirmed** (§12.7): S3/S4 raw numbers + S7 `#PR-…` redundancy — parehong pinapakita ng preview chips.