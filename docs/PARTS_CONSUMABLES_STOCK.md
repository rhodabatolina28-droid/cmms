# Parts & Consumables Stock — Detailed Implementation Plan

> Sub-module ng **Inventory** para sa mga **parts/consumable na walang serial**
> (RAM, SSD/HDD, toner, screws, connectors) at itinatago ayon sa **quantity + unit**.
> Ito ang **"stock source"** na ichi-check ng Supply Officer bago magdesisyon
> (Issue vs Purchase Request) sa parts requisition.

---

## ⭐ Today's system update — Parts & Consumables (August 17, 2026)

What we changed in the **Parts & Consumables** module today (in the system):

- **Per-piece serial / property / cost tracking:** every physical piece (pcs/unit) now has its own **serial number**, **property number**, and **cost per unit** (new `parts_stock_units` table).
- **Units list:** a **🔢 Units** button on each part opens a list of its pieces (serial, property, status, custodian) with a **TOTAL cost** row.
- **Cost columns on the Parts page:** the table now shows **Unit Value** and **Total Cost** (₱ format, like Inventory).
- **Stock In / Stock Out with serials:** Stock In can add a list of serials; Stock Out can pick specific serials and mark them as issued (stock count stays consistent).
- **Linked to Asset & Request:** a piece can be linked to an **asset** (the "Installed Parts" card on the Asset Profile) and to a **repair request** (the "Parts Used" card on Maintenance/ICT forms).
- **CSV Import:** an **Import CSV** button on the Parts page loads a file → preview → imports parts with their per-piece serial/property/cost.
- **Sample data:** seeded sample parts (RAM, HDD) so this can be tested right away.

---


## ✅ PARTS & CONSUMABLES — Recent Work (2026-08)

A plain-English summary of what was added to the Parts & Consumables module.

1. **Parts & Consumables Stock (Aug 13)** — add/edit parts, stock-in, stock-out, and movement history; Purchase Request (RA 9184) process; linked parts to repair requisitions; fixed a stock-in/out URL bug.

2. **Live filtering & summary cards (Aug 14)** — the Parts page now filters and updates its summary cards instantly (like Inventory) without a page reload; fixed Super Admin's Parts History; removed the read-only banner; added automatic low-stock alerts (email/in-app, runs daily).

3. **Serialized per-unit tracking (Aug 17)** — each physical piece (pcs/unit) now has its own **serial number**, **property number**, and **cost per unit**:
   - New table `parts_stock_units` — one row per physical piece.
   - **Units modal** — see every serial/property/status per piece, with a TOTAL cost row.
   - **Stock In** can add a list of serials; **Stock Out** can pick specific serials and mark them issued.
   - **Units button** on each part row.
   - **Parts table** now shows **Unit Value** (cost per unit) and **Total Cost**, formatted like Inventory (₱).
   - **Reference tracking** — a unit can be linked to an **asset** (the "Installed Parts" card on the Asset Profile) and to a **repair request** (the "Parts Used" card on Maintenance/ICT forms).
   - Kept the stock count consistent: issuing via Stock-Out or a Requisition marks the right units as issued (no count mismatch).

4. **CSV Import (Aug 17)** — the Parts page has an **Import CSV** button that loads a prepared CSV → shows a preview (parts, units, duplicate serials) → imports the parts with their per-piece serials/property/cost, keeping the stock count in sync.

5. **Sample data** — a seeder adds sample parts (RAM, HDD) with per-piece serial/property/cost so the feature can be tested right away.

**Full technical details:** see `docs/PARTS_SERIALIZED_UNITS_DEEPVIEW.md`.

---


## ✅ STATUS — Na-update 2026-08-13

> **Kumpleto at na-verify ang Phases A + B + C + D** — ang buong Parts & Consumables Stock module, kasama ang Purchase Request (RA 9184) workflow.

| Phase | Status | Katibayan |
|---|---|---|
| **A — Schema + Models** | ✅ DONE | migrate/rollback OK · models smoke test OK |
| **B — Parts Stock module** | ✅ DONE | `PartsStockTest` — 8 passed |
| **C — Requisition integration** | ✅ DONE | `RequisitionPartsIssueTest` — 5 passed |
| **D — PR workflow (RA 9184)** | ✅ DONE | `PurchaseRequestTest` — 5 passed |

