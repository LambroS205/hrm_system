-- migration_rewards_disciplines.sql
-- Nâng cấp Phase 1: Phân hệ Khen Thưởng & Kỷ Luật (Rewards & Disciplines)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Bảng Khen Thưởng (rewards)
CREATE TABLE IF NOT EXISTS `rewards` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reward_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã quyết định: KT-YYYY-XXX',
    `employee_id` INT NOT NULL COMMENT 'Nhân viên được khen thưởng',
    `reward_type` ENUM('bonus', 'certificate', 'promotion_bonus', 'achievement') DEFAULT 'bonus' COMMENT 'Hình thức khen thưởng',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề khen thưởng',
    `description` TEXT NULL COMMENT 'Lý do & thành tích cụ thể',
    `amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tiền thưởng kèm theo (nếu có)',
    `decision_number` VARCHAR(100) NULL COMMENT 'Số quyết định / văn bản ban hành',
    `reward_date` DATE NOT NULL COMMENT 'Ngày ban hành quyết định',
    `status` ENUM('pending', 'approved', 'executed', 'rejected') DEFAULT 'pending' COMMENT 'Trạng thái quy trình',
    `attachment` VARCHAR(255) NULL COMMENT 'Tệp văn bản scan đính kèm',
    `created_by` INT NULL COMMENT 'Người lập đề xuất',
    `approved_by` INT NULL COMMENT 'Lãnh đạo phê duyệt',
    `approved_at` DATETIME NULL,
    `executed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng Kỷ Luật (disciplines)
CREATE TABLE IF NOT EXISTS `disciplines` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `discipline_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã biên bản: KL-YYYY-XXX',
    `employee_id` INT NOT NULL COMMENT 'Nhân viên bị xử lý kỷ luật',
    `discipline_type` ENUM('warning', 'reprimand', 'salary_cut', 'demotion', 'termination') DEFAULT 'warning' COMMENT 'Hình thức kỷ luật',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề / Hành vi vi phạm',
    `description` TEXT NULL COMMENT 'Nội dung chi tiết vi phạm & kết luận',
    `penalty_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Số tiền phạt / khấu trừ (nếu có)',
    `decision_number` VARCHAR(100) NULL COMMENT 'Số quyết định kỷ luật',
    `discipline_date` DATE NOT NULL COMMENT 'Ngày lập biên bản / ban hành quyết định',
    `status` ENUM('pending', 'approved', 'executed', 'cancelled') DEFAULT 'pending' COMMENT 'Trạng thái quy trình',
    `attachment` VARCHAR(255) NULL COMMENT 'Tệp biên bản scan đính kèm',
    `created_by` INT NULL COMMENT 'Người lập biên bản',
    `approved_by` INT NULL COMMENT 'Lãnh đạo phê duyệt',
    `approved_at` DATETIME NULL,
    `executed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Cập nhật bảng Permissions với các quyền mới
INSERT INTO `permissions` (`module`, `action`, `description`) VALUES
('rewards', 'view', 'Xem danh sách & chi tiết khen thưởng'),
('rewards', 'create', 'Lập quyết định khen thưởng mới'),
('rewards', 'approve', 'Phê duyệt & thực thi quyết định khen thưởng'),
('rewards', 'delete', 'Xóa quyết định khen thưởng'),
('disciplines', 'view', 'Xem danh sách & chi tiết kỷ luật'),
('disciplines', 'create', 'Lập biên bản / quyết định kỷ luật mới'),
('disciplines', 'approve', 'Phê duyệt & thực thi quyết định kỷ luật'),
('disciplines', 'delete', 'Xóa quyết định kỷ luật')
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`);

-- 4. Gán toàn bộ quyền mới cho Role 1 (Admin Quản Lý Nhân Sự)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p 
WHERE p.module IN ('rewards', 'disciplines');

-- 5. Nạp dữ liệu mẫu Khen Thưởng
INSERT INTO `rewards` (`id`, `reward_code`, `employee_id`, `reward_type`, `title`, `description`, `amount`, `decision_number`, `reward_date`, `status`, `created_by`, `approved_by`, `approved_at`, `executed_at`) VALUES
(1, 'KT-2026-001', 1, 'achievement', 'Thành tích xuất sắc trong Dự án Chuyển đổi số toàn diện', 'Đã dẫn dắt đội ngũ kỹ thuật hoàn thành vượt tiến độ hệ thống điều hành số hóa 2026.', 5000000.00, 'QĐ-12/QĐ-KT', '2026-03-01', 'executed', 1, 1, '2026-03-02 09:00:00', '2026-03-05 14:30:00'),
(2, 'KT-2026-002', 3, 'bonus', 'Sáng kiến tối ưu mạng lưới chi nhánh TP.HCM', 'Đề xuất giải pháp tiết giảm 25% chi phí vận hành kho vận phía Nam.', 3000000.00, 'QĐ-18/QĐ-KT', '2026-03-10', 'approved', 1, 1, '2026-03-12 11:15:00', NULL),
(3, 'KT-2026-003', 4, 'certificate', 'Bằng khen Lao động Tiên tiến Quý I/2026', 'Hoàn thành xuất sắc 100% KPI chỉ tiêu hỗ trợ kỹ thuật khách hàng.', 1500000.00, 'QĐ-25/QĐ-KT', '2026-03-18', 'pending', 1, NULL, NULL, NULL),
(4, 'KT-2026-004', 5, 'promotion_bonus', 'Thưởng thăng tiến cán bộ nòng cốt chi nhánh Đà Nẵng', 'Thành tích nổi bật trong công tác kết nối đối tác khu vực miền Trung.', 4000000.00, 'QĐ-30/QĐ-KT', '2026-03-20', 'pending', 1, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE `reward_code`=VALUES(`reward_code`);

-- 6. Nạp dữ liệu mẫu Kỷ Luật
INSERT INTO `disciplines` (`id`, `discipline_code`, `employee_id`, `discipline_type`, `title`, `description`, `penalty_amount`, `decision_number`, `discipline_date`, `status`, `created_by`, `approved_by`, `approved_at`, `executed_at`) VALUES
(1, 'KL-2026-001', 2, 'warning', 'Nhắc nhở vi phạm quy định an toàn thông tin nội bộ', 'Để lộ tài khoản kiểm thử ra môi trường bên ngoài mà không thông báo kịp thời.', 0.00, 'BB-02/BB-KL', '2026-02-15', 'executed', 1, 1, '2026-02-16 10:00:00', '2026-02-18 08:30:00'),
(2, 'KL-2026-002', 6, 'reprimand', 'Khiển trách đi làm muộn nhiều lần không lý do chính đáng', 'Ghi nhận đi muộn 6 lần trong tháng 02/2026 mà không gửi đơn phép qua cổng.', 500000.00, 'QĐ-05/QĐ-KL', '2026-03-05', 'approved', 1, 1, '2026-03-06 15:00:00', NULL),
(3, 'KL-2026-003', 3, 'warning', 'Chậm trễ nộp báo cáo tổng kết tiến độ quý', 'Biên bản nhắc nhở lần đầu về việc bàn giao tài liệu kỹ thuật trễ hạn 5 ngày.', 0.00, 'BB-09/BB-KL', '2026-03-19', 'pending', 1, NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE `discipline_code`=VALUES(`discipline_code`);

SET FOREIGN_KEY_CHECKS = 1;
