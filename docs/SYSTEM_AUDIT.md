# CMMS V.1.8 — Full System Audit Report

**Audited:** 2026-06-30  
**Stack:** Laravel 11 + Blade + Vanilla CSS  
**Environment:** Laragon / Local (Laragon v6+, PHP 8.2, MySQL)  
**Auditor:** Antigravity System Auditor

---

## Executive Summary

> The system is **functionally solid** with a well-structured Laravel architecture, but has notable **security gaps**, **logic inconsistencies**, **UX rough edges**, and **missing features** that must be addressed before production or public deployment.

**Overall Score: 74 / 100 (B-)**

| Category | Score | Grade |
|----------|-------|-------|
| Security | 72/100 | C+ |
| Backend Logic | 78/100 | B- |
| Routes | 80/100 | B |
| Frontend / UX (Desktop) | 82/100 | B |
| Frontend / UX (Mobile) | 61/100 | D+ |
| Database | 75/100 | B- |
| Feature Completeness | 73/100 | C+ |

---

## 1. Architecture Overview

```
Routes (web.php) → Middleware Stack → Controllers → Models + Services → Blade Views
        ↓
Roles:  user | it | admin | supply_officer | super_admin
        ↓
Modules: Auth · Dashboard · ICT Requests · PM Schedules · Inventory
         Requisitions · Physical Count · QR Scan · CSM Survey
         Notifications · Audit Logs
```

### Role Hierarchy

| Role | Scope | Key Permissions |
|------|-------|-----------------|
| `user` | Own records only | Submit ICT requests, view own assets |
| `it` | Assigned tickets only | Update ticket status, sign off PM forms |
| `admin` | Division / office scoped | Review requests, manage personnel, supply (if `can_supply=true`) |
| `supply_officer` | Branch scoped (supply) | Inventory management, requisition approval |
| `super_admin` | Branch scoped (all) | User management, PM schedules, audit logs |

---

## 2. Security Audit

### ✅ What's Done Right

| Area | Finding |
|------|---------|
| CSRF | `@csrf` on all POST forms via Laravel middleware |
| Brute Force | Rate limiter: 5 attempts/email, 20/IP per minute |
| Session | Session regenerated on login, invalidated on logout |
| Deactivated Users | Double-checked in `AuthController::login()` + `EnsureUserIsActive` middleware |
| Security Headers | `X-Frame-Options: DENY`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `HSTS` (on HTTPS) |
| CSP | `Content-Security-Policy` with nonce system via `SecurityHeaders` middleware |
| Password Validation | Requires uppercase + numeric for user creation |
| Scope Guards | `abortIfOutsideOfficeScope()` prevents cross-branch access |
| Role Middleware | Central `RoleMiddleware` handles JSON (401) or redirect for browsers |
| Throttle | Applied on all state-changing routes (30 req/min) |
| Audit Logging | `AuditLog::log()` called on critical operations |
| QR Security | QR IDs are numeric only — no guessable tokens or slugs |
| Asset Ownership | `assetAssignedToUser()` validates before ICT ticket creation |
| Soft Deletes | Users cannot be hard-deleted — protects audit trail |

---

### ❌ Security Gaps

#### GAP-SEC-01 · CSP Contains `unsafe-inline` — HIGH RISK

```
script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com
```

Even though a **nonce** is generated per-request, `'unsafe-inline'` **nullifies nonce protection** in all browsers. Nonces only work when `'unsafe-inline'` is **absent**. All Blade inline scripts already carry `nonce="{{ $cspNonce }}"`, so removing `unsafe-inline` should work immediately.

**Impact:** XSS attacks via injected inline scripts are not blocked by CSP despite the nonce infrastructure.  
**Fix:** Remove `'unsafe-inline'` from `script-src` in `SecurityHeaders.php`.

---

#### GAP-SEC-02 · `.env` Contains Real Credentials — CRITICAL

```dotenv
MAIL_USERNAME=rhodabatolina28@gmail.com
MAIL_PASSWORD=uqigcdyhjpsaicje      # Google App Password — plaintext
DB_USERNAME=root
DB_PASSWORD=                          # Empty root password
APP_KEY=base64:W6bAI6...             # Exposed in version control
```

**Impact:** Anyone with read access to the filesystem reads live credentials. Git history may also have leaked these.  
**Fix:** Set credentials via OS environment variables or a secrets manager (e.g., `.env.production` outside the repo, or server-level `export` statements). Rotate the Google App Password immediately.

---

