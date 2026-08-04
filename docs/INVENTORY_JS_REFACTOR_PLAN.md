
# Inventory JavaScript Behavior-Preserving Refactor Plan

## Zero-Risk Execution Checklist

This is the required implementation order. Do not start a later step until the current step has passed all verification gates and has its own micro-commit.

### Step 0 ? Capture the live baseline (no source change)

Use the same role, test data and browser session before and after a future extraction. Record:

1. Initial inventory load, row order, summary counts and pagination.
2. Search debounce; category and status filtering; export URL.
3. Add asset, edit asset, category-specific specifications, assigned/unassigned status behavior, server validation error and successful save.
4. Lifecycle history display; transfer to another user; attempted transfer to the current user; transfer to stock.
5. CSV preview, cancellation, invalid preview and successful import.
6. For each write operation, capture the browser Network request and compare the relevant database record and lifecycle/audit result.

The comparison point is the current working application. This step deliberately does not ?correct? observed legacy behavior.

### Step 1 ? Extract immutable lookup data only

Allowed code: only the division lookup data and the branch lookup data.

Required constraints:

- Copy every existing map key and value verbatim.
- Keep \`getDivisionAbbr()\` behavior, including the null/empty fallback, unchanged.
- Keep \`window.getDivisionAbbr = getDivisionAbbr\` in the Vite entry.
- Do not move listeners, state, DOM access, fetch calls or rendered HTML.

Verification gate:

- Existing list renders the same division abbreviation and branch options.
- \`typeof window.getDivisionAbbr === 'function'\`.
- Run \`php artisan test\`, \`php artisan route:list\`, and the available Vite build command.
- Browser-check the Inventory page, then create one isolated commit.

### Step 2 ? Extract modal branch and custodian helpers only

Allowed code: \`updateModalBranchDropdown()\` and \`fetchFilteredUsers()\` as one literal pair.

Required constraints:

- Preserve literal \`/inventory/users\` URL, current query keys, current headers and \`credentials: "include"\`.
- Preserve option labels, current-value restore, one-branch auto-selection, error handling and async timing.
- Keep \`window.updateModalBranchDropdown = updateModalBranchDropdown\` in the entry.
- Do not alter the existing inline \`onchange\` behavior in Blade.

Verification gate:

- Add Asset: open modal, select each applicable region/branch/department/office value and compare users/order to baseline.
- Edit Asset: verify the existing assigned user remains selectable and selected.
- Compare the GET request URL and response handling with Step 0 evidence.
- Run automated checks, browser-check, then create one isolated commit.

### Step 3 ? Optional history modal relocation

Allowed code: \`viewAssetHistory()\` and \`closeHistoryModal()\` together.

Required constraints:

- Copy the existing request prefix, response field reads, date conversion, generated HTML and error/empty states literally.
- Keep both existing window exports in the entry.
- Do not modify history routes, lifecycle records, authorization or modal IDs.

Verification gate:

- Verify populated history, empty history and failed request behavior.
- Compare displayed actor, action, old/new user, old/new status, remarks and timestamps with baseline.
- Run automated checks, browser-check, then create one isolated commit.

### Explicit stop point after Step 3

Do not proceed automatically to the remaining code. Review the current diff and baseline again before proposing any next extraction.

The following areas remain high-risk and should stay in \`inventory.js\` unless separately approved with a new exact plan:

- \`saveAsset()\`, \`editAsset()\`, category/specification display and serialization;
- \`openTransferModal()\` and \`saveTransfer()\`;
- \`loadInventory()\`, \`renderInventoryTable()\`, summary and pagination;
- CSV preview/commit;
- all live-state migration and activation of \`resources/js/inventory/state.js\`;
- the final \`window.*\` export block and global event listeners.

### Mandatory stop conditions

Stop immediately and restore the literal original code in the same small change if any check finds:

- a changed route, endpoint, request key, HTTP method, header, body, response field or redirect;
- a missing/renamed \`window.*\` function, inline handler, Blade variable or DOM ID;
- changed status, validation, authorization, lifecycle/audit, pagination, ordering, notification, SweetAlert message or UI sequence;
- a missing selected custodian, changed dropdown ordering or a different async result;
- a changed database record, history/audit record or CSV import result;
- a Vite runtime/import error or browser-console error.

No optimization is permitted while executing this checklist. Any desired cleanup belongs to a separate, later behavior-change review.

## Review scope and analysis

## Purpose and decision

This is a read-only deep review of [resources/js/inventory.js](../resources/js/inventory.js). No JavaScript, Blade, routes, controller, request, action, database, or CSS file was changed for this review.

The file is 1,258 lines and is the Vite entry point for the Inventory Masterlist. A full module split is **not safe as one change**. It is safe to plan small literal relocations only when every contract below is retained byte-for-byte in effect and manually verified after each file/method move.

This plan follows the project refactor rules:

- relocation, not rewrite;
- copy-paste existing branches, payloads, messages, request keys, and side effects exactly;
- no change to routes, API URLs, HTTP methods, validation, authorization, query/pagination/order, asset-status behavior, audit/lifecycle behavior, redirects, alerts, or markup behavior;
- one extraction at a time, test and browser-check it, then make a micro-commit;
- if a dependency cannot be passed unchanged, leave that code in \`inventory.js\`.

## Actual system integration

| Contract | Current source of truth | Must remain unchanged |
|---|---|---|
| Vite entry | \`vite.config.js\` and \`@vite(['resources/js/inventory.js'])\` in \`resources/views/inventory/index.blade.php\` | Entry path and load order |
| List endpoint | \`window.CMMS_INVENTORY_DATA_URL\`, injected by the Blade view | Admin uses \`/inventory/data\`; Super Admin uses \`/super-admin/inventory/data\` |
| Detail/export/history prefixes | \`CMMS_INVENTORY_DETAIL_PREFIX\` and \`CMMS_RECEIPT_PREFIX\` in the Blade view | Existing prefixes, URLs and hashes |
| Permissions/UI mode | \`CMMS_INVENTORY_CAN_WRITE\`, \`CMMS_IS_SUPPLY_ADMIN\`, \`CMMS_IS_SUPER_ADMIN_VIEW\` | Read-only and write UI behavior |
| Server behavior | existing Inventory controller, actions, Form Requests, routes | Do not alter the already-existing authorization, validation, ordering, scope, pagination, lifecycle history or asset handling |
| UI structure | \`inventory/index.blade.php\` plus \`_modal_asset\`, \`_modal_transfer\`, \`_modal_history\` partials | Every current DOM ID, inline handler and modal behavior |

The server already provides its own behavior through actions and Form Requests. This plan does not recommend refactoring it as part of the JavaScript work.

## Non-negotiable browser contract

The rendered table and pagination use inline handlers. The Blade script also subscribes to exported functions after the page \`load\` event. Therefore this global API is public and must remain present with the same names and callable signatures:

\`\`\`
getDivisionAbbr
exportFilteredInventory
updateInventorySummary
renderInventoryTable
toggleDropdown
closeAllDropdowns
confirmDeleteAsset
filterInventory
onFilterChange
renderPagination
goToPage
toggleSpecsForm
itPartTypeChange
toggleNetworkDeviceSpecs
setInputVal
openAddAssetModal
closeAssetModal
closeHistoryModal
openDisposalFromList
openTransferModal
closeTransferModal
saveTransfer
commitImport
saveAsset
loadInventory
editAsset
viewAssetHistory
updateModalBranchDropdown
\`\`\`

A previous Vite compatibility fix explicitly restored \`window.*\` exports. Do not replace inline handlers with event delegation, rename any exported function, or move the window assignment until a later separately approved UI change. During each extraction the main entry must still make the same \`window.functionName = functionName\` assignment.

## State contract

Current live state is module-local in \`inventory.js\`:

\`\`\`
allAssets
assetLookup
allUsers
currentInventoryImportToken
currentPage
lastPage
perPage = 50
filterChangeTimer
\`\`\`

There is an existing \`resources/js/inventory/state.js\` object, but it is currently not imported by \`inventory.js\` and is not live application state. Do **not** wire it in, delete it, or replace local variables with it as an initial cleanup. Replacing primitives such as \`currentPage\` and \`lastPage\` with object properties can subtly change reads/writes across extracted functions.

The following functions rely on shared live state and must either stay together or receive an explicitly identical state reference in the same extraction:

| State | Consumers |
|---|---|
| \`allAssets\`, \`assetLookup\` | list loading, table actions, edit, transfer, delete, summary |
| \`currentPage\`, \`lastPage\`, \`perPage\` | list loading and pagination |
| \`filterChangeTimer\` | delayed search |
| \`currentInventoryImportToken\` | preview then commit CSV workflow |

## Exact HTTP and response contracts

No endpoint, request key, URL prefix, fetch option, alert, or error branch may be altered while relocating code.

| Feature | Existing request contract |
|---|---|
| Inventory list | GET \`CMMS_INVENTORY_DATA_URL\` (default \`/inventory/data\`), \`search\`, \`category\`, \`status\`, \`page\`, \`per_page=50\`; expects \`success, assets, total, current_page, last_page, stats\` |
| Export | browser redirect to \`CMMS_INVENTORY_DETAIL_PREFIX + '/export'\` with current \`search/category/status\` |
| Custodian lookup | GET literal \`/inventory/users\` with existing region/branch/department/office params or transfer branch param; expects \`success, users\` |
| Create asset | POST literal \`/inventory\`, JSON payload and current CSRF/AJAX headers |
| Update asset | PUT literal \`/inventory/{id}\`, JSON payload and current CSRF/AJAX headers |
| Delete action | DELETE literal \`/inventory/{id}\`; retain its existing server response and SweetAlert behavior |
| History | GET \`CMMS_INVENTORY_DETAIL_PREFIX + '/{assetId}/history'\`; expects \`success, history\` |
| Detail/disposal navigation | existing detail prefix and \`#disposal\` hash |
| CSV preview | POST literal \`/inventory/import/preview\`, existing \`FormData\` keys \`file\` and \`_token\` |
| CSV commit | POST literal \`/inventory/import/commit\`, JSON \`{ token: currentInventoryImportToken }\` |

Important: some literal \`/inventory/...\` calls remain even when the read-only Super Admin page supplies a super-admin data/detail prefix. That is current behavior. It may look inconsistent, but changing it is a behavior/authorization/routing change and is outside this refactor.

## DOM and event-order contract

The JS depends on IDs from the Inventory Blade view and modal partials, including:

- list and filters: \`inventoryTableBody\`, \`inventoryPagination\`, \`searchInventoryInput\`, \`filterAssetRegion\`, \`filterAssetDivision\`, \`filterAssetDepartment\`, \`filterAssetCategory\`, \`filterAssetStatus\`;
- summary: \`statTotal\`, \`statActive\`, \`statSpare\`, \`statRepair\`, \`statDisposal\`;
- asset form and specification fields: \`assetForm\`, \`assetId\`, \`assetCategory\`, \`assetAssignedUser\`, \`assetStatus\`, \`assetModal\`, and every current \`spec*\` field;
- modals: \`assetHistoryModal\`, \`historyContent\`, \`transferModal\`, \`transferForm\`, \`transferAssetId\`, \`transferAssignedUser\`, \`transferSaveBtn\`;
- CSV import: \`importCsvBtn\` and \`inventoryCsvInput\`.

There are three global listener blocks: initial inventory boot on \`DOMContentLoaded\`, CSV import boot on \`DOMContentLoaded\`, and outside-click modal close. There is also a document Escape listener. Keep their registration timing and effective order identical. Do not merge them merely to make the file shorter.

The current region/division/department filter listeners call \`loadInventory(1)\`, while the current request builder sends only search/category/status. This may appear redundant, but it is existing UI behavior; documenting it does not authorize a fix.

## Feature map and extraction risk

| Area | Current functions | Risk | Refactor position |
|---|---|---:|---|
| Pure lookup data | \`getDivisionAbbr\`, \`INVENTORY_BRANCH_MAP\` | Low only if exports/import binding are retained | First possible move |
| Modal branch/users | \`updateModalBranchDropdown\`, \`fetchFilteredUsers\` | Medium: DOM IDs, URL/query names, async selected-user restore | After lookup data |
| Initial listeners | first \`DOMContentLoaded\` callback and Escape handler | High: order and duplicate listeners | Keep in entry initially |
| List/filter/export | \`loadInventory\`, \`exportFilteredInventory\`, summary, table, pagination | High: live state, injected URLs, inline HTML handlers and pagination | Later, one coherent literal slice |
| Asset modal/specification form | toggle/set/open/edit/save functions | Very high: status changes, serialized specification structure, validation payload, modal order | Keep until characterization tests exist |
| History/disposal | history open/close and disposal navigation | Medium: rendered response HTML and detail prefix | Later isolated move |
| Transfer | open/close/save transfer | Very high: asset status preservation, update payload, lifecycle/audit side effect | Keep together; do not alter |
| CSV import | second \`DOMContentLoaded\` block and \`commitImport\` | High: preview token lifecycle, FormData and confirmation flow | Move only as one unit after tests |
| Window exports | final \`window.*\` assignments | Critical | Keep in main entry permanently for this phase |

## Safe, small relocation sequence

Each item is a separate pull-sized change. Copy the exact existing code; do not improve naming, promises, optional chaining, formatting, error handling, or conditions.

1. **Baseline only ? no source change.** Capture current network requests, UI results and database records for a list/filter, asset update, transfer and CSV preview. Run \`php artisan test\` and \`php artisan route:list\`.

2. **Extract immutable lookup data only.** Move the division lookup map and branch map to \`resources/js/inventory/config.js\`; export them, import them into \`inventory.js\`, and retain the exact \`getDivisionAbbr\` function plus its existing \`window.getDivisionAbbr\` export in the entry. Do not alter map entries or fallback values.

3. **Extract organization-user modal helpers.** Move only \`updateModalBranchDropdown\` and \`fetchFilteredUsers\` to a module that receives/uses the same imported map. Keep request URLs, param names, select option text, current-value restore, headers and catch behavior byte-for-byte in effect. The entry continues to export \`window.updateModalBranchDropdown\`.

4. **Stop and verify.** Test add asset, edit asset with existing custodian, branch/office/department selection, and user list ordering. Compare the GET URL/query and dropdown values with baseline. Commit only if identical.

5. **History module, only if needed.** Move \`viewAssetHistory\` and \`closeHistoryModal\` together, preserving rendered HTML strings and response field access. Retain both window exports in entry. Test empty history, populated history and failed history request.

6. **Do not split state yet.** Do not activate the pre-existing \`state.js\` during Steps 2?5. A later approved change may relocate the entire list/pagination group around a single shared state object, but not one local variable at a time.

7. **CSV workflow, only as one literal unit.** Move the second \`DOMContentLoaded\` registration plus \`commitImport\` together. Preserve FormData, preview HTML, token reset timing, confirmation callback, throttled endpoint calls, and \`window.commitImport\`. Verify preview-cancel, preview-confirm, validation-error and successful commit with before/after database comparison.

8. **Leave high-risk workflows in the entry.** Asset specification/save and transfer logic contain status and lifecycle-sensitive behavior. They remain in \`inventory.js\` unless an isolated extraction can be proven identical using the verification matrix below.

## Explicitly unsafe changes

Do not do any of the following under this refactor:

- switch to a framework, Alpine, React, event delegation, or a new fetch wrapper;
- change \`onclick\`, \`oninput\`, DOM IDs, Blade variable names or load timing;
- convert promises to \`async/await\`, simplify conditions, or consolidate listeners;
- change \`assetLookup\` rebuilding behavior, \`perPage\`, pagination boundaries, asset order, or summary calculation;
- change the current selected-user, Active/Spare, Defective/Scrapped/For Repair, Scrapped, For Disposal, or transfer behavior;
- change JSON/FormData payload keys, empty-string/null behavior, URLs, headers, credentials, response fields, SweetAlert text, redirects, or error handling;
- change any existing controller/action/request/policy/route to accommodate a JavaScript module;
- ?fix? the existing literal endpoint/prefix behavior or currently unused state file as part of a relocation.

## Verification matrix per extraction

Before and after each micro-change, verify the same logged-in role and data:

| Check | Required evidence |
|---|---|
| Build/syntax | \`npm run build\` if the project build is available; no Vite resolution error |
| Laravel safety | \`php artisan test\` and \`php artisan route:list\` |
| Global API | Browser console confirms every required \`window.*\` function exists |
| Network contract | Compare method, URL, query string, request body, headers and JSON structure against baseline |
| List | initial load, search debounce, category/status filters, pagination and export |
| Permissions | Supply/admin writable screen and Super Admin read-only screen retain existing controls |
| Asset modal | add/edit, each category/specification panel, assigned/unassigned status behavior, validation failure and success |
| Lifecycle | history view, transfer same-custodian warning, transfer status preservation, resulting history record |
| CSV | file selection, preview, cancel, invalid file, confirmed commit and token lifecycle |
| Database | For create/update/transfer/import, compare saved columns and lifecycle/audit records exactly with baseline |

Manual browser verification is mandatory before moving to the next extraction. After it passes, commit only that extraction with an isolated Git diff.

## Final safety conclusion

A module refactor is feasible, but only in the staged order above. The first safe candidate is immutable lookup data; the safest second candidate is the modal user-fetch pair. The central list, asset save/specification, transfer and CSV workflows are not safe to ?clean up? or rewrite. They must remain literal relocations with all existing context, state, UI strings and server contracts preserved.

If any extraction requires a different payload, a renamed global, a changed event sequence, or a new state-access pattern, stop and keep the original code in \`resources/js/inventory.js\`.

