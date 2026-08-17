# Parts & Consumables — Serialized Units (Per-Piece Custodian) — DEEPVIEW & REVIEW

> Batay sa malalim na pagsusuri ng kasalukuyang Parts & Consumables module bago mag-implement ng
> **per-unit serial + property number tracking** (na-trigger ng CSV "PROPERTY NUMBERS — INTANGIBLE").
> Petsa: 2026-08-14

---

## 1. Layunin at Trigger

- Ang CSV (per-unit: SERIAL + PROPERTY NUMBER + RESPONSIBLE OFFICER kada row) ay nagpapakita na ang
  ilang parts (RAM, HDD, licenses) ay kailangang i-track **per piraso** — hindi lang bilang.
- Kailangan ng Parts & Consumables ang **per-piece accountability**: sino ang may hawak ng
  partikular na serial/property number, at saan ito naka-install / ginamit (asset / request).

## 2. Kasalukuyang Arkitektura (na-verify ang bawat file)

| File | Ginagawa ngayon | Epekto ng units feature |
|---|---|---|
| `.../PartsStock/StockInAction.php` | lock parts row → `increment(on_hand_qty)` → `PartMovement(+qty)` | Dapat gumawa rin ng unit rows (kung may serials) sa **parehong transaction** |
| `.../StockOutAction.php` | lock → block negative → `decrement` → `PartMovement(−qty)` | Dapat markahan ang N unit rows (`issued`) sa parehong transaction; dapat i-lock ang units |
| `.../IssuePartsForRequisitionAction.php` | per line (`source=parts-stock`): lock → validate → `decrement` | **RISK:** dapat din mag-mark ng units (iwas divergence) |
| `.../StorePartAction.php` / `UpdatePartAction.php` | create/update part + audit | Walang pagbabago sa on_hand/units |
| `.../ListPartsStockAction.php` | baseQuery/applyFilters/statsFor/data | Data JSON dapat maglaman ng `unit_count` para sa Units button |
| `.../ExportPartsStockAction.php` | CSV export | (Phase 6) puwedeng isama ang serial/property |
| `.../CheckLowStockAction.php` | low/critical notif (reads `on_hand_qty`) | **Walang epekto** (on_hand pa rin ang binabasa) |
| `app/Http/Controllers/Inventory/PartsStockController.php` | index, data, export, store/update/stock-in/out, movements | Magkakaroon ng bagong endpoints ng units |
| `app/Http/Requests/StockInPartRequest.php` | `qty, reason, reference_type, reference_id` | Dapat magdagdag ng `units` validation |
| `.../StockOutPartRequest.php` | same | Dapat magdagdag ng `unit_ids` / `issued_to` |
| `app/Models/Part.php` | fillable/casts; `statusLevel()` | Magkakaroon ng `units()`, `inStockUnits()` |
| `app/Models/PartMovement.php` | append-only audit | (opsyonal) `unit_id` |
| `resources/views/inventory/parts.blade.php` | cards, live table (JS), modals | Magkakaroon ng **Units modal** + stock modal extensions + Units button |
| `resources/views/inventory/detail.blade.php` | asset profile (hero, stats, main-grid cards) | Magkakaroon ng **🧩 Components card** (Phase 5) |
| `routes/web.php` | parts routes + super-admin | Magkakaroon ng units endpoints |

## 3. Kasalukuyang Data Model

```
parts_stock            parts_stock_movements
  id PK                  id PK
  item_name              part_id FK
  unit                   qty_change (+/−)
  category?              reason
  on_hand_qty            reference_type?, reference_id?
  reorder_level          performed_by?
  region?, branch?       created_at (append-only)
  is_active              (+ low_notified_at, critical_notified_at)
```

