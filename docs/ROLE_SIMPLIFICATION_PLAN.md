# ROLE SIMPLIFICATION PLAN — Remove Division Admin Role
## Super Admin Direct Review + Personnel Profile & Activity Transfer

> **Status:** 📝 APPROVED PLAN — hindi pa nai-implement. Sundin ang phases sa order.
> **Created:** 2026-08-24 · **Audit basis:** Full codebase sweep (app/, routes/, resources/, database/, tests/, config/)
> **Rule:** WALANG laktawan na phase. Data migration BAGO enum change. Walang column rename sa requests.

---

## 1. GOALS

| # | Goal |
|---|------|
| G1 | Tanggalin ang per-division `admin` (Division Admin) role — kasama ang review step nito |
| G2 | Si **Super Admin mismo** ang mag-Aapprove/Reject ng ICT requests (direct review) |
| G3 | Ang **Personnel Profile & Activity** ay ilalipat/ilalagay sa Super Admin sidebar |
| G4 | Final roles: `user`, `supply_officer`, `super_admin`, `it` *(IT = KEEP — dumaan sa buong repair/PM workflow)* |
| G5 | Supply Officer ay magiging tunay na `supply_officer` role (hindi na disguised `admin`) |
| G6 | ZERO data loss; preserved review history; walang masisira sa PM/parts/requisition flows |

---

## 2. VERIFIED CURRENT STATE (Deep Audit Results)

### 2.1 Roles ngayon
- `config/roles.php`: `super_admin | admin | supply_officer | it | user`
- DB enum (`users.role`): `enum('user','admin','supply_officer','super_admin','it')`

### 2.2 CRITICAL QUIRK — Supply Officers are stored as `admin`
Tatlong lugar ang nagko-convert ng `supply_officer` → `role='admin' + can_supply=1`:
- `app/Actions/SuperAdmin/StoreUserAction.php:28-30`
- `app/Actions/SuperAdmin/UpdateUserAction.php:25-34` (may Administrative-Division guard)
- `app/Http/Controllers/Admin/PersonnelController.php:149-153`

**Result:** Sa live DB, ang mga aktwal na Supply Officer ay `role='admin' AND can_supply=1`.
Ang `role='supply_officer'` rows ay possible pero bihira. Kaya ang migration ay data-first (Phase 1).

### 2.3 Current ICT Review Flow
```
CreateIctTicketAction:114 ──► RequestNotificationService::notifyAdminsOfNewRequest()
                                └─ cascadeDivisionAdminsForUser(): role IN (admin,supply_officer)
                                   branch/department/office cascade
Division Admin opens form ──► RequestHelpers::ictFormFlags()/maintenanceFormFlags()
                                └─ canReviewAsDivisionAdmin (isDivisionAdmin + NULL status check)
POST /requests/ict/{id}/review ──► routes/web.php:79 middleware role:admin
                                └─ ReviewIctTicketAction: isDivisionAdmin() guard,
                                   office-scope check, sets division_admin_review_status/
                                   division_admin_notes/reviewed_by_admin_id/reviewed_at
Approved ──► notifySuperAdminOfForwardedRequest()  [in-app lang kay SA]
Rejected ──► status='Rejected' + notification sa requestor (resubmittable)
Resubmit ──► ResubmitIctTicketAction:66 resets review fields; :88 re-notifies admins
Super Admin views ──► LAHAT may filter na division_admin_review_status='Approved':
     ListIctRequestsAction:53 · GetRequestsDataAction:23-31 (×5) · SuperAdminDashboardAction:26
     Models/Scopes/RequestScope.php:42
Assign IT ──► RequestPolicy::assignTicket: super_admin ONLY + status must be 'Approved'
Technician edit ──► RequestPolicy::editIctTechnician: requires 'Approved'
Auto-PM ──► GeneratePMScheduleService:181 sets 'Approved' directly (auto-approve)
PM repair ──► RepairBrokenPMRecordsAction:85-88 backfills NULL→'Approved'
```

### 2.4 Email Mechanism
- Lahat ng `Notification::send()` → `Notification::booted()` hook → automatic email.
- **`Notification.php:38-40`: super_admin emails SKIPPED** ("no email flood").
- SMTP = direct send; log/array = queue. Local env = laravel.log preview.
- Ticket URL builder `Notification.php:69-81`: ICT + `role==='admin'||super_admin` → `ict.show`;
  else `ict.edit`. PM: it/super_admin → `maintenance.edit`; else walang URL.

---

## 3. ⚠️ HIDDEN TRAPS NA DAPAT MA-PREVENT

