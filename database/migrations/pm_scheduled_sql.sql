-- ============================================================
-- Scheduled PM System — SQL Migrations
-- Run these directly in MySQL if artisan migrate is unavailable
-- ============================================================

-- 1. Create pm_schedules table
CREATE TABLE IF NOT EXISTS pm_schedules (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    schedule_name VARCHAR(255) UNIQUE NOT NULL,
    asset_categories JSON NOT NULL,
    division_filter VARCHAR(50) DEFAULT NULL COMMENT 'Null = all divisions',
    frequency VARCHAR(50) NOT NULL COMMENT 'Monthly, Quarterly, Semi-annual, Annual',
    last_generated_date DATE DEFAULT NULL,
    next_scheduled_date DATE DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by BIGINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP DEFAULT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create pm_schedule_history table
CREATE TABLE IF NOT EXISTS pm_schedule_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pm_schedule_id BIGINT NOT NULL,
    action VARCHAR(50) NOT NULL COMMENT 'generated, modified, deactivated',
    generated_count INT DEFAULT 0,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pm_schedule_id) REFERENCES pm_schedules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Add columns to inventory_assets
ALTER TABLE inventory_assets
    ADD COLUMN last_pm_date DATE DEFAULT NULL AFTER total_maintenance_cost,
    ADD COLUMN next_pm_due_date DATE DEFAULT NULL AFTER last_pm_date,
    ADD COLUMN pm_schedule_id BIGINT DEFAULT NULL AFTER next_pm_due_date,
    ADD FOREIGN KEY (pm_schedule_id) REFERENCES pm_schedules(id) ON DELETE SET NULL;

-- 4. Add columns to requests
ALTER TABLE requests
    ADD COLUMN is_auto_generated TINYINT(1) DEFAULT 0 AFTER detail_id,
    ADD COLUMN pm_schedule_id BIGINT DEFAULT NULL AFTER is_auto_generated,
    ADD COLUMN asset_id BIGINT DEFAULT NULL AFTER pm_schedule_id,
    ADD FOREIGN KEY (pm_schedule_id) REFERENCES pm_schedules(id) ON DELETE SET NULL;
