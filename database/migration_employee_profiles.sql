-- database/migration_employee_profiles.sql
-- Phase 3: Hồ Sơ Nhân Viên Nâng Cao (Enhanced Employee Profiles)
-- Bổ sung 5 phân hệ dữ liệu: Học vấn, Chứng chỉ, Hợp đồng, Bảo hiểm, Người phụ thuộc

-- 1. Bảng Trình độ Học vấn (employee_education)
CREATE TABLE IF NOT EXISTS `employee_education` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `degree` VARCHAR(100) NOT NULL COMMENT 'Cử nhân, Kỹ sư, Thạc sĩ, Tiến sĩ, Cao đẳng...',
    `institution` VARCHAR(255) NOT NULL COMMENT 'Tên trường đại học / học viện / cơ sở đào tạo',
    `major` VARCHAR(200) NOT NULL COMMENT 'Chuyên ngành đào tạo',
    `graduation_year` INT NOT NULL COMMENT 'Năm tốt nghiệp',
    `gpa` VARCHAR(50) NULL COMMENT 'Điểm tốt nghiệp / Xếp loại',
    `certificate_file` VARCHAR(255) NULL COMMENT 'File scan bằng đại học / bảng điểm',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_education_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng Chứng chỉ chuyên môn & Ngoại ngữ (employee_certificates)
CREATE TABLE IF NOT EXISTS `employee_certificates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL COMMENT 'Tên chứng chỉ (PMP, AWS, IELTS, CPA...)',
    `issuer` VARCHAR(255) NOT NULL COMMENT 'Tổ chức cấp chứng chỉ',
    `issue_date` DATE NOT NULL COMMENT 'Ngày cấp',
    `expiry_date` DATE NULL COMMENT 'Ngày hết hạn (NULL nếu vĩnh viễn)',
    `certificate_file` VARCHAR(255) NULL COMMENT 'File scan chứng chỉ',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cert_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng Hợp đồng Lao động (employee_contracts)
CREATE TABLE IF NOT EXISTS `employee_contracts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `contract_number` VARCHAR(50) NOT NULL COMMENT 'Số hợp đồng',
    `contract_type` ENUM('probation', 'fixed_term', 'indefinite', 'seasonal') NOT NULL DEFAULT 'fixed_term' COMMENT 'Loại hợp đồng',
    `start_date` DATE NOT NULL COMMENT 'Ngày hiệu lực',
    `end_date` DATE NULL COMMENT 'Ngày hết hạn (NULL = Không thời hạn)',
    `base_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Mức lương cơ bản trên HĐ',
    `allowance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Phụ cấp thỏa thuận',
    `contract_file` VARCHAR(255) NULL COMMENT 'File scan hợp đồng (PDF/Ảnh)',
    `status` ENUM('active', 'expired', 'terminated') NOT NULL DEFAULT 'active' COMMENT 'Trạng thái',
    `notes` TEXT NULL COMMENT 'Ghi chú điều khoản',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_contract_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng Bảo hiểm Xã hội & Y tế (employee_insurance)