**Walang regression sa existing:** `PMCalendarTest` (26) · `InventoryCsvImportTest` (7) · Unit (1) — lahat pumasa.
**Pre-existing lang ang 2 failing tests** sa `PMFlowTest` (cooldown + disposed-user) — **hindi** gawa ng module; itatama sa hiwalay na fix.

**Rollback checkpoint:** `git branch checkpoint/pre-parts-stock` (HEAD `51fcc2a`) + DB dump `storage/ux_backup/cmms_pre-parts-stock_20260813_083836.sql`.

---

## 🔧 Recent fix — Stock In/Out/Edit/History URL (2026-08-13)

**Sintomas:** `The route inventory/parts//stock-in19 could not be found.` kapag nag-restock (Stock In), Stock Out, nag-edit, o nag-bukas ng History sa parts page.

**Root cause** (sa `resources/views/inventory/parts.blade.php`): mali ang pagbuo ng URL sa JS para sa mga `{part}`-parameterized routes:
- `'{{ route('inventory.parts.stock-in', ['part' => 0]) }}'.replace('/0', '/')` → nagdulot ng **dobleng slash** (`…/parts//stock-in`).
- Ang id ay **ini-append sa wakas** (`…/stock-in` + `19`) sa halip na isingit sa `{part}` → `…/parts//stock-in19` → **404**.
  - Dahil ang `{part}` ay **nasa gitna** ng URL (`/inventory/parts/{part}/stock-in`), hindi maaaring `prefix + id` (na naglalagay ng id sa dulo).

**Fix (commit `1eecaec`):** pinalitan ng isang tunay na `PART_ID` placeholder (na pinapalitan sa JS) ang sirang `.replace('/0','/')` trick:
```js
const PARTS_UPDATE_PREFIX  = '{{ route('inventory.parts.update',   ['part' => 'PART_ID']) }}';
const PARTS_STOCK_IN_PREFIX  = '{{ route('inventory.parts.stock-in',   ['part' => 'PART_ID']) }}';
const PARTS_STOCK_OUT_PREFIX = '{{ route('inventory.parts.stock-out',  ['part' => 'PART_ID']) }}';
const PARTS_MOVEMENTS_PREFIX = '{{ route('inventory.parts.movements',  ['part' => 'PART_ID']) }}';
```
Sa mga call site: `PARTS_STOCK_IN_PREFIX.replace('PART_ID', stockPart.id)` (gayundin para sa update, stock-out, movements).

**Resulta:** `…/parts/19/stock-in` — walang dobleng slash, tama ang posisyon ng id.

**Na-verify:** `route('inventory.parts.stock-in', ['part' => 'PART_ID'])` → `…/parts/PART_ID/stock-in` ✓ · `view:clear` + `view:cache` OK ✓ · `PartsStockTest` — **10/10 pass (39 assertions)** ✓

**Commit:** `1eecaec` — buong parts-stock + purchase-request feature at ang URL fix (41 files, 3647 insertions).


## 🔄 Recent updates — Live Ajax filtering, Super Admin history, smooth filter (2026-08-14)

**Commits:** `66b8ba1` (live filtering/stats) · `586cfc4` (super admin history + banner + smooth filter).

### A. Live Ajax filtering + stats cards (gaya ng inventory)
- Bagong data endpoints: `inventory.parts.data` (supply/admin) at `super_admin.parts.data` (super admin) — JSON `{ success, parts[], total, per_page, current_page, last_page, stats }`.
- `ListPartsStockAction` — nag-share ng query builder (`baseQuery`, `applyFilters`, `statsFor`); ang `stats` (totalParts/totalOnHand/lowStockCount/criticalCount) ay **sumusunod sa filter** (hindi na global).
- `parts.blade.php` — JS-render table/pagination/stats; debounced search (400ms) + select change (120ms) → live update nang **walang page reload** (gaya ng inventory).

### B. Super Admin history fix ("Unable to load")
- Root cause: `inventory.parts/{part}/movements` ay nasa `role:admin` group → Super Admin (role `super_admin`) ay 403.
- Fix: bagong route `super_admin.parts.movements` (sa `role:super_admin` group); `PARTS_MOVEMENTS_PREFIX` ay conditional sa view. Inalis din ang read-only banner.

### C. Smooth filter (gaya ng inventory)
- Alinsunod sa inventory: **walang** "Loading"/dim UI kapag nagpapalit ng filter — direktang re-render pagkatapos ng fetch. May `partsReqSeq` guard para iwas out-of-order.

---

