# CMMS Scheduled PM — Database Schema Diagram

## Database Tables (Current + New)

```
╔════════════════════════════════════════════════════════════════════════════╗
║                         DATABASE RELATIONSHIPS                              ║
╚════════════════════════════════════════════════════════════════════════════╝

users
├─ id (PK)
├─ name
├─ email
├─ password_hash
├─ role (enum: user, admin, super_admin, it, supply_admin)
├─ branch
├─ division
├─ is_active
├─ created_at
└─ deleted_at

                                ▲
                                │
                        ┌───────┴────────┐
                        │                │
                        │                │
                        │                │
                        │                │

requests (EXISTING)
├─ id (PK)
├─ user_id (FK → users.id)
├─ request_number (UNIQUE)
├─ type (enum: ICT, Preventive Maintenance)
├─ requestor_name
├─ description
├─ region
├─ office
├─ status (enum: Pending, Approved, Assigned, Ongoing, Completed)
├─ detail_id (FK)
├─ is_auto_generated (BOOLEAN) ◄──── NEW
├─ pm_schedule_id (FK) ◄───────────── NEW
├─ asset_id (FK) ◄──────────────────── NEW
├─ assigned_to (FK → users.id)
├─ is_deleted
├─ created_at
└─ deleted_at

        │                │
        │                │
        ▼                ▼

preventive_maintenance (EXISTING)         ┌──────────────────────────────┐
├─ id (PK)                                │   pm_schedules (NEW)         │
├─ form_no                                ├─ id (PK)                     │
├─ technician_name                        ├─ schedule_name (UNIQUE)      │
├─ technician_signature                   ├─ asset_categories (JSON)     │
├─ problem_description                    ├─ division_filter             │
├─ diagnosis                              ├─ frequency                   │
├─ end_user_name                          ├─ assigned_to_it_user_id (FK) │
├─ end_user_division                      ├─ last_generated_date         │
├─ for_disposal                           ├─ next_scheduled_date         │
├─ repair_parts                           ├─ is_active (BOOLEAN)         │
├─ desktop_cpu, desktop_ram, etc.         ├─ created_by (FK → users.id) │
├─ laptop_cpu, laptop_ram, etc.           ├─ created_at                  │
├─ maintenance_tasks_json                 ├─ updated_at                  │
├─ maintenance_date                       └─ deleted_at
├─ created_at
└─ deleted_at

        │                                        ▲
        │                                        │
        │                                        │
        └────────────────────────────────────────┘
               linked via detail_id


inventory_assets (EXISTING → MODIFIED)
├─ asset_id (PK)
├─ category (enum: Desktop, Laptop, Printer, etc.)
├─ item_name
├─ serial_number (UNIQUE)
├─ specifications (JSON)
├─ assigned_to_user (FK → users.id)
├─ region
├─ status (enum: Active, Spare, Defective, For Repair, Scrapped)
├─ property_number
├─ date_acquired
├─ branch
├─ total_maintenance_cost
├─ last_pm_date (DATE) ◄─────────────── NEW
├─ last_serviced_date (DATE) ◄───────── NEW
├─ next_pm_due_date (DATE) ◄────────── NEW
├─ maintenance_notes (TEXT) ◄───────── NEW
├─ pm_schedule_id (FK) ◄───────────── NEW
├─ date_added
├─ created_at
├─ updated_at
└─ deleted_at

        │
        │
        ▼

inventory_history (EXISTING → MODIFIED)
├─ id (PK)
├─ asset_id (FK → inventory_assets.asset_id)
├─ old_status
├─ new_status
├─ changed_by (FK → users.id)
├─ change_reason
├─ request_id (FK → requests.id) ◄──── NEW
├─ event_type (VARCHAR) ◄────────────── NEW (PM, Repair, Transfer, etc.)
├─ change_type
├─ transfer_receipt_number
├─ created_at
└─ updated_at


                ┌─────────────────────────────────────┐
                │   pm_schedule_history (NEW)         │
                ├─ id (PK)                           │
                ├─ pm_schedule_id (FK)               │
                ├─ action (VARCHAR)                  │
                │   "generated" / "modified" / etc.  │
                ├─ generated_request_ids (JSON)      │
                │   ["REQ-2026-001", "REQ-2026-002"] │
                ├─ batch_count (INT)                 │
                ├─ created_at                        │
                └─ updated_at


requisitions (EXISTING)
├─ id (PK)
├─ request_id (FK → requests.id)
├─ item_description
├─ quantity
├─ status
├─ created_at
└─ deleted_at

notifications (EXISTING)
├─ id (PK)
├─ user_id (FK → users.id)
├─ type
├─ message
├─ is_read
├─ created_at
└─ deleted_at
```

