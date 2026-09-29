-- ==============================================================================
-- MIGRATION: MODULE ĐỀ XUẤT (PROPOSALS) & CẢI TIẾN CHẤM CÔNG
-- Tạo bảng proposals, thêm permissions, gán quyền, seed data
-- Thêm cột overtime/late/early vào bảng attendance
-- ==============================================================================

SET NAMES utf8mb4;
SET TIME_ZONE = '+07:00';

-- ============================================================
-- 1. CẢI TIẾN BẢNG ATTENDANCE — Thêm cột OT, đi muộn, về sớm
-- ============================================================

-- Kiểm tra và thêm cột nếu chưa tồn tại
SET @dbname = DATABASE();

-- Thêm cột overtime_hours
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'overtime_hours');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `attendance` ADD COLUMN `overtime_hours` DECIMAL(4,2) DEFAULT 0.00 COMMENT ''Số giờ làm thêm (OT)'' AFTER `check_out`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Thêm cột late_minutes
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'late_minutes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `attendance` ADD COLUMN `late_minutes` INT DEFAULT 0 COMMENT ''Số phút đi muộn'' AFTER `overtime_hours`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Thêm cột early_minutes
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'early_minutes');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `attendance` ADD COLUMN `early_minutes` INT DEFAULT 0 COMMENT ''Số phút về sớm'' AFTER `late_minutes`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Thêm cột proposal_id (liên kết đề xuất nghỉ phép)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'attendance' AND COLUMN_NAME = 'proposal_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `attendance` ADD COLUMN `proposal_id` INT NULL COMMENT ''Liên kết đề xuất nghỉ phép'' AFTER `note`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 2. TẠO BẢNG PROPOSALS
-- ============================================================

