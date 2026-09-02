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

### Parts & Consumables (`parts.blade.php`) — `b0fc781` — 🔶 WIP (hindi pa tapos)
- Committed state: mobile `table-layout:auto`, `min-width:640px`, `nowrap`, inline 42px action buttons, centered pagination.
- **Hindi pa naresolba ang UX:** feedback ng user pagkatapos ng commit — *"mahihirapan ang gagamit, kailangan pa i-improve."* Na-eksperimento ang sticky first column + `colgroup:hidden` + mas malalaking touch (44px) — **hindi pa na-save sa commit / hindi pa na-approve** (na-revert o nawala sa working tree). **Kailangan i-resume bukas.**

### Asset Detail QR spacing (`inventory/detail.blade.php`) — 🔶 UNCOMMITTED
- `working tree: M resources/views/inventory/detail.blade.php` — idinagdag ang `card-mt20` (16px top margin) sa **QR Code card** para magkaroon ng space pagkatapos ng Notes card (base `.detail-card` ay walang margin → magkadikit).
- **As per user:** "sure ka na" — kailangan pa i-verify sa live bago i-commit.

---

## 🔶 NEXT (resume bukas)

### 1. `parts.blade.php` — tapusin ang table mobile UX
- Gusto ng user: **table gaya ng inventory** (scrollable, lahat ng columns, hindi stacked).
- Kailangan i-polish para hindi "mahihirapan ang gagamit": sticky first column (solid bg per row-state), 44px action buttons, malinaw na row tints (low amber / critical red) pati sa sticky cell.
- **Pending decision:** i-commit ang sticky-column approach, o subukan pa ang iba → i-check sa user bago mag-implement.

### 2. `detail.blade.php` — i-verify sa live ang QR spacing; kung OK, i-commit.

### 3. Susunod na modules (per plan):
1. ✅ Dashboard
2. ✅ Supply Asset Registry
3. 🔶 Parts & Consumables (ito)
4. **Requisitions / PR Queue** (`requisitions/supply-index.blade.php`)
5. **Department Requests** (`admin/requests/index.blade.php`)
6. **Batch QR Sticker Print** (`qr-batch.blade.php`)

## Notes / Gotchas (session-learned)
- **Global mobile rule:** `button { width:100% !important }` sa layout — para sa inline buttons sa tables/modals, kailangan `width:auto !important` **inline** hindi lang stylesheet (ang stylesheet `!important` ay tinatalo ang normal na inline; kailangan ng inline na may `!important` para talunin ito).
- **`npm run build`** — ang `npm` sa PowerShell ay naka-block (execution policy); gamitin ang **`npm.cmd run build`**.
- **Font plugin:** ayaw mag-build kapag walang `fontaine` — i-install ito kung may nag-rebuild (default ay OK kapag naroon na).
- **`git checkout -- file`** ang ginagamit para i-revert ang hindi nagustuhang design (parts card experiment).
- **Checklist pagkatapos ng bawat mobile change:** `php artisan view:clear` → `view:cache` (walang ERROR) → zero-width char scan → related tests → commit.