-- migration_branches_transfers_planning.sql
-- Nâng cấp hệ thống: Chi nhánh, Cơ cấu tổ chức (Org Chart), Thuyên chuyển công tác và Quy hoạch nhân sự

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Bảng Chi nhánh (Branches)
CREATE TABLE IF NOT EXISTS `branches` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `address` VARCHAR(255) NULL,
    `phone` VARCHAR(20) NULL,
    `email` VARCHAR(100) NULL,
    `manager_id` INT NULL,
    `target_headcount` INT DEFAULT 50 COMMENT 'Định biên nhân sự mục tiêu',
    `is_headquarter` TINYINT(1) DEFAULT 0 COMMENT '1: Trụ sở chính, 0: Chi nhánh',
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Thêm dữ liệu chi nhánh mẫu
INSERT INTO `branches` (`id`, `code`, `name`, `address`, `phone`, `email`, `target_headcount`, `is_headquarter`, `status`, `notes`) VALUES
(1, 'HQ-HN', 'Trụ Sở Chính - Hà Nội', 'Tầng 12, Tòa nhà Landmark 72, Phạm Hùng, Nam Từ Liêm, Hà Nội', '024 3888 9999', 'hq@coquan.gov.vn', 60, 1, 'active', 'Trụ sở điều hành cao nhất của cơ quan'),
(2, 'BR-HCM', 'Chi Nhánh Miền Nam - TP. Hồ Chí Minh', 'Số 180 Nguyễn Thị Minh Khai, Phường Võ Thị Sáu, Quận 3, TP.HCM', '028 3999 8888', 'hcm@coquan.gov.vn', 45, 0, 'active', 'Phụ trách toàn bộ khu vực Đông Nam Bộ'),
(3, 'BR-DNG', 'Chi Nhánh Miền Trung - Đà Nẵng', 'Số 254 Nguyễn Văn Linh, Quận Thanh Khê, TP. Đà Nẵng', '0236 3777 666', 'danang@coquan.gov.vn', 30, 0, 'active', 'Đầu mối điều hành khu vực Duyên hải Miền Trung & Tây Nguyên'),
(4, 'BR-CT', 'Chi Nhánh Tây Nam Bộ - Cần Thơ', 'Số 88 đường 30 Tháng 4, Quận Ninh Kiều, TP. Cần Thơ', '0292 3666 555', 'cantho@coquan.gov.vn', 25, 0, 'active', 'Phụ trách khu vực Đồng bằng Sông Cửu Long')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. Cập nhật bảng departments: Thêm branch_id, parent_id, manager_id nếu chưa có
SET @dbname = DATABASE();
SET @tablename = 'departments';

-- Thêm branch_id
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'branch_id') > 0,
  "SELECT 1",
  "ALTER TABLE departments ADD COLUMN branch_id INT DEFAULT 1 AFTER description"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Thêm parent_id
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'parent_id') > 0,
  "SELECT 1",
  "ALTER TABLE departments ADD COLUMN parent_id INT DEFAULT NULL AFTER branch_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Thêm manager_id
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'manager_id') > 0,
  "SELECT 1",
  "ALTER TABLE departments ADD COLUMN manager_id INT DEFAULT NULL AFTER parent_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Cập nhật branch_id mặc định cho các phòng ban hiện có
UPDATE departments SET branch_id = 1 WHERE branch_id IS NULL;

-- Thêm các phòng ban chi nhánh mẫu phong phú
INSERT INTO `departments` (`id`, `code`, `name`, `description`, `branch_id`, `parent_id`) VALUES
(4, 'HCM_KD', 'Phòng Phát Triển Thị Trường TP.HCM', 'Phát triển khách hàng và dự án phía Nam', 2, NULL),
(5, 'HCM_KT', 'Bộ Phận Kỹ Thuật Chi Nhánh TP.HCM', 'Hỗ trợ kỹ thuật và triển khai hạ tầng', 2, NULL),
(6, 'DNG_VP', 'Văn Phòng Điều Hành Đà Nẵng', 'Quản lý hành chính và vận hành miền Trung', 3, NULL),
(7, 'CT_DV', 'Bộ Phận Dịch Vụ Khách Hàng Cần Thơ', 'Chăm sóc và tiếp nhận đối tác miền Tây', 4, NULL)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 3. Cập nhật bảng employees: Thêm branch_id
SET @tablename = 'employees';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'branch_id') > 0,
  "SELECT 1",
  "ALTER TABLE employees ADD COLUMN branch_id INT DEFAULT 1 AFTER avatar"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