CREATE TABLE IF NOT EXISTS `proposals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `proposal_code` VARCHAR(30) NOT NULL UNIQUE COMMENT 'Mã đề xuất tự động (DX-2026-001)',
    `employee_id` INT NOT NULL COMMENT 'Người tạo đề xuất',
    `type` ENUM('leave', 'overtime', 'business_trip', 'salary_raise', 'equipment', 'other') NOT NULL DEFAULT 'leave',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề đề xuất',
    `description` TEXT NULL COMMENT 'Nội dung chi tiết',
    `start_date` DATE NULL COMMENT 'Ngày bắt đầu (nghỉ phép, công tác, tăng ca)',
    `end_date` DATE NULL COMMENT 'Ngày kết thúc',
    `leave_type` ENUM('annual', 'sick', 'maternity', 'personal', 'unpaid') NULL COMMENT 'Loại nghỉ phép (nếu type = leave)',
    `total_days` DECIMAL(4,1) DEFAULT 0 COMMENT 'Tổng số ngày đề xuất',
    `amount` DECIMAL(15,2) DEFAULT 0 COMMENT 'Số tiền liên quan (tăng lương, thiết bị...)',
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `approved_by` INT NULL COMMENT 'Người phê duyệt',
    `approved_at` DATETIME NULL COMMENT 'Thời gian phê duyệt',
    `rejection_reason` TEXT NULL COMMENT 'Lý do từ chối',
    `attachment` VARCHAR(255) NULL COMMENT 'File đính kèm',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_type` (`type`),
    INDEX `idx_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. THÊM PERMISSIONS CHO MODULE PROPOSALS
-- ============================================================

INSERT IGNORE INTO `permissions` (`module`, `action`, `description`) VALUES
('proposals', 'view', 'Xem danh sách & chi tiết đề xuất'),
('proposals', 'create', 'Tạo đề xuất mới'),
('proposals', 'approve', 'Phê duyệt / từ chối đề xuất'),
('proposals', 'delete', 'Xóa đề xuất');

-- ============================================================
-- 4. GÁN QUYỀN CHO CÁC ROLES
-- ============================================================

-- Role 1 (Super Admin): Tất cả quyền proposals
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) 
SELECT 1, id FROM `permissions` WHERE module = 'proposals';

-- Role 2 (Branch Director): view, create, approve
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE module = 'proposals' AND action IN ('view', 'create', 'approve');

-- Role 3 (HR Executive): view, create
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE module = 'proposals' AND action IN ('view', 'create');

-- ============================================================
-- 5. SEED DATA — 7 ĐỀ XUẤT MẪU
-- ============================================================

INSERT INTO `proposals` (`proposal_code`, `employee_id`, `type`, `title`, `description`, `start_date`, `end_date`, `leave_type`, `total_days`, `amount`, `priority`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `created_at`) VALUES
('DX-2026-001', 5, 'leave', 'Xin nghỉ phép năm 3 ngày', 'Đề xuất nghỉ phép năm để giải quyết việc gia đình tại quê Thanh Hóa. Đã bàn giao công việc cho đồng nghiệp Trần Thị Hương Giang.', '2026-10-05', '2026-10-07', 'annual', 3.0, 0, 'normal', 'approved', 1, '2026-09-28 10:30:00', NULL, '2026-09-27 09:00:00'),
('DX-2026-002', 8, 'overtime', 'Đề xuất tăng ca hoàn thiện dự án Cloud Migration', 'Cần tăng ca 3 ngày liên tiếp (18:00 - 21:00) để hoàn thiện giai đoạn deployment hệ thống ERP lên AWS Cloud. Đã được Tech Lead phê duyệt về mặt kỹ thuật.', '2026-10-01', '2026-10-03', NULL, 3.0, 0, 'high', 'pending', NULL, NULL, NULL, '2026-09-28 14:00:00'),
('DX-2026-003', 11, 'business_trip', 'Công tác Chi Nhánh Đà Nẵng khảo sát mặt bằng mới', 'Đi công tác kết hợp khảo sát và đàm phán hợp đồng thuê mặt bằng cửa hàng flagship mới tại khu vực Ngũ Hành Sơn. Dự kiến chi phí di chuyển + lưu trú: 12.000.000đ.', '2026-10-10', '2026-10-14', NULL, 5.0, 12000000, 'normal', 'approved', 2, '2026-09-29 08:45:00', NULL, '2026-09-28 16:30:00'),
('DX-2026-004', 9, 'leave', 'Xin nghỉ ốm 1 ngày', 'Bị cảm sốt cao 39°C, cần nghỉ ngơi theo chỉ dẫn bác sĩ phòng khám đa khoa quận Cầu Giấy. Có giấy xác nhận nghỉ ốm đính kèm.', '2026-09-29', '2026-09-29', 'sick', 1.0, 0, 'urgent', 'approved', 1, '2026-09-29 07:30:00', NULL, '2026-09-29 06:45:00'),
('DX-2026-005', 12, 'equipment', 'Đề xuất cấp màn hình phụ 27 inch cho quản lý cửa hàng', 'Cần màn hình phụ Dell UltraSharp U2723QE 27" 4K để theo dõi hệ thống camera an ninh và dashboard bán hàng POS đồng thời. Hiện tại chỉ có 1 màn hình, gây khó khăn trong vận hành.', NULL, NULL, NULL, 0, 8500000, 'normal', 'pending', NULL, NULL, NULL, '2026-09-28 11:00:00'),
('DX-2026-006', 16, 'salary_raise', 'Đề xuất xem xét tăng lương theo hiệu suất Q3/2026', 'Hoàn thành vượt 130% KPI logistics quý 3/2026. Đề xuất điều chỉnh tăng 3.000.000đ/tháng theo chính sách đãi ngộ nhân tài và giữ chân cán bộ chủ chốt khối SCM.', NULL, NULL, NULL, 0, 3000000, 'high', 'rejected', 1, '2026-09-28 16:00:00', 'Đợi kết quả đánh giá KPI toàn khối SCM cuối quý 3 trước khi xem xét điều chỉnh. Sẽ tái xem xét vào tháng 11/2026.', '2026-09-26 09:00:00'),
('DX-2026-007', 3, 'leave', 'Nghỉ phép việc riêng 2 ngày', 'Tham dự lễ tốt nghiệp Thạc sĩ Quản trị Kinh doanh của con gái tại Đại học Kinh Tế TP.HCM. Đã sắp xếp phó phòng phụ trách trong thời gian vắng.', '2026-10-15', '2026-10-16', 'personal', 2.0, 0, 'low', 'pending', NULL, NULL, NULL, '2026-09-29 08:00:00');