#### GAP-SEC-03 · `APP_DEBUG=true` — HIGH RISK

Full stack traces, SQL query strings, server paths, and environment variable values are returned in the browser on any unhandled exception.  
**Fix:** Set `APP_DEBUG=false` in every environment that is accessible over a network (ngrok, staging, production).

---

#### GAP-SEC-04 · Plaintext Temp Password Returned in JSON Response — MEDIUM

**File:** `app/Http/Controllers/SuperAdminController.php::resetPassword()`

```php
$tempPassword = Str::random(12);
$user->password = Hash::make($tempPassword);
// ...
return response()->json(['temp_password' => $tempPassword, ...]);
```

The plaintext password is returned in the response body. Any MITM or network logger captures it.  
**Fix:** Send the temp password via email only. Return only `{ success: true, message: "Password reset. Check your email." }`.

---

#### GAP-SEC-05 · `remember_token` Not Rotated on Role/Status Change — MEDIUM

When Super Admin deactivates or changes a user's role, the `sessions` table row is deleted but the `remember_token` is NOT rotated. If the user had checked "Remember Me," they may still be authenticated until the token expires.  
**Fix:** Call `$user->setRememberToken(Str::random(60))` and `$user->save()` after any role/status change.

---

#### GAP-SEC-06 · `SessionTimeout` Middleware Registration Unclear — MEDIUM

**File:** `app/Http/Middleware/SessionTimeout.php` exists but is not visible in the route middleware stack in `web.php`. Verify it is registered in `bootstrap/app.php`. The class also duplicates what Laravel's `SESSION_LIFETIME` config already handles internally.

---

#### GAP-SEC-07 · Free-Text Fields Not Sanitized for JS Rendering — LOW-MEDIUM

Fields like `repairDescription`, `itRemarks`, `asset_notes`, and `specifications` (JSON array) are stored as-is. Blade auto-escapes `{{ }}` output, but if any of these values are passed raw into `<script>` blocks (e.g., `var data = {!! json_encode($asset) !!}`), they must be explicitly escaped.  
**Fix:** Audit all `.blade.php` files for `{!! !!}` usage with user-supplied data.

---

#### GAP-SEC-08 · QR Session Stored Before Asset Existence Validation — LOW

```php
// ScanController.php — guest flow
session(['qr_redirect_asset_id' => (int) $id]);
return redirect()->route('login', ['redirect' => url('/r/' . $id)]);
```

The `$id` is stored in session before confirming the asset exists. A crafted URL like `/r/9999999` will write a bad ID to the session. The asset ownership check downstream would catch invalid use, but the session pollution is unnecessary.  
**Fix:** Validate `InventoryAsset::exists($id)` before writing to session.

---

#### GAP-SEC-09 · Audit Log Column Naming Mismatch — LOW (Technical Debt)

The `audit_logs` table uses a column named `region` to store branch/office **scope context**, not an actual geographic region. This is acknowledged in code comments but remains a maintenance risk.  
**Fix:** Add a future migration to rename `audit_logs.region` to `audit_logs.scope`.

---

#### GAP-SEC-10 · Notification Read Endpoint Throttle Too High — LOW

```php
Route::post('/notifications/{id}/read', ...)->middleware('throttle:60,1');
```

60 read-marks per minute is excessive. Reduce to `throttle:10,1`.

---

## 3. Backend / Logic Audit

### ✅ What's Done Right

| Area | Finding |
|------|---------|
| Role-Based Scoping | Admin = division-scoped, Super Admin = branch-scoped — properly enforced |
| Cascade Logic | Ticket cancel/reject auto-rejects all pending requisitions |
| Asset Status Sync | ICT ticket status changes auto-sync linked asset status via `Request::booted()` |
| PM Anti-Spam | Checks `current_focus_division` before generating new PM cycle |
| Soft Deletes | `Request`, `InventoryAsset`, `User`, `PMSchedule` all use `SoftDeletes` |
| Depreciation | Auto-calculated at 5-year lifecycle from `date_acquired` |
| Warranty | Auto-classified: Active / Expiring Soon (≤30 days) / Expired |
| Form Requests | `StoreICTRequest`, `StoreInventoryRequest`, `StoreMaintenanceRequest` validate all input |
| Observers | `InventoryAssetObserver` registered for asset lifecycle events |
| Dual Notification | In-app + email (queue-based) notification system; PM has dedicated mail template |
| Physical Count | Branch-scoped, ongoing session prevention, counted/missing/damaged tracking |
| QR Sticker | QR code generated per asset, batch printing supported |
| CSM Survey Gate | `RequirePendingSurvey` middleware blocks users until survey is completed |
| Historical Assignment | ICT ticket allows asset used within last 30 days via `InventoryHistory` check |

