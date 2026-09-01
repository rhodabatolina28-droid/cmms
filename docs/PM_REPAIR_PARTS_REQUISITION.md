# PM Repair → Parts Requisition Flow (Phase PM-RP)

## Goal
Kapag nag-check ang IT ng **FOR REPAIR** sa PM form (Suggestion/Recommendation section),
lalabas ang dropdown ng mga assets at pipili si IT kung aling asset ang ire-repair.
Ang napiling asset ay naka-link sa PM ticket, kaya't ang PM ticket ay lumalabas sa
**My Parts Requisitions** (IT) at sa Supply **Job Orders** tab para makapag-request ng parts.

## Flow
```
PM Form (Suggestion/Recommendation — right column)
  ├─ ☐ FOR DISPOSAL → "SELECT ASSET TO DISPOSE" dropdown (existing pattern)
  └─ ☑ FOR REPAIR   → "SELECT ASSET TO REPAIR" dropdown (NEW — mirrors disposal)
        └─ IT pipili ang asset → save
             └─ preventive_maintenance.repair_asset_id = X
                requests.linked_asset_id = X (sync)
                  └─ PM ticket lumalabas sa:
                      • My Parts Requisitions "Job order no." dropdown ([PM] tag)
                      • Supply Job Orders tab
                      • "Request Parts (Material Requisition)" button sa PM form
```

## Why no requisition-logic changes were needed
Ang mga sumusunod na gates ay tumitingin lang ng `requests.linked_asset_id` +
`assigned_to` + status — kapag naka-link na ang repair asset, lahat automatic:
- `RequisitionSupport::canItSubmitForTicket()` (PM + linked_asset_id + assigned IT)
- `RequisitionSupport::ticketIssueContext()` (custodian = assigned user ng asset)
- `IssuePartsForRequisitionAction` (unit destination + PartMovement traceability)
- `ListRequisitionsAction::itIndex` dropdown (PM + `whereNotNull('linked_asset_id')`)

## Changes (Phase PM-RP)
1. **Migration** `2026_09_01_000001_add_repair_asset_id_to_preventive_maintenance.php` —
   nullable `repair_asset_id` FK → `inventory_assets.asset_id` (mirrors `disposal_asset_id`).
   DB dump bago mag-migrate: `storage/ux_backup/cmms_pre_pm_repair_20260901_1009.sql`.
2. **Model** — `PreventiveMaintenance::$fillable` + `repairAsset()` relation.
3. **PM Form UI** (`partials/maintenance/_end_user_section.blade.php`) —
   "SELECT ASSET TO REPAIR" dropdown sa ilalim ng FOR REPAIR checkbox, may JS toggle
   (`_pm_scripts.blade.php`), pre-select ng saved value. **Persistence:** dahil naka-save
   sa DB ang `for_repair` + `repair_asset_id`, mananatili ang check + selection kahit
   mag-back at bumalik pa si IT sa PM Work Order (ongoing ang ticket).
4. **Save logic:**
   - `CreateMaintenanceTicketAction` — `repair_asset_id` whitelist/mapping; kapag
     `for_repair=YES` + repair asset → `linked_asset_id` = repair asset sa bagong ticket.
   - `UpdateMaintenanceTicketAction` — mapping + validation (422 kapag YES na walang asset);
     post-update sync ng `requests.linked_asset_id` + AuditLog "Linked Repair Asset (PM)".
5. **PM form entry point** (Phase PM-RP4, REVISED per user feedback) —
   ~~"Request Parts" button + requisitions chips sa PM form~~ **TINANGGAL** —
   ang pag-request ay sa **Parts Requisition page lang**. Sa halip: **auto-save**
   ang FOR REPAIR recommendation — pag-click ng checkbox o pag-select ng asset ay
   agad nagse-save via lightweight endpoint (walang signature/complete-form requirement),
   kaya agad lumalabas ang [PM] ticket sa My Parts Requisitions dropdown.
   - Endpoint: `POST /requests/maintenance/{id}/repair-recommendation`
     (`maintenance.repair-recommendation`, role:it,super_admin)
     → `SaveRepairRecommendationAction`: persistence + linked_asset_id sync + audit log;
     422 kapag YES na walang asset; NO = clear (unlink lang kung galing sa repair selection).
   - JS: standalone `<script>` block sa `_pm_scripts.blade.php` (delegated, independent
     ng main DOMContentLoaded handler), auto-save on checkbox change + asset select change,
     may in-flight guard. Success = toast lang (walang page reload, para hindi mawala
     ang ibang unsaved edits).

