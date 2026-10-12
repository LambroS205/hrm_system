-- database/migrations/2026_10_12_kpi_hierarchy_system.sql
-- Nâng cấp hệ thống KPI phân tầng cấp bậc (Hierarchical BSC & SMART KPI System theo Link Power)

-- 1. Bổ sung trường cấp bậc vào kpi_templates
ALTER TABLE `kpi_templates` 
ADD COLUMN `hierarchy_level` ENUM('executive', 'manager', 'specialist', 'operational') NOT NULL DEFAULT 'specialist' AFTER `department_id`,
ADD COLUMN `applicable_positions` TEXT NULL AFTER `hierarchy_level`;

-- 2. Bổ sung trường SMART & Nhóm ưu tiên vào kpi_template_items
ALTER TABLE `kpi_template_items`
ADD COLUMN `priority_group` ENUM('A', 'B', 'C') NOT NULL DEFAULT 'B' AFTER `criteria_name`,
ADD COLUMN `bsc_pillar` ENUM('financial', 'customer', 'internal_process', 'learning_growth') NOT NULL DEFAULT 'internal_process' AFTER `priority_group`,
ADD COLUMN `unit` VARCHAR(50) NOT NULL DEFAULT '%' AFTER `target_type`,
ADD COLUMN `calc_direction` ENUM('higher_better', 'lower_better', 'boolean') NOT NULL DEFAULT 'higher_better' AFTER `target_value`,
ADD COLUMN `data_source` VARCHAR(150) NULL AFTER `calc_direction`,
ADD COLUMN `min_threshold` DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER `data_source`,
ADD COLUMN `max_cap` DECIMAL(5,2) NOT NULL DEFAULT 120.00 AFTER `min_threshold`;

-- 3. Bổ sung hệ số thưởng Total Rewards và phê duyệt chuẩn chi vào evaluations
ALTER TABLE `evaluations`
ADD COLUMN `bonus_multiplier` DECIMAL(4,2) NOT NULL DEFAULT 1.00 AFTER `rating`,
ADD COLUMN `hr_approved_at` DATETIME NULL AFTER `promotion_recommendation`,
ADD COLUMN `hr_approved_by` INT(11) NULL AFTER `hr_approved_at`;