UPDATE employees SET branch_id = 1 WHERE branch_id IS NULL;

-- Thêm một số nhân viên mẫu tại các chi nhánh để trực quan hóa
INSERT INTO `employees` (`id`, `employee_code`, `fullname`, `gender`, `birth_date`, `identity_card`, `phone`, `email`, `address`, `branch_id`, `department_id`, `position_id`, `hire_date`, `employment_status`) VALUES
(3, 'NV003', 'Trần Văn Hưng', 'Nam', '1988-06-12', '079088001234', '0903112233', 'hung.tran@coquan.gov.vn', 'Quận 1, TP.HCM', 2, 4, 1, '2023-03-15', 'official'),
(4, 'NV004', 'Nguyễn Thị Thu Hà', 'Nu', '1992-11-20', '079192005678', '0912445566', 'ha.nguyen@coquan.gov.vn', 'Bình Thạnh, TP.HCM', 2, 5, 2, '2024-01-10', 'official'),
(5, 'NV005', 'Đặng Quốc Bảo', 'Nam', '1990-04-05', '048090009876', '0935667788', 'bao.dang@coquan.gov.vn', 'Hải Châu, Đà Nẵng', 3, 6, 1, '2023-08-01', 'official'),
(6, 'NV006', 'Phạm Quỳnh Anh', 'Nu', '1995-09-18', '092195003456', '0978990011', 'anh.pham@coquan.gov.vn', 'Ninh Kiều, Cần Thơ', 4, 7, 2, '2024-05-20', 'probation')
ON DUPLICATE KEY UPDATE `fullname`=VALUES(`fullname`);

-- Cập nhật người đứng đầu chi nhánh
UPDATE branches SET manager_id = 1 WHERE id = 1;
UPDATE branches SET manager_id = 3 WHERE id = 2;
UPDATE branches SET manager_id = 5 WHERE id = 3;
UPDATE branches SET manager_id = 6 WHERE id = 4;

-- 4. Bảng Quyết định Thuyên Chuyển Công Tác (transfers)
CREATE TABLE IF NOT EXISTS `transfers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `transfer_code` VARCHAR(50) NOT NULL UNIQUE,
    `employee_id` INT NOT NULL,
    `from_branch_id` INT NOT NULL,
    `to_branch_id` INT NOT NULL,
    `from_department_id` INT NULL,
    `to_department_id` INT NOT NULL,
    `from_position_id` INT NULL,
    `to_position_id` INT NOT NULL,
    `transfer_type` ENUM('branch_transfer', 'promotion', 'rotation', 'special_assignment', 'demotion') DEFAULT 'branch_transfer',
    `reason` TEXT NOT NULL,
    `effective_date` DATE NOT NULL,
    `decision_number` VARCHAR(100) NULL COMMENT 'Số quyết định / văn bản điều động',
    `allowance_support` DECIMAL(15,2) DEFAULT 0 COMMENT 'Phụ cấp chuyển vùng / trợ cấp thuyên chuyển',
    `status` ENUM('pending', 'approved', 'executed', 'rejected', 'cancelled') DEFAULT 'pending',
    `requested_by` INT NULL,
    `approved_by` INT NULL,
    `approved_at` DATETIME NULL,
    `executed_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_branch_id`) REFERENCES `branches`(`id`),
    FOREIGN KEY (`to_branch_id`) REFERENCES `branches`(`id`),
    FOREIGN KEY (`to_department_id`) REFERENCES `departments`(`id`),
    FOREIGN KEY (`to_position_id`) REFERENCES `positions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng Kế Hoạch Quy Hoạch Điều Động Nhân Sự (transfer_plans)
