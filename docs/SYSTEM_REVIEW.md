# 🔍 CMMS System Deep Review
**Date:** July 19, 2026  
**System:** Computerized Maintenance Management System (Laravel)  
**Context:** Government ICT Asset & Maintenance Management — NCMB  
**Version:** Post-Refactor (V1.8+)

---

## 📊 Overall Ratings

| Category | Score | Status |
|---|---|---|
| Architecture | 8/10 | ✅ Clean, well-structured |
| Security | 6/10 | ⚠️ Critical issues found |
| Routes | 7/10 | ✅ Mostly correct |
| Backend Code | 8/10 | ✅ Good Laravel practice |
| Frontend | 6/10 | ⚠️ CSP gaps, no audit |
| Government Compliance | 6/10 | ⚠️ Key gaps remain |
| Laravel Best Practice | 7/10 | ✅ Good overall |

---

## 🚨 CRITICAL Security Issues

### 1. Gmail App Password Exposed in `.env`
```
MAIL_PASSWORD=uqigcdyhjpsaicje  ← EXPOSED!
MAIL_USERNAME=rhodabatolina28@gmail.com
```
**Risk:** If `.env` is ever committed to Git or exposed via misconfiguration, attacker can send phishing emails as the CMMS system.  
**Fix:** Revoke this App Password in Gmail immediately. Regenerate a new one and store it only on the server environment — never in source code.

---

### 2. CSP Nonce = Dead Feature (False Security)
`SecurityHeaders.php` generates a nonce and calls `Vite::useCspNonce($nonce)` — but the CSP string itself never uses it:
```php
// Generated nonce is NEVER inserted into the CSP directive:
$csp = "... script-src 'self' 'unsafe-inline' ...";
//                          ^^^^^^^^^^^^^^^^ this cancels all XSS protection
```
The `'unsafe-inline'` in `script-src` fully neutralizes XSS protection. The nonce exists in code but has zero effect.  
**Fix:** Remove `'unsafe-inline'` and interpolate the nonce into the CSP string:
```php
$csp = "... script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net ...";
```
Then ensure ALL inline `<script>` tags in Blade views have `nonce="{{ $cspNonce }}"`.

---

### 3. MySQL Root Account Has No Password
```
DB_USERNAME=root
DB_PASSWORD=        ← empty
```
**Risk:** If any tunnel (ngrok, etc.) accidentally exposes the DB port, there's zero authentication.  
**Fix:** Create a dedicated MySQL user with a strong password for this app only.

---

### 4. QR Scan Route — Verify No PII Leak
```php
Route::get('/r/{id}', [ScanController::class, 'redirect']); // PUBLIC
```
Intentionally public — but verify that unauthenticated users do NOT see serial numbers, user names, or asset details before login.

---

## 🔴 HIGH Severity Issues

### 5. `authorize()` in FormRequests is Only `auth()->check()`
```php
// StoreICTRequest.php, UpdateICTRequest.php, StoreInventoryRequest.php
public function authorize(): bool {
    return auth()->check(); // just "am I logged in?"
}
```
**Fix:** Add role-specific checks inside `authorize()`:
```php
public function authorize(): bool {
    return auth()->check() && auth()->user()->isUser();
}
```

### 6. No Max Size on Signature Fields
```php
'endUserSignature' => 'required|string', // ← no max!
```
Base64 canvas drawings can be very large. Without a limit, a malicious payload can cause memory exhaustion.  
**Fix:** `'endUserSignature' => 'required|string|max:100000'`

### 7. `remember_token` Not Hidden in User Model
```php
protected $hidden = ['password']; // ← remember_token missing
```
**Fix:** `protected $hidden = ['password', 'remember_token'];`

### 8. IT History-Based Access — Excessive After Reassignment
Once an IT user is assigned and then reassigned a ticket, `wasEverAssignedToIt()` grants **permanent** read access. For a government system, terminated or reassigned staff should not retain access.

### 9. `SESSION_SECURE_COOKIE=false` — No HTTPS for Cookies
Session tokens sent in plaintext over HTTP. DICT guidelines require HTTPS for all government web systems.

---

## 🟡 MEDIUM Severity Issues

### 10. Dual-Key Fields in `UpdateICTRequest` — High Maintenance Risk
Every field appears twice (camelCase + snake_case = ~50 duplicate rules). Any change must be mirrored.  
**Fix:** Normalize to snake_case at the frontend, or use a middleware to transform before validation.

### 11. `publicProfile()` Method — Dead Code with IDOR Risk
`InventoryController::publicProfile()` has no route — but fetches asset data without scope checking. If a route is ever accidentally added, any authenticated user can query any asset by ID.  
**Fix:** Remove the method or add proper branch/region scoping.