## 4. Target Data Model — `parts_stock_units`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `part_id` | FK → parts_stock | onDelete cascade |
| `serial_number` | string(190) nullable | |
| `property_number` | string(64) nullable | |
| `unit_value` | decimal(14,2) nullable | cost per piece |
| `status` | string(20) default `in_stock` | in_stock/issued/scrapped |
| `issued_to` | FK users nullable | **custodian per piece** |
| `asset_id` | FK inventory_assets nullable | Phase 5 |
| `request_id` | FK requests nullable | Phase 5 |
| `issued_at` | timestamp nullable | |
| `created_at`/`updated_at` | timestamps | |
| index | `[part_id, status]` · `[serial_number, property_number]` | |

## 5. Desisyon sa Disenyo

1. **`on_hand_qty` = source of truth.** Hindi i-a-derive mula sa units, para hindi masira ang
   existing logic (stats, low-stock, requisition, exports).
2. **`parts_stock_units` = OPTIONAL.** Para sa serialized parts lang; ang non-serialized
   (toner, screws) ay gumagana gaya ng dati.
3. **I-sync sa iisang transaction:** lahat ng increment/decrement ng `on_hand_qty` ay may
   katumbas na create/mark ng units SA PAREHONG `DB::transaction`.

## 6. Detalyadong Data Flows

### 6.1 Stock In (may serial)
1. Validate: `qty` ≥ bilang ng ibinigay na serial lines (kung may serial). Sobra → generic units (status in_stock, walang serial).
2. `DB::transaction`:
   - lock parts row → `increment(on_hand_qty, qty)`
   - `PartUnit::insert()` para sa bawat serial line (`part_id, serial, property, unit_value, status=in_stock`)
   - gumawa ng generic units para sa natirang qty (kung walang serial)
   - `PartMovement(+qty)`
3. Audit log.

### 6.2 Stock Out (may unit picker)
1. Validate: `qty` ≥ 1; `unit_ids` (opsyonal) o bilang lang.
2. `DB::transaction`:
   - lock parts row → check `on_hand_qty ≥ qty` (block negative)
   - kung may `unit_ids`: i-lock ang mga unit na iyon (`whereIn->lockForUpdate`); i-verify `status=in_stock` at sapat ang bilang
   - `decrement(on_hand_qty, qty)`
   - markahan ang mga unit (`status=issued, issued_to, issued_at`); kung walang unit_ids, kunin ang pinakamatatandang `in_stock` (oldest-first) at i-mark
   - `PartMovement(−qty)`
3. Kapag kulang ang units → matatapos ang transaction (walang partial).

### 6.3 Requisition Issue (kritikal)
- `IssuePartsForRequisitionAction`: pagkatapos ng `decrement` sa bawat line, dapat ding markahan ang
  N na pinakamatatandang `in_stock` units (kung may units).
- **Kung hindi gagawin** → mag-i-iba ang `on_hand_qty` sa bilang ng `in_stock` units = BUG.

### 6.4 Add Unit (Units modal)
- Direct: gumawa ng isang unit row + `increment(on_hand_qty, 1)` (same transaction).

### 6.5 Per-piece custodian
- `on_hand_qty` = bilang ng `in_stock` units (summary).
- `parts_stock_units.issued_to` = kung sino ang may hawak ng specific serial/property.
- Hal: RAM on_hand 5 → 5 unit rows; unit A → Juan, unit B → Maria, atbp.

## 7. Integration Points

| Saan | Koneksyon |
|---|---|
| **Asset Profile** (`detail.blade.php`) | 🧩 Components card — units na `asset_id` = ito (Phase 5) |
| **Request detail** (ICT/Maintenance) | 🧰 Parts Used card — units na `request_id` = ito (Phase 5) |
| **Requisition** | `IssuePartsForRequisitionAction` — dapat mag-mark ng units (Phase 4) |
| **Low-stock** | `CheckLowStockAction` — walang epekto (reads on_hand) |
| **Physical Count** | base = unit status / on_hand (future) |
| **Export** | (Phase 6) isama ang serial/property |
| **Data endpoint** (`data()`) | magdagdag ng `unit_count` para sa Units button / hint |