## 🧭 NEXT — Low-Stock Automation (Plano)

### Goal
Awtomatikong alerto (in-app + email) tuwing may parts/consumables na **Low** o **Critical (no stock)**, at **CSV export** ng parts.

### ✅ Phase 1 — Combined low-stock notifications — **DONE 2026-08-14** (commit `6d75ef1`)
> Verified: `PartsLowStockTest` — **4/4 pass (20 assertions)** · command `parts:check-low-stock` registered sa `Console\Kernel` (`dailyAt('07:00')`).
- Migration: idagdag ang `low_notified_at` + `critical_notified_at` (nullable timestamp) sa `parts_stock`.
- `CheckLowStockAction`:
  - **Hindi per-item** — i-grupo ang lahat ng bagong low/critical ayon sa **location** (region/branch) at magpadala ng **ISANG summary notification bawat supply staff** na naglilista ng lahat ng item (hal. on-hand/reorder). Kung isa lang ang item, isa pa ring notification na may 1 laman lang.
  - Dedupe: i-flag ang item kapag naipadala na ang alerto (`low_notified_at`/`critical_notified_at`); **self-heal** kapag healthy — para makapag-alert ulit kung bumaba muli.
  - Recipient: supply staff (`supply_officer` OR `admin` + `can_supply`) na tugma sa region/branch.
- Artisan command `parts:check-low-stock` (+ `--dry-run`), i-register sa `Console\Kernel` (`dailyAt('07:00')`).
- Part model: i-add sa `fillable`/`casts`.

### Phase 2 — Parts CSV export
- `ExportPartsStockAction` (CSV: Item, Unit, Category, On-hand, Reorder, Level, Region, Branch) na may scoping + filters.
- Routes `inventory.parts.export` / `super_admin.parts.export` + "Export" button sa blade.

### Phase 3 — Verify + docs + commit
- `view:cache` + `PartsStockTest` + bagong tests; i-update itong docs; commit.

---

## 🧭 Serialized Parts / Per-Unit Custodian (Serial + Property Number)

### Layunin
Dahil sa CSV ("PROPERTY NUMBERS — INTANGIBLE") na **per-unit** (bawat RAM/license ay may sariling **SERIAL** at **PROPERTY NUMBER**, at may **RESPONSIBLE OFFICER** kada row), kailangan ng Parts & Consumables ang **per-unit tracking**:
- Mula sa **quantity-based** (`on_hand_qty`) → **per-piece accountability** (sino ang may hawak ng specific serial/property).

### Desisyon sa disenyo — paano gumagana sa quantity system
1. **`on_hand_qty` = source of truth** (nananatili). Hindi i-a-derive mula sa units — para hindi masira ang existing logic (stats, low-stock, requisition).
2. **`parts_stock_units` = OPTIONAL na detalye** — para sa serialized parts lamang. Ang non-serialized parts (toner, screws) ay magtrabaho gaya ng dati (qty lang).
3. **I-sync sa iisang transaction:** bawat Stock In (may serial) → gumawa ng units + `increment`; bawat Stock Out / Requisition → `decrement` + markahan ang units (`issued`).

### Data model — `parts_stock_units`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `part_id` | FK → parts_stock | |
| `serial_number` | string(190) nullable | |
| `property_number` | string(64) nullable | |
| `unit_value` | decimal(14,2) nullable | cost per piece (mula sa CSV) |
| `status` | enum in_stock/issued/scrapped | |
| `issued_to` | FK users nullable | **custodian PER PIECE** |
| `asset_id` | FK inventory_assets nullable | (may Asset) |
| `request_id` | FK requests nullable | (may Request) |
| `issued_at` | timestamp nullable | |
| `timestamps` | | |

### Per-piece custodian (sagot sa "qty system, paano ang custodian?")
- `parts_stock` = quantity summary (on_hand = bilang ng `in_stock`).
- `parts_stock_units` = bawat piraso; `issued_to` = sino ang may hawak ng specific serial/property.
- Hal: RAM on_hand 5 → 5 unit rows; unit 1 (serial KR8220Y2BS) → custodian Juan; unit 2 → custodian Maria; atbp.