| # | Trap | Lokasyon | Epekto kapag hindi na-address | Solusyon |
|---|------|----------|-------------------------------|----------|
| T1 | Filter na `division_admin_review_status='Approved'` sa 4 na query sites (+2 policy gates na KEEP) | `ListIctRequestsAction:53`, `GetRequestsDataAction:23,24,25,26,31`, `SuperAdminDashboardAction:26`, `RequestScope:42` | Bagong requests (NULL review) ay **hindi kailanman makikita** ni SA | Tanggalin ang filter; idagdag "Pending My Review" visibility |
| T2 | Super_admin emails skipped sa Notification hook | `Notification.php:38` | Si SA (bagong reviewer) ay **walang matatanggap na email** para sa bagong requests | Targeted whitelist exception para sa review-type notifications |
| T3 | SO stored as `admin+can_supply=1` | DB + 3 conversion sites | Enum change nang hindi nagmi-migrate → SQL error / lost access / broken logins | Phase 1 data-first migration |
| T4 | Silent alias sa middleware (`supply_officer` passes `role:admin`) | `RoleMiddleware:24-27` | Security bypass; SO makaka-review pa rin kahit bawal na | Tanggalin ang alias; lahat ng routes explicit na |
| T5 | Policy gates naka-depende sa `'Approved'` | `RequestPolicy:32,65` | Pagtanggal → ma-a-assign ang IT sa hindi pa naaapprobahan | **KEEP gates** — same column, si SA na ang setter |
| T6 | Auto-PM auto-set `'Approved'` | `GeneratePMScheduleService:181`, `RepairBrokenPMRecordsAction:85-88` | Semantics change → PM pipeline masisira | **HINDI galawin** — compatible sa reuse design |

## 4. KEY DESIGN DECISIONS

| # | Decision | Dahilan |
|---|----------|---------|
| D1 | **HINDI i-rename** ang columns `division_admin_review_status`, `division_admin_notes`, `reviewed_by_admin_id`, `reviewed_at`. Re-use bilang "Final Review record" — si Super Admin na ang reviewer. | Zero schema risk sa requests; preserved audit history; T5/T6 gates tuloy gumagana; UI labels lang ang palitan |
| D2 | `role='admin' AND can_supply=1` → **`supply_officer`** (migration). Plain `role='admin'` → **`user`** (demote; manual promote ni SA kung kailangan). | Tumpak na mapping: can_supply admins AY supply officers sa praktika |
| D3 | `requisitions.review` mananatili kay **Supply Officer** | Supply work ito (approve/reject/issue parts), hindi request review |
| D4 | Personnel Management (**Personnel Profile & Activity**) → **Super Admin ONLY** | Per user instruction: "dapat meron nito sa SUPER ADMIN" |
| D5 | `dashboard.admin` route/view mananatili bilang tahanan ng **Supply Officer** (relabel lang) | Iwas rebuild; SO experience unchanged |
| D6 | Quick status update feature (`admin.requests.update-status`) — walang tumatawag na view ang route name nito sa buong resources/views (dead-ish legacy) pero functional — panatilihin para sa `supply_officer` + `super_admin` | Walang frontend reference na nahanap; safe i-update ang guards |
| D7 | IT role **KEEP** — walang pagbabago sa IT workflows | Buong assignment/PM/technician flow nakasalalay dito |

---

## 5. COMPLETE FILE-BY-FILE CHANGE INVENTORY
> ⚠️ Line numbers = audit snapshot (2026-08-24). Gamitin ang function/pattern names bilang
> primary anchor kapag nag-shift ang linya habang nage-edit.

### 5.A DATABASE
| File | Change |
|------|--------|
| `database/migrations/2026_08_24_000001_remove_division_admin_role.php` | NEW — tingnan §6.1 |