---

## Detailed Table Schemas

### `pm_schedules` (NEW TABLE)

```sql
CREATE TABLE pm_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_name VARCHAR(255) NOT NULL UNIQUE,
    asset_categories JSON NOT NULL,  -- ["Desktop", "Laptop"]
    division_filter VARCHAR(50),     -- "RID", "AD", null=all
    frequency VARCHAR(50) NOT NULL,  -- "Monthly", "Quarterly", "Semi-annual", "Annual"
    assigned_to_it_user_id BIGINT UNSIGNED,
    last_generated_date DATE,
    next_scheduled_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    UNIQUE KEY unique_schedule_name (schedule_name),
    FOREIGN KEY (assigned_to_it_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_is_active (is_active),
    INDEX idx_next_scheduled_date (next_scheduled_date),
    INDEX idx_division_filter (division_filter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `pm_schedule_history` (NEW TABLE)

```sql
CREATE TABLE pm_schedule_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pm_schedule_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,  -- "generated", "modified", "activated", "deactivated"
    generated_request_ids JSON,   -- ["PM-2026-001", "PM-2026-002"]
    batch_count INT,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (pm_schedule_id) REFERENCES pm_schedules(id) ON DELETE CASCADE,
    INDEX idx_pm_schedule_id (pm_schedule_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### `inventory_assets` — ALTER TABLE

```sql
ALTER TABLE inventory_assets 
ADD COLUMN last_pm_date DATE DEFAULT NULL AFTER status,
ADD COLUMN last_serviced_date DATE DEFAULT NULL AFTER last_pm_date,
ADD COLUMN next_pm_due_date DATE DEFAULT NULL AFTER last_serviced_date,
ADD COLUMN maintenance_notes TEXT DEFAULT NULL AFTER next_pm_due_date,
ADD COLUMN pm_schedule_id BIGINT UNSIGNED DEFAULT NULL AFTER maintenance_notes,
ADD FOREIGN KEY (pm_schedule_id) REFERENCES pm_schedules(id) ON DELETE SET NULL,
ADD INDEX idx_last_pm_date (last_pm_date),
ADD INDEX idx_next_pm_due_date (next_pm_due_date),
ADD INDEX idx_pm_schedule_id (pm_schedule_id);
```

### `requests` — ALTER TABLE

```sql
ALTER TABLE requests 
ADD COLUMN is_auto_generated BOOLEAN DEFAULT FALSE AFTER status,
ADD COLUMN pm_schedule_id BIGINT UNSIGNED DEFAULT NULL AFTER is_auto_generated,
ADD COLUMN asset_id BIGINT UNSIGNED DEFAULT NULL AFTER pm_schedule_id,
ADD FOREIGN KEY (pm_schedule_id) REFERENCES pm_schedules(id) ON DELETE SET NULL,
ADD FOREIGN KEY (asset_id) REFERENCES inventory_assets(asset_id) ON DELETE SET NULL,
ADD INDEX idx_is_auto_generated (is_auto_generated),
ADD INDEX idx_pm_schedule_id (pm_schedule_id),
ADD INDEX idx_asset_id (asset_id),
ADD UNIQUE INDEX unique_monthly_pm (asset_id, pm_schedule_id, YEAR(created_at), MONTH(created_at)) 
  WHERE is_auto_generated = TRUE;
```

### `inventory_history` — ALTER TABLE

```sql
ALTER TABLE inventory_history 
ADD COLUMN request_id BIGINT UNSIGNED DEFAULT NULL AFTER change_type,
ADD COLUMN event_type VARCHAR(50) DEFAULT NULL AFTER request_id,
ADD FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE SET NULL,
ADD INDEX idx_request_id (request_id),
ADD INDEX idx_event_type (event_type);
```

---

## Data Relationships & Constraints

### Foreign Key Relationships

```
users (id)
  ├─← requests (user_id)
  ├─← requests (assigned_to)
  ├─← pm_schedules (assigned_to_it_user_id)
  ├─← pm_schedules (created_by)
  ├─← inventory_assets (assigned_to_user)
  └─← inventory_history (changed_by)

pm_schedules (id)
  ├─→ requests (pm_schedule_id)
  ├─→ inventory_assets (pm_schedule_id)
  └─→ pm_schedule_history (pm_schedule_id)

inventory_assets (asset_id)
  ├─← requests (asset_id)
  ├─← inventory_history (asset_id)
  └─← users (assigned_to_user)

requests (id)
  ├─→ inventory_assets (asset_id)
  ├─→ pm_schedules (pm_schedule_id)
  ├─→ inventory_history (request_id)
  └─→ requisitions (request_id)

inventory_history (id)
  ├─← inventory_assets (via asset_id)
  ├─← requests (via request_id)
  └─← users (via changed_by)
```

### Unique Constraints

| Table | Constraint | Purpose |
|-------|-----------|---------|
| pm_schedules | schedule_name | Only one schedule with that name |
| inventory_assets | serial_number | Each asset has unique serial |
| requests | request_number | Each request has unique ID |
| requests | (asset_id, pm_schedule_id, YEAR, MONTH) WHERE is_auto_generated=TRUE | Prevent duplicate PM same asset same month |

---

## Indexes for Performance

```
pm_schedules:
├─ PRIMARY KEY (id)
├─ UNIQUE (schedule_name)
├─ INDEX (is_active) — for active schedules query
├─ INDEX (next_scheduled_date) — for cron job
└─ INDEX (division_filter) — for filtering by division

pm_schedule_history:
├─ PRIMARY KEY (id)
├─ FOREIGN KEY (pm_schedule_id)
└─ INDEX (created_at) — for audit logs

inventory_assets:
├─ PRIMARY KEY (asset_id)
├─ INDEX (last_pm_date) — for "assets due for PM" query
├─ INDEX (next_pm_due_date) — for sorting/filtering
├─ INDEX (pm_schedule_id) — for schedule lookups
├─ INDEX (status) — for "Active assets" query
└─ INDEX (assigned_to_user) — for custodian views

requests:
├─ PRIMARY KEY (id)
├─ UNIQUE (request_number)
├─ INDEX (is_auto_generated) — for filtering manual vs auto
├─ INDEX (pm_schedule_id) — for schedule history
├─ INDEX (asset_id) — for asset history
├─ INDEX (status) — for status queries
├─ UNIQUE (asset_id, pm_schedule_id, YEAR, MONTH) — duplicate prevention
└─ COMPOSITE INDEX (type, status) — for request list queries

inventory_history:
├─ PRIMARY KEY (id)
├─ FOREIGN KEY (asset_id)
├─ INDEX (request_id) — for request-linked history
├─ INDEX (event_type) — for PM vs Repair filtering
└─ INDEX (created_at) — for audit trail
```

---

## Sample Data

### pm_schedules Example

```sql
INSERT INTO pm_schedules VALUES (
    1,
    'RID Desktops Monthly',
    '["Desktop"]',
    'RID',
    'Monthly',
    5,  -- assigned_to_it_user_id (IT person)
    '2026-06-01',  -- last_generated_date
    '2026-07-01',  -- next_scheduled_date
    TRUE,
    1,  -- created_by (Super Admin)
    NOW(),
    NOW(),
    NULL
);

INSERT INTO pm_schedules VALUES (
    2,
    'All Laptops Quarterly',
    '["Laptop"]',
    NULL,  -- no division filter = all divisions
    'Quarterly',
    NULL,  -- no pre-assigned IT
    NULL,
    '2026-07-01',
    TRUE,
    1,
    NOW(),
    NOW(),
    NULL
);
```

### requests Example (Auto-Generated)

```sql
INSERT INTO requests VALUES (
    1000,
    NULL,  -- no user_id (system-generated)
    'PM-2026-001',
    'Preventive Maintenance',
    'Desktop PC - Unit 5',  -- requestor_name
    'Monthly preventive maintenance per schedule',
    'NCR',
    'RID Division',
    'ASSIGNED',  -- auto-set to ASSIGNED
    NULL,  -- no detail_id yet (created during PM execution)
    TRUE,  -- is_auto_generated ← KEY DIFFERENCE
    1,  -- pm_schedule_id → 'RID Desktops Monthly'
    47,  -- asset_id → FK to inventory_assets
    5,  -- assigned_to → IT person from schedule
    FALSE,
    NOW(),
    NOW(),
    NULL
);
```

### inventory_assets Example (Updated)

```sql
UPDATE inventory_assets SET
    last_pm_date = '2026-05-15',
    last_serviced_date = '2026-05-15',
    next_pm_due_date = '2026-06-15',  -- Calculated: last_pm_date + 1 month
    maintenance_notes = 'Cleaned cooling vents, replaced thermal paste',
    pm_schedule_id = 1
WHERE asset_id = 47;
```

### inventory_history Example (PM Event)

```sql
INSERT INTO inventory_history VALUES (
    5000,
    47,  -- asset_id
    'For Repair',
    'Active',
    5,  -- changed_by (IT person)
    'PM Completed',
    1000,  -- request_id → PM-2026-001
    'Preventive Maintenance',
    'PM',
    NULL,  -- transfer_receipt_number
    NOW(),
    NOW()
);
```

---

## Backfill Strategy (Migration Safety)

When deploying new columns, use this backfill:

```sql
-- Backfill existing PM requests with asset_id
UPDATE requests r
JOIN preventive_maintenance pm ON r.detail_id = pm.id
JOIN inventory_assets ia ON pm.form_no = ia.serial_number
SET r.asset_id = ia.asset_id
WHERE r.type = 'Preventive Maintenance' 
AND r.is_auto_generated = FALSE;

-- Set all existing PM requests as manual (not auto-generated)
UPDATE requests 
SET is_auto_generated = FALSE
WHERE type = 'Preventive Maintenance';

-- Set all existing assets with NULL next_pm_due_date
-- (These will be calculated on first PM completion)
UPDATE inventory_assets 
SET last_pm_date = NULL,
    last_serviced_date = NULL,
    next_pm_due_date = NULL,
    pm_schedule_id = NULL;
```

---

## Query Examples

### Find Assets Due for PM

```sql
SELECT ia.asset_id, ia.item_name, ia.next_pm_due_date, ps.schedule_name
FROM inventory_assets ia
JOIN pm_schedules ps ON ia.pm_schedule_id = ps.id
WHERE ia.next_pm_due_date <= CURDATE()
AND ia.status = 'Active'
ORDER BY ia.next_pm_due_date ASC;
```

### Find Schedules Ready for Generation

```sql
SELECT id, schedule_name, asset_categories, division_filter, frequency
FROM pm_schedules
WHERE is_active = TRUE
AND next_scheduled_date <= CURDATE();
```

### Count Auto-Generated PM This Month

```sql
SELECT COUNT(*) as batch_count
FROM requests
WHERE is_auto_generated = TRUE
AND type = 'Preventive Maintenance'
AND YEAR(created_at) = YEAR(CURDATE())
AND MONTH(created_at) = MONTH(CURDATE());
```

### Asset PM History

```sql
SELECT ih.*, r.request_number, r.status
FROM inventory_history ih
JOIN requests r ON ih.request_id = r.id
WHERE ih.asset_id = 47
AND ih.event_type = 'Preventive Maintenance'
ORDER BY ih.created_at DESC;
```

---

## Data Validation Rules

```
pm_schedules:
├─ schedule_name: NOT NULL, UNIQUE, MAX 255 chars
├─ asset_categories: NOT NULL, valid JSON array, at least 1 category
├─ division_filter: NULL or valid division code
├─ frequency: NOT NULL, IN ('Monthly', 'Quarterly', 'Semi-annual', 'Annual')
├─ assigned_to_it_user_id: NULL or valid user.id with role='it'
└─ next_scheduled_date: > today or NULL

inventory_assets (new columns):
├─ last_pm_date: NULL or valid DATE ≤ TODAY
├─ next_pm_due_date: NULL or valid DATE ≥ TODAY
└─ pm_schedule_id: NULL or valid pm_schedules.id

requests (new columns):
├─ is_auto_generated: BOOLEAN (default FALSE)
├─ pm_schedule_id: NULL or valid pm_schedules.id (if is_auto_generated=TRUE)
└─ asset_id: NULL or valid inventory_assets.asset_id
```
