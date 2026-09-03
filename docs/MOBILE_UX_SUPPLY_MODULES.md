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
1. ✅ Dashboard
2. ✅ Supply Asset Registry
3. ✅ Parts & Consumables
4. ✅ Asset Detail
5. ✅ Purchase Request Show
6. ✅ Purchase Request Create / Edit
7. ✅ Requisitions / Supply Workspace
8. ✅ Department Requests
9. **Batch QR Sticker Print** (`qr-batch.blade.php`)

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