### 5.B app/Actions — ICT workflow
| File:Line | Current | Planned Change |
|-----------|---------|----------------|
| `ICT/ReviewIctTicketAction.php:25` | `if (!$admin->isDivisionAdmin()) return 403 'Only Division Admins…'` | → `if (!$admin->isSuperAdmin()) return 403 'Only Super Admins can review requests.'` |
| `ICT/ReviewIctTicketAction.php:29-45` | office-scope check (canProcessSupply vs office match) | Palitan ng branch-scope check lang: kung `$admin->branch && $ticketUser->branch !== $admin->branch` → 403 (SA = branch-wide, walang office filter) |
| `ICT/ReviewIctTicketAction.php:61-73` | Approved → `notifySuperAdminOfForwardedRequest()`; Rejected → notify requestor | Approved → wala nang forward notification (reviewer na ang SA); Rejected → KEEP same flow |
| `ICT/ReviewIctTicketAction.php:75-80` | `AuditLog::log('Division Admin Review',…)` | → `'Super Admin Review'` + details reword |
| `ICT/CreateIctTicketAction.php:114` | `notifyAdminsOfNewRequest(…,'ICT Request')` | → `notifySuperAdminsOfNewRequest(…)` (bagong method — §5.F) |
| `ICT/ResubmitIctTicketAction.php:66` | resets review fields → null | KEEP ✓ (para muling ma-review ni SA) |
| `ICT/ResubmitIctTicketAction.php:88` | `notifyAdminsOfNewRequest(…)` | → `notifySuperAdminsOfNewRequest(…)` |
| `ICT/ListIctRequestsAction.php:36-50` | admin/supply branch → view `admin.requests.index` | Gawing supply_officer-only: `elseif ($user->role === 'supply_officer')` — same office-scoped list |
| `ICT/ListIctRequestsAction.php:52-53` | SA branch + `where('division_admin_review_status','Approved')` | **TANGGALIN ang filter** (T1) — makikita LAHAT ng ICT requests sa branch; UI badge/filter ang bahala sa review status |
| `ICT/QuickUpdateStatusAction.php:21` | `if ($admin->role !== 'admin') 403` | → `if (!in_array($admin->role,['supply_officer','super_admin'],true)) 403` (D6) |
| `ICT/RecommendAssetDisposalAction.php:69-73` | `User::where('role','admin')->where('can_supply',true)…` | → `User::where('role','supply_officer')` (tanggalin can_supply condition) |

### 5.C app/Actions — Dashboard, SuperAdmin, PartsStock
| File:Line | Current | Planned Change |
|-----------|---------|----------------|
| `Dashboard/AdminDashboardAction.php:23` | visibility branch `user/admin/supply_officer` | → tanggalin ang `'admin'` arm |
| `Dashboard/SuperAdminDashboardAction.php:26` | T1 filter `'Approved'` | **TANGGALIN** + idagdag `$pendingMyReview` count (NULL review status, type ICT) na ipapasa sa view |
| `SuperAdmin/StoreUserAction.php:27-33` | SO→admin conversion; else can_supply override | Tanggalin conversion — i-store role as-is; `can_supply = false` default |
| `SuperAdmin/UpdateUserAction.php:25-37` | same conversion + Admin-Div guard | Tanggalin conversion; **KEEP ang Administrative-Division guard** para sa SO assignment |
| `Inventory/PartsStock/CheckLowStockAction.php:111-117` | supplyUsers(): `supply_officer OR admin+can_supply` | → simplify: `where('role','supply_officer')` lang |
| ~30 iba pang `canProcessSupply()` guards (Inventory/PartsStock/PhysicalCount actions) | write-access 403s | **WALANG babaguhin** ✓ (migration ang bahala sa role values) |

### 5.D app/Http
| File:Line | Current | Planned Change |
|-----------|---------|----------------|
| `Middleware/RoleMiddleware.php:24-27` | silent alias supply_officer→role:admin | **TANGGALIN** (T4) |
| `Controllers/Admin/PersonnelController.php:23,54,89` | scope arms admin/supply vs super_admin | Linisin: tanggalin admin arm; SA = branch-wide (D4) |
| `PersonnelController.php:149-153 store()` | SO→admin+can_supply conversion | Tanggalin; gawing SA-only ang personnel create (D4) |
| `Controllers/*` iba pang super_admin guards | PartsStockController:49,118,173,193 · PMScheduleController:89,103,143 · PurchaseRequestController:37 · InventoryController:125 · ICTRequestController:105 | **WALANG babaguhin** ✓ verified walang 'admin' dependency |

