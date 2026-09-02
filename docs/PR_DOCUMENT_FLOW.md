# Purchase Request â€” Document Flow & Asset Traceability

> **Last Updated:** September 1, 2026
> **Branch:** `develop`
> **Status:** Core flow complete; + My Parts Requisitions "View delivery" action (IT/Super Admin) + one-page Delivery Confirmation PDF (see sections 9.7-9.8.**

---

## 1. Overview

The Purchase Request (PR) follows a government Appendix-60-style document flow:

```
IT / Super Admin creates PR form (Appendix 60 sheet)
        â”‚  Job Order linkage inherited from parts requisition
        â–¼
   STATUS: submitted â”€â”€â–º Supply Officer queue
        â”‚                  â”œâ”€â”€ can VIEW full document anytime
        â”‚                  â”œâ”€â”€ can EDIT submitted PRs (corrections)
        â”‚                  â””â”€â”€ can FINALIZE (locks document, unlocks official print)
        â–¼
   STATUS: finalized â”€â”€â–º printable â”€â”€â–º physical submission to Procurement
```

**Key tables / relations**

| Table.Column | Points to | Purpose |
|---|---|---|
| `purchase_requests.requisition_id` | `requisitions.id` | Source parts requisition (deficit-line prefill) |
| `purchase_requests.requested_by` / `created_by` | `users.id` | Requester / creator |
| `purchase_requests.finalized_by` | `users.id` | Supply Officer who finalized |
| `purchase_requests.request_id` *(planned Â§4)* | `requests.id` | Job order ticket â†’ asset + custodian traceability |

---

## 2. Recently Completed (August 26, 2026 session)

### 2.1 Print Document Fixes
| Commit | Change |
|---|---|
| `61cd08d` | **Cost/Total save bug fixed.** Root cause: repeated `items[][field]` bracket names are parsed by PHP into separate single-key elements, orphaning `quantity`/`unit_cost`. Fix: explicit per-row indices (`items[0][description]`, â€¦). Verified end-to-end (form â†’ DB â†’ print). |
| `bd6da8e` | Print sheet font switched to Arial; A4 portrait `@page` rule; table fits A4 with page-break protection. |

### 2.2 Edit Feature (Supply Officer / Super Admin / IT-owner)
| Commit | Change |
|---|---|
| `585b076` | New routes `GET .../{pr}/edit` and `POST .../{pr}` (`update`). Authorization via `canEdit()`: Supply/SA may edit any **submitted** PR; IT only their own; **finalized is locked for everyone**. New view `purchase-requests/edit.blade.php` reuses the A60 sheet prefilled from DB (header fields + all item rows, add/remove/edit). Totals recalculated on save; every save writes an AuditLog entry. |

### 2.3 Show Page UX
| Commit | Change |
|---|---|
| `d5136b7` / `74c0524` | Status explainer box replaces bare pill + lock text: contextual guidance per status. |
| `977456b` | Unified action buttons: Edit (secondary), Finalize & Print (**green** â€” locking action), Print document (**blue**), Print locked (disabled). Consistent size, icons, hover lift/shadow. |
| `7dbbd88` | Removed print-dialog tip text under Print button. Page title now prints clean: `Purchase Request PR-YYYY-NNNN`. |

### 2.4 Create Form UX
| Commit | Change |
|---|---|
| `1b81fce` / `7479b4e` | Original A60 header layout restored; unified action-button styling inlined in both create and edit views (cache-proof). |
| `84a58d3` | Blank padding rows submit cleanly: quantity fields empty by default, auto-fill to 1 when a description is typed; server strips empty rows before validation. |

### 2.5 Manual Item â†’ PR Hint (Requisitions page)
| Commit | Change |
|---|---|
| `5352ebf` / `45de656` / `4ffcea9` / `2f4944c` | English hint with live item name and quantity, debounced 500 ms while typing, no icon/side border, fixed always-visible bug (`hidden` overridden by inline `display:flex`). |

### 2.6 Supply Officer Queue Table (3-phase overhaul)
| Phase | Commit | Change |
|---|---|---|
| 1 | `d25c711` / `102ab70` | Merged duplicate tables into ONE unified table with working filter chips; added View button; dropped "Created by". |
| 1+ | `06a59db` | Removed Legacy chip and `(legacy)` pill. |
| 2 | `2d01fee` | Status visuals: amber left-accent on submitted rows; waiting-days badge (warn â‰¥3 d, urgent â‰¥7 d). |
| 3 | `d6a5136` / `bd6da8e` | Count badges inside chips; contextual empty states; smooth row hover; responsive natural column widths.

---

## 3. Current Authorization Matrix

| Action | Supply Officer | Super Admin | IT (owner) | IT (others) | Regular user |
|---|---|---|---|---|---|
| Create PR form | âœ… | âœ… | âœ… | âœ… | âŒ |
| View PR document | âœ… any | âœ… any | âœ… own | âŒ | âŒ |
| **Edit** submitted PR | âœ… any | âœ… any | âœ… own | âŒ | âŒ |
| Edit **finalized** PR | âŒ locked | âŒ locked | âŒ locked | âŒ locked | âŒ |
| Finalize | âœ… | âœ… | âŒ | âŒ | âŒ |

Implemented in `PurchaseRequestController::canEdit()` â€” enforced server-side (403), buttons hidden client-side.

---


## 4. PR Lifecycle Completion - Receiving, PHP 10k Threshold & Asset Traceability

> **STATUS (Aug 27, 2026): Phase A âœ… DONE Â· Phase A+ âœ… DONE Â· Phase B âœ… DONE Â· Phase C groundwork âœ… DONE Â· Phase C proper âœ… DONE (C4, C6, C3, C5, C7, C8 all landed) â€” FEATURE COMPLETE**
> Approved plan; user decisions recorded in 4.2 Scope Table.
> Rollback safety net: git tag `checkpoint/pre-pr-receiving` @ 66ef4d4 (+ DB dump taken before migration).
> Scope principle: CMMS records/tracks purchasing FOR MAINTENANCE PARTS ONLY (PR document, receiving, stock linkage). Bidding, supplier management, payments stay OUTSIDE - completes the parts loop without becoming an ERP.

### 4.0a Execution Progress (updated as work completes)
| Commit | What landed | Gate |
|---|---|---|
| `940b9cd` | Final approved plan (this document) | â€” |
| `40b4dcf` | **Phase A**: request_id column + model relation + silent derive on store/update (requisition -> ticket inheritance) | 10 passed |
| `20d5027` | **Phase B**: Pending-PR sub-section on asset Installed Parts / Consumables card; visible ONLY on the linked asset | 12 passed |
| `f4c3d2c` | **Phase C groundwork**: STATUS_DELIVERED + delivered_at/by columns + pr_attachments table + PrAttachment model | schema verified |
| `57a5ed8` | **Phase A+**: manual-item PRs from My Parts Requisitions now carry the selected job order invisibly (hidden server-validated carrier through create form -> store) | **14 passed (55 assertions)** |
| *(C4)* | **Phase C4**: ReceivePurchaseRequestAction with â‚±10k threshold auth (owner receives <10k after finalize; Supply receives everything; 403 otherwise) + isOwnedBy() helper | 20 passed |
| *(C3+C6)* | Upload/Download/Delete PrAttachment actions (pdf/jpg/png â‰¤10MB, sealed after delivery, AuditLog per action) + below-threshold receive gate (no receipt â†’ no receive) | 23 passed |
| *(C5)* | Per-line receive: stock-in (on_hand increment + PartMovement ref) or direct-asset (PartUnit created as issued to asset+custodian â€” lands on Installed Parts card); tracked parts require serial+property per unit; duplicates rejected; validate-before-apply (no half-applied receipts); receive panel UI + receipt card on PR show page | 27 passed |
| *(C7)* | Delivered chip in supply queue + cmms-status-received pill + delivered date on PR page + Delivered state on IT tab | 27 passed |
| *(C8)* | Full receive via HTTP records audit log; every receive/upload/delete audited | **28 passed (98 assertions)** |

### 4.0a-2 Post-completion UX hardening (Aug 27, 2026 continued)
| Commit | What landed | Notes |
|---|---|---|
| `cee5dab` | Receiving moved OFF the PR document page to a dedicated screen (`GET /purchase-requests/{id}/receive` -> `purchase_requests.receiveForm`); document page keeps only a "Record delivery" shortcut button. Document stays clean/printable. | route+controller `receiveForm()` with `canReceive()` guard redirect |
| `8a356c6` / `ec92526` | Record Delivery page redesign: delivery-slip hero (PR number, items, total, target asset stats), breadcrumb topbar, per-line item cards, unified `.rxb` button system (green confirm / blue upload / white secondary) | user-driven iterations |
| `81d1773` | Cleanup pass: removed colored step badges/icons and green palette inside panels at user request; fixed `rz-details` visibility bug caused by duplicate `display` declarations in inline style (`display:none; ... display:flex;` - flex won, panel always visible) | CSS cascade bug pattern worth remembering |
| `1529cbc` | **Create-new-part on the fly**: dropdown gains "Not in the list? Create new..." option (`value="new"`); inline mini-form prefills name from the PR line description + unit select (pcs/box/set/pair/pack); server `createPartOnTheFly()` registers the part during receiving with case-insensitive duplicate rejection, region inherited from linked asset (fallback requester), auto `requires_unit_tracking=true` (serial/property stay REQUIRED), AuditLog "Part Created During Receiving". **Root bug fixed meanwhile**: controller `receive()` was casting `part_id` through `(int)` which silently converted the `"new"` marker to 0 - marker now passed through as string. | **31 passed (110 assertions)** incl. create-new success, duplicate-name blocked, serialized-without-serial blocked |
| `f6e39bb` | Proof of purchase dropzone rebuilt: `<label for>` replaced by div+JS click handler (label kept re-opening file dialog after selection), AJAX submit instead of raw JSON page response, spinner state, drag&drop, cancel/reset | fixes raw JSON dump reported by user |
| `c3d46f8` | Removed orphaned duplicate script block left after an edit collision - rendered as visible page text under Proof of purchase card | root cause of stray JS text |
| `b7fe6e8` / `dbf0605` | **Action buttons reach the queues**: Supply PURCHASE REQUESTS tab finalized rows get direct "Record delivery" link; IT Purchase Requests tab gets proper `Action` column header, text-only buttons (no emoji), "With Procurement" hint for >=10k finalized rows (owner cannot receive those). Server-side `canReceive()` remains the enforcement. | 31 passed |

Data cleanup (same day): all 16 legacy/test PR records deleted at user request (PR-2026-0001..0016); sequence reset - next number is back to PR-2026-0001. One legit link was salvaged first via backfill before deletion. From this point every new PR auto-links to its job order/asset, so orphaned asset-card gaps cannot recur.

### 4.0c The full receiving journey as shipped
```
Supply queue (finalized row)          IT Purchase Requests tab (<10k owned, finalized row)
        [Record delivery]                      [Record delivery]
                 \                                /
                  v                              v
            GET /purchase-requests/{id}/receive   (canReceive() guarded)
                  |
    Step 1  Log items & destinations
            - each PR line: match inventory item OR create new on the fly
              (name prefilled from PR description, unit select,
               duplicate rejected, region inherited)
            - destination radio-cards:
                * Add to inventory  -> stock-in  (+on_hand, PartMovement refs PR)
                * Install on asset  -> direct-asset (PartUnit issued straight
                  onto asset_id + custodian -> lands on Installed Parts card)
            - serialized parts: serial + property number REQUIRED per piece,
              duplicates within receipt or against existing stock rejected
    Step 2  Proof of purchase
            - dropzone/click/drag&drop upload, pdf/jpg/png <=10MB
            - REQUIRED when total < 10k (gate blocks Confirm otherwise)
            - sealed forever once delivered
    Step 3  Sticky confirm bar ("cannot be undone")
            v
      status=DELIVERED, delivered_by/at set, audited
```

### 4.0b How a PR gets its job order link now (invisible, two paths)
```
Path 1: Requisition deficit line  ->  PR inherits requisition.ticket     (store/update derive)
Path 2: Manual item + Create PR   ->  hidden ticket carrier in form      (server validates ownership + active status per role)
Manual PR typed directly in form with NO ticket context            ->  stays null-linked (excluded from asset cards)
```

### 4.0 Core Gap Being Fixed

Current lifecycle stops at finalized; nobody can tell whether the item was purchased, arrived, or reached its asset. Target end state:

    draft -> submitted -> finalized -> DELIVERED (units stocked-in OR installed straight onto the asset)

Issue-from-stock requisitions never need a PR (issued units already land on the Installed Parts card). PR covers ONLY the buy-path (deficit/manual items).

### Phase A - Invisible Job Order Linkage (backend only) â€” âœ… DONE (`40b4dcf` + `57a5ed8`)
| Step | Task | Status |
|---|---|---|
| A1 | Migration: nullable unsignedBigInteger request_id on purchase_requests (-> requests.id). | âœ… |
| A2 | PurchaseRequest model: request_id into fillable; request(): BelongsTo relation (ticket carries linked_asset_id + custodian). | âœ… |
| A3 | Silent auto-derive server-side: whenever a PR is stored/updated WITH a linked requisition_id, inherit that requisition request_id. Applies to store(), update(), CreatePurchaseRequestAction::createFromForm(). | âœ… |
| A4 | Nothing displayed anywhere - DB-only linkage. No JO selector, hidden input, or printed field on any form. | âœ… |
| A5 | Manual-item path: My Parts Requisitions "Create PR" link carries ?ticket=; create form holds it in a hidden server-validated carrier; store() links it via validatedContextTicketId() (role-scoped ownership + active-status checks, never throws). | âœ… |

### Phase B - Asset Profile Visibility â€” âœ… DONE (`20d5027`)
| Step | Task | Status |
|---|---|---|
| B1 | inventory/detail.blade.php Installed Parts / Consumables card: append Requested (pending PR) sub-section. | âœ… |
| B2 | Query: PRs where request_id in requests.where(linked_asset_id = this asset). | âœ… |
| B3 | Render each: PR # (link) . submission date . status pill (Submitted/Finalized/Delivered) . item summary + N more . total. | âœ… |
| B4 | Custodian context comes free via ticket assigned_to. | âœ… |
| B5 | Empty state silently absent; unlinked PRs never appear. | âœ… tested both directions |

### 4.2 Phase C Scope Table (user-decided)
| Decision | Locked Choice |
|---|---|
| New status name | delivered (legacy read-only received rows remain tagged legacy - name collision avoided) |
| Partial deliveries | NOT modeled - one-shot delivery; adjust PR or raise a new one |
| Integration depth | FULL receive screen: destination choice + serial tracking |
| Serial / property number | REQUIRED for tracked parts (reuse existing requires_unit_tracking guards) |
| PHP 10,000 rule | Below: owning IT/SuperAdmin buys AND receives; 10k+: Supply Officer only |
| Mid-flight price drift | Deferred (no role switch; audit data will surface discrepancies) |
| Proof of purchase | Receipt upload REQUIRED before any receive below 10k (replaces separation-of-duties check) |

Scope cuts removed from earlier drafts: editable actual-cost field on receive (receipt is the evidence), per-line received-qty partial columns, delivery timeline visualization, quotation/canvass attachments (receipts only), notifications.

### Phase C - Receiving Flow Implementation â€” âœ… DONE (all steps)
| Step | Task | Status |
|---|---|---|
| C1 | Migration: purchase_requests.delivered_at + delivered_by (nullable FK users); new pr_attachments table mirroring asset_attachments shape. | âœ… (`f4c3d2c`) |
| C2 | Model: STATUS_DELIVERED constant; legacy display logic untouched so old received rows stay view-only + tagged. | âœ… (`f4c3d2c`) |
| C3 | Attachment actions cloned from UploadAssetAttachmentAction pattern: pdf/jpg/jpeg/png max ~10MB, disk pr-attachments/{prId}, AuditLog per upload/delete, IMMUTABLE once delivered. | âœ… |
| C4 | Receive authorization: allowed if (total < 10000 AND user owns PR OR SuperAdmin) OR canProcessSupply(). Server-enforced 403; button hidden client-side. | âœ… |
| C5 | Receive UI lives ON THE PR SHOW PAGE (not Parts page): per line destination - Stock-in (spare/reusable) or Direct-to-asset (one-time install, bypasses general stock); part match prefilled from description text but manually confirmed; tracked parts require serial_number + property_number per unit, duplicates rejected within same part. | âœ… |
| C6 | Below-threshold gate: receive returns error until at least one receipt attachment exists. | âœ… |
| C7 | Supply queue gains Delivered chip + pill; show page displays delivered date. NO timeline widget. | âœ… |
| C8 | Every receive/upload/delete audited (who, when, which serials). | âœ… |

### Phase D - Tests (every gate)
> Phase A/B test rows below are âœ… already passing (14 passed / 55 assertions). Phase C rows activate as code lands.
| Test | Assertion | Status |
|---|---|---|
| PR from requisition inherits request_id | equals the requisition ticket id | âœ… |
| Manual PR without job order stays null-linked | not shown on any asset card | âœ… |
| Asset card isolation | PR visible ONLY on its linked asset detail, assertDontSee elsewhere | âœ… |
| Below-threshold without receipt | receive attempt blocked | â³ Phase C |
| Below-threshold with receipt | owning IT receives successfully | â³ Phase C |
| 10k+ receive by IT | blocked (403) | â³ Phase C |
| Tracked part missing serial or property no. | blocked | â³ Phase C |
| Duplicate serial on same part | validation error | â³ Phase C |
| Stock-in path | on_hand increments exactly once, movement referenced to PR | â³ Phase C |
| Direct-to-asset path | unit created status=issued with asset_id, custodian, request_id - visible on asset card | â³ Phase C |
| Post-delivered immutability | attachments can no longer be deleted | â³ Phase C |
| Legacy received rows | still tagged legacy, excluded from new flows | â³ Phase C |

## 5. Known Gaps / Deferred Items

| Item | Notes |
|---|---|
| Supply Officer standalone PRs (no job order) | User-requested; deferred until receiving flow lands. Would relax job-order requirement for supply creators. |
| ~~Legacy PR records~~ | RESOLVED Aug 27, 2026: all 16 pre-revamp test/scratch PR records deleted at user request; sequence reset (next = PR-2026-0001). No orphaned rows remain. |
| Retro-filling costs on old PRs | Moot after the deletion above; all future PRs capture costs at creation. |
| Mid-flight price drift (small PR actual > 10k) | No role-switch handling; surfaces via audit data later. |
| Notifications (delivered / awaiting review) | Deferred polish. |
| Consumable-only receive (no serial) | Current "Create new..." flow always marks the part serialized/accountable (requires_unit_tracking=true). True non-serialized consumables (thermal paste, cables) still must be pre-registered via Parts & Consumables before receiving, or delivered as existing parts. Deferred by design to keep every PR-received unit accountable. |
| Parts & Consumables PR visibility (next) | Movements table records reference_type=purchase_request but History modal does not yet render PR number as clickable; Units modal does not yet show "via PR-xxxx" per unit; no in-row badge for in-flight PRs on a parts row. All data exists server-side - UI surfacing planned. |

## 6. File Map (PR flow)

Existing:
| File | Role |
|---|---|
| app/Http/Controllers/PurchaseRequest/PurchaseRequestController.php | createForm/store/edit/update/show/finalize + canEdit() + normalizeSubmittedItems() |
| app/Actions/PurchaseRequest/CreatePurchaseRequestAction.php | Creates PR (form + prefill helpers) |
| app/Actions/PurchaseRequest/ShowPurchaseRequestAction.php | Document view authorization |
| app/Actions/PurchaseRequest/ListPurchaseRequestsAction.php | Supply queue listing + counts |
| resources/views/purchase-requests/create.blade.php | A60 create form (indexed item names) |
| resources/views/purchase-requests/edit.blade.php | A60 edit form (prefilled) |
| resources/views/purchase-requests/show.blade.php | Document + screen chrome |
| resources/views/requisitions/supply-index.blade.php | Supply Officer PURCHASE REQUESTS tab |
| resources/views/inventory/detail.blade.php | Asset profile (Installed Parts card receives PR sub-section) |

Added during Phases Aâ€“C groundwork:
| File | Role |
|---|---|
| database/migrations/2026_08_27_000001_add_receiving_to_purchase_requests_and_create_pr_attachments.php | request_id + delivered_at/delivered_by + pr_attachments table |
| app/Models/PrAttachment.php | Receipt attachment model (asset-attachment mirror) |
| resources/views/purchase-requests/create.blade.php | hidden ticket carrier (manual-item JO context) |

Landed during Phase C proper:
| File | Role |
|---|---|
| app/Actions/PurchaseRequest/ReceivePurchaseRequestAction.php | Threshold/auth gates, validate-then-apply per-line receiving, stock-in or direct-to-asset unit creation |
| app/Actions/PurchaseRequest/UploadPrAttachmentAction.php | pdf/jpg/png â‰¤10MB uploads; canUpload() gate; sealed after delivery |
| app/Http/Requests/UploadPrAttachmentRequest.php | Stricter receipt validation vs asset attachments |
| Controller additions | receive() + uploadAttachment/downloadAttachment/deleteAttachment |
| View partials | purchase-requests/partials/receive-panel.blade.php + attachments-card.blade.php |
| routes/web.php | purchase_requests.receive + attachments.store/download/destroy |

---

## 7. Option B — AJAX Pagination Consistency (August 28, 2026 session)

**Goal:** Make every list tab behave (and look) exactly like the Inventory and
Parts & Consumables modules — server data endpoints + custom pagination with
ellipsis, so clicking a page/search/sort/filter **does not reload the page**.

### 7.1 New AJAX data endpoints (all project page number parity)

| Phase | Surface | Endpoint | Renders |
|---|---|---|---|
| B1 | Supply — Requisition Queue | `GET requisitions.queue.data` | Renders `req-table-row` partial rows + pagination + per-status counts |
| B2 | Supply — Job Orders | `GET requisitions.tickets.data` | Renders `ticket-table-rows` partial + pagination |
| B3 | Supply — Purchase Requests | `GET requisitions.pr.data` | Renders `pr-table-rows` partial + pagination + status counts (calls `ListPurchaseRequestsAction::data()`) |
| B4 | IT/SA — History | `GET requisitions.history.data` | Renders `history-rows` partial + pagination |
| B5 | IT/SA — Purchase Requests | `GET requisitions.myprs.data` | Renders `my-pr-rows` partial + pagination |

All endpoints:
- Guarded by role (`canProcessSupply()` for Supply tabs; `it,admin,super_admin` for IT/SA tabs) → 403 otherwise.
- Re-use the **exact existing Blade markup** via small partials (no Blade↔JS drift).
- Return `{ success, rows, pagination, total, current_page, last_page, ... }`.
- Pagination bar uses `vendor/pagination/parts` (Inventory-style: `‹ Prev 1 2 … 6 [7] 8 … 20 Next ›` + ellipsis window).

### 7.2 Front-end behaviour

| Tab | Wiring |
|---|---|
| Queue | `loadQueue()` — pagination, search form, sort toggle, Approve/Issue/Disapprove quick actions all AJAX; **no `window.location.reload()`** — the queue refreshes in place after an action. Event delegation (`_bound` flag) keeps re-rendered rows clickable. |
| Job Orders | `loadTickets()` — search submit + pagination AJAX |
| Purchase Requests (Supply) | `loadPr()` — filter chips (All/Submitted/Finalized/Delivered) + pagination + Finalize confirm all AJAX |
| History (IT/SA) | `loadHistory()` — status chips + search (debounced 400ms) + pagination AJAX, keeps `tab=history` |
| Purchase Requests (IT/SA) | `loadMyPrs()` — pagination AJAX, keeps `tab=myprs` |

### 7.3 Bugs caught & fixed during the conversion

| Bug | Details |
|---|---|
| **PR rows rendered N×N** | `pr-table-rows` partial loops internally; the index view initially included it inside another `foreach` → 5 PRs rendered 25 rows (5×5). Fixed by including the partial **once**. Added regression test `test_pr_index_page_renders_each_row_exactly_once`. |
| **Missing route** | `Route [requisitions.pr.data] not defined` — the route was referenced in JS before being registered. Added B3 route. |
| **PMFlow date-test drift** | `test_next_scheduled_at_computed_correctly_for_quarterly_frequency` failed on Aug 28 because `calculateNextDate()` skips weekends; the assertion expected exact `+3 months`. Updated the assertion to be weekday-skip aware (≥ base, not weekend, ≤ base+2d). |

### 7.4 New shared partials
| File | Purpose |
|---|---|
| `requisitions/partials/ticket-table-rows.blade.php` | One job-order row (data endpoint + index) |
| `requisitions/partials/history-rows.blade.php` | One IT/SA history row |
| `requisitions/partials/my-pr-rows.blade.php` | One IT/SA own-PR row |
| `purchase-requests/partials/pr-table-rows.blade.php` | One Supply PR table row |

### 7.5 Test gate
```
SupplyQueueSearchTest + PurchaseRequestTest + requisition suites: 76 passed (291 assertions)
PMFlowTest quarterly (weekday-aware): 1 passed
Working tree clean after commits.
```

---

## 8. Planned — Create PR from Parts & Consumables + Notifications

> **Status:** Planned (implementing phase-by-phase). Sections below are the
> agreed design before any code changed — updated after each phase lands.
> **PA + PB both IMPLEMENTED** (see status notes below).

### 8.1 Phase PA — "Create PR" entry points + `part_id` submit fix

| # | Change | File |
|---|---|---|
| PA.1 | Per-row **Create PR** icon button in the Parts table — visible **only for critical rows** (`statusLevel() === 'critical'`) **and** supply-writable views (`PARTS_CAN_WRITE`). Links to `purchase_requests.create?part_id={id}`. | `resources/views/inventory/parts.blade.php` (JS `renderPartsTable`) |
| PA.2 | Toolbar **Create PR** button (supply-only) → plain `purchase_requests.create` for brand-new (unlisted) items. | `parts.blade.php` toolbar |
| PA.3 | **`?part_id=N` prefill**: `createForm()` looks up part → `prefill.items[0]` = `{ description: item_name, unit, unit_cost: latestUnitCost(partId), quantity: deficit, part_id }` where deficit = `max(1, reorder_level − on_hand_qty)` when reorder > 0 else 1. | `PurchaseRequestController::createForm()` |
| PA.4 | **Hidden `items[N][part_id]` input** in `rowHtml()` — fixes the existing gap where `tr.dataset.partId` was never submitted, breaking future inventory-match on receive. | `purchase-requests/create.blade.php` JS |
| PA.5 | Clear stale `tr.dataset.partId` when a description no longer matches the catalog. | create form JS |

**Status: IMPLEMENTED.** Per-row `Create PR` (critical only, supply-writable),
toolbar `Create PR` for new items, `?part_id=N` prefill (description/unit/latest
cost/deficit qty), hidden `items[N][part_id]` input submitted to the server, and
stale-partId clearing. Tests: `test_create_form_prefills_from_part_id` +
`test_part_id_lands_in_stored_pr_items` (+ 2 notification tests in PB).

**Gate:** `view:cache` + PartsStockTest + PurchaseRequestTest + new tests
(critical-only visibility, prefill correctness, part_id survives submit).

### 8.2 Phase PB — PR workflow notifications (in-app + email via existing pipeline)

| Event | Recipients | Type string |
|---|---|---|
| Submitted | Supply users in creator's region/branch (exclude creator) | `PR Submitted` |
| Finalized | Requester + creator (dedupe, exclude self) | `PR Finalized` |
| Delivered | Requester + creator | `PR Delivered` |

- `Notification::send(userId, null, type, message)` — `request_id` stays null
  (PRs are not `requests`; the PR number is part of the message text).
- Built-in safety pipeline reused: super-admins in-app only; alias emails
  skipped in production; local dev writes a log preview.

**Status: IMPLEMENTED.** New service
`app/Services/PurchaseRequestNotificationService.php` (notifySubmitted/
notifyFinalized/notifyDelivered + supplyUsers region/branch query). Wired
into `CreatePurchaseRequestAction`, `FinalizePurchaseRequestAction`, and
`ReceivePurchaseRequestAction`. Gate: full PR/parts/requisition suites green
(35 PR + 58 parts/requisition + 15 parts stock).

### 8.3 Explicit non-goals
- No schema change (no new columns/tables).
- No changes to receiving, Lifecycle History, or existing ICT/PM notifications.
- Parts catalog in PR form stays global (matches receive matching); noted only.

---

## 9. Receive / Record Delivery — September 1, 2026 session

> Detailed root-cause notes (Taglish) live in `docs/PM_REPAIR_PARTS_REQUISITION.md`
> Phase PR-RECV. Summary below.

### 9.1 Receiving flow (current state)
```
finalized PR ──► Record Delivery (receiveForm, canReceive() = supply/SA or
                   below-threshold owner; receipt required for ANY amount)
                   ├─ per-line: match part (catalog / "Create new…")
                   ├─ destination: Add to inventory (stock-in) | Install on asset
                   ├─ tracked part → one serial + property per quantity
                   │   (grid appears for BOTH destinations — part-driven,
                   │    data-tracked flag on the part <option>)
                   └─ Confirm delivery ──► STATUS: delivered
                         ├─ stock-in:      PartMovement + PartUnit(in_stock)
                         ├─ direct-asset:  PartUnit(issued → asset/custodian)
                         │                 + InventoryHistory "Part Installed"
                         ├─ linked requisition (requisition_id) → auto-ISSUED
                         │   + AuditLog + IT notification
                         └─ PR Delivered notification (requester + creator)
```

### 9.2 Schema (new in this session)
| Column | Table | Purpose |
|---|---|---|
| `purchase_request_id` *(nullable FK)* | `parts_stock_units` | Links every received physical unit back to its PR — enables per-piece serial/property display on the delivery record. Backfilled for pre-existing rows (installed via `request_id`; stock-in via PR movement ±3s match). |

### 9.3 View-only Delivery Record (delivered PRs)
- New gate `ReceivePurchaseRequestAction::canViewDelivery()` — delivered PR:
  supply/super-admin or PR owner may VIEW (never re-receive).
- `receiveForm` renders view-only for delivered PRs: delivery summary
  (received at/by), **Received items with per-piece destination badge, unit
  cost, "Tracked pieces N of Q", and serial/property grid**, plus the
  **Proof of purchase** card (read-only; PDF/JPG/PNG download).
- Entry points: PR document action area **"🧾 View delivery"** button (delivered
  status) and the **same button on each delivered PR row** in the Supply table.
- Receipt hint: **required for every purchase, any amount** (₱10k exemption removed).

### 9.4 Supply PR list visibility
- Supply officers / super-admin run the procurement desk: no org-narrowing in
  `ListPurchaseRequestsAction` — they see own-created, requisition-origin, and
  PM-origin PRs regardless of region.
- Auto-generated bundled PM tickets now carry `region` from the end user
  (fallback: actor) — legacy NULL-region PM rows were backfilled.

### 9.5 Artisan maintenance commands (this flow)
| Command | Purpose |
|---|---|
| `php artisan requisitions:fix-stuck-issued` | Marks pending/approved requisitions as issued when their linked PR is already delivered (pre-auto-issue backlog). `--dry-run` supported. |
| `php artisan maintenance:fix-stuck-assets` | Restores "Under Maintenance" assets with no active ticket to Active (bundled-PM completion backlog). `--dry-run` supported. |

### 9.6 Test gate (end of session)
```
Full suite: 206 passed (793 assertions) — two-batch split (93 + 113)
PurchaseRequestTest: 46 passed (+4 new this session: My PRs View delivery, Delivery PDF, pre-delivery redirect, non-owner denial)
PmRepairPartsRequestTest: 10 passed (44 assertions)
Blade view:cache compile: OK
```


### 9.7 My Parts Requisitions (IT / Super Admin) — "View delivery" action

> Commit: `8b72685` — `fix(pr): show View delivery action on My Parts Requisitions table for delivered PRs`

**Gap fixed.** The PR rows in the "My Purchase Requests" strip (IT / Super Admin view of `requisitions.index`, tab `myprs`, partial `resources/views/requisitions/partials/my-pr-rows.blade.php`) previously showed only a "View" action for delivered PRs — the "View delivery" button existed only on the Supply table (`purchase-requests/partials/pr-table-rows.blade.php`) and on the PR document page. Now:

- **Delivered** PR row → extra **[View delivery]** button (secondary style) → opens the same view-only delivery record (`purchase_requests.receiveForm`): received-at/by summary, per-piece serial/property grid, destination badges, Proof of purchase card (read-only).
- Branch order in the action `<td>` block:
  1. `@if($pr->status === 'delivered')` → **[View delivery]** (secondary) — NEW branch
  2. `@elseif($pr->status === 'finalized' && $pr->isSmallPurchase())` → **[Record delivery]** (primary — < ₱10k owner fast-track)
  3. `@elseif($pr->status === 'finalized')` → "With Procurement" hint (≥ ₱10k — Supply Officer receives)
- Same intent as the Supply table: IT officers / Super Admins track their own PRs and now also see the delivery record nang direkta mula sa list.



**Test:** `test_super_admin_my_prs_table_shows_view_delivery_for_delivered` — super_admin opens `requisitions.index?tab=myprs` → asserts the `receiveForm` route link and the string "View delivery" appear for the delivered PR row. (3 assertions)

---

### 9.8 Delivery Confirmation PDF — one-page formal bundle (delivered PRs

> Commits: `d921fde` — `feat(pr): formal one-page Delivery Confirmation PDF...`; `7f8ac44` — `fix(pr): Delivery Confirmation PDF fits exactly one A4 page (sakto, hindi sobra o kulang) + proper page-count assertion`

**What it is.** A formal, print/archive-grade A4 PDF for a delivered PR — PR header in the same bordered Appendix-60 style as the official PR document (Entity Name = "National Conciliation and Mediation Board", fund cluster, office/unit, PR No., PR date, responsibility center, received by, date & time received), a received-items table, a serial/property register, certification paragraph, at Prepared/Received signature grid(`same alignment style as the PR document's Requested/Approved grid`. Exactly ONE page ("sakto lang" per user feedback — hindi sobra, hindi kulang.

**Route / controller / action**

| Layer | File | Details |
|---|---|---|
| Route | `routes/web.php` | `GET /purchase-requests/{purchaseRequest}/delivery-confirmation.pdf` → name `purchase_requests.delivery_confirmation.pdf` (throttle:30,1) |
| Controller | `PurchaseRequestController::deliveryConfirmationPdf()` | One-liner → `DownloadDeliveryConfirmationPdfAction` |
| Action | `app/Actions/PurchaseRequest/DownloadDeliveryConfirmationPdfAction.php` | Gate + data assembly + DomPDF output |
**Access gate** — same audience as the view-only delivery record, plus an explicit delivered-only rule:
- `isDelivered()` AND `ReceivePurchaseRequestAction::canViewDelivery($pr, $user)` — delivered PR: supply / super-admin or PR owner; a merely-**finalized** PR → redirect back to `purchase_requests.show` with the standard denial reason (walang recorded delivery pa na puwedeng i-certify).

**Data assembled** (in the action):
- PR eager-loads `requester, creator, finalizer, deliverer` (attachments load inalis na — no proof-of-purchase section).
- Units: same query as the view-only delivery panel — `PartUnit::where('purchase_request_id', $pr->id)` grouped by `part_id`; per-line match via `part_id` (fallback: item-name match for older / "create new" lines). Per line extracts serial/property numbers, recorded count, at destination label ("Installed on asset {code}" via an `issued` unit; "Add to inventory (stock)" via an `in_stock` unit).
- Render: `Pdf::loadView('pdf.delivery-confirmation', ['pr' => ..., 'lines' => ...])->setPaper('a4', 'portrait')`; response inline `Content-Disposition`, filename `{pr_number}-Delivery-Confirmation.pdf`.

**PDF layout** — `resources/views/pdf/delivery-confirmation.blade.php`:
1. **Title** — centered "DELIVERY CONFIRMATION" (PR-form look).
2. **Header grid** — bordered `a60-field` boxes mirroringthe official PR form: Entity Name, Fund Cluster, Office/Unit, PR No., PR Date, Responsibility Center Code, Received By, Date & Time Received (3-row main blocks to conserve vertical space).
3. **Items Purchased** — bordered official grid: Unit | Description / specification | Qty | Unit Cost | Total Cost + TOTAL row with `PHP {amount}`. **No blank padding rows** — ang PR form's 8 blank-row padding ay nagtulak sa PDF sa 2 pages, kaya tinanggal dito.
.
4. **Serial / Property Register** — bordered table: No., Item, Serial No., Property No., Destination ("Installed on asset {asset_code}" o "Add to inventory (stock)"); walang units/consumable → "No individual units were recorded..." note.
5. **Certification + Signatures** — maikling certification paragraph + "Prepared by" (requester — signature line, printed name, date) at "Received by" (deliverer — designation: "Requester / End User" for small purchases, otherwise "Supply Officer"; signature line, printed name, date).


**Entry point** — `resources/views/purchase-requests/receive.blade.php` topbar: kapag `$viewOnly` (delivered, may "Delivery Confirmation PDF" button (`.rxb.rxb-white.rxb-sm`) → inline-download ang PDF. Finalized PRs — walang PDF button (walang recorded delivery pa to certify.



**Bug fixes folded in** (mula sa user feedback sa totoong output):
- "TOTAL ? 20,000.00" → "TOTAL PHP 20,000.00" — DomPDF ay hindi kayang i-render ang ₱ glyph; ginamit ang formal ISO-style "PHP" prefix (no more "?" artifact).
- Literal na "&nbsp;" sa Purpose / justification box → fixed via raw output (`{!! ... !!}`).
- Tinanggal ang"Stock/Property No." column sa items table — hindi ito kopya ng PR form: delivery items ay Unit | Description | Qty | Unit Cost | Total Cost lang; ang serial/property numbers ay nasa Serial / Property Register sa ibaba** (correct per user: "kinopya mo lang ang PR form — hindi dapat").
- Inalis din ang 8 blank padding rows mula sa items grid(ang pangunahing dahilan ng 2-page overflow).
- **Rev 2 (user feedback):** inalis ang Purpose/justification box, Proof of Purchase register, at footer — mas malinis na one-pager; certification reworded (tanggal ang "proof(s) of purchase enumerated above"); sizes re-calibrated — body 12px / `line-height:1.54` (ang 1.56 ay sumasabog sa stress case), title 15px, mas malalaking paddings — sakto sa A4.
**One-page calibration ("sakto lang")** — sinukat gamit ang real page-count harness (`/Type /Page` token count sa DomPDF output), stress-tested na may 3 items × (5+4+2 = 11 tracked units), full header + items + register + signatures (walang proof section pagkatapos ng Rev 2):
- Page margins 10mm (top/bottom), 12mm (sides); body 12px / `line-height:1.54` pagkatapos ng Rev 2 (ang 1.56 ay sumasabog sa stress case) — dati 11px / 1.26; title 15px; header grid 3-row; table cell paddingdown to 1.5px; compact certification/signature block; in-flow footer (no fixed-position overflow).
- Pinakamalaking komportableng laki na kasya pa rin sa 1 page — "sakto lang" per user (hindi sobra, hindi kulang.
- Result: **1 page** sa lahat ng stress variants (FULL / no-signs / no-signs-cert / no-signs-cert-register / only-header-items — lahat 1).

**Regression protection** — `test_delivered_pr_delivery_confirmation_pdf` now asserts **exactly one A4 page**: `preg_match_all('/\/Type\s*\/Page[^s]/', $content) === 1` — huhulihin nito ang "2 papers" na regression sa hinaharap.

**Tests (3 bago sa `tests/Feature/PurchaseRequestTest.php`:**
1. `test_delivered_pr_delivery_confirmation_pdf` — delivered PR → 200, `application/pdf`, starts `%PDF`, exactly 1 page.
2. `test_delivery_confirmation_pdf_redirects_before_delivery` — finalized (hindi pa delivered) → redirect back to the document.
3. `test_delivery_confirmation_pdf_denied_for_non_owner` — ibang IT officer (di-owner, di-supply, di-SA) → redirect back to the document.

**File map additions**

| File | Role |
|---|---|
| `app/Actions/PurchaseRequest/DownloadDeliveryConfirmationPdfAction.php` | Gate + data assembly + DomPDF output (`view: pdf.delivery-confirmation`) |
| `resources/views/pdf/delivery-confirmation.blade.php` | One-page A4 formal layout (PR-form bordered look) |
| `routes/web.php` | `purchase_requests.delivery_confirmation.pdf` route |
| `resources/views/purchase-requests/receive.blade.php` | View-delivery topbar button (when `$viewOnly`) |

**Next phase (planned):** **Phase 2** — i-embed ang JPG/PNG receipt images nang direkta sa PDF (base64 `data:image/...;base64,...` src via `Storage::disk('public')->get($att->filepath)`; PDF receipts stay listed as text metadata). Same one-page constraint applies — babalikin ang page-count harness para i-verify na kasya pa rin sa 1 page ang naka-embed na receipt (images will likely need width limits (e.g. max ~120mm) at possible compression/scale para manatili ang one-page guarantee).