### Mga risk at mitigation (na-analyze bago mag-code)
1. **`on_hand` vs units na mag-iba** → decrement + unit-mark sa iisang transaction; `on_hand` ang kontra sa bilang ng `in_stock`.
2. **Requisition issue hindi nagma-mark ng units** → i-update ang `IssuePartsForRequisitionAction` (mark N `in_stock`, oldest-first).
3. **Concurrency / dobleng i-issue** → `lockForUpdate()` sa parts row at sa pipiliing unit rows.
4. **Negative stock** → panatilihin ang existing check (`on_hand < qty` → block).
5. **Backward compat** → units optional; walang ipinipilit na backfill sa existing parts.
6. **Serial batch > qty / walang tugma** → validasyon: serial count ≤ qty; ang sobra ay generic units.
7. **Existing tests (PartsStockTest / PartsLowStockTest)** → dapat manatiling berde (on_hand pa rin ang pag-u-update).
8. **Migration idempotent** → may `down()`, nullable, walang data loss.

### Phasing
- **Phase 1 — UI/UX (kumpleto — lahat ng 3 panig):**
  1. **Parts page** (`parts.blade.php`): Units modal · Stock In serial box · Stock Out serial picker · Units button.
  2. **Asset Profile** (`detail.blade.php`): 🧩 **Components / Installed Parts card** (visual, empty state).
  3. **Request detail** (ICT/Maintenance): 🧰 **Parts Used card** (visual, empty state).
- **Phase 2 — Backend:** Migration `parts_stock_units` + Part model (`units()`, `inStockUnits()`).
- **Phase 3 — Kumonekta (Parts):** Units modal load/save · Stock In→units+on_hand · Stock Out→mark issued · Add Unit; `PartsUnitsTest`.
- **Phase 4 — Requisition + consistency:** mark units sa requisition issue; test `on_hand == count(in_stock)`.
- **Phase 5 — Kumonekta (Asset & Request):** lagyan ng totoong data ang Components card (`asset_id`) at Parts Used card (`request_id`) + tests.
- **Phase 6 — CSV Import (INTANGIBLE.csv).**

---

## 1. Bakit ito kailangan (government context)
## 1. Bakit ito kailangan (government context)

- Ang `inventory_assets` ay para sa **serialized property** (isa-row-per-asset, may SN).
- Ang **RAM/SSD/HDD/toner** ay nasa **specifications** lang ng buong units, o hindi rehistrado
  — **hindi sila maaaring itago sa `inventory_assets`** (na may serial).
- Kailangan ang **quantity-based "supplies ledger"** (COA-aligned) para ma-check ang stock,
  mag-deduct sa issue, at gumawa ng Purchase Request (RA 9184) kapag kulang.

> ⚠️ **TALA (resulta ng deepview):** May linyang `// Parts & Consumables Inventory (Supply Office) - Removed`
> sa `routes/web.php`. Ito ay **placeholder na komento lang** — walang dating table/model.
> Kaya **bago ang lahat** dito; wala nang ire-revive.

---

## 2. Data Model

### Table `parts_stock`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | |
| `item_name` | string(190) | "NVMe SSD 1TB", "RAM 16GB DDR4" |
| `unit` | string(32) | pcs, pack, tube, pc |
| `category` | string(64) nullable | Storage, Memory, Consumables |
| `on_hand_qty` | integer unsigned, default 0 | |
| `reorder_level` | integer unsigned, default 0 | ibaba rito = low stock na |
| `region` | string(64) nullable | scoping (multilocation) |
| `branch` | string(64) nullable | scoping |
| `is_active` | boolean, default true | |
| `timestamps` | | |

**Indexes:** `region`, `branch`, `category`, `is_active`

### Table `parts_stock_movements` (audit/trail)
| Column | Type | Notes |
|---|---|---|
| `id` | bigint (PK) | |
| `part_id` | bigint FK → `parts_stock.id` | cascade delete (o nullOnDelete) |
| `qty_change` | integer | + (in) / − (out) |
| `reason` | string(190) | hal. "Purchase received", "Issue to requisition" |
| `reference_type` | string(32) nullable | requisition / purchase / adjustment |
| `reference_id` | bigint nullable | |
| `performed_by` | FK → users | sino ang nag-transact |
| `created_at` | timestamp | |

**Indexes:** `part_id`, `created_at`

---

## 3. Architecture / File Map (sundan ang existing Inventory pattern)