### 5.E app/Models · Scopes · Policies
| File:Line | Current | Planned Change |
|-----------|---------|----------------|
| `Models/User.php:91-94 isAdmin()` | `admin \|\| supply_officer` | → `return $this->role === 'supply_officer';` |
| `Models/User.php:111-114 isDivisionAdmin()` | returns isAdmin() | → `return false;` + deprecation note; ayusin ang 2 callers (`RequestHelpers:340,389`) |
| `Models/User.php:121-127 canProcessSupply()` | `isSupplyOfficer() \|\| (isAdmin() && can_supply)` | → `return $this->isSupplyOfficer();` (~30 call sites hindi galawin) |
| `Models/User.php:130-150` dashboardRouteName()/dashboardPath() | `'admin' => …` arms | Tanggalin ang `'admin'` arms |
| `Models/User.php:152-155 assignableRoles()` | may `'admin'` | → `['user','supply_officer','super_admin','it']` |
| `Models/Request.php:345-353` booted hook | supply query `SO OR admin+can_supply` | → `where('role','supply_officer')` |
| `Scopes/RequestScope.php:29` | admin/supply arm | → supply_officer lang |
| `Scopes/RequestScope.php:39-48` SA branch | T1 filter + branch scope | **Tanggalin filter** (T1); keep branch scope |
| `Policies/RequestPolicy.php:17 createIct` | `['user','it','admin','super_admin']` | → palitan `'admin'` ng `'supply_officer'` |
| `Policies/RequestPolicy.php:32,65` gates | require `'Approved'` | **KEEP** (T5) — comments update: "approved by Super Admin" |
| `Policies/RequestPolicy.php:287 viewMaintenance` | `role==='admin'` PM scope arm | → `'supply_officer'` |
| `Policies/RequestPolicy.php:317 updateMaintenance` | `role==='admin') return false` | → `'supply_officer'` (same deny) |
| `Policies/RequisitionPolicy.php:49` | `role==='admin'` fallback view | → tanggalin (SO covered ng L45 canProcessSupply branch) |

### 5.F app/Services & Support
| File:Line | Current | Planned Change |
|-----------|---------|----------------|
| `Services/RequestNotificationService.php:21-36 notifyAdminsOfNewRequest()` | cascade division admins | Palitan: bagong `notifySuperAdminsOfNewRequest(RequestModel $request, User $requestor, string $typeLabel)` — recipients = `cascadeSuperAdminsForUser($requestor)`; type `"New {label} for Review"`; message `"New {label} from {NAME} ({number}). Please review and approve."` |
| `RequestNotificationService.php:38-54 notifySuperAdminOfForwardedRequest()` | forward notice | Obsolete — tanggalin (wala na tatawag) |
| `RequestNotificationService.php:204-241 cascadeDivisionAdminsForUser()` | admin/supply cascade ×3 levels | Tanggalin (i-verify walang ibang caller) |
| `RequestNotificationService.php:331-355 cascadeSupplyOfficersForUser()` | `SO OR admin+can_supply` ×2 | → simplify `where('role','supply_officer')` |
| `Services/PMNotificationService.php:62 notifyDivisionAdmin()` | `whereIn('role',['admin','supply_officer'])` + "Division Admin" wording/logs | → `where('role','supply_officer')`; reword logs |
| `Models/Notification.php:38-40` | skip ALL SA emails | **T2 FIX**: exception whitelist — `SA_EMAIL_TYPES = ['New ICT Request for Review','New Preventive Maintenance']` — i-email ang SA para dito |
| `Models/Notification.php:70` | ICT URL: `role==='admin'\|\|super_admin → ict.show` else ict.edit | → `in_array($user->role,['supply_officer','super_admin'],true)` → ict.show |
| `Support/RequestHelpers.php:329 ictFormFlags` | `$isRegularAdmin = role==='admin' && !canProcessSupply()` | Tanggalin variable; viewOnly = forceView ∥ completed status lang |
| `Support/RequestHelpers.php:339-348` review-flag block | isDivisionAdmin + office scope | → `$user->isSuperAdmin() && !$ticket->division_admin_review_status` + branch check lang; rename flag → `canReviewAsSuperAdmin` |
| `Support/RequestHelpers.php:365,412` array keys | `canReviewAsDivisionAdmin` | Rename → `canReviewAsSuperAdmin` + update consumer `form.blade.php:123` (same commit!) |
| `Support/RequestHelpers.php:376-380 maintenanceFormFlags` viewOnly | `\|\| role==='admin'` | tanggalin admin arm |
| `Support/RequestHelpers.php:388-396` PM review-flag block | same as 339-348 | Same treatment |
| `Support/RequestHelpers.php:419-447 ticketInAdminScope()` | office/branch scope arms | Simplify → branch check lang; callers: quick-update + RequisitionPolicy ✓ |
| `Support/RequestHelpers.php:451-455 canAdminQuickUpdateStatus()` | `!isDivisionAdmin()` deny | → `!in_array(role,['supply_officer','super_admin'])` deny (D6) |
| `Actions/PMSchedule/RepairBrokenPMRecordsAction.php:85-88` | backfill NULL→Approved | **KEEP as-is** ✓ (T6/D1) |
| `Services/GeneratePMScheduleService.php:181` | auto-PM sets `'Approved'` | **KEEP as-is** ✓ (T6/D1) |

