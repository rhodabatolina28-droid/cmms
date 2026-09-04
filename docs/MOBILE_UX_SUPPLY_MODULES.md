# Mobile UX — Supply Officer Modules Series

**Series goal:** Mobile-first UX per module for supply officer / admin, **without touching desktop view**.
**Rule:** lahat ng mobile changes nasa loob ng `@media (max-width: 767/768px)` na media queries — **desktop zero change**.
**Pattern (na-approved sa Physical Count):** table→cards na may `data-label` + `::before`, sticky search bar, 44-50px touch targets, compact stats, custom Prev/Page/Next bar (mobile only).
**⚠️ IMPORTANT LESSON (parts):** hindi lahat ng page gusto ang card/stacked-label approach. **Parts page: pinagpasyahan ng user — table pa rin (gaya ng inventory) na may horizontal scroll** — hindi cards, hindi stacked labels, **WALANG itatago** (lahat ng columns importante).

---

## ✅ DONE — Committed (2026-09-02)

### Physical Count module (complete, tested)
- `docs/PHYSICAL_COUNT_CUSTODIAN_GROUP.md` — full custodian group counting series
- Mobile walk-around UX + buttons + search bar + pagination — commits `a7029c4`, `78b569e`

### Dashboard (`dashboard/admin.blade.php`) — `6d8dfa0`
- Mobile: welcome hero stacks (24px name, full-width office box); compact `premium-table-box` (14px) + Snapshot button padding
- Recent Requests table: **inibalik sa ORIGINAL table** — na-reject ang card design (2x). Walang `data-label`/cards dito.

### Supply Asset Registry (`inventory/index.blade.php`) — `9b4fd4b`, `e3bdbc2`, `30f30bf`, `2be0bd2`, `36a922d`
- Stats cards: 2-col + huling card full-span; table touch-friendly padding
- Pagination: **naayos ang vertical-stacking bug** — root cause ay ang global mobile rule `button { width: 100% !important }`; fix = inline `style="width:auto !important"` sa `resources/js/inventory.js` (5 buttons) + Vite rebuild. **Desktop at mobile pagination pareho na ang look** (nag-wrap lang ang mobile).
- ⚠️ **Font regression naayos:** ang unang rebuild ay gumamit ng `optimizedFallbacks: false` (dahil kulang ang `fontaine`) → bumaba ang built CSS font fallbacks → bahagyang naiiba ang desktop font rendering. **Fix: `npm install fontaine --save-dev` + revert config** (`22ec451`, `ad516ff`) — desktop balik sa orihinal.

### Parts & Consumables (`parts.blade.php`) — ✅ Phase 1 Complete
- Mobile view: **pure horizontal scroll table gaya ng inventory** — walang sticky column na tumatakip, kumpleto lahat ng 8 columns (`Item`, `Unit`, `On-hand`, `Reorder`, `Unit Value`, `Total Cost`, `Status`, `Actions`).
- **Action buttons fix:** Sa mobile, pinalitan ang 6 nakaharang na buttons ng **iisang 3-dots dropdown (`⋯`)** — Inventory Registry pattern; hindi na haharang sa table. Sa desktop, retained ang quick-action icon buttons.
- **Add Part Modal fix:** Tinanggal ang checkbox na `Track every unit (serialized)` at sub-text nito.
- **Modal Close (`×`) fix:** Naayos ang dahilan kung bakit sobrang laki ng `×` button sa mobile; nilagyan ng fixed 32×32px, 0 padding, at centered display sa lahat ng modals (`partModal`, `stockModal`, `historyModal`, `unitsModal`).
- Stats: 2x2 compact grid (`repeat(2, 1fr)`) sa mobile.
- Toolbar: 2-column grid para sa Export/Import at Add/Create buttons.
- Pagination: may inline `style="width:auto !important;"` sa bawat button at smart ellipsis para hindi mag-stack vertically.

### Asset Detail (`inventory/detail.blade.php`) — ✅ Phase 2 Complete
- **Structural Bug Fix:** Tinanggal ang sobrang closing `</div>` sa ilalim ng Installed Parts card na maagang nagko-close sa `.main-grid` at sumisira sa page container.
- **QR Spacing:** Na-verify ang `card-mt20` (16px top margin).
- **Typography Polish:** Pinalitan ang microscopic 8-9px font sizes sa screens `<= 480px` ng nababasang 12-14px mobile typography.

### Purchase Request Show (`purchase-requests/show.blade.php`) — ✅ Phase 3 Complete
- **Mobile Responsive Layout:** Dinagdagan ng `@media (max-width: 768px)` (dating zero mobile CSS).
- **Item Grid Scroll:** Binalot ang `.prd-table` sa `.prd-table-responsive` na may `min-width: 620px` at horizontal touch scroll para hindi mag-ipit ang mga numero at paglalarawan.
- **Actions & Toolbar:** Ginawang mobile-friendly ang toolbar; ang mga action buttons (Print, Finalize, Receive) ay naging 44px touch targets sa 2-column grid.
- **Print Fidelity:** 100% buo at sumusunod sa Annex 60 Government Accounting Standards gamit ang `@media print` overrides.