CREATE TABLE IF NOT EXISTS `employee_insurance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `insurance_type` ENUM('social', 'health', 'unemployment', 'commercial', 'accident') NOT NULL DEFAULT 'social' COMMENT 'Loại bảo hiểm',
    `insurance_number` VARCHAR(50) NOT NULL COMMENT 'Mã số sổ BHXH / Thẻ BHYT',
    `start_date` DATE NOT NULL COMMENT 'Ngày tham gia',
    `hospital_name` VARCHAR(255) NULL COMMENT 'Nơi đăng ký khám chữa bệnh ban đầu (cho BHYT)',
    `status` ENUM('active', 'suspended', 'closed') NOT NULL DEFAULT 'active' COMMENT 'Trạng thái',
    `notes` TEXT NULL COMMENT 'Ghi chú',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_insurance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng Người thân & Liên hệ khẩn cấp (employee_dependents)
CREATE TABLE IF NOT EXISTS `employee_dependents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `fullname` VARCHAR(150) NOT NULL COMMENT 'Họ và tên người thân',
    `relationship` ENUM('spouse', 'child', 'parent', 'sibling', 'other') NOT NULL DEFAULT 'child' COMMENT 'Mối quan hệ',
    `birth_date` DATE NULL COMMENT 'Ngày sinh',
    `phone` VARCHAR(20) NULL COMMENT 'Số điện thoại',
    `tax_deductible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Đăng ký giảm trừ gia cảnh (1: Có, 0: Không)',
    `is_emergency_contact` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Là người liên hệ khẩn cấp (1: Có, 0: Không)',
    `notes` VARCHAR(255) NULL COMMENT 'Nghề nghiệp / Ghi chú',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_dependent_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dữ liệu mẫu (Sample Seed Data)

-- Học vấn mẫu
INSERT INTO `employee_education` (`employee_id`, `degree`, `institution`, `major`, `graduation_year`, `gpa`, `certificate_file`) VALUES
(1, 'Cử nhân', 'Đại học Bách Khoa Hà Nội', 'Kỹ thuật Phần mềm (Software Engineering)', 2018, '3.62 / 4.0 (Giỏi)', NULL),
(1, 'Thạc sĩ', 'Đại học Quốc gia Hà Nội', 'Hệ thống Thông tin Quản lý', 2021, '3.80 / 4.0 (Xuất sắc)', NULL),
(3, 'Cử nhân', 'Đại học Kinh Tế Quốc Dân (NEU)', 'Quản trị Nhân lực', 2019, '3.45 / 4.0 (Khá Giỏi)', NULL),
(4, 'Cử nhân', 'Học viện Tài Chính', 'Kế toán Doanh nghiệp & Kiểm toán', 2020, '3.70 / 4.0 (Giỏi)', NULL),
(7, 'Cử nhân', 'Đại học Công Nghệ - ĐHQGHN', 'Khoa học Máy tính', 2022, '3.55 / 4.0 (Giỏi)', NULL);

-- Chứng chỉ mẫu
INSERT INTO `employee_certificates` (`employee_id`, `name`, `issuer`, `issue_date`, `expiry_date`, `certificate_file`) VALUES
(1, 'AWS Certified Solutions Architect – Associate', 'Amazon Web Services', '2023-04-15', '2026-04-15', NULL),
(1, 'Project Management Professional (PMP)', 'Project Management Institute (PMI)', '2022-09-10', '2025-09-10', NULL),
(1, 'Chứng chỉ Ngoại ngữ IELTS 7.5 Academic', 'British Council', '2021-11-20', NULL, NULL),
(3, 'Chứng chỉ Quản trị Nhân sự Chuyên nghiệp SHRM-CP', 'SHRM', '2023-01-18', '2026-01-18', NULL),
(4, 'Chứng chỉ Kế toán Trưởng Doanh nghiệp', 'Bộ Tài Chính Việt Nam', '2022-06-30', NULL, NULL),
(7, 'Oracle Certified Professional: Java SE 17 Developer', 'Oracle Corporation', '2023-08-12', NULL, NULL);

-- Hợp đồng lao động mẫu
INSERT INTO `employee_contracts` (`employee_id`, `contract_number`, `contract_type`, `start_date`, `end_date`, `base_salary`, `allowance`, `contract_file`, `status`, `notes`) VALUES
(1, 'HĐLĐ-2022/015', 'fixed_term', '2022-01-01', '2024-01-01', 22000000.00, 2000000.00, NULL, 'expired', 'Hợp đồng lao động xác định thời hạn 24 tháng'),
(1, 'HĐLĐ-2024/001', 'indefinite', '2024-01-02', NULL, 28000000.00, 3500000.00, NULL, 'active', 'Hợp đồng không xác định thời hạn sau khi tái ký thăng chức'),
(3, 'HĐLĐ-2023/048', 'fixed_term', '2023-03-01', '2026-03-01', 18000000.00, 1500000.00, NULL, 'active', 'Hợp đồng lao động 36 tháng'),
(4, 'HĐLĐ-2023/062', 'fixed_term', '2023-05-15', '2026-05-15', 19500000.00, 2000000.00, NULL, 'active', 'Hợp đồng lao động 36 tháng'),
(7, 'HĐLĐ-2026/099', 'probation', '2026-09-20', '2026-11-20', 16000000.00, 1000000.00, NULL, 'active', 'Hợp đồng thử việc 60 ngày tuyển từ Pipeline');

-- Bảo hiểm mẫu
INSERT INTO `employee_insurance` (`employee_id`, `insurance_type`, `insurance_number`, `start_date`, `hospital_name`, `status`, `notes`) VALUES
(1, 'social', 'BHXH-0123987456', '2022-01-01', NULL, 'active', 'Sổ BHXH đã đồng bộ VssID'),
(1, 'health', 'BHYT-DN4010123987456', '2022-01-01', 'Bệnh viện Bạch Mai - Hà Nội', 'active', 'Nơi đăng ký khám chữa bệnh ban đầu'),
(1, 'commercial', 'BVI-PRO-2026-088', '2024-01-01', 'Bảo hiểm Sức khỏe Cao cấp Bảo Việt Healthcare', 'active', 'Gói phúc lợi bảo hiểm sức khỏe VIP toàn diện'),
(3, 'social', 'BHXH-0791234567', '2023-03-01', NULL, 'active', 'Sổ BHXH cấp tại BHXH TP. Hồ Chí Minh'),
(3, 'health', 'BHYT-DN4790791234567', '2023-03-01', 'Bệnh viện Nhân dân Gia Định - TP.HCM', 'active', 'Thẻ BHYT diện doanh nghiệp'),
(4, 'social', 'BHXH-0488889999', '2023-05-15', NULL, 'active', 'Sổ BHXH chi nhánh Đà Nẵng'),
(7, 'social', 'BHXH-0199992222', '2026-09-20', NULL, 'active', 'Bắt đầu đóng bảo hiểm theo hợp đồng mới');

-- Người thân & Liên hệ khẩn cấp mẫu
INSERT INTO `employee_dependents` (`employee_id`, `fullname`, `relationship`, `birth_date`, `phone`, `tax_deductible`, `is_emergency_contact`, `notes`) VALUES
(1, 'Lê Minh Khang', 'child', '2021-06-12', NULL, 1, 0, 'Con trai đầu lòng (Đăng ký giảm trừ gia cảnh)'),
(1, 'Nguyễn Phương Thảo', 'spouse', '1995-10-05', '0988123456', 0, 1, 'Vợ - Giảng viên Đại học (Liên hệ khẩn cấp chính)'),
(1, 'Lê Văn Hùng', 'parent', '1962-03-15', '0912345678', 1, 0, 'Bố ruột (Đã nghỉ hưu, phụ thuộc giảm trừ gia cảnh)'),
(3, 'Trần Minh Anh', 'child', '2022-08-20', NULL, 1, 0, 'Con gái (Giảm trừ gia cảnh)'),
(4, 'Nguyễn Văn Tuấn', 'spouse', '1992-04-18', '0903456789', 0, 1, 'Chồng - Kỹ sư xây dựng (Liên hệ khẩn cấp)'),
(7, 'Đỗ Văn Toàn', 'parent', '1968-11-25', '0977654321', 0, 1, 'Bố ruột - Liên hệ gia đình');