### 5.G routes/web.php — kumpletong middleware map
| Line | Current | Planned |
|------|---------|---------|
| 40-42 | dashboard.admin `role:admin` | `role:supply_officer` (D5) |
| 61 | ICT group `user,it,admin,super_admin` | `user,it,supply_officer,super_admin` |
| 78 | ict.destroy `super_admin` | KEEP ✓ |
| 79 | **ict.review `role:admin`** | **`role:super_admin`** ← CORE CHANGE |
| 82,86-93 | maintenance views `…,admin,…` | palitan ng supply_officer |
| 94-103 | assign/scheduled/conduct `it,super_admin` / super_admin | KEEP ✓ |
| 107-112 | requisitions.create/store `it,super_admin` | KEEP ✓ |
| 113-118 | requisitions.index/show `it,admin,super_admin` | `it,supply_officer,super_admin` |
| 119-121 | requisitions.review `role:admin` | `role:supply_officer` (D3) |
| 126-141 | Parts group `role:admin` | `role:supply_officer` |
| 146-157 | Purchase Requests group `role:admin` | `role:supply_officer` |
| 158-201 | Inventory write groups `role:admin` | `role:supply_officer` |
| 203-214 | physical-count group `role:admin` | `role:supply_officer` |
| 215-217 | `/admin/requests/update-status` `role:admin`, name `admin.requests.update-status` | middleware → `role:supply_officer,super_admin`; **URL at name KEEP** (D6 — iwas breakage) |
| 220-222 | inventory.reports `admin,super_admin` | `supply_officer,super_admin` |
| 226-232 | personnel group `role:admin,super_admin` | **`role:super_admin`** (D4) |
| 250 | api asset profile `…,admin,…` | palitan ng supply_officer |

### 5.H resources/views — UI sweep
| File:Line | Change |
|-----------|--------|
| `layouts/app.blade.php:196-203` | Sidebar role label — tanggalin admin/supply arm; SO → `SUPPLY OFFICER` |
| `layouts/app.blade.php:248-276` | DIVISION ADMIN/SUPPLY block → **SUPPLY OFFICER block**: tanggalin "Manage Personnel" link (ilalipat sa SA); keep Inventory accordion (with canProcessSupply wrapper) + Requests + Supply Workspace |
| `layouts/app.blade.php` SA block (~L229) | **IDAGDAG**: nav link → `route('personnel.index')`, label: **"Personnel Profile & Activity"** |
| `dashboard/admin.blade.php:256,423` | hero-role → `"Supply Officer"`; tanggalin ang Division Admin ternary |
| `dashboard/super-admin.blade.php` stats cards | **IDAGDAG**: "PENDING MY REVIEW" card ($pendingMyReview; pulse-red kung >0) |
| `requests/ict/form.blade.php:123-143` | var rename `canReviewAsDivisionAdmin`→`canReviewAsSuperAdmin`; panel title L126 → "Review Decision"; text L128-130 → "Approve or reject this request. After approval, assign IT personnel below."; Approve btn label L138 → "Approve Request" |
| `requests/ict/form.blade.php:145-162` | status box label → "Review Status" |
| `partials/ict/_ict_scripts.blade.php:352,363-365` | comment fix; confirm message `'Approve and forward this request to Super Admin?'` → `'Approve this request? You can then assign IT personnel.'` |
| `partials/super-admin/_user_modals.blade.php:37,121` | tanggalin `<option value="admin">Division Admin</option>` ×2 |
| `partials/admin/_personnel_modals.blade.php:62` | tanggalin admin option |
| `super-admin/users/index.blade.php:457` | tanggalin filter option |
| `partials/super-admin/_user_scripts.blade.php:74` | labels map — tanggalin `admin:` key |
| `requests/maintenance/index.blade.php:121` | `role==='admin'` branch → `supply_officer` |
| `requests/maintenance/form.blade.php:155,164` | `$isAdmin = $user->isAdmin() \|\| …` — gumagana via updated isAdmin(); verify SO = view-only ✓ |
| `admin/personnel/index.blade.php:350` | `!canProcessSupply()` guard — i-adjust para sa SA-only page (ipakita lahat ng action columns) |
| `admin/requests/index.blade.php` | SO list view — i-verify walang review buttons na naiwan para sa SO (reviewer na si SA); tanggalin kung meron |