---

### ❌ Logic Gaps

#### GAP-LOGIC-01 · Only ONE Active PM Schedule Per Branch — DESIGN LIMITATION

```php
$activeSchedule = PMSchedule::active()->first(); // always takes the first
```

The system assumes a single active PM schedule at all times. If multiple schedules exist (e.g., different categories — laptops vs. printers), only the first is used for queue status, IT dashboard, and QR scan context.  
**Impact:** Multiple simultaneous PM campaigns per branch are not supported.

---

#### GAP-LOGIC-02 · `asset_id = 0` as Magic Number for Bundled PMs — CODE SMELL

```php
'asset_id' => 0, // Bundled workstation PM
```

Any query that filters `WHERE asset_id > 0` silently excludes all bundled PM records.  
**Fix:** Use `NULL` instead of `0` for bundled PMs and update queries accordingly.

---

#### GAP-LOGIC-03 · `is_deleted` Flag Coexists With Soft Deletes — REDUNDANCY

The `Request` model uses both `SoftDeletes` (via `deleted_at`) AND an `is_deleted` boolean flag with its own scope:

```php
public function scopeNotDeleted($query) {
    return $query->where('is_deleted', false);
}
```

A record could have `is_deleted=true` but `deleted_at=null` (or vice versa), creating inconsistent results between the two filtering mechanisms.  
**Fix:** Remove `is_deleted` and standardize on `SoftDeletes`.

---

#### GAP-LOGIC-04 · Repair Cost Double-Accumulation Risk

```php
if ($newStatus === self::STATUS_COMPLETED && $repairDetail && $repairDetail->cost) {
    $asset->increment('total_maintenance_cost', $repairDetail->cost);
}
```

This fires inside the `booted()` observer. If a ticket status is set to `Completed` more than once (re-opened and re-completed), the cost accumulates again with no idempotency guard.  
**Fix:** Check whether this specific `$repairDetail->id` was already accumulated before incrementing, or track `cost_accumulated_at` on the repair detail.

---

#### GAP-LOGIC-05 · PM Division Admin Bypass Not Communicated in UI

PM requests auto-set `division_admin_review_status = 'Approved'` on creation. This is intentional but not communicated anywhere in the UI. Admin users may be confused why PM tickets skip their approval queue while ICT tickets do not.  
**Fix:** Add a visible note on the admin request list: _"Preventive Maintenance tickets are pre-approved by the scheduling system."_

---

#### GAP-LOGIC-06 · `scopeActive()` on PMSchedule Does Not Exclude Paused Schedules

```php
public function scopeActive($query) {
    return $query->where('is_active', true);
    // is_paused is NOT checked here
}
```

A paused schedule (`is_paused=true`) is still returned by `PMSchedule::active()`. Downstream callers inconsistently add `->where('is_paused', false)` themselves.  
**Fix:** Update `scopeActive()` to: `->where('is_active', true)->where('is_paused', false)`

---

#### GAP-LOGIC-07 · No Signature Payload Size/Format Validation

```php
'endUserSignature' => 'required|string',
```

The signature is stored as a base64 data URI with no size cap. A crafted payload with a multi-MB base64 string passes validation.  
**Fix:** Add `max:500000` (≈375KB decoded) or use a custom rule to validate the base64 format: `starts_with:data:image/`.

---

#### GAP-LOGIC-08 · `Notification::send()` Does Not Pre-Validate User Existence

```php
public static function send($userId, $requestId, $type, $message) {
    return self::create([...]);
}
```

If `$userId` is for a soft-deleted or non-existent user, the notification row is created but `$notification->user` returns `null`, causing a silent email skip in the `booted()` observer.  
**Fix:** Add `if (!User::find($userId)) return null;` before creating the notification.

---

#### GAP-LOGIC-09 · No Pagination on `getAssets()` — PERFORMANCE RISK

```php
// InventoryController::getAssets()
$assets = $query->orderBy('created_at', 'desc')->get(); // returns ALL matching records
```

All matching inventory assets are returned in a single JSON response with no pagination or limit. For large inventories (500+ assets), this will cause memory exhaustion and slow page loads.  
**Fix:** Switch to `->paginate(50)` or `->limit(200)` with cursor-based pagination on the frontend.

---