CREATE TABLE IF NOT EXISTS `transfer_plans` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_code` VARCHAR(50) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `plan_type` ENUM('quarterly', 'annual', 'branch_expansion', 'emergency_rebalance') DEFAULT 'quarterly',
    `target_branch_id` INT NULL COMMENT 'Chi nhánh trọng điểm cần tăng cường (nếu có)',
    `status` ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `created_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`target_branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng Chi Tiết Kế Hoạch Điều Động (transfer_plan_items)
CREATE TABLE IF NOT EXISTS `transfer_plan_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `plan_id` INT NOT NULL,
    `employee_id` INT NOT NULL,
    `from_branch_id` INT NOT NULL,
    `to_branch_id` INT NOT NULL,
    `from_department_id` INT NULL,
    `to_department_id` INT NOT NULL,
    `from_position_id` INT NULL,
    `to_position_id` INT NOT NULL,
    `priority` ENUM('high', 'medium', 'low') DEFAULT 'medium',
    `rationale` TEXT NULL COMMENT 'Lý do/phương án đề xuất tối ưu',
    `estimated_allowance` DECIMAL(15,2) DEFAULT 0,
    `transfer_id` INT NULL COMMENT 'Liên kết sang phiếu transfer thực tế khi được bấm thực thi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`plan_id`) REFERENCES `transfer_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_branch_id`) REFERENCES `branches`(`id`),
    FOREIGN KEY (`to_branch_id`) REFERENCES `branches`(`id`),
    FOREIGN KEY (`transfer_id`) REFERENCES `transfers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Thêm Dữ Liệu Quyền Hạn (Permissions) Mới
INSERT INTO `permissions` (`module`, `action`, `description`) VALUES
('branches', 'view', 'Xem danh sách & mạng lưới chi nhánh'),
('branches', 'create', 'Thêm mới chi nhánh'),
('branches', 'edit', 'Chỉnh sửa thông tin chi nhánh'),
('branches', 'delete', 'Xóa chi nhánh'),
('orgchart', 'view', 'Xem sơ đồ cơ cấu tổ chức & mạng lưới'),
('transfers', 'view', 'Xem danh sách thuyên chuyển cán bộ'),
('transfers', 'create', 'Lập phiếu đề xuất thuyên chuyển'),
('transfers', 'approve', 'Phê duyệt & thực thi quyết định thuyên chuyển'),
('transfers', 'delete', 'Hủy bỏ đề xuất thuyên chuyển'),
('planning', 'view', 'Xem kế hoạch quy hoạch nhân sự'),
('planning', 'create', 'Lập kế hoạch & chạy thuật toán phương án tối ưu'),
('planning', 'approve', 'Phê duyệt & kích hoạt kế hoạch quy hoạch'),
('planning', 'delete', 'Xóa kế hoạch quy hoạch')
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`);

-- Gán toàn bộ quyền mới cho Role 1 (Admin Nhân Sự)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p 
WHERE p.module IN ('branches', 'orgchart', 'transfers', 'planning');

-- 8. Thêm một số dữ liệu mẫu cho Transfers & Planning để người dùng xem ngay
INSERT INTO `transfers` (`id`, `transfer_code`, `employee_id`, `from_branch_id`, `to_branch_id`, `from_department_id`, `to_department_id`, `from_position_id`, `to_position_id`, `transfer_type`, `reason`, `effective_date`, `decision_number`, `allowance_support`, `status`, `requested_by`, `approved_by`, `approved_at`, `executed_at`, `notes`) VALUES
(1, 'TC-2026-001', 1, 1, 2, 1, 5, 2, 1, 'promotion', 'Điều động tăng cường chuyên gia công nghệ từ Trụ sở chính vào phát triển hệ thống cho Chi nhánh TP.HCM', '2026-04-01', 'QĐ-08/QĐ-TCCB', 5000000.00, 'approved', 1, 1, '2026-03-20 10:30:00', NULL, 'Hỗ trợ nhà ở và phụ cấp công tác xa 5 triệu/tháng')
ON DUPLICATE KEY UPDATE `transfer_code`=VALUES(`transfer_code`);

INSERT INTO `transfer_plans` (`id`, `plan_code`, `title`, `description`, `plan_type`, `target_branch_id`, `status`, `start_date`, `end_date`, `created_by`) VALUES
(1, 'PLAN-2026-Q2', 'Kế Hoạch Điều Động & Cân Bằng Nhân Lực Toàn Hệ Thống Q2/2026', 'Chiến lược luân chuyển cán bộ nòng cốt từ Hà Nội tăng cường cho các chi nhánh Đà Nẵng và Cần Thơ đang thiếu hụt chỉ tiêu định biên', 'quarterly', 3, 'active', '2026-04-01', '2026-06-30', 1)
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

INSERT INTO `transfer_plan_items` (`id`, `plan_id`, `employee_id`, `from_branch_id`, `to_branch_id`, `from_department_id`, `to_department_id`, `from_position_id`, `to_position_id`, `priority`, `rationale`, `estimated_allowance`) VALUES
(1, 1, 2, 1, 3, 2, 6, 3, 2, 'high', 'Phương án tối ưu: Điều động nhân sự thâm niên ngành Nhân sự từ Trụ sở hỗ trợ Chi nhánh Đà Nẵng hoàn thiện quy chuẩn', 4000000.00)
ON DUPLICATE KEY UPDATE `rationale`=VALUES(`rationale`);

SET FOREIGN_KEY_CHECKS = 1;