> ### ACHIEVED FILES (2026-08-13)
> ```
> database/migrations/2026_08_13_000001_create_parts_stock_tables.php   // parts_stock + parts_stock_movements
> app/Models/Part.php                                                     // + PartMovement
> app/Models/PartMovement.php
> app/Actions/Inventory/PartsStock/
>     ListPartsStockAction.php
>     StorePartAction.php
>     UpdatePartAction.php
>     StockInAction.php
>     StockOutAction.php
>     IssuePartsForRequisitionAction.php      // Phase C — deduction sa requisition Issue
> app/Http/Controllers/Inventory/PartsStockController.php
> app/Http/Requests/StorePartRequest.php      // + UpdatePartRequest, StockInPartRequest, StockOutPartRequest
> routes/web.php                              // 6 admin/supply routes + 1 super_admin read-only (super-admin/parts)
> resources/views/inventory/parts.blade.php   // list + add/edit + stock in/out + history
> resources/views/requisitions/it-index.blade.php        // Phase C — IT parts-stock picker
> resources/views/requisitions/show.blade.php            // Phase C — Supply Parts Stock availability panel
> resources/views/requisitions/partials/pr-readonly.blade.php  // Phase C — "From Parts Stock" tag
> tests/Feature/PartsStockTest.php            // 8 tests
> tests/Feature/RequisitionPartsIssueTest.php // 5 tests
> ```

---

## 4. Step-by-Step Implementation

### A. Migration
```bash
php artisan make:migration create_parts_stock_tables
```
Isulat ang schema sa Section 2, pagkatapos:
```bash
php artisan migrate
```
- Gumamit ng malinaw na pangalan `parts_stock` / `parts_stock_movements` (hindi maliligaw).

### B. Models
- `App\Models\Part` (table `parts_stock`)
  - casts: `on_hand_qty` → integer, `is_active` → boolean
  - `movements()` → HasMany `PartMovement`
- `App\Models\PartMovement` (table `parts_stock_movements`)

### C. Actions (nasa App\Actions\Inventory\PartsStock\, gaya ng pattern ng app)
- `StockInAction`: **transaction** → `Part::lockForUpdate()` → `increment('on_hand_qty', qty)` → create movement (+qty).
- `StockOutAction`: **transaction** → `lockForUpdate()` → **i-validate na `on_hand_qty >= qty`** → `decrement` → movement(−qty, ref requisition).
- `Concurrency` → `lockForUpdate()` para hindi ma-over-count (row lock).

### D. Controller + Routes
```php
// routes/web.php (inventory group, role: admin / canProcessSupply)
Route::get('/inventory/parts', [PartsStockController::class, 'index'])->name('inventory.parts');
Route::post('/inventory/parts', [PartsStockController::class, 'store'])->name('inventory.parts.store');
Route::put('/inventory/parts/{part}', [PartsStockController::class, 'update'])->name('inventory.parts.update');
Route::post('/inventory/parts/{part}/stock-in',  [PartsStockController::class, 'stockIn'])->name('inventory.parts.stock-in');
Route::post('/inventory/parts/{part}/stock-out', [PartsStockController::class, 'stockOut'])->name('inventory.parts.stock-out');
Route::get('/inventory/parts/{part}/movements',  [PartsStockController::class, 'movements'])->name('inventory.parts.movements');
```
- Access check: `canProcessSupply()` (Supply/Admin), read-only para sa super_admin.

### E. Views (`resources/views/inventory/parts.blade.php`)
- Listahan: item, unit, on-hand, reorder, status badge (OK / ⚠ Low / 🔻 Critical), actions.
- `＋ Add Part` modal, `Stock In` / `Stock Out` modal.
- `movements` modal (history/trail).
- Search + filter by category/status.
- I-link sa **Inventory navigation/tab** (i-locate ang nav file bago i-code — verification step).


---

## 5. UX/UI Design (kumpleto)

> Ang module ay dapat tumugma sa design language ng system:
> navy `#0038A8`, malinis na cards (10px radius), soft borders `#e2e8f0`,
> at status tints (berde/amber/pula) na consistent sa requisition at inventory.

> ### ✅ VERIFIED vs IMPLEMENTATION (2026-08-13)
> | Sub-section | Status |
> |---|---|
> | 5.1 Design Principles | ✅ Nasa `parts.blade.php` |
> | 5.2 Screen Map | ⚠️ **DEVIATION** — wala ang in-page tabs; separate sidebar nav entry |
> | 5.3 List (desktop) | ✅ Na-implement (may extra status filter) |
> | 5.4 Add/Edit modal | ✅ Na-implement (unit/region/branch = text input, hindi dropdown) |
> | 5.5 Stock In/Out modal | ✅ Na-implement (Source = text input) |
> | 5.6 Movements/History | ✅ Na-implement |
> | 5.7 States | ⚠️ Partial — may Empty + Low banner; **walang skeleton loading** |
> | 5.8 Mobile | ⚠️ Partial — responsive yes; **hindi full cards / full-width sheet** |
> | 5.9 Requisition Supply Review | ⚠️ Na-implement pero **per-requisition** (tingnan ang boundaries sa Section 6) |

