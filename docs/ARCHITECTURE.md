# CMMS Scheduled PM — System Architecture

## Current System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         CMMS WEB APPLICATION                      │
│                      (Laravel 11 + Vue.js)                        │
└─────────────────────────────────────────────────────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
    ┌────────┐            ┌────────┐            ┌────────┐
    │ Routes │            │ Models │            │ Views  │
    │ (web)  │            │ (ORM)  │            │(Blade) │
    └────────┘            └────────┘            └────────┘
        │                      │                      │
        └──────────────────────┼──────────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │    Controllers      │
                    │  & Authorization    │
                    └──────────┬──────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
   ┌─────────┐            ┌─────────┐           ┌──────────┐
   │ Database│            │Services │           │ Artisan  │
   │(MySQL)  │            │& Jobs   │           │Commands  │
   └─────────┘            └─────────┘           └──────────┘
        │
   ┌────────────────────────────┐
   │   Database Tables          │
   │  • users                   │
   │  • requests                │
   │  • preventive_maintenance  │
   │  • inventory_assets        │
   │  • inventory_history       │
   │  • requisitions            │
   │  • notifications           │
   └────────────────────────────┘
```

---

## New System Architecture (With PM Scheduler)

```
┌───────────────────────────────────────────────────────────────────┐
│                    CMMS + SCHEDULED PM SYSTEM                      │
└───────────────────────────────────────────────────────────────────┘
                               │
    ┌──────────────────────────┼──────────────────────────┐
    │                          │                          │
    ▼                          ▼                          ▼
