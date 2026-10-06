-- migration_workforce_planning_optimization.sql
-- Nâng cấp toàn diện phân hệ Kế Hoạch & Đề Xuất Tối Ưu Nhân Lực
-- Bổ sung định biên phòng ban và quy trình Đề xuất nhân lực từ Chi nhánh lên Tập đoàn

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Bổ sung cột target_headcount cho bảng departments nếu chưa có
SET @dbname = DATABASE();
SET @tablename = 'departments';

SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'target_headcount') > 0,
  "SELECT 1",
  "ALTER TABLE departments ADD COLUMN target_headcount INT DEFAULT 10 COMMENT 'Định biên nhân sự phòng ban' AFTER description"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Cập nhật định biên mục tiêu cho các phòng ban hiện hữu để có số liệu chuẩn xác
UPDATE departments SET target_headcount = 3 WHERE code = 'BGD';
UPDATE departments SET target_headcount = 12 WHERE code = 'HR';
UPDATE departments SET target_headcount = 15 WHERE code = 'IT';
UPDATE departments SET target_headcount = 20 WHERE code = 'RETAIL';
UPDATE departments SET target_headcount = 18 WHERE code = 'SCM';
UPDATE departments SET target_headcount = 12 WHERE code = 'FIN';
UPDATE departments SET target_headcount = 10 WHERE code = 'MKT';
UPDATE departments SET target_headcount = 8 WHERE code = 'CX';
UPDATE departments SET target_headcount = 8 WHERE code = 'LEGAL';

-- Bổ sung thêm các phòng ban chi nhánh còn thiếu nếu chưa có
INSERT INTO departments (id, code, name, description, target_headcount, branch_id) VALUES
(10, 'CT_LOG', 'Bộ Phận Vận Hành Kho Vận Cần Thơ', 'Trung tâm trung chuyển Mekong', 12, 4),
(11, 'HP_XNK', 'Phòng Xuất Nhập Khẩu Cảng Hải Phòng', 'Điều phối luồng hàng cảng biển', 10, 5),
(12, 'BD_RETAIL', 'Chi Nhánh Vận Hành Chuỗi Siêu Thị Bình Dương', 'Mạng lưới bán lẻ Đông Nam Bộ', 15, 6)
ON DUPLICATE KEY UPDATE `target_headcount` = VALUES(`target_headcount`);