### 5.I config · seeders · tests
| File | Change |
|------|--------|
| `config/roles.php:15,23` | tanggalin `'admin'` list+labels; relabel supply_officer → `"Supply Officer"` *(auto-fix din: StoreSuperAdminUserRequest:22 · UpdateSuperAdminUserRequest:25 · StorePersonnelRequest:22 dahil config-driven)* |
| `database/seeders/DatabaseSeeder.php:47-54` | Division Admin account → Supply Officer (`role='supply_officer'`) |
| `README.md` test accounts section | update accounts list |
| `tests/Feature/InventoryExportTest.php:36` | `role:'admin',can_supply:true` → `role:'supply_officer'` |
| `tests/Feature/PartsExportTest.php:74` | same |
| `tests/Feature/PartsImportTest.php:153` | same |
| `tests/Feature/PartsStockTest.php:39,159` | same ×2 |
| `tests/Feature/PMCalendarTest.php:69` | role 'admin' user → gawing `supply_officer` (verify ang scenario) |
| `tests/Feature/RequisitionReviewValidationTest.php:57,73` | → supply_officer ×2 |
| `tests/Feature/RequisitionTicketContextTest.php:125,148,206,316,413,455` | → supply_officer ×6 |
| `tests/Feature/SupplyQueueSearchTest.php:36` | → supply_officer |
| NEW regression tests | §6.7 |

### 5.J app/Http/Requests — authorize() methods *(nadiskubre sa final zero-leftover audit)*
| File:Line | Current | Planned Change |
|-----------|---------|----------------|
| `Requests/StoreICTRequest.php:11` | `in_array(auth()->user()->role, ['user','it','admin','super_admin'])` | → palitan `'admin'` ng `'supply_officer'` |
| `Requests/StoreMaintenanceRequest.php:11` | `in_array(auth()->user()->role, ['user','it','admin','super_admin'])` | → palitan `'admin'` ng `'supply_officer'` |
| Iba pang 30 FormRequests | `return true;` o `canProcessSupply()` guards | **WALANG babaguhin** ✓ (sweep-verified lahat) |

---

## 6. IMPLEMENTATION PHASES — EXACT CODE

### PHASE 1 — Database Migration (§6.1)
**PRE-FLIGHT (bago ang lahat):** i-run sa prod DB, i-log ang resulta:
```sql
SELECT id, full_name, email, office, department, can_supply, is_active
FROM users WHERE role = 'admin';
SELECT COUNT(*) FROM users WHERE role='admin' AND can_supply=1;  -- expected SO count
```

**File:** `database/migrations/2026_08_24_000001_remove_division_admin_role.php`
```php
public function up(): void
{
    // STEP 1a — disguised supply officers → real supply_officer (T3 fix)
    DB::table('users')
        ->where('role', 'admin')
        ->where('can_supply', 1)
        ->update(['role' => 'supply_officer']);

    // STEP 1b — plain division admins (reviewer role na wala na) → demote to user
    DB::table('users')
        ->where('role', 'admin')
        ->update(['role' => 'user', 'can_supply' => 0]);

    // STEP 2 — saka pa lang i-drop ang 'admin' sa enum
    Schema::table('users', function (Blueprint $table) {
        $table->enum('role', ['user', 'supply_officer', 'super_admin', 'it'])->change();
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->enum('role', ['user','admin','supply_officer','super_admin','it'])->change();
    });
    // NOTE: per-user role mapping ay hindi maibabalik nang eksakto;
    // gumamit ng audit_logs ('Created/Updated User Account') para sa manual restore.
}
```
- Requests review columns: **WALANG gagalawin** ✓
- In-flight Pending ICT requests (NULL review status): automatic na magiging "For SA Review" ✓

### PHASE 2 — Review Workflow Flip (§6.2)
Order: (1) routes map §5.G → (2) `ReviewIctTicketAction` rewrite →
(3) notification service new method + callers → (4) T1 filter removals (List/Get/SA-Dashboard/
RequestScope) → (5) RequestHelpers flag rename + consumers → (6) RoleMiddleware alias removal →
(7) User model helpers → (8) policies.
```php
// BAGONG METHOD — app/Services/RequestNotificationService.php
public static function notifySuperAdminsOfNewRequest(RequestModel $request, User $requestor, string $typeLabel): void
{
    $recipients = self::cascadeSuperAdminsForUser($requestor);
    $name = strtoupper($requestor->full_name ?? 'USER');
    foreach ($recipients as $r) {
        \App\Models\Notification::send(
            $r->id, $request->id,
            "New {$typeLabel} for Review",
            "New {$typeLabel} from {$name} ({$request->request_number}). Please review and approve."
        );
    }
}
```

### PHASE 3 — Personnel Profile & Activity → Super Admin (§6.3)
- Sidebar SA block: dagdag link "Personnel Profile & Activity"
- Routes L226: personnel group → `role:super_admin`
- `PersonnelController`: tanggalin admin/supply arms; store/toggle/show = branch-wide SA
- `_personnel_modals.blade.php`: tanggalin admin option