### 5.1 Design Principles
- **Consistent** — reusing system tokens (navy, cards, status tints). ✅
- **Scannable** — agad makita ang low/critical stock. ✅
- **Clear affordances** — malinaw kung saan mag-a-add / stock-in / stock-out. ✅
- **Responsive + accessible** — desktop at mobile. ✅ (responsive styles; hindi pa full mobile-card layout)

### 5.2 Screen Map
> ⚠️ **DEVIATION:** Wala talagang in-page tab bar `[Assets] [Parts & Consumables] [Physical Count] [Reports]`. Ang Physical Count at Reports ay **hiwalay na sidebar entries** — kaya ang Parts & Consumables ay ginawang **separate sidebar nav link** din (konsistent sa umiiral). Ang mga modals (Add/Edit, Stock In, Stock Out, History) ay ✅ lahat na-implement.
```
PARTS & CONSUMABLES (sidebar entry sa ilalim ng Inventory & Assets)
  ├─ List (default)
  ├─ Add / Edit (modal)
  ├─ Stock In (modal)
  ├─ Stock Out (modal)
  └─ Movements/History (modal)
```

### 5.3 List (desktop)
```
┌──────────────────────────────────────────────────────────────────┐
│ 💾 Parts & Consumables   [Search] [Category▾] [＋ Add Part]       │
│ Item               Unit  On-hand  Reorder  Status     Actions    │
│ NVMe SSD 1TB        pcs      8       3      ● OK     [In][Out][…] │
│ RAM 16GB DDR4       pcs     12       5      ● OK     [In][Out][…] │
│ HP toner            pc       1       2     ⚠ LOW     [In][Out][…] │
│ Screws M3 (box)     pcs      0       5    🔻 CRITICAL [In][Out][…]│
└──────────────────────────────────────────────────────────────────┘
- On-hand na may kulay (green/amber/red) + row highlight sa Low/Critical
- Search + filter by category
```
> ✅ **VERIFIED:** Ang dalawang nasa itaas ay nasa `parts.blade.php` — may **dagdag na status filter** (OK/Low/Critical) at pagination roon.

### 5.4 Add / Edit (modal)
```
➕ Add Part
 Item name* [ NVMe SSD 1TB ]
 Unit* [ pcs ▾ ] Category [ Storage ▾ ]
 On-hand* [ 0 ] Reorder [ 0 ]
 Region [ NCR ▾ ] Branch [ RCMB ▾ ]
        [Cancel] [💾 Save]
- Validated (required, numbers >= 0)
```
> ✅ **VERIFIED:** Na-implement. Nota — ang `Unit`, `Region`, `Branch` ay **text input** (hindi dropdown gaya ng nasa mock); ang `On-hand` field ay **nakatago sa Edit mode** (dahil sa Stock In/Out ang tamang paraan para baguhin ito).

### 5.5 Stock In / Stock Out (modal)
```
📥 Stock In                    📤 Stock Out (Issue)
 Part: NVMe SSD 1TB             Part: NVMe SSD 1TB
 Current on-hand: 8             Current on-hand: 8
 Qty to add*:  [ + 20 ]         Qty to issue*: [ - 2 ]
 Source: [ Purchase ▾ ]         Para sa: [ Requisition ▾ ]
 Remarks: [ new delivery ]      Remarks: [ ICT-2026-0010 ]
      [Cancel][✓ Stock In]           [Cancel][✓ Issue]
- Ipakita ang current on-hand; i-block ang Stock Out kung mas malaki sa on-hand
```
> ✅ **VERIFIED:** Na-implement — may kasalukuyang on-hand display, at **ang Stock Out ay hinaharangan (disabled button + 422 server-side) kapag mas malaki sa on-hand**. Ang `Source / For` at `Remarks` ay **text input** (hindi dropdown).

