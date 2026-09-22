-- migration_recruitment.sql
-- Nâng cấp Phase 2: Phân hệ Tuyển Dụng (Recruitment Pipeline)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Bảng Vị trí Tuyển dụng (job_positions)
CREATE TABLE IF NOT EXISTS `job_positions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã tin: TD-YYYY-XXX',
    `title` VARCHAR(255) NOT NULL COMMENT 'Tiêu đề vị trí tuyển dụng',
    `department_id` INT NULL COMMENT 'Phòng ban tiếp nhận',
    `branch_id` INT NULL COMMENT 'Chi nhánh làm việc',
    `position_id` INT NULL COMMENT 'Chức danh định ngạch',
    `quantity` INT DEFAULT 1 COMMENT 'Chỉ tiêu tuyển dụng',
    `salary_range_min` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Lương tối thiểu (VNĐ)',
    `salary_range_max` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Lương tối đa (VNĐ)',
    `requirements` TEXT NULL COMMENT 'Yêu cầu năng lực & kinh nghiệm',
    `description` TEXT NULL COMMENT 'Mô tả nhiệm vụ công việc',
    `deadline` DATE NULL COMMENT 'Hạn nộp hồ sơ',
    `status` ENUM('open', 'closed', 'paused', 'filled') DEFAULT 'open' COMMENT 'Trạng thái tin tuyển',
    `created_by` INT NULL COMMENT 'Người tạo tin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`position_id`) REFERENCES `positions`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng Hồ sơ Ứng viên (candidates)
CREATE TABLE IF NOT EXISTS `candidates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã hồ sơ: UV-YYYY-XXX',
    `job_position_id` INT NOT NULL COMMENT 'Vị trí ứng tuyển',
    `fullname` VARCHAR(100) NOT NULL COMMENT 'Họ và tên ứng viên',
    `email` VARCHAR(100) NOT NULL COMMENT 'Email liên hệ',
    `phone` VARCHAR(20) NOT NULL COMMENT 'Số điện thoại',
    `gender` ENUM('Nam', 'Nu', 'Khac') DEFAULT 'Nam',
    `birth_date` DATE NULL COMMENT 'Ngày sinh',
    `education` VARCHAR(255) NULL COMMENT 'Trình độ học vấn & chuyên ngành',
    `experience_years` INT DEFAULT 0 COMMENT 'Số năm kinh nghiệm',
    `cv_file` VARCHAR(255) NULL COMMENT 'Tệp CV scan đính kèm',
    `cover_letter` TEXT NULL COMMENT 'Thư xin việc / ghi chú ứng viên',
    `source` ENUM('website', 'referral', 'headhunt', 'job_board', 'social', 'other') DEFAULT 'website' COMMENT 'Nguồn tuyển dụng',
    `stage` ENUM('applied', 'screening', 'interview', 'offer', 'hired', 'rejected') DEFAULT 'applied' COMMENT 'Giai đoạn trong Pipeline',
    `rating` TINYINT DEFAULT 3 COMMENT 'Đánh giá năng lực từ 1 đến 5 sao',
    `interview_date` DATETIME NULL COMMENT 'Lịch hẹn phỏng vấn',
    `interview_notes` TEXT NULL COMMENT 'Biên bản & nhận xét phỏng vấn',
    `hired_employee_id` INT NULL COMMENT 'ID nhân viên khi đã chuyển đổi',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_position_id`) REFERENCES `job_positions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`hired_employee_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Cập nhật Permissions cho Tuyển Dụng
INSERT INTO `permissions` (`module`, `action`, `description`) VALUES
('recruitment', 'view', 'Xem bảng tin tuyển dụng & ứng viên'),
('recruitment', 'create', 'Tạo vị trí tuyển dụng & thêm hồ sơ ứng viên'),
('recruitment', 'edit', 'Cập nhật tiến độ & phỏng vấn ứng viên'),
('recruitment', 'hire', 'Tuyển dụng & chuyển ứng viên thành nhân viên'),
('recruitment', 'delete', 'Xóa hồ sơ ứng viên & vị trí tuyển dụng')
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`);

-- 4. Gán toàn bộ quyền mới cho Role 1 (Admin Quản Lý Nhân Sự)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p 
WHERE p.module = 'recruitment';