#### GAP-LOGIC-10 · PhysicalCountController Tightly Coupled to InventoryController

```php
app(InventoryController::class)->scopeAssetsToActor($allAssets, $user);
```

Cross-controller method calls break the single-responsibility principle. If `scopeAssetsToActor()` changes signature during a refactor, `PhysicalCountController` silently breaks.  
**Fix:** Extract `scopeAssetsToActor()` into a shared `InventoryService` class or a query scope on `InventoryAsset`.

---

#### GAP-LOGIC-11 · `region` Missing From `Request` Model `$fillable`

The `requests` table has a `region` column (referenced in `Notification::booted()` as `$request->region`), but `region` does not appear in `Request::$fillable`. Mass-assignment of `region` is silently blocked — this column may be `null` in all records.  
**Fix:** Add `'region'` to `Request::$fillable`.

---

#### GAP-LOGIC-12 · Archive Logs Uses `sys_get_temp_dir()` — SERVER RISK

```php
$tempPath = tempnam(sys_get_temp_dir(), 'audit_') . '.csv';
```

On shared Windows servers (Laragon, XAMPP), `sys_get_temp_dir()` may be shared across applications or not writable.  
**Fix:** Use `storage_path('app/temp/audit_' . now()->format('YmdHis') . '.csv')` instead.

---

## 4. Routes Audit

### ✅ What's Done Right

- All protected routes wrapped in `auth` + `active` + `require.survey` middleware group
- Critical mutations are throttled (`throttle:30,1` or stricter)
- Static routes defined **before** parameterized routes — documented in comments
- Per-route AND per-group role middleware for granular control
- `where('id', '[0-9]+')` constraints on parameterized routes prevent ID injection

### ❌ Route Gaps

#### GAP-ROUTE-01 · `maintenance.edit` and `maintenance.update` Allow `user` Role

```php
Route::get('/requests/maintenance/{id}/edit', [...])->middleware('role:user,it,admin,super_admin');
Route::put('/requests/maintenance/{id}', [...])->middleware('role:user,it,admin,super_admin');
```

A regular `user` can reach the maintenance edit controller. Authorization is handled internally in the controller (via `RequestAuthorization`), but defense in depth requires route-level restriction.  
**Fix:** Remove `user` from these route middleware lists.

---

#### GAP-ROUTE-02 · PM Assignment Restricted to Super Admin Only — INCONSISTENCY

```php
Route::post('/requests/maintenance/{id}/assign', [...])->middleware('role:super_admin')
```

ICT request assignment allows `admin` to assign IT personnel. PM assignment does not — only `super_admin`. This inconsistency means division admins cannot route PM work even when Super Admin is unavailable.

---

#### GAP-ROUTE-03 · `ict.show` Has No Route-Level Ownership Guard

```
GET /requests/ict/{id}  →  role:user,it,admin,super_admin
```

A `user` could access another user's ticket by guessing the integer `{id}`. Verify that `ICTRequestController::show()` enforces that users can only view their own tickets (or tickets assigned to them if IT/Admin).

---

#### GAP-ROUTE-04 · `pm-schedules.destroy-all` Has No Secondary Confirmation

```php
Route::delete('/pm-schedules', [...])->middleware('throttle:10,1');
```

One HTTP request deletes ALL PM schedules. Rate-limited to 10/min but no confirmation token, double-submit prevention, or two-step verification exists.  
**Fix:** Require a `confirm_token` in the request body that is pre-generated and single-use.

---

#### GAP-ROUTE-05 · No User Edit Route for Admin Role

Admin can `personnel.show` and `personnel.toggle` but there is no `GET /personnel/{id}/edit` or `PUT /personnel/{id}` route. Admins cannot correct data entry errors on users they manage — only Super Admin can create/update users.

---

#### GAP-ROUTE-06 · Inventory Export Has No Rate Limit

```php
Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
```

Export endpoints perform full table dumps. Without throttling, this can be hammered repeatedly.  
**Fix:** Add `->middleware('throttle:5,1')`.

---

## 5. Frontend / UX Audit

### ✅ What's Done Right

- Premium animations: `fadeInSlide`, hover effects, `cubic-bezier` transitions on stat cards
- Sidebar collapse persisted in `localStorage` — no flash on reload
- Mobile close button and `#sidebarBackdrop` for overlay sidebar
- Real-time clock in topbar
- SweetAlert2 for destructive action confirmations
- Status pills with consistent, semantic color coding across all views
- Notification bell with badge counter and live dropdown
- `clamp()` used for fluid typography and spacing
- CSP nonce correctly applied to all inline `<style>` and `<script>` blocks
- Pagination styled and fixed with `!important` overrides
- `auto-fit minmax` CSS Grid for responsive stat cards