### 5.6 Movements / History (modal)
```
NVMe SSD 1TB · History
 +20  Purchase received   by Supply   02/12
 −2   ICT-2026-0010 (req) by Supply   02/11
 +10  Initial stock       by Supply   01/05
- +/- at sino ang gumawa (audit trail)
```
> ✅ **VERIFIED:** Na-implement — `movements` endpoint → `performedBy` (pangalan) + `created_at` (May d, Y g:i A).

### 5.7 States
- **Empty:** icon + "Wala pang parts — ＋ Add Part" ✅
- **Loading:** skeleton rows ❌ — **HINDI na-implement** (walang skeleton; direct render)
- **Low stock:** banner sa itaas "⚠ N parts ang LOW — mag-PR na" ✅ (may hiwalay na **Critical** banner para sa on-hand = 0)

### 5.8 Mobile
- List → cards, buttons stack full-width ⚠️ — **responsive** (columns stack, full-width buttons) pero **hindi full "card" layout**
- Modals → full-width sheet ⚠️ — **responsive** ang forms, pero hindi full-width bottom sheet (max-width 480px centered pa rin)

### 5.9 Integration sa Requisition (Supply Review)
> ⚠️ **VERIFIED PERO MAY PAGKAKAIBA:** Ang Supply Review ay may **"Parts & Consumables Stock" panel** (on-hand per line + Available/Short badge) at **「＋ Create PR」** button. Pero:
> - Ang **Issue** ay **requisition-level** (nagde-deduct sa lahat ng parts-stock lines), hindi per-line button.
> - Ang **Create PR** ay **per requisition** (lahat ng short lines sa iisang PR), hindi per-line.
> - Ang serialized asset matching (hal. Webcam spare) ay ang **existing** Inventory Availability panel — hindi binago.
```
ITEM           Source      QTY  Available   AKSYON
NVMe SSD 1TB   Parts-Stock   2      8       ✅ (magde-deduct sa Issue)
HP toner       Parts-Stock   3      1       ✅ 「＋ Create PR」 (deficit)
Webcam         Spare (asset) 1     ✅ 1 SN   (existing Inventory panel)
```

---

## 6. Pagka-link sa Requisition ✅ (na-implement sa Phase C)

> ### NAGANAP (2026-08-13)
> - **IT picker:** pumili ng part mula Parts Stock (may on-hand indicator) → nagsi-save ng `source` + `part_id` per line item (backward-compatible sa mga lumang requisitions).
> - **Supply Review:** "Parts & Consumables Stock" panel → on-hand per line, badge **Available** o **Short — needs PR**.
> - **Issue:** `IssuePartsForRequisitionAction` → all-or-nothing deduction (may `lockForUpdate()`), naglo-log ng `PartMovement` (reference → requisition). **Kulang → 422, walang partial deduction.**
> - **PR Received:** ✅ **Phase D DONE** — `ReceivePurchaseRequestAction` → Stock In (on_hand bumabalik), may `PartMovement reference_type='purchase'`.

> ### ⚠️ BOUNDARIES ng Phase C (documented)
> - **Noong Phase C:** wala pang actual na Purchase Request entity — ang "Create PR" ay **indicator badge** lang. ✅ **Na-resolve ito sa Phase D** (may `purchase_requests` na table at full flow).
> - Ang deduction ay naka-ugnay sa **requisition-level Issue** (hindi per-line button) — ganyan pa rin, documented na disenyo.

---

## 7. Validation / Test Steps

1. `php artisan migrate` — walang error, malinis ang rollback.
2. `php artisan route:list | grep inventory.parts` — tama ang routes.
3. `php artisan test` — hindi masira ang existing.
4. Manual: store part → on_hand; stock-in/deduct; **i-block ang negative**; i-check ang movements.
5. Confirm na `canProcessSupply` lang ang may write; super_admin read-only.
6. Confirm na **hindi naantig** ang existing `inventory_assets` / inventory flows.

---

## 8. Risks & Mitigations (RESULTA NG DEEPVIEW)