### 12. Dual Deletion Mechanism on `Request` Model
Both `SoftDeletes` (Laravel's `deleted_at`) and a custom `is_deleted` boolean exist on the same model. This creates ambiguity about which is authoritative. Some queries use `scopeNotDeleted()`, others rely on the global soft delete scope. Gaps may exist.

### 13. Route Throttle is Per-IP, Not Per-User
In government offices, many users share the same public IP (NAT). One user hitting the limit blocks the whole office.  
**Fix:** Use per-user throttle keys for authenticated routes:
```php
RateLimiter::for('forms', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
});
```

### 14. API 501 Instead of 404 for Unknown Routes
```php
// api.php
Route::any('/{any?}', fn() => response()->json([...], 501));
```
501 = "Not Implemented" — semantically wrong for unknown routes. Use 404.

### 15. `SessionTimeout` Middleware — Confirm Global Registration
`SessionTimeout.php` exists but may not be applied globally. Unconfirmed — check `bootstrap/app.php` or `Kernel.php` to ensure it's in the `web` middleware group.

---

## 🟢 LOW / Best Practice Issues

### 16. Redirect Routes Should Be Removed, Not Kept
```php
Route::get('/requests/maintenance/create', fn() => redirect()->route('pm-schedules.index'));
```
Dead redirect routes pollute the route table. Remove them entirely.

### 17. `SessionTimeout` Uses `time()` Instead of `now()`
Minor inconsistency with the rest of the codebase which uses Carbon/`now()`.

### 18. `GetAssetApiProfileAction` Returns `null` on Unauthorized
Returning `null` to signal "forbidden" is an antipattern. Use `abort(403)` or a consistent Result object.

### 19. No PHPUnit Test Coverage Confirmed
`phpunit.xml` exists but coverage is unverified. Government systems require tests for:
- Login/logout flow
- Role-based 403 rejections
- ICT request lifecycle (create → approve → complete)
- PM schedule generation

---

## 🏛️ Government Compliance Gaps

### A. Audit Trail Incomplete
| Event | Logged to `audit_logs` DB? | Logged to log files? |
|---|---|---|
| Login success | ❌ Not confirmed | ✅ `Log::info` |
| Login failure | ❌ Not confirmed | ✅ `Log::warning` |
| Session timeout | ❌ No | ❌ No |
| Password reset | ❌ Not confirmed | Unknown |

**Standard:** DICT ISSP requires tamper-evident audit trail **in the database**, not just log files (which can be rotated/deleted).

### B. No Data Retention Enforcement
The `archiveLogs` route allows a Super Admin to archive (effectively purge) logs at any time. Government records under RA 9470 (National Archives Act) require minimum 3–5 year retention for ICT equipment records.

### C. Canvas Signature ≠ Legal Digital Signature
The ICT repair form uses canvas-based `base64` drawings for signatures. These are **not legally valid** under RA 8792 (Electronic Commerce Act). Consider adding timestamp + IP + user account record as proof of intent at minimum.

### D. PAR Number System — ✅ Correct
Self-referential parent/child asset relationship for "Complete Sets" with shared PAR numbers is correctly implemented. Aligns with COA Circular 2021-003. ✅

### E. No Multi-Factor Authentication
Admin and Super Admin accounts have no MFA. DICT Circular 2021-01 recommends MFA for government web systems with sensitive data.

### F. No IP Restriction for Admin Access
No IP whitelist for Super Admin. Ideally, admin functions should require VPN or be restricted to government IP ranges.

---

## 🛤️ Route Architecture Review

### Route Summary (`web.php` — 275 lines)

| Module | Count | Middleware |
|---|---|---|
| Public | 3 | none |
| Auth | 3 | partial |
| ICT Requests | 12 | `auth`, `active`, `require.survey`, `role:*`, `throttle` |
| Maintenance | 10 | same |
| Inventory | 14 | `auth`, `active`, `require.survey`, `can:process-supply` |
| Physical Count | 7 | same as inventory |
| PM Schedules | 15 | `role:super_admin` |
| Requisitions | 5 | `role:*` |
| Super Admin | 12 | `role:super_admin` |
| Shared | 5 | `auth`, `active`, `require.survey` |

### Route Issues Found

**R1 — Two routes outside the ICT group (inconsistent placement)**
```php
Route::delete('/requests/ict/{id}', ...)->middleware('role:super_admin', ...);
Route::post('/requests/ict/{id}/review', ...)->middleware('role:admin', ...);
```
They work (parent middleware applies), but placement is confusing. Move them inside the ICT group.

**R2 — Dead redirect routes should be removed**
```php
Route::get('/requests/maintenance/create', fn() => redirect()->route('pm-schedules.index'));
Route::post('/requests/maintenance', fn() => redirect()->route('pm-schedules.index'));
```

**R3 — API catch-all returns wrong HTTP status**
```php
// api.php
Route::any('/{any?}', fn() => response()->json([...], 501)); // should be 404
```

**R4 — Static routes must stay before parameterized (fragile ordering)**
Current code has comments noting this. Consider using route prefixes or resource routes to make ordering explicit.

---

## 📄 Blade View Line Count Analysis

> Checked: July 19, 2026 — `resources/views/**/*.blade.php` (55 files total)

### 🔴 Very Long (800+ lines) — Candidates for Partial Extraction

| File | Lines | Notes |
|---|---|---|
| `requests/maintenance/form.blade.php` | **902** | Inline CSS + JS + HTML all in one file |
| `requests/ict/form.blade.php` | **886** | Same — inline CSS + JS + HTML |
| `inventory/index.blade.php` | **814** | Large table + filters + modals + JS |
| `inventory/detail.blade.php` | **745** | Asset detail + history + attachments + JS |

These are candidates for the same **partial extraction pattern** already used in the system:
- `requests/ict/_scripts.blade.php` (18 lines — already extracted)
- `requests/maintenance/_scripts.blade.php` (7 lines — already extracted)

The pattern is: extract `<style>` blocks → `@vite('resources/css/page.css')`, extract `<script>` blocks → `@vite('resources/js/page.js')`, keep HTML in the main blade.

### 🟡 Long (600–711 lines)

| File | Lines | Notes |
|---|---|---|
| `layouts/app.blade.php` | **711** | Main layout — sidebar, topbar, notifications, mobile CSS overrides |
| `super-admin/users/index.blade.php` | **691** | Users table + modals + inline JS |
| `admin/personnel/index.blade.php` | **604** | Personnel table + modals + inline JS |

### 🟢 Acceptable (Under 560 lines)

All remaining 48 files are within manageable range. PDF views (`pdf/ict-form.blade.php` at 556 lines, `pdf/maintenance-form.blade.php` at 509 lines) are expected to be verbose due to government form layout requirements.

### Key Observation

The heavy blade files are NOT a priority concern right now since:
1. JS extraction is already in progress (per `ASSET_REFACTOR.md`)
2. The `@vite()` migration is complete — CSS/JS files are already separate in `resources/css/` and `resources/js/`
3. The remaining inline code in blades is mostly template logic (conditionals, loops), not business logic

**Next step when ready:** Follow the existing `_scripts.blade.php` partial pattern for `requests/ict/form.blade.php` and `requests/maintenance/form.blade.php` — no new patterns needed, just apply what's already established.

### 🛠️ Blade Architecture & UI Refactoring Plan
To further reduce Blade line counts (e.g., getting a 600-line file down to 100 lines), implement a **View/Component-Based Architecture** analogous to our Controller Clean Architecture:

1. **Blade Partials (`@include`) for Modals & Sections:**
   Extract large, isolated blocks of HTML like modals (Create, Edit, Delete) or sidebars into dedicated partial files.
   *Example:* Inside `admin/personnel/index.blade.php`, move the 200+ lines of modal code to `admin/personnel/partials/_create_modal.blade.php` and call it via `@include('admin.personnel.partials._create_modal')`.

2. **Laravel Anonymous Components (`<x-component>`) for UI Elements:**
   Extract highly repetitive UI structures (Tables, Buttons, Alerts, Form Inputs) into reusable components inside `resources/views/components/`.
   *Example:* Instead of repeatedly writing `<table>`, `<thead>`, `<tbody>`, and tailwind classes, create an `<x-table>` component.
   ```blade
   <x-table>
       <x-slot name="header">...</x-slot>
       @foreach($items as $item)
           <x-table.row>...</x-table.row>
       @endforeach
   </x-table>
   ```

3. **View Models / Composers for Data Preparation:**
   Move complex data manipulation, query formatting, or state preparation out of `@php ... @endphp` blocks within the Blade views. Shift this responsibility to the Controller using Data Transfer Objects (DTOs) or View Models so the Blade file is strictly responsible for `echoing` data.

---

## ✅ What's Done Well

### Architecture
- **Actions pattern** — Business logic in dedicated classes (`CreateIctRequestAction`, `AssignItAction`, etc.)
- **Service classes** — Query/workflow logic isolated (`InventoryAssetsQueryService`, `GeneratePMScheduleService`, etc.)
- **FormRequests** — All create/update operations use dedicated validation classes (15 total)
- **Policies** — All 4 policies registered via `Gate::policy()` in `AppServiceProvider`
- **Model Observers** — `InventoryAssetObserver`, `RequestObserver` for lifecycle events
- **Eloquent Scopes** — Well-organized, reused across controllers
- **55 Action/Service classes** extracted — controllers went from 4,645 lines → ~2,019 lines (-56%)

### Security (What's Already Good)
- ✅ Anti-brute-force on login (per-email + per-IP, 5 attempts / 60s lockout)
- ✅ Session regeneration on login
- ✅ `EnsureUserIsActive` middleware forcefully logs out deactivated users
- ✅ `SecurityHeaders` middleware adds X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- ✅ HTTPS enforcement in production (`URL::forceScheme('https')`)
- ✅ CSRF token present on all forms
- ✅ Soft deletes on `Request` and `InventoryAsset` models
- ✅ `SESSION_COOKIE` named `cmms_session` (not default `laravel_session`)
- ✅ `APP_DEBUG=false` is correctly set

### Refactoring Status (Completed July 2026)
All 5 phases complete:
- ✅ **Phase 1** — Model Scopes (10+ scopes across 3 models)
- ✅ **Phase 2** — Policy Classes (4 policies, 25+ authorization methods)
- ✅ **Phase 3** — Controller Refactoring (12 controllers, 60+ methods)
- ✅ **Phase 4** — Remaining Controllers verified
- ✅ **Phase 5** — Action/Observer Extraction (~170 lines of duplicate code eliminated)
- ✅ **Asset Refactor** — Vite migration complete, `@vite()` used throughout, fingerprinted builds

### Clean Architecture Status (from `CLEAN_ARCHITECTURE_PLAN.md`)
| Component | Plan Status | Actual Status |
|---|---|---|
| Actions | Planned | ✅ 34 actions created |
| Enums | Planned | ✅ 4 enums created |
| Observers | Planned | ✅ 2 observers |
| Services | Planned | ✅ 6 new services |
| Policies | Planned | ✅ 4 policies |
| Notifications (custom classes) | Planned | ❌ Not yet implemented |
| Jobs (queueable) | Planned | ❌ Not yet implemented |
| API Resources | Planned | ❌ Not yet implemented |
| Traits | Planned | ❌ Not yet implemented |
| ViewModels | Planned | ❌ Not yet implemented |

---

## 📋 Prioritized Action List

| # | Priority | Action | Effort |
|---|---|---|---|
| 1 | 🔴 CRITICAL | Revoke & rotate Gmail App Password | 5 min |
| 2 | 🔴 CRITICAL | Fix CSP — remove `'unsafe-inline'`, inject nonce into directive string | 2 hrs |
| 3 | 🔴 CRITICAL | Set MySQL password, create dedicated DB user | 10 min |
| 4 | 🔴 HIGH | Add `max:100000` to all signature fields in FormRequests | 30 min |
| 5 | 🔴 HIGH | Add `remember_token` to `$hidden` in `User.php` | 5 min |
| 6 | 🟡 HIGH | Strengthen `authorize()` in FormRequests with role checks | 2 hrs |
| 7 | 🟡 MEDIUM | Fix API 501 → 404 for unknown routes | 5 min |
| 8 | 🟡 MEDIUM | Implement per-user throttle keys for authenticated routes | 1 hr |
| 9 | 🟡 MEDIUM | Remove dead redirect routes (maintenance.create/store) | 15 min |
| 10 | 🟡 MEDIUM | Remove or scope `publicProfile()` dead method | 15 min |
| 11 | 🟡 MEDIUM | Log auth failures to `audit_logs` table (not just log files) | 1 hr |
| 12 | 🟢 LOW | Enforce minimum retention before audit log archive | 4 hrs |
| 13 | 🟢 LOW | Add PHPUnit test coverage for auth + RBAC flows | 8 hrs |
| 14 | 🟢 LOW | Confirm `SessionTimeout` is in global web middleware | 15 min |
| 15 | 🟢 LOW | Add MFA (TOTP) for Admin / Super Admin accounts | 2–4 days |
| 16 | 🟢 LOW | Implement remaining Clean Architecture plan items (Jobs, Notifications, Traits) | Ongoing |

---

## 🏆 Final Verdict

Ang CMMS ay isang **well-structured Laravel application** na may malinaw na architecture at magandang pattern (Actions, Services, Policies, Scopes). Ang role-based access control ay solid, at maliwanag na may seryosong effort sa security (rate limiting, security headers, audit logs).

**Bago i-deploy sa production para sa government use:**
1. 🔴 Palitan ang Gmail credentials — pinaka-urgent
2. 🔴 Ayusin ang CSP nonce — kasalukuyang walang epekto
3. 🔴 MySQL password — kahit local environment
4. 🟡 Strengthenin ang FormRequest authorization
5. 🟡 Kumpletuhin ang audit trail sa database level

Sa kasalukuyan, ang sistema ay **production-ready para sa internal testing** — may mga security hardening pa lang na kailangan bago buksan sa buong government network.