### ❌ UX / Frontend Gaps

#### GAP-UX-01 · 40KB of Inline CSS in Main Layout — PERFORMANCE

`app.blade.php` (40,585 bytes) contains hundreds of lines of inline `<style>` blocks. This:
- Prevents browser caching of styles
- Increases HTML payload on every request
- Makes CSS changes harder to find and test  

**Fix:** Move all layout-level styles to `admin.css` (already compiled by Vite). Use `nonce` on the `<link>` tag if CSP requires it.

---

#### GAP-UX-02 · No AJAX Loading States

All AJAX calls (notification fetch, inventory search, status quick-update) have no loading indicator. Users have no feedback during wait time and may click multiple times, triggering duplicate requests.  
**Fix:** Add a spinner or skeleton loader on AJAX initiation; disable the trigger button during the call.

---

#### GAP-UX-03 · ICT Request Form is 83KB — MAINTAINABILITY

`resources/views/requests/ict/form.blade.php` is **83,236 bytes** — a monolithic Blade file handling both create and edit. Heavy to load, hard to maintain.  
**Fix:** Split into `create.blade.php` and `edit.blade.php` using `@include` partials for shared sections.

---

#### GAP-UX-04 · Inventory Index is 74KB — MAINTAINABILITY

`resources/views/inventory/index.blade.php` is **74,641 bytes** with embedded JS, CSS, and multiple modal definitions.  
**Fix:** Extract modals into `@include`-d partials. Move embedded JS to a compiled asset file.

---

#### GAP-UX-05 · No "Forgot Password" Self-Service Flow

Users who forget their password must contact the Super Admin. In an institutional setting this is a significant support bottleneck.  
**Fix:** Implement Laravel's built-in Password Broker (`php artisan make:controller Auth/PasswordResetController`).

---

#### GAP-UX-06 · Flash Messages Disappear on Refresh

`->with('success', ...)` flash messages display once. On page refresh they vanish — no persistent toast for critical actions like password reset or user creation.  
**Fix:** Use SweetAlert2 `fired on DOM load` pattern for critical success states, or store in `localStorage` for one-time display.

---

#### GAP-UX-07 · Signature Pad Has No "Required" Visual Indicator

`endUserSignature` is `required` in backend validation but the signature canvas likely shows no asterisk or "Required" label in the UI. Users submit without signing, receive a generic validation error, and are confused.  
**Fix:** Add a red asterisk and `aria-required="true"` on the signature canvas wrapper.

---

#### GAP-UX-08 · Super Admin Sidebar Label "My Parts Requests" is Misleading

```html
<a href="{{ route('requisitions.index') }}">My Parts Requests</a>
```

Super Admin sees ALL branch-wide requisitions — not just "their" parts. The label implies personal ownership.  
**Fix:** Change label to **"Requisitions"** or **"Parts Requests"** for the `super_admin` role.

---

#### GAP-UX-09 · QR Error Pages Are Raw PHP HTML Strings

```php
return response("
    <div style='font-family:Arial;text-align:center;...'>
        <h2 style='color:#dc2626;'>Asset Not Assigned</h2>
        ...
    </div>
");
```

These error responses lack the app layout, are not mobile-optimized, and cannot be updated without editing PHP.  
**Fix:** Create `resources/views/scan/error.blade.php` and return `view('scan.error', ['message' => '...'])`.

---

#### GAP-UX-10 · No Empty State Illustrations

Empty states (no requests, no assets, no notifications) display plain text only. Modern UX requires an illustration/icon with a contextual CTA button.

---

#### GAP-UX-11 · Mobile Sidebar May Conflict With iOS Safari Scroll Lock

The fixed overlay sidebar uses `opacity` + `visibility` CSS transitions. iOS Safari has known issues with `overflow: hidden` on `<body>` during overlay menus. Needs device testing on iPhone Safari.

---

#### GAP-UX-12 · Physical Count: No Undo for Incorrect Scan

Once an asset is marked Present / Missing / Damaged in a physical count session, there is no undo button. Incorrect scans require manual database intervention.  
**Fix:** Add a `DELETE /physical-count/{sessionId}/counts/{assetId}` route and an "Undo" button visible for 30 seconds after each scan.

---

## 6. Database Audit

### ✅ What's Done Right

