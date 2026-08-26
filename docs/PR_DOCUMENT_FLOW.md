# Purchase Request — Document Flow & Asset Traceability

> **Last Updated:** August 26, 2026
> **Branch:** `develop`
> **Status:** Core flow complete; asset traceability planned (see §4)

---

## 1. Overview

The Purchase Request (PR) follows a government Appendix-60-style document flow:

```
IT / Super Admin creates PR form (Appendix 60 sheet)
        │  Job Order linkage inherited from parts requisition
        ▼
   STATUS: submitted ──► Supply Officer queue
        │                  ├── can VIEW full document anytime
        │                  ├── can EDIT submitted PRs (corrections)
        │                  └── can FINALIZE (locks document, unlocks official print)
        ▼
   STATUS: finalized ──► printable ──► physical submission to Procurement
```

**Key tables / relations**

| Table.Column | Points to | Purpose |
|---|---|---|
| `purchase_requests.requisition_id` | `requisitions.id` | Source parts requisition (deficit-line prefill) |
| `purchase_requests.requested_by` / `created_by` | `users.id` | Requester / creator |
| `purchase_requests.finalized_by` | `users.id` | Supply Officer who finalized |
| `purchase_requests.request_id` *(planned §4)* | `requests.id` | Job order ticket → asset + custodian traceability |

---

## 2. Recently Completed (August 26, 2026 session)

### 2.1 Print Document Fixes
| Commit | Change |
|---|---|
| `61cd08d` | **Cost/Total save bug fixed.** Root cause: repeated `items[][field]` bracket names are parsed by PHP into separate single-key elements, orphaning `quantity`/`unit_cost`. Fix: explicit per-row indices (`items[0][description]`, …). Verified end-to-end (form → DB → print). |
| `bd6da8e` | Print sheet font switched to Arial; A4 portrait `@page` rule; table fits A4 with page-break protection. |

### 2.2 Edit Feature (Supply Officer / Super Admin / IT-owner)
| Commit | Change |
|---|---|
| `585b076` | New routes `GET .../{pr}/edit` and `POST .../{pr}` (`update`). Authorization via `canEdit()`: Supply/SA may edit any **submitted** PR; IT only their own; **finalized is locked for everyone**. New view `purchase-requests/edit.blade.php` reuses the A60 sheet prefilled from DB (header fields + all item rows, add/remove/edit). Totals recalculated on save; every save writes an AuditLog entry. |

### 2.3 Show Page UX
| Commit | Change |
|---|---|
| `d5136b7` / `74c0524` | Status explainer box replaces bare pill + lock text: contextual guidance per status. |
| `977456b` | Unified action buttons: Edit (secondary), Finalize & Print (**green** — locking action), Print document (**blue**), Print locked (disabled). Consistent size, icons, hover lift/shadow. |
| `7dbbd88` | Removed print-dialog tip text under Print button. Page title now prints clean: `Purchase Request PR-YYYY-NNNN`. |

### 2.4 Create Form UX
| Commit | Change |
|---|---|
| `1b81fce` / `7479b4e` | Original A60 header layout restored; unified action-button styling inlined in both create and edit views (cache-proof). |
| `84a58d3` | Blank padding rows submit cleanly: quantity fields empty by default, auto-fill to 1 when a description is typed; server strips empty rows before validation. |

### 2.5 Manual Item → PR Hint (Requisitions page)
| Commit | Change |
|---|---|
| `5352ebf` / `45de656` / `4ffcea9` / `2f4944c` | English hint with live item name and quantity, debounced 500 ms while typing, no icon/side border, fixed always-visible bug (`hidden` overridden by inline `display:flex`). |

### 2.6 Supply Officer Queue Table (3-phase overhaul)
| Phase | Commit | Change |
|---|---|---|
| 1 | `d25c711` / `102ab70` | Merged duplicate tables into ONE unified table with working filter chips; added View button; dropped "Created by". |
| 1+ | `06a59db` | Removed Legacy chip and `(legacy)` pill. |
| 2 | `2d01fee` | Status visuals: amber left-accent on submitted rows; waiting-days badge (warn ≥3 d, urgent ≥7 d). |
| 3 | `d6a5136` / `bd6da8e` | Count badges inside chips; contextual empty states; smooth row hover; responsive natural column widths.

---

## 3. Current Authorization Matrix

