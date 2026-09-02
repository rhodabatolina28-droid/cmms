# Physical Count — Custodian Group Counting

**Status:** Implemented
**Date:** 2026-09-02
**Related:** `docs/PM_REPAIR_PARTS_REQUISITION.md`, QR Sticker system

---

## 1. Design Decisions (agreed with user)

| Decision | Rationale |
|---|---|
| **Asset QR = permanent identity** | The physical sticker is per-asset (`/r/{asset_id}`), printed **once**, never re-printed due to reassignment. Tag follows the asset, not the person. COA-aligned (property tagging is per-item). |
| **No new sticker type** | No per-person / per-division QR. No new routes, no QR payload changes. |
| **Person grouping lives in software** | The Physical Count page can search by custodian name and count the custodian's whole assigned set at once. Digital equivalent of a PAR-based annual inventory (COA workflow). |
| **Assigned assets only** | Group results exclude unassigned/spare assets and `For Disposal` / `Scrapped` items. |
| **Live grouping** | Group is computed at scan/search time from `assigned_to_user` — new/removed/transferred assets auto-reflect. No reprints ever. |
| **Immutable marks preserved** | Existing rule: an asset already counted in a session cannot be re-marked (422). Bulk "Mark all Present" treats 422 as *skip*, not error. |

## 2. Roles / Access

Feature is confined to the Supply Office workflow — no other role sees any change.

| Role | Group Counting | Mark All | Complete Batch List |
|---|---|---|---|
| Supply Officer | ✅ | ✅ | ✅ |
| Division Admin (`can_supply`) | ✅ (branch-scoped) | ✅ | ✅ |
| Division Admin (no supply) | ❌ 403 | ❌ | ❌ |
| Super Admin | ❌ (`role:admin` excludes — existing deliberate design) | ❌ | ❌ |
| IT / End User / Guest | ❌ | ❌ | ❌ |

## 3. What Was Already There (discovered during review)

- `SearchPhysicalCountAssetAction` already returned `user_assets` + `scanned_user_id` on asset-ID scans — the **scanner flow** (QR → "Other Assets of {name}" card with per-asset mark buttons) was already working.
- `InventoryAsset::components()` / `parentAsset()` / `AssetSetIntegrityService` exist (parent-child sets) — not needed for this feature but noted.
- `ShowPhysicalCountAction` computes session totals **live** (not snapshot) — progress auto-adjusts when assets are added/removed mid-session.

## 4. Changes Implemented

### 4.1 `app/Actions/PhysicalCount/SearchPhysicalCountAssetAction.php`
- **Person-name search:** text query now also matches `assignedUser.full_name` (previously only asset fields — typing "Maria" found nothing).
- **Custodian group:** when the query matches **exactly one user**, response includes a new backward-compatible field:
  ```json
  "custodian_group": {
      "user_id": 42,
      "full_name": "Maria Santos",
      "total": 5,
      "assets": [ ... assigned-only, scope-checked, no For Disposal/Scrapped, no 20-limit ... ]
  }
  ```
  Multiple user matches → `custodian_group: null` (flat list only, prevents wrong bulk-marking).
- **Scope fix:** the pre-existing `user_assets` query had **no `InventoryScope`** (cross-branch leak for super-admin-context users). Both `user_assets` and `custodian_group` queries are now scope-checked.

### 4.2 `resources/views/inventory/physical-count-show.blade.php`
- Group checklist UI: custodian header + per-asset Present/Missing buttons + **"Mark all as Present (n)"** bulk button.
- `markMany(ids)`: sequential POSTs to the **existing** `/mark` endpoint, `Swal` progress, 422 = skip, **one `location.reload()` at the end** (prevents reload storm), summary ("4 marked, 1 already counted").
- Flat search results show the custodian name per item when several users match the query.

### 4.3 `resources/views/inventory/qr-batch.blade.php`
- **Pagination fix:** replaces single `inventory.data` fetch (first 50/100 only) with a paged loop (`per_page=100` until `last_page`) so the complete asset list is printable. Loading progress shown per page.
- Removed duplicate `data-id` attribute in the row template.

## 5. What Was NOT Changed (verified safe)

- `MarkPhysicalCountAssetAction` — immutability + audit log already correct.
- `QrCodeService`, `/r/{id}` route, `ScanController`, `scan/asset-info` — asset QR flows untouched.
- `ShowPhysicalCountAction` — live totals already correct.
- Route middleware — `role:admin` semantics preserved.

## 6. Verification (phase-by-phase)

### Phase 1-2: Backend group search + Count page UI — ✅ TESTED
`tests/Feature/PhysicalCountGroupTest.php` — **7 passed, 23 assertions**:
- ✓ Unique custodian name match returns group (assigned-only, no For Disposal/Spare/Scrapped)
- ✓ Multiple name matches → no group (flat list only)
- ✓ Group is scoped to actor branch (cross-branch assets excluded)
- ✓ Plain asset search → no group
- ✓ Search requires `canProcessSupply` (end user → 403)
- ✓ Mark-then-remark rejected (422) — bulk skip semantics verified
- ✓ Completed session rejects search (422)
- Blade compiles clean (`view:cache`), zero-width char scan: 0

## 7. Known Limits / Follow-ups

- Custodian group is bounded only by the custodian's assignment count (no artificial limit) — acceptable.
- Search throttle (`throttle:30,1`) applies as before; bulk marking posts sequentially to `/mark` under the same throttle.
- Super Admin access to Physical Count remains closed by design; opening it later is a separate decision (`role:admin,super_admin`).
