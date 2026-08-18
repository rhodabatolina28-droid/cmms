# Parts & Consumables — Serialized Units (Per-Piece Custodian) — DEEPVIEW & REVIEW

> Batay sa malalim na pagsusuri ng kasalukuyang Parts & Consumables module bago mag-implement ng
> **per-unit serial + property number tracking** (na-trigger ng CSV "PROPERTY NUMBERS — INTANGIBLE").
> Petsa: 2026-08-14

---

## Planned - Automatic Ticket-Based Issue to Asset Custodian (Not Yet Implemented)

### Goal

Kapag ang assigned IT personnel ay nag-submit ng Parts Request para sa isang active **ICT** o
**PM-generated** ticket, ang Supply **Issue** action ay dapat mag-release ng units sa tamang asset
custodian automatically. Hindi dapat manual na pumili ng recipient sa ticket-based issue dahil nasa
ticket na ang asset at custodian context.

### Target flow

```text
ICT / PM ticket with linked asset
  -> assigned IT diagnoses asset
  -> IT selects that assigned, active ticket in Parts Request
  -> IT submits Parts Request
  -> ticket becomes Awaiting Parts
  -> Supply approves requisition
  -> Supply confirms Issue
  -> units are issued to the linked asset's custodian
  -> ticket returns to Ongoing; IT continues repair
```

### Ticket selection by IT

The Parts Request screen must let IT select only tickets that are assigned to the logged-in IT user,
are still active, and are eligible ICT or PM-generated work. Selecting a ticket loads its linked
asset and the asset's current custodian as read-only request context. IT selects the needed parts and
quantity, but does not select the custodian.

### Automatic assignment rule

| Unit field | Source | Rule |
|---|---|---|
| `request_id` | current requisition ticket | Always save the ticket ID. |
| `asset_id` | `ticket.linked_asset_id` | Ticket-based issue requires a linked asset. |
| `issued_to` | `ticket.linkedAsset.assigned_to_user` | Use the asset custodian, never the IT requester. |
| `status` and `issued_at` | Issue transaction | Set to `issued` with the release timestamp. |

`requisition.requested_by` identifies the IT personnel who requested the parts. It is **not** the
recipient/custodian of the issued unit.

### Preconditions and safeguards

1. The ticket must be active, assigned to IT, and eligible as ICT or PM-generated.
2. The ticket must have a linked asset.
3. The linked asset must have `assigned_to_user`. If it does not, block Issue with a clear message;
   never silently assign the unit to IT or another user.
4. Only an approved requisition can be issued. A repeated Issue remains blocked by the existing
   requisition status transition.
5. Lock the part and selected/oldest in-stock units, validate every requested line first, then
   update all records in one transaction.
6. Generic/manual Stock Out remains manual because it has no ticket context. Automatic assignment
   applies only to Issue from a Parts Request.

### Planned Supply Issue UI

Before confirmation, show read-only ticket context: ticket number/type, linked asset, asset
custodian, requested parts and quantity, and serialized units to release. The confirmation must say
that the units will be assigned to the displayed custodian. Replace **"Issue parts to IT"** with
**"Issue parts to asset custodian"**.

### Expected result after issue

- `parts_stock.on_hand_qty` and matching `parts_stock_units` update in the same transaction.
- Units appear on the linked asset's **Installed Parts / Consumables** card through `asset_id`.
- Units appear on the ticket's **Parts Used** card through `request_id`.
- `issued_to` identifies the linked asset custodian; the movement remains linked to the requisition.
- The ticket returns from `Awaiting Parts` to `Ongoing`, and IT is notified to continue repair.

### Visibility rule: Parts Stock versus Asset Profile

| Location | What it shows |
|---|---|
| **Parts & Consumables** | All stock records and units currently held by Supply, including `in_stock` units. |
| **Asset Profile - Installed Parts / Consumables** | Only units already issued and linked to that specific asset through `asset_id`. |
| **ICT / PM Ticket - Parts Used** | Only units already issued and linked to that ticket through `request_id`. |

Adding a part to Supply stock must **not** make it appear on an Asset Profile. It appears only after
Supply issues it from the ticket-based requisition and the transaction saves both the ticket and
asset links.

### Current implementation gaps to close

### Implementation progress

| Phase | Status | Verification |
|---|---|---|
| Phase 1 - Ticket context validation | Complete | Parts Request and Supply Issue are blocked without a linked asset and its custodian; requisition view shows the custodian context. |
| Phase 2 - Automatic unit assignment | Complete | Supply Issue saves `request_id`, `asset_id`, and the linked asset custodian in every issued serialized unit. |
| Phase 3 - Asset/ticket visibility verification | Complete | End-to-end: after a ticket-based Issue, the serialized unit appears on the Asset Profile (via `asset_id`) and the ticket's Parts Used card (via `request_id`), flagged `issued`; unissued stock is not shown and `on_hand` stays consistent. |
| Phase 4 - PM ticket support | Complete | Eligible PM tickets (`type` Preventive Maintenance with a linked asset) can be submitted and issued to the linked asset's custodian; auto-generated bundled PM (no linked asset) is blocked. |
| Phase 5 - Manual Stock Out auto-fill | Complete | The Stock Out modal now lists candidate assets and eligible tickets; selecting one auto-fills the target asset and the linked asset custodian. Leaving both empty keeps the entry manual. |
| Phase 6 - Final regression | Complete | Repeated Issue blocked (no double-deduct), insufficient stock, cancelled-ticket block, serial selection, and on-hand/units consistency all covered. (Authoritative final regression totals are in Phase 9.) |
| Phase 7 - Export (Parts per-unit) | Complete | CSV export lists one row per serialized unit (serial, property, unit value, unit status, issued-to custodian, asset, request). `PartsExportTest` 5 tests / 21 assertions. |
| Phase 8 - Export (Inventory parent-set) | Complete | Inventory CSV export adds a Parent Set / Component Of column reflecting the asset-set model. `InventoryExportTest` 1 test / 7 assertions. |
| Phase 9 - Final regression + doc | Complete | Full 8-suite regression (parts + set + export) 58 tests / 269 assertions; deepview doc aligned. |