┌──────────────┐        ┌─────────────┐        ┌──────────────┐
│   USER FLOW  │        │ ADMIN FLOW  │        │  CRON/AUTO   │
└──────────────┘        └─────────────┘        └──────────────┘
    │                          │                          │
    │                          │                    ┌─────▼─────┐
    │                          │                    │ Task      │
    │                          │                    │ Scheduler │
    │                          │                    │ (2 AM UTC)│
    │                          │                    └─────┬─────┘
    │                          │                          │
    │              ┌───────────▼──────────┐              │
    │              │  PM Schedule CRUD    │              │
    │              │  (Super Admin)       │              │
    │              │  /pm-schedules/*     │              │
    │              └───────────┬──────────┘              │
    │                          │                          │
    │              ┌───────────▼──────────┐              │
    │              │ Config Schedule:     │              │
    │              │ • Name               │              │
    │              │ • Categories         │              │
    │              │ • Division Filter    │              │
    │              │ • Frequency          │              │
    │              │ • IT Assignment      │              │
    │              └───────────┬──────────┘              │
    │                          │                          │
    │              ┌───────────▼──────────┐    ┌────────▼────────┐
    │              │ Manual Trigger       │    │ Auto Trigger    │
    │              │ "Generate Now"       │    │ artisan command │
    │              └───────────┬──────────┘    └────────┬────────┘
    │                          │                        │
    │              ┌───────────┴──────────┐             │
    │              │ GeneratePM Service   │◄────────────┘
    │              │ • Query schedules    │
    │              │ • Find assets        │
    │              │ • Check duplicates   │
    │              │ • Create batch       │
    │              └───────────┬──────────┘
    │                          │
    │              ┌───────────▼──────────┐
    │              │ Create PM Requests   │
    │              │ is_auto_generated=T  │
    │              │ status=ASSIGNED      │
    │              │ asset_id=FK          │
    │              └───────────┬──────────┘
    │                          │
    │                          ▼
    │         ┌────────────────────────────┐
    │         │   requests table           │
    │         │  (auto-generated requests) │
    │         └────────────────┬───────────┘
    │                          │
    ├──────────────┬───────────┘
    │              │
    ▼              ▼
┌─────────────┐  ┌──────────────────┐
│ IT receives │  │ Asset updated:   │
│ assigned PM │  │ • asset_id FK    │
│             │  │ • next_pm_due    │
│             │  │ • pm_schedule_id │
│             │  └──────────────────┘
│             │
│             ▼
│         ┌──────────────────┐
│         │ IT executes PM   │
│         │ changes status   │
│         │ to ONGOING       │
│         └────────┬─────────┘
│                  │
│                  ▼
│         ┌──────────────────┐
│         │ IT completes     │
│         │ status=COMPLETE  │
│         └────────┬─────────┘
│                  │
└──────────────────┼──────────────────┐
                   │                  │
          ┌────────▼──────────┐       │
          │ Asset updated:    │       │
          │ • last_pm_date    │       │
          │ • next_pm_due     │       │
          │ • status=Active   │       │
          │ • update history  │       │
          └──────────────────┘        │
                                      │
                              ┌───────▼────────┐
                              │ Log entry to   │
                              │ inventory_     │
                              │ history table  │
                              └────────────────┘
```

---

## Component Interaction Flow

### 1️⃣ **Schedule Creation Flow**

```
Super Admin
    │
    ├─→ Visits /pm-schedules
    │
    ├─→ Clicks "Create New Schedule"
    │
    ├─→ Fills form:
    │   • Schedule Name: "RID Desktops Monthly"
    │   • Categories: [Desktop, Laptop]
    │   • Division: "RID"
    │   • Frequency: "Monthly"
    │   • IT Assignee: (optional)
    │
    ├─→ System previews: "Will create PM for 12 desktops"
    │
    ├─→ Submits form
    │
    ├─→ PMScheduleController::store()
    │   • Validates input
    │   • Creates pm_schedules record
    │   • Calculates next_scheduled_date
    │
    └─→ Schedule saved ✅
```

### 2️⃣ **Batch Generation Flow**

```
Manual Trigger:
Super Admin clicks "Generate Now" on schedule
    │
    ├─→ POST /pm-schedules/{id}/generate
    │
    ├─→ PMScheduleController::generate()
    │
    ├─→ Dispatches GeneratePMScheduleJob (async)
    │
Auto Trigger:
Cron job runs (2 AM UTC)
    │
    ├─→ artisan pm:generate-scheduled
    │
    ├─→ Queries pm_schedules WHERE next_scheduled_date <= TODAY
    │
    └─→ Dispatches jobs for each schedule
        │
        ├─→ GeneratePMScheduleJob (async queue)
        │
        ▼ (Both paths converge here)
        
        ┌──────────────────────────────┐
        │ GeneratePMScheduleService    │
        │ ::generate($schedule)        │
        └────────────┬─────────────────┘
                     │
        ┌────────────▼──────────────┐
        │ 1. getMatchingAssets()    │
        │    Filter by:             │
        │    • categories           │
        │    • division             │
        │    • status=Active        │
        └────────────┬──────────────┘
                     │
        ┌────────────▼──────────────┐
        │ 2. Check for duplicates   │
        │    SELECT * FROM requests │
        │    WHERE asset_id IN (...)│
        │    AND pm_schedule_id     │
        │    AND MONTH(created_at)  │
        │    AND is_auto_generated  │
        └────────────┬──────────────┘
                     │
        ┌────────────▼──────────────┐
        │ 3. createBatchRequests()  │
        │    For each asset:        │
        │    • Create request row   │
        │    • set is_auto_generated│
        │    • set pm_schedule_id   │
        │    • set asset_id         │
        │    • status = ASSIGNED    │
        │    • assign_to_it         │
        └────────────┬──────────────┘
                     │
        ┌────────────▼──────────────┐
        │ 4. updateSchedule()       │
        │    • last_generated_date  │
        │    • next_scheduled_date  │
        └────────────┬──────────────┘
                     │
        ┌────────────▼──────────────┐
        │ 5. logGeneration()        │
        │    Write to                │
        │    pm_schedule_history    │
        └────────────┬──────────────┘
                     │
                     ▼
        Batch generation complete ✅
        Notify Super Admin
        Notify assigned IT personnel
```

### 3️⃣ **PM Execution Flow**

```
IT Personnel receives assigned PM request
    │
    ├─→ Views /requests/maintenance
    │   Sees badge: "🤖 Auto-Generated"
    │
    ├─→ Clicks request to view details
    │   Sees: Asset specs, last PM date, etc.
    │
    ├─→ Changes status to ONGOING
    │   (Asset status auto-updates to "For Repair")
    │
    ├─→ Performs maintenance tasks
    │   Updates form fields:
    │   • Problem description
    │   • Diagnosis
    │   • Tasks completed
    │   • Technician name/signature
    │
    ├─→ If parts needed:
    │   • Submits Parts Requisition
    │   • Ticket status → AWAITING_PARTS
    │   • (waits for supply approval)
    │
    ├─→ Once parts received or no parts needed:
    │   • Changes status to COMPLETED
    │   • System triggers update:
    │       ├─ asset.last_pm_date = TODAY
    │       ├─ asset.next_pm_due = last_pm_date + frequency
    │       ├─ asset.status = Active
    │       ├─ Create inventory_history entry
    │       └─ Update asset.maintenance_notes
    │
    └─→ PM record archived ✅
```

---

## Data Flow Diagram

```
┌──────────────────────────────────────────────────────────┐
│              PM SCHEDULER DATA FLOW                       │
└──────────────────────────────────────────────────────────┘

INPUT (Configuration)
    │
    ├─→ pm_schedules table
    │   ├─ schedule_name
    │   ├─ asset_categories (JSON: ["Desktop", "Laptop"])
    │   ├─ division_filter ("RID", "AD", null)
    │   ├─ frequency ("Monthly", "Quarterly", etc.)
    │   ├─ assigned_to_it_user_id (optional)
    │   ├─ last_generated_date
    │   └─ next_scheduled_date
    │
    └─→ inventory_assets table
        ├─ asset_id
        ├─ category
        ├─ assigned_to_user
        ├─ division (implied from custodian)
        ├─ status
        ├─ last_pm_date (NEW)
        ├─ last_serviced_date (NEW)
        ├─ next_pm_due_date (NEW)
        └─ pm_schedule_id (NEW)

PROCESSING (GeneratePMScheduleService)
    │
    ├─ Query: SELECT * FROM inventory_assets
    │  WHERE category IN (["Desktop", "Laptop"])
    │  AND division = "RID"
    │  AND status = "Active"
    │  Result: Asset Set A (e.g., 12 assets)
    │
    ├─ Query: SELECT * FROM requests
    │  WHERE asset_id IN (A)
    │  AND pm_schedule_id = {schedule_id}
    │  AND MONTH(created_at) = MONTH(TODAY)
    │  AND is_auto_generated = TRUE
    │  Result: Existing PMs for this month (e.g., 2)
    │
    ├─ Calculate: Asset Set B = A - Existing (10 new assets)
    │
    └─ For each asset in B:
       ├─ INSERT into requests:
       │  ├─ request_number (auto-generated: "PM-2026-001")
       │  ├─ type = "Preventive Maintenance"
       │  ├─ status = "ASSIGNED"
       │  ├─ is_auto_generated = TRUE
       │  ├─ pm_schedule_id = {schedule_id}
       │  ├─ asset_id = {asset_id}
       │  └─ assigned_to = {IT user from schedule or Super Admin pick}
       │
       └─ RETURN: Created request IDs

OUTPUT (Requests Created)
    │
    └─→ requests table
        ├─ request_number: "PM-2026-001" to "PM-2026-010"
        ├─ type: "Preventive Maintenance"
        ├─ status: "ASSIGNED" (skipped approval flow)
        ├─ is_auto_generated: TRUE (flag for reporting/filtering)
        ├─ pm_schedule_id: FK → pm_schedules.id
        ├─ asset_id: FK → inventory_assets.asset_id
        └─ assigned_to_it: {IT user ID}

SIDE EFFECTS
    │
    ├─→ pm_schedule_history table
    │   ├─ pm_schedule_id
    │   ├─ action: "generated"
    │   ├─ generated_request_ids: ["PM-2026-001", ...]
    │   ├─ batch_count: 10
    │   └─ created_at: TODAY
    │
    ├─→ pm_schedules table (UPDATE)
    │   ├─ last_generated_date = TODAY
    │   └─ next_scheduled_date = TODAY + frequency
    │
    └─→ Notifications
        ├─ Super Admin: "Generated 10 PM requests from schedule 'RID Desktops Monthly'"
        └─ IT Personnel: "You have been assigned 10 Preventive Maintenance tasks"
```

---

## Role & Permission Matrix

```
┌────────────────┬──────┬──────┬───────────┬────┬──────────┐
│ Feature        │ User │ Admin│ SuperAdmin│ IT │ Supply   │
├────────────────┼──────┼──────┼───────────┼────┼──────────┤
│ View PM        │  ✅  │  ✅  │     ✅    │ ✅ │    ✅    │
│ Create PM      │  ❌  │  ❌  │     ✅    │ ❌ │    ❌    │
│ (manually)     │      │      │           │    │          │
├────────────────┼──────┼──────┼───────────┼────┼──────────┤
│ Create Schedule│  ❌  │  ❌  │     ✅    │ ❌ │    ❌    │
│ Edit Schedule  │  ❌  │  ❌  │     ✅    │ ❌ │    ❌    │
│ Delete Schedule│  ❌  │  ❌  │     ✅    │ ❌ │    ❌    │
│ Generate Batch │  ❌  │  ❌  │     ✅    │ ❌ │    ❌    │
│ View History   │  ❌  │  ❌  │     ✅    │ ✅ │    ❌    │
├────────────────┼──────┼──────┼───────────┼────┼──────────┤
│ Approve PM     │  ❌  │  ✅  │     ✅    │ ❌ │    ❌    │
│ (auto-gen)     │      │      │ (skipped) │    │          │
├────────────────┼──────┼──────┼───────────┼────┼──────────┤
│ Assign IT      │  ❌  │  ❌  │     ✅    │ ❌ │    ❌    │
│ Receive PM     │  ❌  │  ❌  │     ❌    │ ✅ │    ❌    │
│ Execute PM     │  ❌  │  ❌  │     ❌    │ ✅ │    ❌    │
│ Complete PM    │  ❌  │  ❌  │     ❌    │ ✅ │    ❌    │
├────────────────┼──────┼──────┼───────────┼────┼──────────┤
│ Submit Req.    │  ❌  │  ❌  │     ❌    │ ✅ │    ❌    │
│ (parts)        │      │      │           │    │          │
└────────────────┴──────┴──────┴───────────┴────┴──────────┘
```

---

## Technology Stack

```
Frontend
├─ Blade Templates (Server-side rendering)
├─ Vue.js 3 (Interactive components)
├─ Tailwind CSS (Styling)
└─ Alpine.js (Lightweight interactivity)

Backend
├─ Laravel 11 (PHP framework)
├─ Eloquent ORM (Database abstraction)
├─ Laravel Queues (Async jobs)
├─ Laravel Scheduler (Cron jobs)
└─ Policies & Middleware (Authorization)

Database
├─ MySQL 8.0
├─ Tables: 15+ existing + 2 new
└─ Indexes on: user_id, asset_id, status, type

DevOps
├─ Laragon (Local dev)
├─ Apache/PHP 8.2
├─ Composer (PHP dependencies)
└─ npm (Frontend dependencies)
```

---

## System Constraints & Dependencies

```
├─ Single server (no distributed system)
├─ Synchronous approval for manual PM
├─ Async batch generation (queue-based)
├─ No API yet (web portal only)
├─ No mobile app
├─ Cron depends on server uptime
├─ Notifications: in-app only (no email integration yet)
└─ Asset categories: fixed enum (Desktop, Laptop, etc.)
```