## Test gate
`tests/Feature/PmRepairPartsRequestTest.php` — 9 tests:
- repair selection links asset to PM ticket on update
- for repair without asset selection is rejected (422)
- PM ticket with repair asset appears in requisitions dropdown (gate false → true)
- IT can request parts for PM ticket with repair asset (requisition created)
- PM show page: persisted FOR REPAIR state/selection; walang Request Parts button
- repair recommendation endpoint saves WITHOUT signature (can_request_parts true)
- endpoint requires asset when YES (422)
- endpoint clears linkage when NO
- endpoint denies regular users (403)

Full suite: **192 passed (731 assertions)** — green.

## Known notes
- **Pre-existing quirk (out of scope):** ang `<x-form-layout>` paired-component + `@slot`
  stack ng PM form page ay nag-iiwan ng open output buffers (nalaman via scratch test,
  `LEVEL_AFTER_SHOW=2`). Naka-drain sa test lamang; hindi binago ang page structure.
- Ang `repair_parts` free-text ay hindi pa naka-prefill sa requisition form —
  possible follow-up (auto-match sa parts catalog).

## Phase PM-FIX — "Under Maintenance" stuck even after PM completed (2026-09-01)

### Root cause
Sa asset status sync (`Request::booted()` → `updated`), kapag may `linked_asset_id`
na ang bundled (auto-generated) PM — e.g., mula sa FOR REPAIR selection — ang
`elseif` bundled branch ay **hindi na tumatakbo**, kaya:
1. Sa completion, **isang asset lang** (ang linked) ang naire-restore sa Active —
   ang ibang assets ng user ay **naiwan sa "Under Maintenance"**.
2. Ang bundled PM-date stamping ay hindi rin tumakbo (elseif din).
3. Ang linked asset na hindi tugma ang custodian ay hindi kasama sa sync.
4. Side-bug: ang bundled PM-date stamp ay may `where('status','Active')` —
   hindi nito naabot ang mga assets na "Under Maintenance" pa sa oras ng stamp.

### Fixes (code — para sa susunod)
1. `Request.php` sync — kapag may linked asset: i-push ito **palaging** (kahit
   magkaiba ang custodian) + kung auto-generated bundled PM, i-push pa rin ang
   LAHAT ng assets ng user; dedupe via `unique('asset_id')`.
2. `UpdateMaintenanceTicketAction` completion — i-stamp ang PM dates ng linked
   asset **AT** (kung bundled) lahat ng assets ng user — dalawang `if` na, hindi
   na `elseif`.
3. Bundled stamp: `whereNotIn('status', ['For Disposal','Scrapped'])` na imbes na
   `where('status','Active')` para maabot ang mga "Under Maintenance" pa.

### Fixes (data — existing stuck assets)
Artisan command: `php artisan maintenance:fix-stuck-assets` (may `--dry-run`).
- Naghahanap ng "Under Maintenance" assets na **walang aktibong ticket**
  (direct `linked_asset_id` o aktibong bundled PM ng custodian) at inire-restore
  sa **Active** + InventoryHistory ("PM Stuck Status Restored") + AuditLog.
- Run noong 2026-09-01: **16 assets restored**, 1 skipped (asset #168 EPSON L121 —
  may aktibong ticket pa, tama lang).

### Tests (+2)
- completing bundled PM with repair linked asset restores **all** assets to Active
  (may pending parts requisition pa nga sa ticket — auto-rejected ng completion flow)
- completing PM stamps PM dates for repair-linked **and** other assets

Full suite: **195 passed (745 assertions)** — green.

## Phase PR-FIX — Supply Officer PR fixes (2026-09-01)

### 1. 500 error sa PR submission na may catalog part_id
**Root cause:** ang `store()`/`update()` validation ay `exists:parts,id` — pero ang
parts catalog ay nasa `parts_stock` table (tingnan ang `Part` model). Kapag may
`part_id` ang item line, nag-query ang validator sa walang `parts` table →
QueryException → 500.
**Fix:** pinalitan ng `exists:parts_stock,id` ang parehong rules (kaparehas ng
`StoreRequisitionRequest`). Regression test: supply officer stores a PR with
part_id via the store route.