- 47 migration files with well-organized, incremental changes
- Performance indexes added in `2026_06_09_084622_add_performance_indexes.php`
- Soft deletes on all critical tables
- `specifications` JSON column properly cast to `array` in model
- QR code column on assets, generated and stored via `QrCodeService`
- Physical count tables properly normalized
- `pm_schedules` table supports pause/resume cycle control

### ❌ Database Gaps

#### GAP-DB-01 · Duplicate `users` Table Migrations

```
0001_01_01_000000_create_users_table.php  ← Laravel framework default
2024_01_01_000001_create_users_table.php  ← Custom CMMS version
```

Two migrations create the `users` table. On a fresh `php artisan migrate`, the second will fail with "Table already exists." On existing databases it is silently skipped.  
**Fix:** Delete `2024_01_01_000001_create_users_table.php` if the custom columns were added to the framework default, or vice versa.

---

#### GAP-DB-02 · `division` in `Request::$fillable` May Not Exist as a Column

The `Request` model `$fillable` includes `'division'` but the original migration and subsequent scoping uses `office` for division-level filtering. Verify the column exists:

```sql
SHOW COLUMNS FROM requests LIKE 'division';
```

---

#### GAP-DB-03 · `inventory_assets.status` Changed From ENUM to String — No App Guard

Migration `2026_06_09_120000_change_asset_status_enum_to_string.php` removed database-level validation. Now any arbitrary string can be stored as `status`.  
**Fix:** Add a `StoreInventoryRequest` validation rule: `'status' => 'in:Active,Spare,For Repair,For Disposal,Scrapped,Defective'`.

---

#### GAP-DB-04 · No Composite Index on `notifications(user_id, is_read)`

The notification bell fetches unread notifications per user on every page load. Without a composite index this query performs a full table scan as data grows.  
**Fix:**

```php
// New migration
$table->index(['user_id', 'is_read'], 'notif_user_unread_idx');
```

---

#### GAP-DB-05 · `audit_logs.region` Stores Branch Info — Naming Mismatch

Explicitly documented in code comments but still a maintenance risk. Plan a rename migration.

---

#### GAP-DB-06 · No Soft Deletes on `notifications` Table

Notifications are hard-deleted, inconsistent with the rest of the system. Old notifications cannot be recovered for audit purposes.  
**Fix:** Add `$table->softDeletes()` to the notifications migration via a new migration file.

---

## 7. Mobile vs. Desktop Rating

### Desktop — 8.2 / 10

| Criterion | Score | Notes |
|-----------|-------|-------|
| Visual Design | 9 | Premium gradients, hover effects, animations |
| Layout Consistency | 8 | Inline CSS sprawl causes minor inconsistencies |
| Navigation | 8 | Role-based sidebar links are clean |
| Table Readability | 7 | Wide tables scroll horizontally on 13" laptops |
| Dashboard Widgets | 9 | Stat cards, warranty alerts, unassigned jobs |
| Form UX | 7 | ICT form is long but guided; signature pad works |
| Loading States | 5 | No spinners on AJAX calls |
| Error Feedback | 7 | Flash messages disappear on refresh |
| PDF / Print | 8 | DomPDF integration functional |
| Accessibility | 6 | Missing ARIA labels; no skip-navigation link |

---

### Mobile — 6.1 / 10

| Criterion | Score | Notes |
|-----------|-------|-------|
| Responsive Layout | 7 | `mobile-responsive.css` loaded; basic responsiveness |
| Sidebar Behavior | 7 | Close button + backdrop — functional |
| Table Layout | 4 | Tables overflow; no card-based mobile fallback |
| Forms | 5 | ICT form is very long; signature pad too small on phones |
| Touch Targets | 6 | Most buttons are adequate; some nav links are tight |
| Stats Dashboard | 8 | `auto-fit minmax` grid adapts well |
| QR Scan Flow | 7 | Designed for mobile; scan page is clean |
| Font Sizes | 7 | `clamp()` used — readable on most devices |
| Notification Dropdown | 5 | Dropdown may overflow viewport on narrow screens |
| Physical Count | 6 | Search-and-mark works but not one-hand optimized |

---

## 8. Missing Features (Functional Gaps)