### Purchase Request Create & Edit (`create.blade.php`, `edit.blade.php`) — ✅ Phase 4 Complete
- **Touch-friendly Delete Button:** Ginawang laging kita ang `.pr-x` sa mobile (dating naka-depende sa desktop `:hover` at `opacity: 0` kaya hindi ma-delete ang rows sa phone).
- **Mobile Toolbar & Actions:** 44px min-height buttons, responsive sticky header.
- **Stacked Signatures:** Malinis na 1-column layout para sa Requested By at Approved By sa mobile screens.

---

## 🔶 NEXT MODULES (per plan):
1. ✅ Dashboard (admin/supply)
2. ✅ Supply Asset Registry
3. ✅ Parts & Consumables
4. ✅ Asset Detail
5. ✅ Purchase Request Show
6. ✅ Purchase Request Create / Edit
7. ✅ Requisitions / Supply Workspace
8. ✅ Department Requests
9. ✅ Personnel Management
10. ✅ PM Work Orders / PM Schedules
11. ✅ Maintenance Calendar
12. 🔶 **SUPER ADMIN series (in progress):** Dashboard ✅ · User Management ✅ · Audit Logs ⏳ · QR Batch Print ⏳ · ibang super-admin pages ⏳

## Notes / Gotchas (session-learned)
- **Global mobile rule:** `button { width:100% !important }` sa layout — para sa inline buttons sa tables/modals, kailangan `width:auto !important` **inline** hindi lang stylesheet (ang stylesheet `!important` ay tinatalo ang normal na inline; kailangan ng inline na may `!important` para talunin ito).
- **`npm run build`** — ang `npm` sa PowerShell ay naka-block (execution policy); gamitin ang **`npm.cmd run build`**.
- **Font plugin:** ayaw mag-build kapag walang `fontaine` — i-install ito kung may nag-rebuild (default ay OK kapag naroon na).
- **`git checkout -- file`** ang ginagamit para i-revert ang hindi nagustuhang design (parts card experiment).
- **Checklist pagkatapos ng bawat mobile change:** `php artisan view:clear` → `view:cache` (walang ERROR) → zero-width char scan → related tests → commit.
## Session 2 Additions
- **Dept Requests table** (12ed0d7): Supply Workspace scroll pattern � hint banner + .ad-table-wrap (touch scroll) + min-width 780px. Desktop: walang pagbabago.
- **Parts fix** (12ed0d7): inalis ang dobleng @media (max-width: 768px) (nested) � base .mobile-table-hint { display:none } nasa labas ng MQ (pareho sa inventory).
- **Pattern rule:** ang base hide rule (display:none para sa hint) ay dapat nasa **labas** ng media query; ang display:flex !important na re-show ay nasa **loob** ng MQ.

---

## Session 3 — Super Admin Series + Maintenance Calendar Deep-Dive (2026-09-03)

### A. Supply-side completion (bago ang super admin)
| Commit | Ano |
|---|---|
| `f76b593` | My Parts Requisitions — 3 tabs kasya sa isang row; Awaiting Parts checkbox = touch-friendly card |
| `4f33c4d` | Checkbox align sa unang linya ng "Awaiting Parts" label |
| `3b102c5` | PM Work Orders — swipe hint, 2x2 filter grid, natural-width table, 44px touch targets |
| `8d7da63` / `9c565bb` | PM Schedules — swipe hint + natural-width table; **tinanggal ang Delete All Schedules button** (UI + JS + dead CSS); per-schedule delete retained |
| `7410e95` | fix: em-dash mojibake sa orders blade |

### B. Maintenance Calendar (`maintenance-calendar.css`) — mobile
| Commit | Ano |
|---|---|
| `f5e86c8` | Calendar grid: desktop-like cells na may VISIBLE PM/ICT chips, horizontal scroll (min-width 700px), compact 2-col legend, swipe hint |
| `836d1b4` / `ef939dc` | Nav group polish; right-panel cards (detail/summary/upcoming/empty) width-contained, titles ellipsis |
| `b2469b5` → `119ba85` → `51b246d` → `8615b10` | **Detail card deep-dive** (tingnan sa C sa ibaba) |

### C. Detail Card Deep-Dive (`calDetailCard`) — 3 suliranin at root causes

**1. Header naka-vertical (title sa taas, X sa baba) — `119ba85`**
- **Root cause:** Global touch rule sa `mobile-responsive/_base.css`:
  `button:not(.swal2-confirm):not(.swal2-cancel):not(.swal2-deny) { min-height:48px; padding:12px 24px }`
  Specificity **(0,3,1)** — TATALO sa `.cal-detail-close` (0,1,0) **kahit parehong may `!important`** (mas mataas ang specificity, hindi pinagtatalunan ang source order).