-- 5. Nạp dữ liệu mẫu Vị trí tuyển dụng
INSERT INTO `job_positions` (`id`, `job_code`, `title`, `department_id`, `branch_id`, `position_id`, `quantity`, `salary_range_min`, `salary_range_max`, `requirements`, `description`, `deadline`, `status`, `created_by`) VALUES
(1, 'TD-2026-001', 'Kỹ Sư Phần Mềm Fullstack (PHP & Vue/React)', 1, 1, 2, 3, 18000000.00, 28000000.00, 'Tối thiểu 2 năm kinh nghiệm phát triển Web. Nắm vững OOP, MySQL, RESTful API và Git.', 'Phát triển và bảo trì các hệ thống điều hành số hóa nội bộ cơ quan.', '2026-04-30', 'open', 1),
(2, 'TD-2026-002', 'Chuyên Viên Tuyển Dụng & Đào Tạo Nhân Lực', 2, 1, 1, 2, 12000000.00, 18000000.00, 'Tốt nghiệp Đại học chuyên ngành QTKD, Luật, Nhân sự. Kỹ năng giao tiếp và phỏng vấn tốt.', 'Lên kế hoạch đăng tin, sàng lọc hồ sơ và điều phối các vòng phỏng vấn chuyên môn.', '2026-04-15', 'open', 1),
(3, 'TD-2026-003', 'Trưởng Nhóm Kỹ Thuật Chi Nhánh TP.HCM', 5, 2, 1, 1, 25000000.00, 35000000.00, 'Kinh nghiệm quản lý nhóm từ 3 người trở lên. Am hiểu triển khai hạ tầng chi nhánh.', 'Điều hành công tác kỹ thuật và giải pháp dịch vụ tại khu vực miền Nam.', '2026-05-15', 'open', 1)
ON DUPLICATE KEY UPDATE `job_code`=VALUES(`job_code`);

-- 6. Nạp dữ liệu mẫu Hồ sơ Ứng viên trải dài qua các giai đoạn
INSERT INTO `candidates` (`id`, `candidate_code`, `job_position_id`, `fullname`, `email`, `phone`, `gender`, `birth_date`, `education`, `experience_years`, `source`, `stage`, `rating`, `interview_date`, `interview_notes`, `hired_employee_id`) VALUES
(1, 'UV-2026-001', 1, 'Nguyễn Hoàng Nam', 'nam.nguyen@email.com', '0912345678', 'Nam', '1996-03-15', 'Kỹ sư CNTT - ĐH Bách Khoa Hà Nội', 4, 'job_board', 'interview', 4, '2026-03-25 09:30:00', 'Ứng viên có kiến thức nền tảng vững, tư duy thuật toán tốt, tự tin trả lời phỏng vấn kỹ thuật.', NULL),
(2, 'UV-2026-002', 1, 'Lê Thị Mỹ Duyên', 'duyen.le@email.com', '0987654321', 'Nu', '1998-07-22', 'Cử nhân Phần mềm - ĐH FPT', 3, 'website', 'offer', 5, '2026-03-20 14:00:00', 'Đã pass vòng phỏng vấn chuyên môn xuất sắc. Đang gửi offer mức lương 22.000.000đ.', NULL),
(3, 'UV-2026-003', 2, 'Phạm Minh Tuấn', 'tuan.pm@email.com', '0903334455', 'Nam', '1995-11-08', 'Cử nhân Quản trị Nhân lực - ĐH Kinh Tế Quốc Dân', 5, 'referral', 'screening', 4, NULL, 'Hồ sơ đẹp, từng có kinh nghiệm tuyển dụng quy mô 500+ nhân sự.', NULL),
(4, 'UV-2026-004', 1, 'Vũ Thành Long', 'long.vt@email.com', '0934567890', 'Nam', '2000-01-18', 'Cử nhân Tin học - ĐH Công Nghệ', 1, 'social', 'applied', 3, NULL, 'Ứng viên tiềm năng mới tốt nghiệp, cần sàng lọc thêm kinh nghiệm thực tế.', NULL),
(5, 'UV-2026-005', 3, 'Đỗ Văn Thành', 'thanh.dv@email.com', '0977889900', 'Nam', '1990-09-12', 'Kỹ sư Điện tử Viễn thông - ĐH Bách Khoa TP.HCM', 7, 'headhunt', 'hired', 5, '2026-03-15 10:00:00', 'Chuyên gia cao cấp, đồng ý tiếp nhận vị trí Trưởng nhóm Kỹ thuật.', NULL),
(6, 'UV-2026-006', 2, 'Hoàng Thu Thảo', 'thao.ht@email.com', '0945671234', 'Nu', '1999-05-30', 'Cử nhân Luật Kinh Tế', 1, 'website', 'rejected', 2, '2026-03-12 15:30:00', 'Chưa đáp ứng đủ số năm kinh nghiệm theo yêu cầu khung vị trí.', NULL)
ON DUPLICATE KEY UPDATE `candidate_code`=VALUES(`candidate_code`);

SET FOREIGN_KEY_CHECKS = 1;
