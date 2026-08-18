# Asset Set Integrity & Verification — Implementation Notes

**Status:** Implemented (phases #2–#6). The read-only Verification Queue (#1) was
intentionally **skipped** per the August 18, 2026 instruction so we can first see the
real data gaps before enforcing any rules.

Plain-English summary of what this change set does and why it is safe for the existing
imported Desktop sets.

---

## Why this exists

Government PAR asset sets group several **physical** records (CPU, monitor, keyboard,
mouse, etc.) under **one shared PAR number and one custodian**. The database has long
supported `parent_asset_id`, but a user could previously:

- Create a component that clashed with a different parent's PAR/property number.
- Detach a component from a set through the normal edit form (splitting an accountable set).
- Pick a parent by typing a raw numeric asset id (fragile, error-prone).
- Get raw database errors (e.g. serial duplicate) instead of a friendly message.

This phase hardens the set model **without breaking anything already imported**.

---

## Phase 2 — Set-aware validation (`app/Services/AssetSetIntegrityService.php`)

New service guards the parent/component rules on every create/update:

- **One parent per asset** — an asset can only be a standalone parent, never a child.
- **Components must link to their parent** — a component's PAR, custodian, and org scope
  (`region`/`branch`/`office`/`department`) are **inherited** from the parent
  (`applyParentContext()`), so they can't drift.
- **Shared PAR/property context only inside a set** — property numbers may repeat *only*
  within the same parent set; the same property number on an unrelated standalone asset is
  rejected.
- **Block unrelated parents sharing a PAR** — property-number reuse is scoped per set.
- **Serial stays unique per physical asset** — enforced by the DB unique index and exposed
  via user-facing form-request rules (no raw DB errors).
- **An asset cannot be its own parent**, a **component cannot be another's parent**, and the
  parent **must have a PAR number** before it can own components.

## Phase 3 — Manual Create/Update protection

- Both `StoreInventoryRequest` and `UpdateInventoryRequest` expose the serial-number
  uniqueness as a **user-facing rule** (friendly `"This serial number already exists"`),
  instead of a 500/DB error.
- Both Actions return clean 422 JSON for set/property/PAR conflicts.
- A component **cannot be detached or moved** to another set from the normal edit form
  (dedicated audited workflow is required instead).
- **New parent picker:** the Inventory Add/Edit modal now offers a **searchable list** of
  valid candidate parents (standalone assets with a PAR, within the supply admin's scope)
  instead of a raw numeric id field.
  - New endpoint: `GET /inventory/parent-assets` → `ListParentAssetsAction`.
  - Search matches item name, serial, PAR, and property number.
  - When editing an existing component, the parent field is locked (server-side too).

## Phase 4 — Custodian transfer audit + notification

- `RequestNotificationService::notifyNewAssetCustodian()` — notify the custodian the moment
  a new asset is assigned to them.
- `RequestNotificationService::notifyAssetCustodianTransfer()` — after an **approved**
  transfer, notify the **old** custodian ("no longer assigned") and the **new** custodian
  ("now assigned").
- Parent custodian/PAR changes **propagate to every component** inside the transaction, with
  a `'Set Custodian Updated'` history row per component.
- Historical issued-part recipients are **unchanged** — `notifyAssetCustodianOfPartsIssue`
  still notifies the custodian recorded at issue time; nothing about existing history rows is
  rewritten.

## Phase 5 — Gradual completeness enforcement

- `activationError()` only requires **custodian, property number, acquisition cost** for:
  - **brand-new** assets, or
  - an existing asset transition **Spare → Active**.
- **Legacy imports are NOT blocked.** Incomplete imported sets stay untouched until they are
  corrected — nothing flips status or hard-fails just because a field is missing.

## Phase 6 — Tests

- `tests/Feature/InventoryAssetIntegrityTest.php` — parent/component set behavior, duplicate
  unrelated PAR rejection, transfer history + notifications, and legacy incomplete records.
- `tests/Feature/RequisitionTicketContextTest.php` — requisition context integrity.

Migrations (new, untracked):
- `2026_08_18_000001_harden_parts_unit_accountability.php`
- `2026_08_18_000002_harden_inventory_asset_history.php`

---

## How to verify

```bash
php artisan test --filter=InventoryAssetIntegrityTest
php artisan test --filter=RequisitionTicketContextTest
php artisan route:list --name=inventory   # confirm inventory.parent-assets is registered
```

Manual browser check:
1. Inventory → **Add Asset** → "Part Of Parent Set" shows the searchable parent list.
2. Type a keyword → the dropdown filters to matching standalone parents with a PAR.
3. Edit a component → the parent field is locked.
4. Try creating a component under a parent with a duplicate property number on an unrelated
   asset → friendly rejection (not a DB error).

## Assumptions / limits

- The parent picker lists up to 200 candidates in the admin's scope (per search). If your
  set's parent is outside that list, type to search for it.
- Verification Queue (#1) remains out of scope until a later phase.