## 8. Mga Risk at Mitigation (detalyado)

| # | Risk | Mitigation |
|---|---|---|
| 1 | `on_hand` vs `count(in_stock)` mag-iba pagkatapos ng requisition issue / generic stock-out | Lahat ng decrement → mag-mark ng units sa parehong transaction (Phase 4 rin) |
| 2 | Concurrency / doble-issue ng isang unit | `lockForUpdate()` sa parts row AT sa target unit rows |
| 3 | Negative stock | Panatilihin ang existing check (`on_hand < qty` → 422) |
| 4 | Serial batch > qty | Validation: serial count ≤ qty; sobra = generic |
| 5 | Duplicate serial | `where` check sa part scope bago gumawa (i-report sa UI) |
| 6 | Backward compat (existing parts walang units) | Units optional; walang pinipilit na backfill |
| 7 | Pagbabago sa validation forms | i-extend ang `StockInPartRequest`/`StockOutPartRequest` (units, unit_ids, issued_to) |
| 8 | Pagkasira ng existing tests | `PartsStockTest`/`PartsLowStockTest` dapat manatiling berde (on_hand pa rin ang pag-u-update) |
| 9 | Migration | may `down()`; lahat nullable; walang data loss |

## 9. Test Impact Analysis

- **Hindi magbabago:** `test_stock_in...`, `test_stock_out...`, `test_supply_officer_can_create_part`,
  `test_parts_data_endpoint...`, `PartsLowStockTest`, `PartsExportTest` — basta panatilihin ang `on_hand` behavior.
- **Bagong tests (Phase 3/4):** `PartsUnitsTest`
  - stock-in na may serials → units nalikha + `on_hand` + N
  - stock-out → tamang units na-mark `issued` + `on_hand` − N
  - `on_hand == count(in_stock)` (consistency) — kahit pagkatapos ng requisition issue
  - may tamang custodian (issued_to) kada unit
  - non-supply → 403

## 10. Detalyadong Phased Plan (may verification bawat phase)

- **Phase 1 — UI/UX (3 panig):** Units modal sa `parts.blade.php` · Stock In serial box ·
  Stock Out serial picker · 🧩 Components card sa `detail.blade.php` (empty state) ·
  🧰 Parts Used card sa request detail (empty state). **Verify:** browser + `view:cache`.
- **Phase 2 — Backend:** Migration `parts_stock_units` + Part model (`units()`, `inStockUnits()`) +
  `ListPartsStockAction::data()` isama ang `unit_count`. **Verify:** migrate + smoke.
- **Phase 3 — Kumonekta (Parts):** Units modal load/save · Stock In→units+on_hand ·
  Stock Out→mark units · Add Unit; i-extend ang StockIn/StockOutPartRequest. **Verify:** `PartsUnitsTest`.
- **Phase 4 — Requisition + consistency:** i-update ang `IssuePartsForRequisitionAction`;
  test `on_hand == count(in_stock)`. **Verify:** test.
- **Phase 5 — Asset & Request:** Components card (data via `asset_id`) · Parts Used card
  (data via `request_id`); magdagdag ng fields sa Stock Out. **Verify:** `AssetPartsTest` + `RequestPartsUsedTest`.
- **Phase 6 — CSV Import:** import wizard → `parts_stock_units` + `unit_value`.
  **Verify:** `PartsImportTest`.

## 11. Konklusyon

Ang pinakamalaking technical risk ay ang **pagpapanatili ng konsistensya ng `on_hand_qty`**
habang may per-unit tracking. Sa pamamagitan ng **iisang transaction** (on_hand + units sabay),
**row locks**, at **pagproseso rin ng units sa requisition issue** (Phase 4), maiiwasan ang divergence.
Priority: **Phase 1 (UI/UX) first** para ma-confirm ang design bago ang backend.