| # | Feature | Priority | Impact |
|---|---------|----------|--------|
| 1 | **Forgot Password / Self-Service Reset** | HIGH | Users locked out without Super Admin |
| 2 | **User Profile Edit by Admin** | HIGH | Admin cannot fix data entry errors |
| 3 | **Formal Asset Transfer Workflow** | HIGH | No transfer form — only history logs |
| 4 | **Mobile-Friendly Tables (Card View)** | HIGH | Tables break on phones |
| 5 | **AJAX Loading States** | MEDIUM | Poor perceived performance |
| 6 | **Requisition "Issued" Workflow** | MEDIUM | `STATUS_ISSUED` in model but no UI |
| 7 | **Multiple Active PM Schedules** | MEDIUM | Complex deployments unsupported |
| 8 | **Undo / Edit Physical Count Scan** | MEDIUM | No recovery from wrong scan |
| 9 | **Asset Depreciation Report** | MEDIUM | Flag exists but no export/report |
| 10 | **Role Change Without Re-login** | MEDIUM | Role change needs user session refresh |
| 11 | **Notification Pagination / "See All"** | LOW | Only most recent shown in dropdown |
| 12 | **Date Range Filters on Reports** | LOW | Exports lack filtering |
| 13 | **Dark Mode** | LOW | CSS variables ready but no toggle |
| 14 | **Offline / PWA Support** | LOW | No service worker |
| 15 | **Bulk Request Status Update** | LOW | Admin must open each ticket individually |

---

## 9. Summary Scorecard

| Category | Score | Grade | Key Issue |
|----------|-------|-------|-----------|
| Security | 72 | C+ | `unsafe-inline` in CSP, debug mode on, exposed `.env` credentials |
| Backend Logic | 78 | B- | Solid core; `is_deleted` redundancy, missing pagination, magic `0` |
| Routes | 80 | B | Good structure; user on maintenance.edit, no export throttle |
| Frontend Desktop | 82 | B | Premium design; no AJAX spinners, accessibility gaps |
| Frontend Mobile | 61 | D+ | Tables overflow, ICT form too long, notification dropdown overflows |
| Database | 75 | B- | Good migrations; needs notification index, duplicate migration fix |
| Features | 73 | C+ | Core flows work; forgot password and asset transfer missing |
| **OVERALL** | **74** | **B-** | |

---

## 10. Priority Recommendations

### 🔴 Critical — Fix Before Any External Access

1. Set `APP_DEBUG=false` in all shared/ngrok/staging environments
2. Move credentials out of `.env` — use OS environment variables or a secrets manager
3. Remove `'unsafe-inline'` from `Content-Security-Policy` `script-src` (nonces already applied)
4. Return temp password via **email only** — remove it from JSON response body

### 🟠 High — Fix Before Production Launch

5. Add self-service password reset (Laravel Password Broker)
6. Add AJAX loading spinners on all async operations
7. Implement card-based table layout for mobile views
8. Replace `ScanController` raw HTML error responses with Blade views
9. Add composite index on `notifications(user_id, is_read)`
10. Update `PMSchedule::scopeActive()` to filter `is_paused = false`

### 🟡 Medium — Next Development Sprint

11. Extract `scopeAssetsToActor()` into `InventoryService`
12. Add `throttle:5,1` to inventory export route
13. Remove `is_deleted` flag — standardize on `SoftDeletes`
14. Add `GET /personnel/{id}/edit` + `PUT /personnel/{id}` routes for Admin
15. Add UI note documenting PM auto-approval bypass
16. Add `'region'` to `Request::$fillable`
17. Fix or consolidate duplicate `users` table migrations

### 🟢 Low — Future Roadmap

18. Paginate `getAssets()` JSON endpoint (`->paginate(50)`)
19. Add empty state illustrations with CTAs
20. Dark mode CSS toggle using existing CSS variables
21. Formal asset transfer request workflow
22. Bulk status update for Admin (checkbox + bulk action dropdown)
23. Date range filters on all export/report views
24. Soft deletes on `notifications` table

---

## Appendix — File Size Reference

| File | Size | Notes |
|------|------|-------|
| `resources/views/requests/ict/form.blade.php` | 83 KB | Monolithic — split into partials |
| `resources/views/inventory/index.blade.php` | 75 KB | Monolithic — split into partials |
| `resources/views/layouts/app.blade.php` | 41 KB | Inline CSS — move to `admin.css` |
| `resources/views/inventory/detail.blade.php` | 50 KB | Large but acceptable |
| `app/Http/Controllers/ICTRequestController.php` | 65 KB | Very large controller — consider splitting |
| `app/Http/Controllers/MaintenanceController.php` | 46 KB | Large controller |
| `app/Http/Controllers/InventoryController.php` | 41 KB | Large controller |
| `app/Support/RequestAuthorization.php` | 29 KB | Utility class — acceptable |