### 2. Auto-issue ng linked requisition kapag na-deliver ang PR
**Gap:** kapag ang PR ay ginawa mula sa isang requisition (deficit lines,
`requisition_id` link), ang requisition ay nananatiling pending/approved sa
**Supply Office → Requisition Review** kahit na-deliver na ang PR — manual pa
ang pag-issue.
**Fix (ReceivePurchaseRequestAction):** kapag na-deliver ang PR at may linked
requisition na nasa pending/approved:
- awtomatikong ginagawang **issued** ang requisition (reviewed_by/reviewed_at
  = user na nag-receive, may "Auto-issued from delivered PR-x" remarks)
- audit log entry ("Issue requisition #N ... auto, on delivery")
- notification sa IT requester: "Parts Request — Issued" ("Parts were issued
  for {request_number}. You may continue repair work.")
Nasa loob ng receive transaction para atomic.

### Tests (+2)
- supply_officer_can_store_pr_with_catalog_part_id (dating 500, ngayon OK)
- receiving_pr_auto_issues_linked_requisition (status issued + notification)

Full suite: **197 passed (753 assertions)** — green.

### 3. Cleanup — mga requisition na naiwang "need issue" kahit na-deliver na ang PR
Ang mga requisition na na-fulfill ng PR **bago pa maidagdag ang auto-issue fix**
ay nakatengga pa rin sa pending/approved. Bagong artisan command:
`php artisan requisitions:fix-stuck-issued` (may `--dry-run`).
- Hinahanap ang pending/approved requisitions na may **delivered** na linked PR
  (finalized-but-not-delivered ay hindi binibilang — legit na naghihintay pa)
- Markahang **issued** (reviewed_by = PR delivered_by, reviewed_at = PR
  delivered_at) + AuditLog + notification sa IT requester
- Run noong 2026-09-01: **1 requisition restored** (#13, PM-NCR-RCMB-2026-0003,
  fulfilled by PR-2026-0006).

## Phase PR-RECV — Record Delivery overhaul (2026-09-01, hapon)

> Saklaw: serial/property grid sa "Add to inventory", supply PR visibility,
> view-only delivery record + proof of purchase, per-piece serial/property
> detail, PR table "View delivery" button, receipt-required-for-any-amount.

### 1. Serial/property grid lumalabas na sa "Add to inventory" din
**Bug:** sa Record Delivery form, ang serial/property grid ay `direct-asset`
lang ang nagpapakita (`sel.value === 'direct-asset'` sa JS) — pero ang backend
validation ay nangangailangan ng isang serial + property kada quantity para sa
tracked parts (hal. `SSD 1TB M.2 NVMe`) **kahit stock-in**. Kaya: pumili ng
"Add to inventory" → walang fields → submit → error na walang paraan ayusin.
**Fix:** ang part `<option>` ay may `data-tracked="1"` flag kapag
`requires_unit_tracking` ("Create new…" ay laging tracked), at ang grid
visibility (`rxSyncUnits`) ay base na sa **part**, hindi sa destination.

### 2. Supply Workspace → PURCHASE REQUESTS: hindi nakikita ang PRs
Dalawang root cause:
1. **Auto-generated PM tickets ay walang `region`** — `GeneratePMScheduleService`
   ay walang `region` key sa bundled PM creation (lahat ng 6 PM tickets sa DB:
   `region=NULL`) → hindi tumutugma ang PR-origin filter
   (`requests.region = 'NCR'`) → nakatago ang PM-origin PRs.
   **Fix:** generator ay kumukuha na ng region mula sa end user (fallback:
   actor). Data backfill: 6 PM tickets inayos via `UPDATE ... JOIN users`.
2. **Supply officer ay fini-filter sa sariling org scope** — ang
   `ListPurchaseRequestsAction::applyOrgScope` ay nag-ni-narrow kahit sa mga PR
   na si supply mismo ang gumawa. **Fix:** supply officers (+ super_admin) ang
   nagpapatakbo ng **procurement desk** — walang org-narrowing, kita nila ang
   lahat ng PR na kailangan nilang finalize/receive.
Tests: `supply_sees_own_created_and_pm_origin_prs` + PM generation asserts
region on auto-PM tickets.

### 3. View-only Delivery Record + Proof of Purchase access
**Gap:** ang Record Delivery page ay guarded ng `canReceive()` na **false na
agad kapag delivered** — walang paraan para makita ang delivery record at ang
**Proof of purchase** (receipt/invoice) pagkatapos ma-deliver. Sa PR document
page, `delivered` = locked "Delivered" badge lang.
**Fix:**
- Bagong gate `ReceivePurchaseRequestAction::canViewDelivery()`: delivered PR →
  supply/super-admin o PR owner (IT requester) ay puwedeng **makakita**;
  finalized → `canReceive()` pa rin (edit mode).
- `receiveForm`: kapag delivered → view-only mode (walang form/radios/units
  grid/submit; may delivery summary, sino nag-receive, received items, at ang
  Proof of purchase card na read-only — automatic na dahil `canUploadReceipt`
  ay false kapag delivered).
- PR document action area: `delivered` → aktibong **"🧾 View delivery"** button
  + locked Delivered badge.
- **Blade 500 bug:** ang `@if(!empty($viewOnly))Delivery record@else ...@endif`
  sa `receive.blade.php` ay may **naka-dikit na directives** (`record@else`,
  `delivery@endif`) — hindi kinilala ng Blade compiler, naging PHP parse error
  ("expecting elseif/else/endif") → 500. Pinalitan ng ternary
  (`{{ $viewOnly ? '...' : '...' }}`) ang lahat ng ganitong pattern
  (receive.blade.php + receive-panel.blade.php) — bulletproof na.

### 4. Per-piece serial/property detail sa "Received items"
**Gap:** ang view-only "Received items" ay PR-items listahan lang — walang
serial/property kada pcs at walang destination kada line.
**Fix (3 layers):**
1. **Migration** `2026_09_01_150000_add_purchase_request_id_to_parts_stock_units.php`
   — nullable `purchase_request_id` FK → `purchase_requests` (+ index) sa
   `parts_stock_units`. May **backfill**: installed units via `request_id` →
   `purchase_requests.request_id`; stock-in units via PR
   `parts_stock_movements` entry (same part, ±3s window). DB dump bago:
   `storage/ux_backup/cmms_pre_pr_units_20260901_1439.sql`. Run: **151 units,
   30 naka-link** (totoong data, hal. PR-2026-0006 SSD
   `XPG-SX8200-26-073814` / `NCMB-RID-IT-2026-0049`).
2. **Receive action** — `applyLines()` nagsta-stamp na ng `purchase_request_id`
   sa bawat bagong PartUnit, **stock-in man o install-on-asset**.
3. **View** — bawat item line sa delivery record ay may: **destination badge**
   ("Installed on asset · {asset_code}" o "Add to inventory (stock)"),
   **unit cost**, **"Tracked pieces: N of QTY recorded"**, at **per-piece
   serial/property grid**. May warning kapag may pcs na walang serial
   ("N piece(s) were recorded without serial/property details") at note kapag
   walang units (consumable / pre-tracking receipt).
   **Matching:** units ay hinahanap muna via `items[N][part_id]`, fallback via
   part `item_name` === item description (para sa lumang PRs / "create new").
   **Key decision:** ang grid ay lumalabas **kapag may naitalang units, anuman
   ang `requires_unit_tracking` flag** — ang naitalang serial/property ang truth.
Tests: delivery record asserts VISIBLE ang serial (RX-VIEW-01) at property
(PN-VIEW-01) kada pcs.

### 5. PR table "View delivery" button + receipt rule
- `purchase-requests/partials/pr-table-rows.blade.php`: kapag `delivered` ang
  PR row sa Supply table → **"🧾 View delivery"** secondary button (bukod sa
  mismong form/show page).
- `attachments-card.blade.php`: ang receipt hint ay
  **"required for every purchase, any amount (PDF / JPG / PNG)"** na — tanggal
  ang dating "required under ₱10,000" exemption; ang empty-state warning ay
  para na sa **lahat** ng undelivered PRs (hindi lang small purchase).

### Test gate (final, buong session)
```
PurchaseRequestTest: 42 passed (165 assertions)
PmRepairPartsRequestTest: 10 passed (44 assertions)
Full suite: 202 passed (781 assertions) — green
view:cache compile — OK
```

### Commits (session, 2026-09-01)
| Sha | Saklaw |
|---|---|
| `72ed529` | Squash ng 9 commits: PA/PB, dashboard colors, PM repair flow, auto-save, Job Orders fix, stuck assets, inventory toolbar |
| `bd320b0` | part_id validation 500 fix + auto-issue linked requisition on delivery |
| `81f442c` | `requisitions:fix-stuck-issued` cleanup command |
| `5769ef4` | serial/property grid sa Add to inventory din |
| `dd0250b` | supply PR visibility (region + org scope) |
| `9608d75` | view-only delivery record + View delivery button + blade 500 fix |
| `cbb1a67` | per-piece serial/property detail (migration + backfill + view) |
| `1311fe1` | PR table View delivery action + receipt-required-any-amount hint |

Lahat ay naka-push sa `origin/develop` (= `HEAD` = `1311fe1`).