Phase 1-5 automated verification: `RequisitionTicketContextTest` (14 tests, 68 assertions) covers Phase 1 guards (no asset,
no custodian, IT not assigned, Completed and Cancelled blocks), Phase 2 unit + PartMovement links, Phase 3 card visibility, Phase 4 eligible-PM success, no-linked-asset block, and PM issue-to-custodian, and Phase 5 Stock Out context endpoint (assets/tickets/custodians). Related regression suites: `PartsUnitsTest`, `RequisitionPartsIssueTest`, `PartsImportTest` - Final regression (Phase 6-8) across the 8 parts+set+export suites: 58 tests, 269 assertions. Blade view compilation also passed.

### 2026-08-18 Accountability Hardening (completed)

This is separate from the original UI/backend phase numbering above. It records the audit items
implemented after reviewing the current CMMS flow.

| Audit item | Completed safeguard | Verification |
|---|---|---|
| 3. Referential integrity | `parts_stock_units.asset_id` and `request_id` now have indexes and foreign keys; serial and property number are unique per part. | Migration applied successfully to the local database. |
| 4. Unit accountability | A per-item `requires_unit_tracking` rule blocks Stock In, direct Add Unit, CSV Import, Stock Out, and requisition Issue when a tracked unit lacks serial number, property number, unit cost, or a matching unit row. | Focused suite passed; FIFO selection is deterministic (`created_at`, then `id`). |
| 5. Custodian history | `issued_to` remains the historical recipient snapshot even if the asset is later reassigned. Asset and ticket cards load the saved custodian, cost, issue date, and ticket context. | Feature test confirms asset reassignment does not overwrite the issued unit recipient. |
| 6. Custodian notice | On successful Supply Issue, the recorded asset custodian receives a `Parts Issued to Asset` in-app/email notification; duplicate notice is avoided when they are also the ticket requester. | Feature test confirms the custodian notification is created. |

Post-hardening verification (updated 2026-08-18 after Phase 1-5 tests): `PartsUnitsTest`, `PartsImportTest`,
`RequisitionPartsIssueTest`, and `RequisitionTicketContextTest` - **40 tests passed, 177 assertions**. Blade view compilation passed.

### 2026-08-18 Asset Set & Custody Hardening (verification queue deferred)

The Asset Verification Queue was deliberately deferred. The safeguards below apply to new records
and normal edits without altering historical imported assets that remain `Spare` for review.

| Phase | Status | Rule now enforced |
|---|---|---|
| 2. Set-aware validation | Complete | A component must have a top-level parent with a PAR. Shared property context is allowed only within that same parent/component set; unrelated assets are blocked. |
| 3. Manual Create/Update protection | Complete | Manual component creation inherits the parent PAR, custodian, and organizational scope. Update serial validation now gives a normal validation error instead of relying only on the database constraint. |
| 4. Transfer audit and notice | Complete | Reassigning a parent updates all components’ PAR/custodian context, writes a history entry for every component, and notifies the old and new custodians. |
| 5. Gradual accountability | Complete | A new asset or a `Spare` asset becoming `Active` requires a custodian, property number, and acquisition cost. Existing already-active legacy records are not retroactively blocked. |
| 6. Automated verification | Complete | `InventoryAssetIntegrityTest` covers component inheritance, unrelated property rejection, whole-set transfer/history/notifications, and activation completeness. |

Asset Set hardening verification (updated 2026-08-18): `InventoryAssetIntegrityTest` and `InventoryCsvImportTest` -
**12 tests passed, 64 assertions**. The `inventory_history.previous_user_id` audit reference now also
has an index and foreign key to `users` (`nullOnDelete`).

### Remaining implementation gaps

- Final regression coverage (mostly covered): repeated Issue, insufficient stock, and `on_hand_qty == in_stock_units`
  consistency `PartsUnitsTest`; add any remaining end-to-end browser pass for the auto-fill UI. **Phases 6-8 (final regression + exports) are complete.**

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
| `.../ExportPartsStockAction.php` | CSV export | (Phase 7) per-unit serial/property/custodian/asset/request - done |
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
| **Export** | (Phase 7-8) isama ang serial/property per-unit + parent-set column - done |
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

> **Tandaan sa numbering:** Ang planong ito (Phase 1-6: UI/UX, Backend, Kumonekta, Requisition,
> Asset & Request, CSV Import) ay ang **orihinal na design plan** at **iba ang numbering** sa
> "Implementation progress" table sa itaas (Phase 1-5: ticket context, automatic issue, visibility,
> PM support, manual Stock Out auto-fill). Ang kasalukuyang status ay naka-track sa itaas.


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