-- 2. Bổ sung bảng branch_workforce_requests (Đề Xuất Nhu Cầu & Tối Ưu Nhân Lực Từ Chi Nhánh)
CREATE TABLE IF NOT EXISTS `branch_workforce_requests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `request_code` VARCHAR(50) NOT NULL UNIQUE,
    `branch_id` INT NOT NULL,
    `department_id` INT NULL,
    `position_id` INT NULL,
    `request_type` ENUM('request_headcount', 'surplus_relocation', 'internal_restructure', 'management_appointment') DEFAULT 'request_headcount',
    `title` VARCHAR(255) NOT NULL,
    `urgency` ENUM('normal', 'high', 'urgent') DEFAULT 'normal',
    `quantity` INT DEFAULT 1 COMMENT 'Số lượng nhân sự cần bổ sung/điều chuyển',
    `candidate_id` INT NULL COMMENT 'Nhân sự tiến cử hoặc đề xuất luân chuyển (nếu có)',
    `rationale` TEXT NOT NULL COMMENT 'Căn cứ, lý do và giải trình chi tiết',
    `estimated_cost` DECIMAL(15,2) DEFAULT 0 COMMENT 'Dự toán ngân sách / phụ cấp hỗ trợ',
    `status` ENUM('pending', 'approved', 'rejected', 'incorporated') DEFAULT 'pending',
    `reviewed_by` INT NULL,
    `reviewed_at` DATETIME NULL,
    `review_notes` TEXT NULL COMMENT 'Ý kiến phản hồi / phê duyệt từ Ban Nhân sự Tập đoàn',
    `linked_plan_id` INT NULL COMMENT 'Liên kết kế hoạch sau khi được tích hợp',
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`position_id`) REFERENCES `positions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`candidate_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`linked_plan_id`) REFERENCES `transfer_plans`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bổ sung quyền hạn mới nếu chưa có
INSERT INTO `permissions` (`module`, `action`, `description`) VALUES
('planning', 'request', 'Gửi và quản lý đề xuất nhân lực từ chi nhánh')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p WHERE p.module = 'planning';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p WHERE p.module = 'planning' AND p.action IN ('view', 'request');

-- 4. Thêm dữ liệu mẫu sống động cho branch_workforce_requests
INSERT INTO `branch_workforce_requests` (`id`, `request_code`, `branch_id`, `department_id`, `position_id`, `request_type`, `title`, `urgency`, `quantity`, `candidate_id`, `rationale`, `estimated_cost`, `status`, `reviewed_by`, `reviewed_at`, `review_notes`, `linked_plan_id`, `created_by`, `created_at`) VALUES
(1, 'DX-CT-2026-001', 4, 10, 14, 'request_headcount', 'Đề xuất bổ sung khẩn cấp 02 Chuyên viên Kho vận & Phân phối Vùng', 'urgent', 2, NULL, 'Sản lượng phân phối hàng nông sản và bách hóa tại Hub Cần Thơ tăng 45% trong Q2/2026, kho lạnh vận hành 3 ca liên tục gây quá tải nhân sự.', 8000000.00, 'pending', NULL, NULL, NULL, NULL, 1, '2026-04-02 08:30:00'),
(2, 'DX-HCM-2026-002', 2, 4, 8, 'management_appointment', 'Đề xuất tiếp nhận Quản lý Cửa hàng Flagship từ Hội sở biệt phái vào', 'high', 1, 46, 'Chuẩn bị khai trương Flagship Store Quận 1 trong tháng 5/2026, cần nhân sự quản lý có kinh nghiệm vận hành chuẩn mực tập đoàn để đào tạo đội ngũ mới.', 5000000.00, 'approved', 1, '2026-04-03 14:15:00', 'Đã thẩm định nhu cầu thực tế hợp lý. Đề nghị đưa vào kế hoạch quy hoạch Q2/2026.', NULL, 1, '2026-04-02 10:45:00'),
(3, 'DX-DNG-2026-003', 3, 5, 6, 'surplus_relocation', 'Đề xuất luân chuyển 01 Chuyên viên SCM sang hỗ trợ Chi nhánh Hải Phòng', 'normal', 1, 16, 'Sau khi tối ưu hóa tuyến vận tải miền Trung, chi nhánh Đà Nẵng có thể bố trí 01 nhân sự giàu kinh nghiệm sang hỗ trợ Hub Cảng Hải Phòng thiết lập hệ thống WMS.', 4000000.00, 'incorporated', 1, '2026-04-04 09:20:00', 'Đã tích hợp thành công vào Kế hoạch mở rộng Hải Phòng PLAN-2026-HAI-PHONG.', 2, 1, '2026-04-03 16:00:00'),
(4, 'DX-HP-2026-004', 5, 11, 12, 'request_headcount', 'Xin bổ sung Chuyên viên Pháp chế Hợp đồng Ngoại thương cảng biển', 'high', 1, 31, 'Khối lượng hợp đồng ngoại thương và lưu kho bãi quốc tế tăng mạnh, cần nhân sự chuyên môn về luật hàng hải và thương mại quốc tế.', 4500000.00, 'pending', NULL, NULL, NULL, NULL, 1, '2026-04-05 11:10:00'),
(5, 'DX-BD-2026-005', 6, 12, 4, 'internal_restructure', 'Đề xuất kiện toàn tái cơ cấu bộ phận Giám sát Bán lẻ Bình Dương', 'normal', 2, NULL, 'Sáp nhập 2 cụm siêu thị nhỏ thành 1 cụm liên phường để tinh gọn biên chế quản lý và tối ưu hóa chi phí lương tại khu công nghiệp.', 0.00, 'pending', NULL, NULL, NULL, NULL, 1, '2026-04-06 09:00:00')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

SET FOREIGN_KEY_CHECKS = 1;