### PHASE 4 — Supply Officer Dashboard relabel (§6.4)
- `dashboard/admin.blade.php` hero texts; sidebar badge; AdminDashboardAction arm cleanup

### PHASE 5 — UI Cleanup Sweep (§6.5)
- Lahat ng §5.H rows, config/roles.php, seeder, README

### PHASE 6 — Email adjustments (§6.6)
- Notification.php:38 whitelist exception (T2); URL builder :70 update

### PHASE 7 — Tests (§6.7)
1. Update ~14 existing test files (list sa §5.I): `'admin'+can_supply` → `'supply_officer'`
2. Bagong regression tests:
   - `SuperAdminDirectReviewTest`: (a) user creates ICT → SA notified in-app+email, request VISIBLE kay SA kahit NULL review [anti-T1]; (b) SA approve → assignTicket policy passes; IT assignment OK; (c) SA reject → requestor notified + resubmit flow OK; resubmit visible ulit kay SA
   - `RoleRemovalAccessTest`: (d) SO gets 403 sa `ict.review`; (e) SO gets 403 sa `/personnel*`; (f) walang user ang maka-login na may role='admin' pagkatapos ng migration; (g) quick-update-status OK para sa SO at SA
   - `SupplyOfficerUnchangedTest`: inventory/parts/PR/requisitions/physical-count flows green bilang supply_officer
3. Full PHPUnit suite run

---

## 7. EMAIL FLOW AUDIT — BEFORE vs AFTER

| # | Scenario | Ngayon (recipients) | Pagkatapos | Change needed |
|---|----------|--------------------|------------|---------------|
| E1 | Bagong ICT request | Division Admins — **may email** | Super Admins dapat | NEW method `notifySuperAdminsOfNewRequest` + **whitelist exception** sa SA email skip (T2) |
| E2 | Approved (forward) | "Forwarded ICT Repair" → SA in-app only | obsolete | tanggalin ang call |
| E3 | Rejected | Requestor — may email | SAME | ✓ wala |
| E4 | Resubmit | Re-notifies admins (email) | → SA | bagong method + caller update |
| E5 | Parts requisition created | Supply Officers — may email | SO role value changed; hindi skip ang SO sa hook | ✓ automatic OK |
| E6 | Requisition approved/rejected/issued | IT requester — may email | SAME | ✓ wala |
| E7 | PM Scheduled / PM Task Created / PM Batch | End-user + IT + SA + division admins | division admins → supply officers (`PMNotificationService:62`) | Phase 2 update |
| E8 | Low stock alerts | `SO OR admin+can_supply` | `supply_officer` lang | CheckLowStockAction simplify |
| E9 | Disposal recommended/tagged | admin+can_supply users | `supply_officer` | RecommendAssetDisposalAction + Request.php hook queries |
| E10 | Ticket URL sa email (ICT) | admin/super_admin→ict.show; iba→ict.edit | SO dapat ict.show din | Notification.php:70 |
| E11 | PM generation monitoring (cron) | SA only, direct Mail::send | SAME | ✓ GenerateScheduledPM walang change |
| E12 | PM due reminders (weekly cron) | super_admin+it | SAME | ✓ SendPMDueReminders walang change |

Email templates verified: `emails/default.blade.php`, `SystemNotificationMail`, `PMScheduledMail`,
`PMAdminNotificationMail` — walang "Division Admin" wording sa bodies ✓

---

## 8. POST-IMPLEMENTATION VERIFICATION CHECKLIST
- [ ] Pre-flight audit query naka-log (bilang ng admin users bago migration)
- [ ] `php artisan migrate` — data step muna, tapos enum step (walang error)
- [ ] Login test: lahat ng dating accounts — tama ang dashboard redirect (SO→dashboard.admin)
- [ ] **T1 check:** user creates ICT → lumilitas AGAD sa (a) SA All Requests list, (b) SA dashboard counter, (c) SA requests data stats
- [ ] **T2 check:** laravel.log may email preview para kay SA ("New ICT Request for Review")
- [ ] SA Approve → Assign-IT panel gumagana (policy gate passes)
- [ ] SA Reject → requestor notified; resubmit → balik kay SA
- [ ] **T4 check:** bilang Supply Officer — manual na i-access ang `/requests/ict/{id}/review` via POST → 403
- [ ] SO login: sidebar "SUPPLY OFFICER"; inventory/parts/PR/requisitions/physical-count OK; personnel page 403
- [ ] Personnel Profile & Activity: visible sa SA sidebar; profile modal (assets+requests+stats) gumagana; toggle active OK
- [ ] User Management (SA): create user dropdown WALANG Division Admin option; SO creation → stored as `role='supply_officer'` (hindi admin!)
- [ ] Auto-generated PM: review status auto-'Approved' pa rin; PM flow green (T6)
- [ ] Low-stock alert email → SO natatanggap
- [ ] Audit Logs: bagong entries "Super Admin Review"
- [ ] PHPUnit full suite green

