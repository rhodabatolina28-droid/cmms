# 🔬 CMMS Deep System Review — September 2, 2026

**System:** Computerized Maintenance Management System (Laravel 11)  
**Context:** Government ICT Asset & Maintenance Management — NCMB  
**Review Type:** Full system audit against industry-standard CMMS requirements  
**Previous Review:** [SYSTEM_REVIEW.md](./SYSTEM_REVIEW.md) (July 19, 2026)  
**Scope:** Architecture, Data Model, Business Logic, Security, Compliance, Feature Completeness

---

## Table of Contents

1. [CMMS Feature Completeness Scorecard](#1-cmms-feature-completeness-scorecard)
2. [System Architecture Analysis](#2-system-architecture-analysis)
3. [Module-by-Module Deep Dive](#3-module-by-module-deep-dive)
   - [3.1 Asset Registry / Inventory](#31-asset-registry--inventory)
   - [3.2 Work Order Management (ICT + PM Tickets)](#32-work-order-management-ict--pm-tickets)
   - [3.3 Preventive Maintenance Scheduling](#33-preventive-maintenance-scheduling)
   - [3.4 Parts & Consumables Stock (MRO)](#34-parts--consumables-stock-mro)
   - [3.5 Purchase Requests / Procurement](#35-purchase-requests--procurement)
   - [3.6 Requisitions (IT → Supply Flow)](#36-requisitions-it--supply-flow)
   - [3.7 Downtime Tracking](#37-downtime-tracking)
   - [3.8 Physical Count / Audit](#38-physical-count--audit)
   - [3.9 QR Code / Asset Tagging](#39-qr-code--asset-tagging)
   - [3.10 Notification System](#310-notification-system)
   - [3.11 Customer Satisfaction (CSM Survey)](#311-customer-satisfaction-csm-survey)
   - [3.12 Audit Trail & Logging](#312-audit-trail--logging)
   - [3.13 User & Role Management](#313-user--role-management)
   - [3.14 Reporting & Analytics](#314-reporting--analytics)
4. [Data Model Review](#4-data-model-review)
5. [Code Quality & Architecture Findings](#5-code-quality--architecture-findings)
6. [Security Review Update](#6-security-review-update)
7. [Government Compliance Status](#7-government-compliance-status)
8. [Missing CMMS Features](#8-missing-cmms-features)
9. [Items to Remove / Clean Up](#9-items-to-remove--clean-up)
10. [Prioritized Recommendations](#10-prioritized-recommendations)
11. [Final Verdict](#11-final-verdict)

---

## 1. CMMS Feature Completeness Scorecard

A proper Computerized Maintenance Management System requires the following core modules. Here is where this system stands:

| # | CMMS Core Module | Status | Score | Details |
|---|---|---|---|---|
| 1 | **Asset Registry / Inventory** | ✅ Implemented | **9/10** | Categories, serial/property/PAR numbers, parent-child sets, QR codes, CSV import, warranty tracking, depreciation |
| 2 | **Work Order Management** | ✅ Implemented | **7/10** | ICT tickets + PM tickets serve as work orders. Full lifecycle. But naming is non-standard CMMS and no priority/severity. |
| 3 | **Preventive Maintenance Scheduling** | ✅ Implemented | **9/10** | Automated PM cycles, division-based scheduling, calendar, cron generation, pause/resume/stop, frequency config |
| 4 | **Corrective/Reactive Maintenance** | ✅ Implemented | **7/10** | ICT repair requests handle this. Full diagnosis + action taken + disposal recommendation. No failure codes. |
| 5 | **Parts & Inventory (MRO)** | ✅ Implemented | **8/10** | Parts stock, serialized units, stock-in/out, reorder alerts, CSV import, per-unit custodian tracking |
| 6 | **Purchase Requests / Procurement** | ✅ Implemented | **8/10** | Full document flow: draft → submitted → finalized → delivered. Appendix 60 format. Attachments. |
| 7 | **Downtime Tracking** | ✅ Implemented | **7/10** | Per-ticket start/end/duration. Per-asset total_downtime accumulation. No MTBF/MTTR calculations. |
| 8 | **Asset Lifecycle Management** | ⚠️ Partial | **6/10** | Depreciation + disposal flow exist. Missing: procurement, receiving/inspection, transfer, donation stages. |
| 9 | **Reporting & Analytics** | ⚠️ Weak | **4/10** | Only inventory reports + CSV export. No maintenance KPI dashboard. No cost analysis charts. |
| 10 | **Customer Satisfaction (CSM)** | ✅ Implemented | **8/10** | Government-standard CSM survey after ICT ticket completion. ARTA-compliant. |
| 11 | **Audit Trail** | ✅ Implemented | **7/10** | AuditLog model + InventoryHistory. Gaps in login/session tracking coverage. |
| 12 | **User/Role Management** | ✅ Implemented | **8/10** | 5 roles, policy-based authorization, branch/region scoping, activation/deactivation |
| 13 | **Notification System** | ✅ Implemented | **8/10** | Dual-channel (in-app + email). PM-specific templates. Smart routing. |
| 14 | **QR/Barcode Asset Tagging** | ✅ Implemented | **8/10** | QR generation, scanning, batch printing, public redirect route |
| 15 | **Physical Count / Audit** | ✅ Implemented | **7/10** | Session-based counting, search, export, print report |
| 16 | **Failure Analysis (RCA)** | ❌ Missing | **0/10** | No root cause analysis, failure codes, or failure categories |
| 17 | **SLA / Response Time Tracking** | ❌ Missing | **0/10** | No SLA targets, no response/resolution time metrics |
| 18 | **Vendor/Contractor Management** | ❌ Missing | **0/10** | External vendor fields exist but no centralized vendor registry |
| 19 | **Warranty Claims Tracking** | ❌ Missing | **1/10** | Warranty expiry shown on assets but no claims workflow |
| 20 | **Maintenance Cost Analysis** | ⚠️ Basic | **3/10** | `total_maintenance_cost` on assets, repair cost field — but no cost reports or analysis |

### Overall CMMS Completeness: **~65%**

**Verdict:** This IS a real CMMS — not just a ticketing system. The core pillars (Asset Management, PM Scheduling, Work Orders, Parts Inventory) are all present and functional. The main gaps are in analytics/reporting and advanced classification features.

---

## 2. System Architecture Analysis

### 2.1 Architecture Pattern: Clean Architecture ✅

The system follows a well-implemented **Clean Architecture** pattern with proper separation of concerns:

```
app/
├── Actions/          # 55+ single-responsibility action classes (business logic)
│   ├── ICT/          # 18 actions (CreateIctTicketAction, SignIctAcceptanceAction, etc.)
│   ├── Maintenance/  # 17 actions (CreateMaintenanceTicketAction, UpdateMaintenanceTicketAction, etc.)
│   ├── Inventory/    # 19 actions (CreateInventoryAssetAction, ExportInventoryAction, etc.)
│   ├── PMSchedule/   # 10 actions (ForceRunPMAction, ManagePMCycleAction, etc.)
│   ├── PMGenerationSchedule/  # Calendar data actions
│   ├── PurchaseRequest/       # PR workflow actions
│   ├── Requisition/           # Requisition review actions
│   ├── Dashboard/    # 4 role-specific dashboard actions
│   ├── PhysicalCount/  # Physical count workflow actions
│   ├── SuperAdmin/   # User management + audit log actions
│   └── Csm/         # CSM survey actions
├── Http/
│   ├── Controllers/  # Thin controllers — delegate to Actions
│   ├── Requests/     # 32 FormRequest validation classes
│   └── Middleware/   # 5 middleware (Role, Active, Survey, Security, Session)
├── Models/           # 23 Eloquent models
├── Services/         # 9 service classes (complex business workflows)
├── Policies/         # 3 policy classes (RequestPolicy, RequisitionPolicy, UserPolicy)
├── Observers/        # 2 observers (InventoryAssetObserver, RequestObserver)
├── Enums/            # 1 enum class (AssetStatus)
├── Mail/             # 3 mailable classes
├── Console/          # 4 scheduled commands
└── Support/          # 2 helper classes (RequestHelpers, RequisitionSupport)
```

**Assessment:** This is a **well-organized codebase**. Controllers average ~100 lines (thin). Business logic lives in Action classes. Validation is in FormRequests. Authorization is in Policies. This is better than most CMMS implementations.

### 2.2 Database Structure Summary

| Table | Model | Purpose | Relationships |
|---|---|---|---|
| `users` | User | System users (5 roles) | → requests, assets, notifications |
| `requests` | Request | Work orders (ICT + PM) | → user, assignedTo, repairRequest, maintenanceRequest, linkedAsset, requisitions, notifications, csmSurvey |
| `repair_requests` | RepairRequest | ICT repair detail form | → request (1:1 via detail_id) |
| `preventive_maintenance` | PreventiveMaintenance | PM detail form | → request (1:1 via detail_id) |
| `inventory_assets` | InventoryAsset | Asset registry | → assignedUser, history, attachments, parentAsset, components, repairRequests |
| `inventory_history` | InventoryHistory | Asset change log | → asset |
| `asset_attachments` | AssetAttachment | Asset files/documents | → asset |
| `pm_schedules` | PMSchedule | PM schedule configuration | → creator, assignedIt, cycles, divisionSchedules, requests |
| `pm_cycles` | PMCycle | PM cycle tracking | → schedule, divisionSchedules |
| `pm_division_schedules` | PMDivisionSchedule | Per-division PM records | → schedule, cycle |
| `pm_schedule_history` | PMScheduleHistory | PM schedule audit trail | → schedule |
| `pm_generation_schedules` | PMGenerationSchedule | Scheduled future PM generations | → schedule |
| `parts_stock` | Part | Parts/consumables inventory | → movements, units |
| `parts_stock_units` | PartUnit | Serialized individual parts | → part, issuedTo, asset, request, purchaseRequest |
| `part_movements` | PartMovement | Stock in/out transactions | → part |
| `requisitions` | Requisition | Parts requisition (IT → Supply) | → ticket, requester, reviewer |
| `purchase_requests` | PurchaseRequest | PR document for procurement | → requisition, request, requester, creator, finalizer, deliverer, attachments |
| `pr_attachments` | PrAttachment | PR document attachments | → purchaseRequest |
| `notifications` | Notification | In-app + email notifications | → user, request |
| `csm_surveys` | CsmSurvey | Customer satisfaction surveys | → request |
| `audit_logs` | AuditLog | System-wide audit trail | → user |
| `physical_count_sessions` | PhysicalCountSession | Physical count sessions | → counts |
| `inventory_physical_counts` | PhysicalCount | Individual asset counts | → session, asset, countedBy |

**Assessment:** 23 tables, well-normalized. The dual-detail pattern (Request → RepairRequest / PreventiveMaintenance via `detail_id`) is a valid polymorphic approach — type-specific fields are separated from the generic work order tracking.

### 2.3 System Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          SYSTEM ENTRY POINTS                                │
├──────────┬──────────┬──────────────┬────────────┬──────────────────────────┤
│ End User │ IT Staff │ Admin/Supply │ Super Admin│ Cron (4 daily jobs)       │
└────┬─────┴────┬─────┴──────┬───────┴─────┬──────┴────────────┬─────────────┘
     │          │            │             │                   │
     ▼          ▼            ▼             ▼                   ▼
┌─────────┐ ┌────────┐ ┌──────────┐ ┌───────────┐ ┌──────────────────────┐
│ Submit  │ │ Conduct│ │ Manage   │ │ Configure │ │ 02:00 GeneratePM     │
│ ICT     │ │ PM     │ │ Inventory│ │ PM Sched  │ │ 06:00 PM Reminders   │
│ Request │ │ Task   │ │ Parts    │ │ Users     │ │ 07:00 Low Stock Alert│
│         │ │ Repair │ │ PR Docs  │ │ Audit     │ │ 08:00 Asset Integrity│
└────┬────┘ └───┬────┘ └────┬─────┘ └─────┬─────┘ └──────────────────────┘
     │          │           │              │
     ▼          ▼           ▼              ▼
┌──────────────────────────────────────────────────────┐
│                  WORK ORDER LIFECYCLE                  │
│                                                        │
│  Scheduled → Pending → Ongoing → Awaiting Parts       │
│                                  → Awaiting Signature  │
│                                  → Referred External   │
│                          → Completed / Cancelled       │
│                                                        │
│  On status change:                                     │
│  • Asset status auto-syncs (Under Maintenance/Active)  │
│  • Downtime tracking starts/stops                      │
│  • Requisitions auto-cascade on cancel                 │
│  • Disposal flow triggers if IT recommends             │
│  • Maintenance cost accumulates to asset               │
│  • History logged to inventory_history                  │
│  • Notifications sent (in-app + email)                 │
└──────────────────────────────────────────────────────┘
```

---

## 3. Module-by-Module Deep Dive

### 3.1 Asset Registry / Inventory

**Files Involved:**
- Model: `app/Models/InventoryAsset.php` (218 lines)
- Controller: `app/Http/Controllers/Inventory/InventoryController.php` (192 lines)
- Actions: 19 action classes in `app/Actions/Inventory/`
- Service: `app/Services/InventoryCsvImportService.php` (55,140 bytes — large)
- Views: `resources/views/inventory/` (index, detail, qr-sticker, qr-batch)
- Form Requests: `StoreInventoryRequest`, `UpdateInventoryRequest`, `PreviewImportRequest`, `CommitImportRequest`, `ConfirmDisposalRequest`, `UploadAttachmentRequest`

**What's Correct ✅:**
- Asset categories: Desktop, Laptop, Printer/Scanner, Network/Server, Tablet, and peripherals
- Unique serial numbers (nullable for assets without serial)
- Property numbers per COA requirements
- PAR numbers with parent-child set relationships (COA Circular 2021-003 compliant)
- Status auto-enforcement via `saving()` hook:
  - Active asset with no user → auto-converts to Spare
  - Spare asset with user assigned → auto-converts to Active
  - Locked statuses (Defective, For Repair, Scrapped, For Disposal, Under Maintenance) are preserved
- Warranty tracking with expiry alerts (Active / Expiring Soon / Expired / No Warranty Info)
- 5-year depreciation calculation
- Acquisition cost + accumulated maintenance cost
- Total downtime tracking (minutes) with human-readable formatting
- CSV import with preview/commit two-step process
- File attachments per asset (upload/download/delete)
- Asset detail page with full history timeline
- QR code generation (single + batch printing)
- Export to CSV
- Region/branch scoping for multi-location

**What Needs Improvement ⚠️:**
- **No asset transfer workflow** — when `assigned_to_user` changes, it's logged as a history entry but there's no formal "Transfer Request" with acceptance by the receiving party
- **No receiving/inspection stage** — new assets are added directly as "Spare" or "Active". A government process typically has: Purchased → Received → Inspected → Accepted → Deployed
- **No donation/turn-over tracking** — when assets are given to another agency, there's no "Donated" or "Turned Over" status
- **The `InventoryCsvImportService` is 55KB** — this is extremely large for a single service. Consider breaking into smaller steps (parser, validator, transformer, committer)

**Data Integrity — Asset Status Flow:**
```
                    ┌──────────┐
                    │  Spare   │ ← New asset (no user)
                    └────┬─────┘
                         │ Assign user
                         ▼
                    ┌──────────┐
         ┌─────────│  Active   │←──────────┐
         │         └────┬─────┘            │
         │              │                  │
    For Repair     Under Maintenance   Complete
    (ICT ticket)   (PM ticket)         (ticket done)
         │              │                  │
         ▼              ▼                  │
    ┌──────────┐  ┌──────────────┐         │
    │For Repair│  │Under Maint.  │─────────┘
    └────┬─────┘  └──────────────┘
         │
         │ IT recommends disposal
         ▼
    ┌──────────────┐
    │ For Disposal  │ ← Unassigned, awaiting Supply confirmation
    └──────┬───────┘
           │ Supply confirms
           ▼
    ┌──────────┐
    │ Scrapped │ ← Terminal state
    └──────────┘
```

This flow is correctly implemented in `Request.php`'s `booted()` callback and in `InventoryAsset.php`'s `saving()` hook.

---

### 3.2 Work Order Management (ICT + PM Tickets)

**Files Involved:**
- Model: `app/Models/Request.php` (436 lines) — **the core work order model**
- Detail Models: `app/Models/RepairRequest.php` (118 lines), `app/Models/PreventiveMaintenance.php` (128 lines)
- Controllers: `ICTRequestController.php` (123 lines), `MaintenanceController.php` (112 lines)
- Actions: 18 ICT actions, 17 Maintenance actions
- Policy: `app/Policies/RequestPolicy.php` (334 lines)
- Views: `resources/views/requests/ict/`, `resources/views/requests/maintenance/`
- Form Requests: `StoreICTRequest`, `UpdateIctRequest`, `StoreMaintenanceRequest`, `UpdateMaintenanceRequest`, `AssignItRequest`, `ReviewIctRequest`

**What's Correct ✅:**
- Two distinct work order types with shared tracking (Request) + type-specific details (RepairRequest / PreventiveMaintenance)
- Request number format: `REQ-{REGION}-{BRANCH}-{YEAR}-{SEQUENCE}` for ICT, `PM-{REGION}-{BRANCH}-{YEAR}-{SEQUENCE}` for PM
- Display number format converts `REQ` → `ICT` for user-facing display
- Full status lifecycle with 9 states:
  - `Scheduled` — PM ticket awaiting its scheduled date
  - `Pending` — submitted, awaiting admin review/approval
  - `Ongoing` — IT technician is actively working on it
  - `Awaiting Parts` — waiting for parts requisition to be fulfilled
  - `Awaiting Signature` — IT done, waiting for end-user sign-off
  - `Referred - External` — sent to external vendor for repair
  - `Completed` — fully closed
  - `Cancelled` — cancelled by authorized party
  - `Rejected` — rejected by division admin
- Division admin review/approval gate before IT can be assigned (ICT tickets)
- IT assignment by Super Admin only (proper segregation)
- Automatic asset status sync on work order status changes
- Cascade rejection of child requisitions when parent ticket is cancelled
- PDF generation for both ICT and PM forms
- Disposal recommendation flow (IT recommends → Supply confirms)

**What Needs Improvement ⚠️:**

**a) No Priority/Severity System**
The `requests` table has a `priority` column (added via migration `2026_05_21_150000_add_it_supply_roles_and_assigned_to_requests.php`) but it is:
- Listed in `$fillable` in the Request model
- **Never used** in any controller, action, view, or query
- No priority values are defined
- No SLA targets tied to priority levels

This is a fundamental CMMS requirement. Every work order should be classified:

| Priority | Government Example | Expected Response |
|---|---|---|
| P1 - Critical | Server down, payroll system failure | Within 1 hour |
| P2 - High | User's primary workstation unusable | Within 4 hours |
| P3 - Medium | Printer issue, non-critical software problem | Within 1 business day |
| P4 - Low | Enhancement request, cosmetic issue | Within 3 business days |

**b) Naming Convention Mismatch**
In standard CMMS terminology:
- **Work Order (WO)** = a task to perform maintenance on an asset
- **Service Request (SR)** = the user's initial request for help

This system calls everything a "Request" which is technically a service request. The actual work order (diagnosis, repair, parts, sign-off) is embedded within it. For a government system this is fine operationally, but if this system needs to integrate with SAP, Oracle, or other enterprise systems in the future, the terminology gap will cause confusion.

**Recommendation:** No database changes needed. Update UI labels:
- "ICT Request" → "Job Order" (already used in some places like `maintenance.pdf`)
- Group both under "Work Orders" on the navigation/dashboard

**c) The Request Model's `booted()` Callback is a God Object**
At 218 lines, the `static::updated()` callback in `Request.php` handles:
1. Requisition auto-rejection on ticket cancel (lines 220-254)
2. Asset status synchronization (lines 256-400)
3. Downtime tracking start/stop (lines 302-304, 339-346)
4. Disposal flow triggering (lines 348-399)
5. Maintenance cost accumulation (lines 403-408)
6. Inventory history logging (lines 410-429)
7. Duplicate detection via time-based hack (lines 412-416)

**All of this should be extracted to an Event/Listener or Observer.** The model should not know about `InventoryAsset`, `User`, `Requisition`, `Notification`, `PreventiveMaintenance`, `RepairRequest`, and `InventoryHistory` directly. It should emit a `RequestStatusChanged` event and let listeners handle the side effects.

---

### 3.3 Preventive Maintenance Scheduling

**Files Involved:**
- Models: `PMSchedule.php`, `PMCycle.php`, `PMDivisionSchedule.php`, `PMScheduleHistory.php`, `PMGenerationSchedule.php`
- Controller: `PMScheduleController.php` (7,778 bytes)
- Service: `GeneratePMScheduleService.php` (43,554 bytes / 971 lines)
- Actions: 10 actions in `app/Actions/PMSchedule/`
- Commands: `GenerateScheduledPM` (daily 02:00), `SendPMDueReminders` (daily 06:00)
- Views: `resources/views/pm-schedules/` (index, show, edit, calendar)

**What's Correct ✅:**
This is the strongest module in the system. It implements a sophisticated PM scheduling engine:

- **Schedule Configuration:**
  - Schedule name, target asset categories, frequency (Monthly/Quarterly/Semi-annual/Annual)
  - Active/inactive toggle, pause/resume/stop controls
  - Assigned IT personnel per schedule
  - Created-by tracking for audit

- **Cycle Management:**
  - Each schedule runs in cycles (Cycle 1, Cycle 2, etc.)
  - Each cycle covers all divisions in the branch
  - Division-by-division progression within a cycle
  - Cycle completion tracking with timestamps

- **Auto-Generation Engine (971 lines):**
  - Anti-spam guard: won't generate if active tickets exist
  - Cooldown guard: waits between cycle completions
  - Start date guard: respects `next_scheduled_date`
  - Division rotation: generates for one division, waits for completion, moves to next
  - Per-user ticket creation: one PM ticket per end-user in the division
  - Asset auto-linking: links PM ticket to user's assets
  - Weekend skipping: scheduled dates avoid Saturday/Sunday
  - Console/HTTP dual-context: resolves actor in both web and cron contexts

- **Calendar Integration:**
  - FullCalendar view showing all PM events
  - Color-coded by status (scheduled, in-progress, completed)
  - Drag-and-drop rescheduling via `scheduleLater`
  - PM Generation Schedule for future-dated auto-generation

- **Automated Scheduling:**
  - Cron runs `GenerateScheduledPM` daily at 02:00
  - Cron runs `SendPMDueReminders` daily at 06:00

**What Needs Improvement ⚠️:**
- **GeneratePMScheduleService is 971 lines** — while it's well-structured internally, it's pushing the limits. Consider extracting sub-steps into dedicated classes:
  - `PMAntiSpamGuard` — handles duplicate/spam prevention
  - `PMDivisionRotator` — handles which division to generate for
  - `PMTicketFactory` — handles individual ticket creation
  - `PMAssetLinker` — handles linking assets to tickets
- **No PM task template/checklist** — the `maintenance_tasks_json` column stores a JSON array of tasks, but there's no standardized checklist template per asset category. Each PM ticket's tasks are either auto-populated or manually entered. A proper PM system would have:
  - Template: "Desktop PM Checklist" → [Dust cleaning, Fan check, HDD health, Windows Update, Antivirus scan, ...]
  - Template: "Printer PM Checklist" → [Roller cleaning, Toner check, Paper path, Test print, ...]

---

### 3.4 Parts & Consumables Stock (MRO)

**Files Involved:**
- Models: `Part.php` (107 lines), `PartUnit.php` (67 lines), `PartMovement.php`
- Controller: `PartsStockController.php` (7,345 bytes)
- Service: `PartsCsvImportService.php` (3,476 bytes)
- Views: `resources/views/inventory/parts/` (shared via inventory views)
- Form Requests: `StorePartRequest`, `UpdatePartRequest`, `StockInPartRequest`, `StockOutPartRequest`
- Command: `CheckPartsLowStock` (daily 07:00)

**What's Correct ✅:**
- **Dual tracking model:** quantity-based (consumables like toner) + serialized unit tracking (parts with serial numbers)
- `Part` (parts_stock) — item name, unit, category, on_hand_qty, reorder_level, region/branch scoping
- `PartUnit` (parts_stock_units) — serial number, property number, unit value, status (in_stock/issued/defective), custodian tracking
- `PartMovement` — full movement audit trail (stock-in, stock-out, with quantity and remarks)
- Stock health indicators: OK / Low / Critical based on reorder level
- Low stock alert notifications via cron
- Alert cooldown timestamps (`low_notified_at`, `critical_notified_at`) to prevent notification spam
- CSV import for bulk data loading
- Export functionality
- Region/branch scoping for multi-location

**What Needs Improvement ⚠️:**
- **No minimum/maximum stock levels** — only `reorder_level` exists. A proper MRO system should have min, max, and reorder point.
- **No purchase order linkage** — when stock reaches reorder level, there's no automatic PR suggestion. The supply officer must manually create a PR.
- **PartUnit.purchase_request_id** exists but the auto-stock-in on PR delivery is not fully integrated (the PR delivery confirmation creates the record, but it's a recent addition and may have edge cases).

---

### 3.5 Purchase Requests / Procurement

**Files Involved:**
- Model: `PurchaseRequest.php` (153 lines), `PrAttachment.php`
- Controller: `PurchaseRequestController.php` (18,772 bytes)
- Service: `PurchaseRequestNotificationService.php`
- Views: `resources/views/purchase-requests/` (create, show, edit, receive)

**What's Correct ✅:**
- **Revised document flow** (August 2026): `draft → submitted → finalized → delivered`
- PR number generation
- Line items stored as JSON array with description, quantity, unit, unit cost, total
- Appendix 60 government form fields: fund cluster, responsibility center, office/unit
- Finalization by Supply officer
- Delivery confirmation with receipt recording
- File attachment support (PO copies, delivery receipts, inspection reports)
- ₱10,000 threshold logic: below = IT fast-track, above = Supply/Procurement
- Request linkage: PR can be traced back to originating work order ticket
- Legacy status handling: old records from previous workflow show "(legacy)" tag

**What Needs Improvement ⚠️:**
- **The controller is 18,772 bytes** — this is the largest controller in the system. It should delegate more to Action classes.
- **No approval workflow** — the current flow goes directly from submitted to finalized. In government procurement, there's typically a multi-level approval chain (Section Head → Division Chief → Budget Officer → Head of Procuring Entity).
- **No BAC integration tracking** — procurement above ₱50,000 goes through BAC (Bids and Awards Committee). There's no tracking of BAC resolution numbers, bid dates, or supplier selection.
- **No delivery/inspection acceptance** — the system records that delivery happened, but there's no formal Inspection and Acceptance Report (IAR) form.

---

### 3.6 Requisitions (IT → Supply Flow)

**Files Involved:**
- Model: `Requisition.php` (56 lines)
- Controller: `RequisitionController.php` (2,094 bytes)
- Policy: `RequisitionPolicy.php`
- Views: `resources/views/requisitions/`

**What's Correct ✅:**
- Clean flow: IT creates requisition from a ticket → Supply reviews → Approve/Reject → Issue
- Status workflow: `pending → approved → issued` or `pending → rejected`
- Tied to parent ticket via `request_id`
- Auto-rejection when parent ticket is cancelled/rejected
- Submission ID for grouping related requisitions
- JSON items array for flexible line items

**What Needs Improvement ⚠️:**
- The model is thin (56 lines) which is good, but there's no `SoftDeletes` — deleted requisitions are gone permanently.

---

### 3.7 Downtime Tracking

**Implementation Location:** Inline in `Request.php` model's `booted()` callback (lines 302-346) and `InventoryAsset.php`'s `total_downtime` column.

**What's Correct ✅:**
- Downtime starts when work order status → `Ongoing` (line 302-304)
- Downtime ends when work order status → `Completed` (lines 339-346)
- Duration calculated in minutes via Carbon's `diffInMinutes()`
- Duration accumulated to asset's `total_downtime` via `increment()` (line 345)
- Formatted display: "2d 4h 30m" format (lines 119-131)
- Per-asset downtime tickets relationship (`downtimeTickets()` on InventoryAsset)

**What Needs Improvement ⚠️:**
- **No MTBF (Mean Time Between Failures)** — the data exists to calculate this but no computation is implemented
- **No MTTR (Mean Time To Repair)** — same, `downtime_duration` per ticket exists but no aggregate metric
- **No availability percentage** — `(Total Operational Hours - Downtime Hours) / Total Operational Hours`
- **Downtime only tracks when ticket is Ongoing** — if a ticket stays in "Pending" for 3 days while the user can't use their PC, that downtime is not captured. "Pending" should arguably also count as downtime for the asset.

---

### 3.8 Physical Count / Audit

**Files Involved:**
- Models: `PhysicalCountSession.php`, `PhysicalCount.php`
- Controller: `PhysicalCountController.php` (1,714 bytes)
- Views: `resources/views/inventory/physical-count/`

**What's Correct ✅:**
- Session-based: create a count session → scan/search assets → mark as found/missing → complete session
- Export results to CSV
- Printable report
- Admin/Supply only access

**What Needs Improvement ⚠️:**
- **No variance report** — the system counts assets but doesn't automatically compare against expected inventory to produce a discrepancy report
- **No count frequency enforcement** — government requires annual physical count (COA rules). The system doesn't remind or enforce this.

---

### 3.9 QR Code / Asset Tagging

**Files Involved:**
- Service: `QrCodeService.php`
- Controller: `ScanController.php` (7,836 bytes)
- Routes: Public `/r/{id}` redirect route

**What's Correct ✅:**
- QR generation for individual assets
- Batch QR printing for multiple assets
- Public scan route that handles both authenticated and guest users
- Redirects to appropriate asset view based on user role
- Proper validation that asset exists before redirect

**Assessment:** Solid implementation. No significant issues.

---

### 3.10 Notification System

**Files Involved:**
- Model: `Notification.php` (169 lines)
- Service: `RequestNotificationService.php` (13,211 bytes), `PMNotificationService.php`, `PurchaseRequestNotificationService.php`
- Mail: `SystemNotificationMail.php`, `PMScheduledMail.php`, `PMAdminNotificationMail.php`
- Controller: `NotificationController.php`

**What's Correct ✅:**
- **Dual-channel delivery:** in-app notification bell + email
- **Auto-send on creation:** `Notification::booted()` fires email on every notification create
- **Smart routing:**
  - Super admin: in-app only (no email flood for shared region oversight)
  - Production alias emails (`+tag@gmail.com`): skipped to prevent bounce
  - Local environment: writes preview to `laravel.log` instead of sending
- **SMTP direct send:** when mailer is SMTP, sends synchronously without queue dependency
- **PM-specific template:** `PMScheduledMail` with distinct formatting
- **Ticket URL resolution:** correct URL based on user role (admin → show, IT → edit, user → edit)
- Read/unread tracking with `read_at` timestamp
- Bulk "mark all as read" functionality

**What Needs Improvement ⚠️:**
- **Email sending inside model boot** — the `Notification::booted()` callback sends emails synchronously. If SMTP is slow or fails, it blocks the notification creation. Should use Laravel's queue system (`ShouldQueue`) or at minimum, a try-catch with graceful degradation (which it partially has, but the try-catch wraps a large block).
- **No notification preferences** — users can't opt out of specific notification types.

---

### 3.11 Customer Satisfaction (CSM Survey)

**Files Involved:**
- Model: `CsmSurvey.php` (38 lines)
- Controller: `CsmController.php`
- Middleware: `RequirePendingSurvey.php`
- Form Request: `StoreCsmSurveyRequest`
- Views: `resources/views/csm/`

**What's Correct ✅:**
- Government-standard CSM format with CC (Citizen's Charter) and SQD (Service Quality Dimensions) ratings
- 9 rating dimensions (CC1-CC3, SQD1-SQD9) + freetext suggestions
- Demographic data collection (age, sex) per government standard
- **Mandatory survey enforcement:** `RequirePendingSurvey` middleware redirects users to complete their oldest pending survey before accessing other system features
- Survey tied to specific request via `request_id` with unique constraint
- Only for ICT tickets (PM tickets excluded — correct, since PM is initiated by the system, not requested by the user)
- Only end-users can submit (role: user)

**Assessment:** Excellent ARTA compliance. No significant issues.

---

### 3.12 Audit Trail & Logging

**Files Involved:**
- Model: `AuditLog.php` (46 lines), `InventoryHistory.php`
- Controller: `SuperAdminController.php` (audit logs section)
- Service: `RequestNotificationService::logLocalEmailPreview()`

**What's Correct ✅:**
- **AuditLog:** captures user_id, action, module, details, region, IP address, user agent
- **InventoryHistory:** captures asset_id, action, performed_by, previous/new user and status, remarks
- **Convenience method:** `AuditLog::log()` for quick logging from anywhere
- Archive functionality for Super Admin
- DataTables view with search/filter

**What Needs Improvement ⚠️:**
- **Login/logout events not consistently logged to audit_logs table** — they appear in Laravel log files (`Log::info`) but not in the database audit trail. Government standards (DICT ISSP) require database-level audit.
- **No session timeout logging** — when `SessionTimeout` middleware expires a session, there's no audit record
- **No failed login attempt logging to database** — critical for security monitoring
- **Archive can purge data without retention check** — RA 9470 requires minimum 3-5 year retention for government ICT records. The archive function should enforce this.
- **No tamper protection** — audit logs can be edited/deleted by someone with database access. Consider a checksum/hash chain for critical records.

---

### 3.13 User & Role Management

**Files Involved:**
- Model: `User.php` (157 lines)
- Controllers: `SuperAdminController.php`, `PersonnelController.php`
- Policy: `UserPolicy.php`
- Middleware: `RoleMiddleware.php`, `EnsureUserIsActive.php`

**Roles in the System:**

| Role | Access Level | Key Capabilities |
|---|---|---|
| `user` | End-user | Submit ICT requests, view own tickets, view own assets, complete CSM surveys |
| `it` | IT Personnel | Conduct PM tasks, repair assets, diagnose issues, manage assigned tickets |
| `admin` | Division Admin / Supply Officer | Approve/reject tickets, manage inventory, manage parts stock, personnel management |
| `supply_officer` | Supply Officer (treated as admin) | Same as admin, with supply-specific access |
| `super_admin` | System Administrator | Full access: user management, PM scheduling, IT assignment, audit logs, all reports |

**What's Correct ✅:**
- `RoleMiddleware` properly handles comma-separated role lists and treats `supply_officer` as `admin`
- `EnsureUserIsActive` middleware force-logs-out deactivated accounts
- `canProcessSupply()` method properly checks both `supply_officer` role and `admin` with `can_supply` flag
- Branch/region scoping throughout policies
- Personnel management (create, activate/deactivate, view)
- Super Admin: full user CRUD, password reset, role assignment

**What Needs Improvement ⚠️:**
- **`supply_officer` should arguably be a permission, not a separate role** — the system already has `can_supply` flag on admin. Having both a role AND a flag creates ambiguity.
- **No role hierarchy** — Super Admin should automatically inherit all Admin permissions. Currently, each access check lists all allowed roles individually (`role:it,admin,super_admin`), which is verbose and error-prone if a new role is added.
- **No password complexity enforcement** — the system validates login but doesn't enforce password strength on creation/reset. Government systems should require minimum 8 chars, uppercase, lowercase, number, special character.

---

### 3.14 Reporting & Analytics

**Files Involved:**
- Controller: `InventoryReportController.php` (2,618 bytes)
- Views: `resources/views/inventory/reports/`
- Export: `ExportInventoryAction.php`, parts export

**Current State:**
The system currently offers:
- Inventory count by status (active, spare, defective, etc.)
- Inventory count by category
- Inventory export to CSV
- Parts stock export to CSV
- Physical count reports

**What's MISSING (Critical CMMS Gap) ❌:**

| Report / KPI | Data Available? | Report Built? |
|---|---|---|
| **MTBF (Mean Time Between Failures)** | ✅ Yes (`downtime_start` per asset) | ❌ No |
| **MTTR (Mean Time To Repair)** | ✅ Yes (`downtime_duration`) | ❌ No |
| **Asset Availability Rate** | ✅ Yes (total time - downtime) | ❌ No |
| **PM Compliance Rate** | ✅ Yes (completed vs scheduled PM tickets) | ❌ No |
| **Cost per Asset** | ✅ Yes (`total_maintenance_cost`) | ❌ No |
| **Open vs Closed WO Trend** | ✅ Yes (`requests.status` + `created_at`) | ❌ No |
| **Average Resolution Time** | ✅ Yes (`created_at` to `completed_at`) | ❌ No |
| **Top 10 Most Repaired Assets** | ✅ Yes (count of linked requests per asset) | ❌ No |
| **Repair Cost by Category** | ✅ Yes (`cost` on RepairRequest) | ❌ No |
| **Parts Consumption Report** | ✅ Yes (`part_movements`) | ❌ No |
| **Overdue PM Report** | ✅ Yes (scheduled PMs past due date) | ❌ No |
| **Asset Age Distribution** | ✅ Yes (`date_acquired`) | ❌ No |
| **Warranty Expiry Report** | ✅ Yes (`warranty_expiration`) | ❌ No |

**This is the single biggest gap in the system.** The data to generate all these reports already exists in the database. The system is collecting excellent data but doing nothing with it analytically.

---

## 4. Data Model Review

### 4.1 The Dual-Detail Pattern
The system uses a polymorphic-like pattern for work order details:

```
requests (generic work order)
├── detail_id → repair_requests (ICT type)
└── detail_id → preventive_maintenance (PM type)
```

**Assessment:** This is a valid approach. The `type` column (`ICT` or `Preventive Maintenance`) determines which detail table to join. It avoids the complexity of true Eloquent polymorphism while maintaining type-specific fields.

**Concern:** The `detail_id` column has no actual foreign key constraint in the migration — it's just an unsigned bigint. If the detail record is deleted, the request has an orphaned `detail_id`. This is partially mitigated by `SoftDeletes` on both models.

### 4.2 PreventiveMaintenance Detail — Column Explosion

`preventive_maintenance` table has **84 fillable columns** for hardcoded equipment types:

```
desktop_brand, desktop_model, desktop_pno, desktop_computer_name,
desktop_cpu, desktop_ram, desktop_gpu, desktop_os, desktop_hd1, desktop_hd2,
desktop_office, desktop_year_purchased,
monitor1_pno, monitor1_brand, monitor1_model,
monitor2_pno, monitor2_brand, monitor2_model,
printer1_pno, printer1_brand, printer1_model, printer1_type,
printer2_pno, printer2_brand, printer2_model, printer2_type,
ups_pno, ups_brand, ups_model,
scanner_pno, scanner_brand, scanner_model,
laptop_pno, laptop_brand, laptop_model, laptop_computer_name,
laptop_cpu, laptop_ram, laptop_gpu, laptop_os, laptop_hd1, laptop_hd2,
laptop_office, laptop_year_purchased,
webcam_brand, webcam_model, webcam_pno,
speakers_brand, speakers_model, speakers_pno,
earphone_brand, earphone_model, earphone_pno,
other_equipment, other_equipment_brand, other_equipment_model_pno,
...
```

**Why it exists:** This mirrors the physical government PM form layout exactly. When generating the PM PDF, each field maps to a specific cell on the printed form.

**Problems:**
- Adding a new equipment category requires a migration for 3-5 new columns
- Most of these columns are NULL for any given PM record (a desktop PM won't have laptop fields)
- The data is duplicated from `inventory_assets` table

**Recommendation:** Keep for now (government form compliance trumps normalization). Mark as technical debt. Long-term, consider storing equipment snapshot data as JSON instead of individual columns.

### 4.3 RepairRequest — Form Snapshot Fields

Similar issue: `repair_requests` stores user name, property number, and serial number that already exist in `users` and `inventory_assets`. These serve as point-in-time snapshots for the printed form.

**Assessment:** Acceptable for government form compliance. The form must show the values as they were at the time of submission, not the current values. This is actually correct archival behavior.

### 4.4 Migration Count: 77 Files

The `database/migrations/` directory has **77 migration files**. This is a lot but most are incremental additions (adding columns, indexes, new tables) which is healthy. No squashing recommended while in active development.

Noteworthy migrations:
- Several "patch" and "fix" migrations exist (`patch_missing_columns_for_testing`, `drop_*_table`, `cleanup_legacy_*`) — these indicate iterative development, which is normal
- The `recreate_pm_schedules_and_add_division_schedules` migration dropped and recreated tables — this is acceptable in pre-production but would be dangerous in production
- Performance indexes added in later migrations (`add_performance_indexes`) — good practice

---

## 5. Code Quality & Architecture Findings

### 5.1 Controllers — ✅ Properly Thin

| Controller | Lines | Assessment |
|---|---|---|
| ICTRequestController | 123 | ✅ Clean — delegates to 18 action classes |
| MaintenanceController | 112 | ✅ Clean — delegates to 17 action classes |
| InventoryController | 192 | ✅ Acceptable — most methods delegate |
| PMScheduleController | ~200 | ✅ Acceptable — delegates to 10 action classes |
| DashboardController | 32 | ✅ Perfect — 4 one-liner methods |
| PurchaseRequestController | **~500** | ⚠️ **Largest controller — needs action extraction** |
| PartsStockController | ~200 | ✅ Acceptable |
| RequisitionController | ~70 | ✅ Clean |

### 5.2 Action Classes — ✅ Well-Structured

The Actions pattern is consistently applied:
- Each action is a single class with an `execute()` method
- Actions receive a FormRequest or primitive parameters
- Actions return views or redirects (for web) or JSON (for API endpoints)
- Complex actions like `TechnicianUpdateIctTicketAction` (17KB) and `UpdateMaintenanceTicketAction` (28KB) could be further decomposed but are manageable

### 5.3 Services — Mixed Quality

| Service | Size | Assessment |
|---|---|---|
| GeneratePMScheduleService | 43,554 bytes (971 lines) | ⚠️ Very large but internally well-structured |
| InventoryCsvImportService | 55,140 bytes | ⚠️ **Largest file in the codebase — needs decomposition** |
| RequestNotificationService | 13,211 bytes | ⚠️ Large but acceptable for a notification routing service |
| All others | < 5,000 bytes | ✅ Good size |

### 5.4 Form Requests — ✅ Comprehensive

32 FormRequest classes cover all write operations. Good coverage.

**Known issue from previous review:** Some `authorize()` methods only check `auth()->check()` without role verification. The middleware handles role checking at the route level, so this is defense-in-depth weakness rather than a security vulnerability — but should still be improved.

### 5.5 Policies — ✅ Thorough

`RequestPolicy` at 334 lines is comprehensive with fine-grained permission checks:
- `viewIct()` — 42 lines of scoping logic for 4 roles
- `viewMaintenance()` — 66 lines with asset-based branch scoping
- `editIctTechnician()` — assignment-based editing
- `signAcceptance()` — multi-condition acceptance gate
- Division admin scoping via `RequestHelpers::ticketInAdminScope()`

---

## 6. Security Review Update

**Previous review (July 2026) found critical issues. Here is the current status:**

| # | Issue | July Status | September Status |
|---|---|---|---|
| 1 | Gmail App Password in `.env` | 🔴 Critical | ⚠️ **Still in `.env`** — should be rotated |
| 2 | CSP nonce dead feature | 🔴 Critical | ✅ **Fixed** — nonce now injected into CSP string, `unsafe-inline` removed from script-src in production |
| 3 | MySQL root no password | 🔴 Critical | ⚠️ Unknown (development environment) |
| 4 | No max on signature fields | 🔴 High | ⚠️ **Unconfirmed** — needs verification |
| 5 | `remember_token` not hidden | 🔴 High | ⚠️ **Unconfirmed** — needs verification |
| 6 | FormRequest authorize() weak | 🟡 High | ⚠️ Same |
| 7 | API 501 → should be 404 | 🟡 Medium | ⚠️ Same |
| 8 | Per-IP throttle | 🟡 Medium | ⚠️ Same |

**New security observations:**

- **SecurityHeaders middleware** is properly implemented with:
  - Development: permissive CSP for Vite dev server
  - Production: strict nonce-based CSP with whitelisted CDNs
  - X-Frame-Options: DENY
  - X-Content-Type-Options: nosniff
  - Referrer-Policy: strict-origin-when-cross-origin
  - Permissions-Policy: no geolocation, microphone, camera
  - HSTS when on HTTPS

- **Session management:**
  - `SessionTimeout` middleware exists
  - `EnsureUserIsActive` middleware properly force-logs-out deactivated users
  - Session cookie named `cmms_session` (not default)

---

## 7. Government Compliance Status

| Requirement | Standard | Status | Details |
|---|---|---|---|
| **Property Accountability** | COA Circular 2021-003 | ✅ Compliant | PAR numbers, property numbers, parent-child asset sets |
| **Asset Inventory** | COA | ✅ Compliant | Complete asset registry with category, S/N, specifications |
| **Physical Count** | COA | ✅ Compliant | Session-based physical counting with export/print |
| **ICT Service Request Form** | DICT | ✅ Compliant | Multi-section form matching the standard government JO template |
| **PM Checklist** | DICT/Agency | ✅ Compliant | Equipment-specific PM form with task checklist |
| **CSM Survey** | ARTA | ✅ Compliant | CC + SQD dimensions per ARTA standard |
| **Audit Trail** | DICT ISSP | ⚠️ Partial | AuditLog exists but gaps in login/session coverage |
| **Data Retention** | RA 9470 | ⚠️ Risk | No retention period enforcement on audit log archive |
| **Digital Signatures** | RA 8792 | ⚠️ Partial | Canvas-based (not PKI) but includes timestamp + user identity |
| **MFA** | DICT Circular 2021-01 | ❌ Missing | No TOTP/MFA for admin accounts |
| **HTTPS** | DICT | ✅ Configured | Force HTTPS in production, HSTS header |
| **Access Control** | DICT ISSP | ✅ Compliant | Role-based, branch-scoped, policy-enforced |
| **Purchase Request** | GPPB/Appendix 60 | ✅ Compliant | PR form follows Appendix 60 format |
| **Equipment Lifecycle** | COA/GAM | ⚠️ Partial | Missing procurement/receiving/inspection/donation stages |

---

## 8. Missing CMMS Features

### 8.1 Critical Missing Features (Should Have for a Real CMMS)

**a) Priority/Severity Classification System**
- Database column exists (`requests.priority`) but completely unused
- No P1-P4 classification in UI or business logic
- No escalation rules based on priority
- No SLA targets tied to priority

**b) Maintenance KPI Dashboard**
- MTBF, MTTR, Availability Rate, PM Compliance Rate, Cost per Asset
- All data exists in the database — zero visualization
- This is what separates a CMMS from a ticketing system

**c) Failure Codes / Root Cause Analysis**
- Free-text diagnosis and action fields only
- No structured failure taxonomy (Hardware/Software/Network → specific codes)
- Cannot do pattern analysis or predictive maintenance without this

### 8.2 Important Missing Features (Should Consider)

**d) SLA Tracking**
- No response time targets
- No resolution time targets
- No escalation on SLA breach
- No SLA compliance reporting

**e) Vendor/Contractor Registry**
- External vendor fields (company name, phone, email, address) are free-text per ticket
- No vendor master table for spend analysis and performance tracking

**f) Warranty Claims Workflow**
- Asset warranty expiry is tracked and displayed
- No mechanism to file a warranty claim against a vendor/manufacturer
- No tracking of claim status (submitted, approved, replaced, denied)

**g) Asset Transfer Workflow**
- Asset reassignment is done by editing `assigned_to_user`
- No formal transfer request with sending/receiving party approval
- No PAR/ICS document generation for transfers

### 8.3 Nice-to-Have Features (Future Consideration)

**h) Mobile App / PWA**
- QR scanning works on mobile browser but no native offline capability
- IT technicians in the field could benefit from mobile PM checklists

**i) Predictive Maintenance**
- With failure code data + maintenance history, the system could predict likely failures
- Requires failure code implementation first

**j) Dashboard Widgets**
- Current dashboards show basic counts and tables
- Could benefit from charts, trend lines, and real-time status boards

---

## 9. Items to Remove / Clean Up

### 9.1 Junk Files in Project Root (Remove Immediately)

| File | Size | Why Remove |
|---|---|---|
| `Copy of PROPERTY NUMBERS-MARK VERSION - INTANGIBLE.csv` | 8,322 bytes | Test/reference data — move to `storage/` or delete |
| `Laurence.xlsx - Sheet5.pdf` | 58,350 bytes | Personal file — does not belong in codebase |
| `toArray()` | 43 bytes | Accidental file creation — delete |
| `{`r`n` | 43 bytes | Accidental file creation — delete |
| `build.log` | 2,184 bytes | Build artifact — add to `.gitignore` |
| `builderr.log` | 184 bytes | Build artifact — add to `.gitignore` |
| `database/m` | 580 bytes | Incomplete/accidental file — delete |

### 9.2 Dead Code (Remove)

| Location | Code | Why Remove |
|---|---|---|
| `InventoryController.php` L171-185 | `publicProfile()` method | No route, returns asset data without scope checking. IDOR risk if route is accidentally added. Flagged in July review — still present. |
| `InventoryController.php` L84-90 | `destroy()` method | Always returns 403. If deletion is not allowed, remove the route AND method. |
| `routes/web.php` L83-85 | `maintenance.create` + `maintenance.store` | Dead redirect routes — PM creation now uses PM Schedules. Flagged in July review — still present. |

### 9.3 Technical Debt (Plan for Removal)

| Item | Details | Impact |
|---|---|---|
| `is_deleted` column on `requests` | Dual deletion with `SoftDeletes` (`deleted_at`). Creates ambiguity about which is authoritative. | Medium — confusing for new developers |
| Legacy PR statuses | `PurchaseRequest` model defines `STATUS_PENDING`, `STATUS_APPROVED`, `STATUS_RECEIVED`, `STATUS_CANCELLED` for old records. | Low — harmless if old records exist |
| `AssetStatus` as plain class | Should be PHP 8.1 native `enum` for type safety | Low — cosmetic improvement |

---

## 10. Prioritized Recommendations

### Tier 1 — High Impact, Reasonable Effort (Do First)

| # | Recommendation | Impact | Effort | Details |
|---|---|---|---|---|
| 1 | **Extract `Request.php` booted() to Event/Listener** | Maintainability, testability, separation of concerns | 1-2 days | The 218-line callback handles 6 different concerns. Extract to `RequestStatusChanged` event + dedicated listeners. |
| 2 | **Implement Priority/Severity on work orders** | Core CMMS feature, enables SLA tracking | 2-3 days | Column exists. Add P1-P4 dropdown to ICT form, display priority badge on lists, color-code in tables. |
| 3 | **Build KPI Dashboard** | Transforms the system from "smart ticketing" to "real CMMS" | 3-5 days | MTTR, MTBF, PM Compliance %, Availability Rate, Top Repaired Assets, Cost per Category. Data already exists — just need visualization (Chart.js or ApexCharts). |
| 4 | **Clean up junk files from project root** | Professionalism, git hygiene | 10 minutes | Delete accidental files, add build logs to `.gitignore` |
| 5 | **Remove dead code** | Code hygiene, security | 30 minutes | Remove `publicProfile()`, always-403 `destroy()`, dead redirect routes |
| 6 | **Convert `AssetStatus` to native PHP enum** | Type safety, modern PHP practices | 1 hour | `class AssetStatus` → `enum AssetStatus: string` |

### Tier 2 — Medium Impact (Do When Possible)

| # | Recommendation | Impact | Effort | Details |
|---|---|---|---|---|
| 7 | **Add Failure Codes / Categories** | Data analysis, pattern detection, predictive maintenance foundation | 2-3 days | Create `failure_codes` table. Add dropdown to ICT form diagnosis section. Enable "Top Failure Modes" report. |
| 8 | **Add SLA Targets + Tracking** | Performance accountability, management visibility | 2-3 days | Define SLA per priority level. Track response time (created → assigned) and resolution time (created → completed). Alert on breach. |
| 9 | **Remove `is_deleted` dual deletion** | Reduced confusion, single source of truth | 2-3 hours | Backfill `deleted_at` where `is_deleted=true`. Remove column. Update `scopeNotDeleted()` users to use default `SoftDeletes` scope. |
| 10 | **Add Vendor Registry** | Spend tracking, vendor performance analysis | 1-2 days | Create `vendors` table. Normalize `RepairRequest.company_*` fields to a foreign key. Add vendor list view. |
| 11 | **Implement MFA for admin/super_admin** | DICT compliance, security | 2-3 days | TOTP-based MFA using `pragmarx/google2fa-laravel` or similar. |
| 12 | **Extract PurchaseRequestController to Actions** | Code quality, consistency | 1 day | At 18,772 bytes it's the largest controller. Apply the same Actions pattern used in ICT and Maintenance. |

### Tier 3 — Lower Priority (Future Enhancement)

| # | Recommendation | Impact | Effort | Details |
|---|---|---|---|---|
| 13 | **Add full asset lifecycle stages** | Complete lifecycle tracking, procurement-to-disposal | 1 week | Add statuses: On Order, Received, Under Inspection, Transferred, Donated |
| 14 | **Refactor PreventiveMaintenance columns** | Schema normalization | 3-5 days | Store equipment details as JSON or pull from InventoryAsset at render time |
| 15 | **Add Warranty Claims workflow** | Vendor accountability, cost recovery | 2-3 days | Track warranty claims: filed → in review → approved/denied → resolved |
| 16 | **Build PHPUnit test suite** | Reliability, CI/CD readiness | Ongoing | Start with auth flow tests, RBAC tests, ICT lifecycle test, PM generation test |
| 17 | **Maintenance cost analysis reports** | Budget planning, replacement decisions | 2-3 days | Cost per division, cost per category, cost trend over time |
| 18 | **PM Task Templates** | Standardization, efficiency | 2 days | Create reusable checklists per asset category instead of free-form tasks |
| 19 | **Decompose large services** | Maintainability | 2-3 days | Split `InventoryCsvImportService` (55KB) and `GeneratePMScheduleService` (43KB) into smaller focused classes |

---

## 11. Final Verdict

### Is this a real CMMS? — **YES.**

The system implements **~65% of standard CMMS functionality**, covering all five CMMS pillars:

1. ✅ **Asset Management** — comprehensive registry with government compliance
2. ✅ **Work Order Management** — full lifecycle tracking with role-based access
3. ✅ **Preventive Maintenance** — automated scheduling engine (best-in-class for government)
4. ✅ **Parts/MRO Inventory** — stock tracking with reorder alerts
5. ⚠️ **Reporting/Analytics** — data collection is excellent, visualization is the gap

### Strengths

| Area | Verdict |
|---|---|
| **Architecture** | Better than most commercial CMMS implementations. Clean Architecture with Actions pattern. |
| **PM Scheduling Engine** | Legitimately impressive. 971-line service with division rotation, anti-spam, calendar, cron automation. |
| **Government Compliance** | PAR, property numbers, COA standards, CSM surveys, Appendix 60 PR forms — all correct. |
| **Security Foundation** | CSP nonce, rate limiting, RBAC policies, session management, HTTPS — solid base. |
| **Multi-location Support** | Region/branch scoping throughout models, policies, and queries. |
| **Parts Stock Management** | Dual tracking (quantity + serialized), reorder alerts, custodian accountability — rare in government CMMS. |

### Critical Gaps

| Gap | Why It Matters |
|---|---|
| **No KPI Dashboard** | Management cannot make data-driven decisions about asset replacement, staffing, or vendor selection. The data is there — it just needs charts. |
| **No Priority Classification** | Every work order looks equally important. IT can't triage. There's no escalation path. |
| **Request.php God Object** | The 218-line model callback will become a maintenance nightmare as the system grows. Every new feature touching work order status changes must modify this one file. |

### Bottom Line

**The system is production-viable for government ICT maintenance operations.** It correctly handles the full cycle from asset registration → service request → IT assignment → repair/PM → parts requisition → procurement → completion → customer satisfaction survey.

To cross the line from "smart ticketing system" → "real CMMS", focus on:
1. **KPI Dashboard** (Tier 1, Item #3)
2. **Priority/Severity** (Tier 1, Item #2)
3. **Request model refactor** (Tier 1, Item #1)

These three items alone would bring the CMMS completeness score from ~65% to ~80%.

---

*Document generated: September 2, 2026*  
*Previous review: [SYSTEM_REVIEW.md](./SYSTEM_REVIEW.md) — July 19, 2026*