---

## Appendix — GAP Reference Index

| GAP ID | Category | Severity | Title |
|--------|----------|----------|-------|
| GAP-SEC-01 | Security | HIGH | CSP `unsafe-inline` nullifies nonce |
| GAP-SEC-02 | Security | CRITICAL | `.env` real credentials exposed |
| GAP-SEC-03 | Security | HIGH | `APP_DEBUG=true` |
| GAP-SEC-04 | Security | MEDIUM | Temp password in JSON response |
| GAP-SEC-05 | Security | MEDIUM | `remember_token` not rotated on change |
| GAP-SEC-06 | Security | MEDIUM | SessionTimeout middleware may not be registered |
| GAP-SEC-07 | Security | LOW-MEDIUM | Free-text fields in JS rendering |
| GAP-SEC-08 | Security | LOW | QR session written before asset exists |
| GAP-SEC-09 | Security | LOW | Audit log `region` column mismatch |
| GAP-SEC-10 | Security | LOW | Notification read throttle too high |
| GAP-LOGIC-01 | Logic | MEDIUM | Only 1 active PM schedule per branch |
| GAP-LOGIC-02 | Logic | LOW | `asset_id = 0` magic number |
| GAP-LOGIC-03 | Logic | MEDIUM | `is_deleted` redundant with SoftDeletes |
| GAP-LOGIC-04 | Logic | MEDIUM | Repair cost double-accumulation risk |
| GAP-LOGIC-05 | Logic | LOW | PM bypass not documented in UI |
| GAP-LOGIC-06 | Logic | MEDIUM | `scopeActive()` ignores `is_paused` |
| GAP-LOGIC-07 | Logic | LOW | No signature payload validation |
| GAP-LOGIC-08 | Logic | LOW | `Notification::send()` no user guard |
| GAP-LOGIC-09 | Logic | HIGH | No pagination on `getAssets()` |
| GAP-LOGIC-10 | Logic | MEDIUM | Cross-controller tight coupling |
| GAP-LOGIC-11 | Logic | MEDIUM | `region` missing from `Request::$fillable` |
| GAP-LOGIC-12 | Logic | LOW | Archive logs uses `sys_get_temp_dir()` |
| GAP-ROUTE-01 | Routes | MEDIUM | `maintenance.edit` allows `user` role |
| GAP-ROUTE-02 | Routes | LOW | PM assign restricted to super_admin only |
| GAP-ROUTE-03 | Routes | MEDIUM | `ict.show` no route-level ownership guard |
| GAP-ROUTE-04 | Routes | HIGH | `destroy-all` PM schedules — no 2FA |
| GAP-ROUTE-05 | Routes | MEDIUM | No user edit route for Admin |
| GAP-ROUTE-06 | Routes | LOW | Inventory export no throttle |
| GAP-UX-01 | Frontend | MEDIUM | 40KB inline CSS in layout |
| GAP-UX-02 | Frontend | HIGH | No AJAX loading states |
| GAP-UX-03 | Frontend | LOW | ICT form 83KB monolith |
| GAP-UX-04 | Frontend | LOW | Inventory index 74KB monolith |
| GAP-UX-05 | Frontend | HIGH | No forgot password flow |
| GAP-UX-06 | Frontend | LOW | Flash messages disappear on refresh |
| GAP-UX-07 | Frontend | LOW | Signature pad no required indicator |
| GAP-UX-08 | Frontend | LOW | "My Parts Requests" misleading label |
| GAP-UX-09 | Frontend | MEDIUM | QR errors are raw PHP HTML strings |
| GAP-UX-10 | Frontend | LOW | No empty state illustrations |
| GAP-UX-11 | Frontend | LOW | iOS Safari scroll lock risk |
| GAP-UX-12 | Frontend | MEDIUM | No undo on physical count scan |
| GAP-DB-01 | Database | MEDIUM | Duplicate users table migrations |
| GAP-DB-02 | Database | LOW | `division` column may not exist |
| GAP-DB-03 | Database | MEDIUM | Asset status no DB-level ENUM guard |
| GAP-DB-04 | Database | HIGH | Missing `notifications` composite index |
| GAP-DB-05 | Database | LOW | `audit_logs.region` naming mismatch |
| GAP-DB-06 | Database | LOW | No soft deletes on notifications |

---

*Generated by Antigravity System Auditor · CMMS V.1.8 · 2026-06-30*