## 9. ROLLBACK PLAN
1. `php artisan migrate:rollback --step=1` — ibabalik ang enum (kasama ang 'admin')
2. Code revert per commit — bawat phase ay hiwalay na commit para granular ang rollback
3. Data restore reference: `audit_logs` ('Created/Updated User Account' entries) +
   pre-flight query snapshot
4. Requests review data: HINDI naaapektuhan kahit kailan (D1 — walang column changes)

## 10. VERIFIED SAFE — WALANG PAGBABAGO (para hindi sayangin ang oras)
- ~30 `canProcessSupply()` guards sa Inventory/PartsStock/PhysicalCount actions
- Lahat ng `role !== 'super_admin'` guards sa controllers
- IT workflows: assignment, technician forms, PM tasks, calendar, requisitions.create
- Console commands: GenerateScheduledPM, SendPMDueReminders, ResetSuperAdminPassword
- PDF templates (ict-form, maintenance-form, disposal-tag) — walang role-dependent sections
- JS files (inventory.js, maintenance-calendar.js, modules) — server-injected flags lang
- bootstrap/app.php middleware alias registration (name 'role' stays)

---

## 11. FINAL ZERO-LEFTOVER AUDIT (2026-08-24, second pass)
> Ito ang mga dagdag na sweep na ginawa para siguradong WALANG maiwan.
> Resulta: 2 bagong gaps (nakalagay na sa §5.J) — lahat ng iba ay VERIFIED SAFE.

| Check | Saklaw | Resulta |
|-------|--------|---------|
| Double-quoted `"admin"` sa app/routes/config/database | lahat ng PHP | ✓ WALANG match |
| Double-quoted `"admin"` sa views | lahat ng blade | ✓ 4 lang — `<option value="admin">` ×4 (nasa §5.H na) |
| LAHAT ng `authorize()` methods sa Http/Requests (32 files) | bawat FormRequest | ⚠️ **2 gaps** → `StoreICTRequest:11`, `StoreMaintenanceRequest:11` (§5.J); iba OK |
| `app/Observers/` (InventoryAssetObserver, RequestObserver) | role/admin refs | ✓ WALA |
| `app/Enums/` (AssetStatus lang ang laman) | role refs | ✓ WALA |
| `app/Models/Scopes/InventoryScope.php` | role logic | ✓ comments lang ("supply admin" wording), walang role check |
| `database/factories/UserFactory.php` | default role | ✓ `'user'` lang |
| `tests/TestCase.php` + makeUser helpers | role refs | ✓ WALA sa base; per-test files nasa §5.I |
| `resources/views/components/` (8 components incl. admin-controls) | role logic | ✓ props-driven lang; walang role checks |
| Inline JS `=== 'admin'` sa blade scripts | lahat ng blade | ✓ layouts ×2 + maintenance/index ×1 lang (nasa §5.H na); `_personnel_scripts:66` 'ADMIN' = office-name string cleaning, hindi role ✓ |
| Case-insensitive 'ADMIN' strings | office values ('ADMINISTRATIVE…') | ✓ hindi roles — hindi galawin |

### Coverage summary ng buong audit:
```
app/            → quoted 'admin' sweep ✓ · helper calls sweep ✓ · controller ->role sweep ✓
                  double-quote sweep ✓ · authorize() sweep ✓ · Observers ✓ · Enums ✓ · Scopes ✓ · Support ✓
routes/         → middleware map kumpleto (§5.G) ✓
resources/views → single+double quote sweeps ✓ · inline-JS compares ✓ · components ✓ · PDFs ✓ · emails ✓
resources/js    → role-string sweep ✓ (server-injected flags lang)
database        → migrations/seeds/factories sweep ✓
config          → roles.php + others sweep ✓
bootstrap       → middleware alias ✓
tests           → quoted-admin sweep ✓ (~14 files, §5.I)
console         → commands sweep ✓ (§10 safe list)
```
**Konklusyon:** Kumpleto ang inventory. Walang huling nalalamang `'admin'` role reference
na wala sa plan. Ang tanging hindi kasama ay mga HISTORICAL data sa DB (audit_logs text,
notification messages) — mananatili bilang history ayon sa D1.

*END OF PLAN — docs/ROLE_SIMPLIFICATION_PLAN.md*