| Risk | Mitigation |
|---|---|
| Magsisimulang WALANG laman | Mag-populate si Supply (seeder o manual) bago umasa sa stock-check |
| Concurrency sa on_hand | `lockForUpdate()` + `increment/decrement` sa transaction |
| Negative stock | Validate sa `StockOutAction`; i-block kung kulang |
| Pangalan ng table maligaw | gumamit `parts_stock` / `parts_stock_movements` |
| Region/branch scope | filter sa lahat ng queries; consistent sa inventory |
| Hindi masira ang existing inventory | bagong table + controller lang; hindi gagalawin ang `inventory_assets` |
| Menu/nav hindi sigurado | i-locate ang nav partial bago i-code (verification step #1) |

---

## 9. Kailangang i-confirm (bago mag-code) — ✅ LAHAT KUMPIRMADO 2026-08-13

- ✅ **Pangalan ng table:** `parts_stock` / `parts_stock_movements` — na-implement.
- ✅ **Saan ilalagay ang nav:** `resources/views/layouts/app.blade.php` — Admin/Supply (canProcessSupply block) + Super Admin (read-only block). Separate sidebar link (hindi in-page tabs, gaya ng Physical Count/Reports).
- ✅ **Access:** Supply/Admin (`canProcessSupply()`) write · Super Admin read-only (`super-admin/parts`). May 403 guard sa controller + Form Requests.
- ✅ **Auth model:** existing `canProcessSupply()` (User::canProcessSupply) — walang bagong permission/gate.
- ✅ **Concurrency:** `lockForUpdate()` + `increment/decrement` sa transactions (StockIn/StockOut/IssuePartsForRequisition).

---

## 10. Phasing

1. ✅ **Phase A** — Migration + `Part`/`PartMovement` models — **DONE 2026-08-13**
2. ✅ **Phase B** — Actions + Controller + Routes + `parts.blade.php` (CRUD + Stock In/Out) + nav — **DONE 2026-08-13**
3. ✅ **Phase C** — I-link sa requisition (picker, stock-check, deduction) — **DONE 2026-08-13**
4. ✅ **Phase D** — Purchase Request (RA 9184) workflow — **DONE 2026-08-13** (`PurchaseRequestTest` 5 passed)

---

## 11. Phase D Plan — Purchase Request (RA 9184)

> Layunin: gawing totoong dokumento ang "Short — needs PR" — may PR number, approval flow, at kapag **PR Received** → awtomatikong **Stock In** (bumabalik ang on_hand).

### D1 — Data model
- Bagong table `purchase_requests` (o `prs`):
  - `id`, `pr_number` (unique, format `PR-2026-xxxx`), `requisition_id` FK (nullable),
  - `status` enum (`pending` → `approved` → `received` / `cancelled`),
  - `items` JSON (kopya ng kulang na linya: `description`, `qty`, `part_id`), `requested_by`, `approved_by`, `approved_at`, `received_by`, `received_at`, remarks.
- Migration `create_purchase_requests_tables` + model `PurchaseRequest` (at kung kinakailangan `PurchaseRequestItem`).

### D2 — Backend Actions (`app/Actions/Inventory/PartsStock/` o `app/Actions/PurchaseRequest/`)
- `CreatePurchaseRequestAction` — galing sa requisition lines na **Short** (deficit) → bumubuo ng PR.
- `ApprovePurchaseRequestAction` / `ReceivePurchaseRequestAction` (PR Received → **Stock In** ang bawat line; bawas ang PR qty; sarado ang PR kapag 0).
- `ListPurchaseRequestsAction` / `ShowPurchaseRequestAction` — supply + super_admin.

### D3 — UI
- Supply review (show.blade.php): per "Short" line → `[＋ Create PR]` button (may confirm).
- Bagong view `purchase-requests/` (index + show): PR number, items, status tracker, Receive button (may date + remarks).

### D4 — Integration sa Parts Stock
- `ReceivePurchaseRequestAction` → para sa bawat line na may `part_id`: `StockInAction`-style transaction (`lockForUpdate()` + `increment` + `PartMovement` na `reference_type = 'purchase'`, `reference_id = PR id`).

### D5 — Verifikasyon
- Feature test `PurchaseRequestTest`: create PR mula sa deficit; **receive → tumaas ang on_hand**; PR status sarado; movement recorded; non-supply → 403.

### D6 — Confirmation bago mag-code (LAHAT KUMPIRMADO 2026-08-13)
- ✅ **Pangalan ng table:** `purchase_requests` · PR number format `PR-YYYY-xxxx`.
- ✅ **Flow:** May approval step — `pending → approved → received / cancelled` (kumpleto RA 9184).
- ✅ **Nav:** ilalagay sa ilalim ng Supply section (canProcessSupply) + Super Admin read-only link.

> ✅ **DONE 2026-08-13** — na-implement at na-verify (tingnan ang STATUS sa itaas).