| Action | Supply Officer | Super Admin | IT (owner) | IT (others) | Regular user |
|---|---|---|---|---|---|
| Create PR form | ✅ | ✅ | ✅ | ✅ | ❌ |
| View PR document | ✅ any | ✅ any | ✅ own | ❌ | ❌ |
| **Edit** submitted PR | ✅ any | ✅ any | ✅ own | ❌ | ❌ |
| Edit **finalized** PR | ❌ locked | ❌ locked | ❌ locked | ❌ locked | ❌ |
| Finalize | ✅ | ✅ | ❌ | ❌ | ❌ |

Implemented in `PurchaseRequestController::canEdit()` — enforced server-side (403), buttons hidden client-side.

---

## 4. PLANNED NEXT: PR ↔ Asset / Custodian Traceability

### Goal
When parts are requested for a specific asset (via job order → linked asset + custodian), the PR must be traceable from that asset's profile — visible in the **Installed Parts / Consumables** card of `inventory/detail.blade.php`, alongside installed units.

### Phase A — Invisible Job Order Linkage (backend only)
| Step | Task |
|---|---|
| A1 | Migration: add nullable `unsignedBigInteger request_id` to `purchase_requests` (→ `requests.id`). |
| A2 | `PurchaseRequest` model: add `request_id` to `$fillable`; add `request(): BelongsTo` relation (job order ticket, carries `linked_asset_id` + assigned custodian). |
| A3 | Auto-derivation (server-side, no UI): whenever a PR is stored or updated **with** a linked `requisition_id`, inherit that requisition's `request_id`. Applies to `store()`, `update()`, and `CreatePurchaseRequestAction::createFromForm()`. |
| A4 | Nothing displayed anywhere — pure database linkage for traceability. No selectors, no hidden inputs, no printed fields. |

### Phase B — Asset Profile Visibility
| Step | Task |
|---|---|
| B1 | In `inventory/detail.blade.php`, inside the existing **Installed Parts / Consumables** card — append a "Requested (pending PR)" sub-section. |
| B2 | Query: PRs where `request_id ∈ requests.where(linked_asset_id = this asset)` — all PRs tied to job orders raised against this asset. |
| B3 | Render each: **PR #** (link to show page) · submission date · status pill (Submitted / Finalized) · first-item summary + "+N more" · total amount. |
| B4 | Custodian context comes free: job order ticket carries `assigned_to` (custodian). |
| B5 | Empty state: silently absent when there are no linked PRs. |

### Phase C — Tests
| Test | Assertion |
|---|---|
| Creating PR from requisition inherits `request_id` | equals the requisition's ticket id |
| PR appears on linked asset detail card | `assertSee($pr->pr_number)` on asset detail route |
| PR does NOT leak to unrelated assets | `assertDontSee` on another asset's page |
| Manual PRs without job order stay unlinked | `request_id` null; not shown on any asset card |

---

## 5. Known Gaps / Deferred Items

| Item | Notes |
|---|---|
| Supply Officer standalone PRs (no job order) | Requested by user; deferred until traceability lands. Would relax job-order requirement for `canProcessSupply()` users. |
| Legacy PR records | Pre-revamp rows have no job order linkage; view-only, excluded from asset cards. |
| Retro-filling costs on old PRs | Old documents keep original (blank) cost data by design. |

---

## 6. File Map (PR flow)

| File | Role |
|---|---|
| `app/Http/Controllers/PurchaseRequest/PurchaseRequestController.php` | createForm / store / edit / update / show / finalize + `canEdit()` + `normalizeSubmittedItems()` |
| `app/Actions/PurchaseRequest/CreatePurchaseRequestAction.php` | Creates PR (form + prefill helpers) |
| `app/Actions/PurchaseRequest/ShowPurchaseRequestAction.php` | Document view authorization |
| `app/Actions/PurchaseRequest/ListPurchaseRequestsAction.php` | Supply queue listing + counts |
| `resources/views/purchase-requests/create.blade.php` | A60 create form (indexed item names) |
| `resources/views/purchase-requests/edit.blade.php` | A60 edit form (prefilled) |
| `resources/views/purchase-requests/show.blade.php` | Document + screen chrome |
| `resources/views/requisitions/supply-index.blade.php` | Supply Officer PURCHASE REQUESTS tab |