- **Fix:** High-specificity mirror selector na (0,5,1):
  `.cal-detail-header button.cal-detail-close:not(.swal2-confirm):not(.swal2-cancel):not(.swal2-deny)`
  → 28x28px, padding:4px, flex:0 0 28px + `flex-flow:row nowrap` sa header. **Title kaliwa, X kanan — hindi na nagsta-stack.**

**2. Date / Assignee / Office values lumalagpas — `51b246d`**
- **Root cause:** `table-layout:fixed` + `td width:36%/64%` — value column 64% lang, may edge cases ang fixed layout sa iba't ibang screen sizes.
- **Fix — vertical stacking (bulletproof):** bawat `tr`/`td` → `display:block`; label (td:first-child) = 10px uppercase gray; value (td:last-child) = 13px, **100% width** + `overflow-wrap:anywhere`. Kahit pinakamahabang office name (hal. "RESEARCH AND INFORMATION DIVISION"), nagwa-wrap nang buo sa loob ng card. Walang posibleng lagpas.

**3. Header square corners — `8615b10`**
- **Root cause:** Sa desktop, `overflow:hidden` ng card ang gumugupit ng rounded corners ng blue header. Sa mobile, `overflow:visible !important` ang card (anti-overflow fix) → nawalan ng clip → square.
- **Fix:** `.cal-detail-header { border-radius:13px 13px 0 0 !important }` (13px = 14px card radius minus 1px border) — eksaktong tugma sa kurba ng card, pareho ng ibang cards.

### D. Super Admin modules (sinimulan)
| Commit | Ano |
|---|---|
| `753a79c` | Super Admin Dashboard — Recent Office Requests **hindi na compressed** (min-width 700px sa loob ng scroll-x wrapper; tinanggal ang 10px/8px squish rules) |
| `2b7f589` | Request Volume by Office / Asset Status Overview charts — **hindi na lumalagpas** (grid `minmax(280px,1fr)` → `1fr` sa mobile; canvases constrained) |
| `726f72e` | **User Management** — mobile stats (Total full-width sa taas, Active/Inactive 2-col sa ibaba) + swipe-table hint; pagination footer untouched; desktop unchanged |
| `abfc86d` | Users table container — hindi na inii-shift ang page kapag nag-horizontal scroll (overscroll-contain + clip) |

### E. PINAKAMALALANG LESSON ngayong session: **STALE VITE BUILD**
- **Nangyari:** Lahat ng CSS fixes ay nasa source na, PERO ang built CSS sa `public/build/assets/` ay luma (source 3:49 PM vs build 3:31 PM). Kaya "ganon pa rin" ang nakikita ng user kahit tama na ang code.
- **BAGONG RULE: pagkatapos ng BAWAT CSS/JS edit → `npm.cmd run build` AGAD**, tapos i-verify na ang bagong hash file (hal. `maintenance-calendar-DV0w5k4D.css`) ay may laman ng bagong rules bago mag-report na "ayos na".
- **Dagdag:** ang CSS minifier ay maaaring mag-reorder/merge declarations (hal. `flex-direction:row + flex-wrap:nowrap` → `flex-flow:row`) — huwag mag-panic kapag hindi tugma ang exact string sa built file; gumamit ng looser pattern sa verification.
- **PowerShell false alarm:** `git push` na may stderr output ay nagre-report na "error" kahit SUCCESS — i-verify sa `git rev-parse HEAD` vs `origin/develop` (dapat magkapareho).

## NATITIRA SA SUPER ADMIN SERIES (sunod na gagawin)
1. **Audit Logs** — table mobile UX (swipe pattern + stats kung meron)
2. **QR Batch Sticker Print** — page layout + print preview sa mobile
3. **Ibang super-admin pages** — i-audit kung ano pa ang may table/modal (Settings, Roles, Departments, atbp.) at i-apply ang parehong pattern
4. **Final sweep** — isang pass sa lahat ng natitirang mobile issues bago mag-move sa susunod na malaking feature

### QR Batch Sticker Print (021062c)
- Na-verify: may comprehensive mobile block na (header stack, 44px buttons, filter column, table scroll)
- Nadagdag para sa consistency: mobile-table-hint swipe banner (base display:none, mobile flex)
- Checkbox touch targets: 20x20px, navy accent (20px col)
- Desktop: untouched (base hide + MQ additions lang)

## SESSION 3 FINAL STATUS
- Supply-side modules: 100% COMPLETE (dashboard, inventory, parts, detail, PR show/create/edit,
  requisitions workspace, dept requests, personnel, PM schedules/orders, maintenance calendar)
- Super admin: Dashboard (table uncompressed + charts overflow fix), User Management (stats layout,
  table no-shift), Audit Logs (swipe + 980px), Master List of Requests (stats + swipe)
- QR Batch Sticker Print: COMPLETE (021062c) — ALL MODULES DONE

## NEXT SESSION (naka-queue)
1. Live mobile QA pass sa lahat ng modules (user verification)
2. Push accumulated commits kung gusto (local ahead ngayon)
3. Iba pang super-admin pages kung may ma-spot
