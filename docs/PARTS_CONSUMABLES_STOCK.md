# Parts & Consumables Stock — Detailed Implementation Plan

> Sub-module ng **Inventory** para sa mga **parts/consumable na walang serial**
> (RAM, SSD/HDD, toner, screws, connectors) at itinatago ayon sa **quantity + unit**.
> Ito ang **"stock source"** na ichi-check ng Supply Officer bago magdesisyon
> (Issue vs Purchase Request) sa parts requisition.

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

