-- ==============================================================================
-- MIGRATION: NÂNG CẤP BẢNG LƯƠNG & CHUẨN HÓA NGHIỆP VỤ THEO PHÁP LUẬT LAO ĐỘNG VN
-- Hỗ trợ: Biểu thuế TNCN 5 bậc, Mức giảm trừ gia cảnh mới, BHXH/BHYT/BHTN,
-- OT Điều 98, Khóa kỳ lương & Chặn xóa vi phạm.
-- ==============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. BẢNG NGÀY NGHỈ LỄ QUỐC GIA (PUBLIC HOLIDAYS)
CREATE TABLE IF NOT EXISTS `public_holidays` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `holiday_date` DATE NOT NULL UNIQUE COMMENT 'Ngày nghỉ lễ (YYYY-MM-DD)',
    `name` VARCHAR(150) NOT NULL COMMENT 'Tên ngày lễ (Tết Dương lịch, Giỗ Tổ, 30/4, 2/9...)',
    `is_compensatory` TINYINT(1) DEFAULT 0 COMMENT '1: Ngày nghỉ bù nếu lễ rơi vào cuối tuần',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nạp ngày lễ năm 2026 & 2027 (Điều 112 Bộ luật Lao động 2019)
INSERT INTO `public_holidays` (`holiday_date`, `name`, `is_compensatory`) VALUES
-- 2026
('2026-01-01', 'Tết Dương lịch 2026', 0),
('2026-02-16', 'Tết Nguyên Đán Bính Ngọ (29 Tết)', 0),
('2026-02-17', 'Tết Nguyên Đán Bính Ngọ (Mùng 1 Tết)', 0),
('2026-02-18', 'Tết Nguyên Đán Bính Ngọ (Mùng 2 Tết)', 0),
('2026-02-19', 'Tết Nguyên Đán Bính Ngọ (Mùng 3 Tết)', 0),
('2026-02-20', 'Tết Nguyên Đán Bính Ngọ (Mùng 4 Tết)', 0),
('2026-04-26', 'Giỗ Tổ Hùng Vương (10/3 ÂL)', 0),
('2026-04-27', 'Nghỉ bù Giỗ Tổ Hùng Vương (rơi vào Chủ Nhật)', 1),
('2026-04-30', 'Ngày Giải phóng Miền Nam 30/4', 0),
('2026-05-01', 'Ngày Quốc tế Lao động 1/5', 0),
('2026-09-01', 'Nghỉ liền kề Quốc Khánh 2/9', 0),
('2026-09-02', 'Ngày Quốc khánh Nước CHXHCN Việt Nam', 0),
-- 2027
('2027-01-01', 'Tết Dương lịch 2027', 0),
('2027-02-05', 'Tết Nguyên Đán Đinh Mùi (29 Tết)', 0),
('2027-02-06', 'Tết Nguyên Đán Đinh Mùi (Mùng 1 Tết)', 0),
('2027-02-07', 'Tết Nguyên Đán Đinh Mùi (Mùng 2 Tết)', 0),
('2027-02-08', 'Tết Nguyên Đán Đinh Mùi (Mùng 3 Tết)', 0),
('2027-02-09', 'Tết Nguyên Đán Đinh Mùi (Mùng 4 Tết)', 0),
('2027-04-16', 'Giỗ Tổ Hùng Vương 2027', 0),
('2027-04-30', 'Ngày Giải phóng Miền Nam 30/4', 0),
('2027-05-01', 'Ngày Quốc tế Lao động 1/5', 0),
('2027-09-02', 'Ngày Quốc khánh 2/9', 0),
('2027-09-03', 'Nghỉ liền kề Quốc Khánh 2/9', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 2. NÂNG CẤP BẢNG PAYROLLS: BỔ SUNG CỘT BREAKDOWN LƯƠNG & BẢO HIỂM, THUẾ
ALTER TABLE `payrolls`
  ADD COLUMN IF NOT EXISTS `contract_type` VARCHAR(20) NULL DEFAULT 'fixed_term' COMMENT 'Loại hợp đồng: probation, fixed_term, indefinite, seasonal' AFTER `year`,
  ADD COLUMN IF NOT EXISTS `standard_days` DECIMAL(4,1) NULL DEFAULT 22.0 COMMENT 'Số ngày công chuẩn của tháng (T2-T6)' AFTER `work_days`,
  ADD COLUMN IF NOT EXISTS `paid_leave_days` DECIMAL(4,1) DEFAULT 0.0 COMMENT 'Số ngày nghỉ phép có hưởng lương' AFTER `standard_days`,
  ADD COLUMN IF NOT EXISTS `holiday_days` DECIMAL(4,1) DEFAULT 0.0 COMMENT 'Số ngày nghỉ lễ hưởng nguyên lương' AFTER `paid_leave_days`,
  ADD COLUMN IF NOT EXISTS `unpaid_days` DECIMAL(4,1) DEFAULT 0.0 COMMENT 'Số ngày nghỉ không hưởng lương' AFTER `holiday_days`,
  ADD COLUMN IF NOT EXISTS `late_early_minutes` INT DEFAULT 0 COMMENT 'Tổng số phút đi muộn / về sớm trong tháng' AFTER `unpaid_days`,
  ADD COLUMN IF NOT EXISTS `time_salary` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tiền lương thời gian thực tế' AFTER `basic_salary`,
  ADD COLUMN IF NOT EXISTS `contract_allowance` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Phụ cấp thỏa thuận trong HĐ theo ngày công' AFTER `allowance`,
  ADD COLUMN IF NOT EXISTS `bonus` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Khen thưởng / thưởng hiệu quả trong tháng' AFTER `contract_allowance`,
  ADD COLUMN IF NOT EXISTS `ot_weekday_hours` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'Giờ OT ngày thường (150%)' AFTER `bonus`,
  ADD COLUMN IF NOT EXISTS `ot_weekend_hours` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'Giờ OT ngày nghỉ cuối tuần (200%)' AFTER `ot_weekday_hours`,
  ADD COLUMN IF NOT EXISTS `ot_holiday_hours` DECIMAL(6,2) DEFAULT 0.00 COMMENT 'Giờ OT ngày lễ tết (300%)' AFTER `ot_weekend_hours`,
  ADD COLUMN IF NOT EXISTS `ot_pay` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tổng tiền làm thêm giờ (OT)' AFTER `ot_holiday_hours`,
  ADD COLUMN IF NOT EXISTS `ot_tax_exempt` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Phần tiền OT được miễn thuế TNCN' AFTER `ot_pay`,
  ADD COLUMN IF NOT EXISTS `gross_salary` DECIMAL(15,2) NULL DEFAULT 0.00 COMMENT 'Tổng thu nhập trước thuế (GROSS)' AFTER `ot_tax_exempt`,
  ADD COLUMN IF NOT EXISTS `insurance_salary` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Mức tiền lương làm căn cứ đóng BHXH/BHYT/BHTN' AFTER `gross_salary`,
  ADD COLUMN IF NOT EXISTS `bhxh_employee` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'BHXH người lao động đóng (8%)' AFTER `insurance_salary`,
  ADD COLUMN IF NOT EXISTS `bhyt_employee` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'BHYT người lao động đóng (1.5%)' AFTER `bhxh_employee`,
  ADD COLUMN IF NOT EXISTS `bhtn_employee` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'BHTN người lao động đóng (1%)' AFTER `bhyt_employee`,
  ADD COLUMN IF NOT EXISTS `dependents_count` TINYINT DEFAULT 0 COMMENT 'Số người phụ thuộc đăng ký giảm trừ' AFTER `bhtn_employee`,
  ADD COLUMN IF NOT EXISTS `family_deduction` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tổng giảm trừ gia cảnh (bản thân + người phụ thuộc)' AFTER `dependents_count`,
  ADD COLUMN IF NOT EXISTS `assessable_income` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Thu nhập tính thuế TNCN' AFTER `family_deduction`,
  ADD COLUMN IF NOT EXISTS `tax_method` VARCHAR(15) NULL DEFAULT 'progressive' COMMENT 'Phương pháp tính thuế: progressive (lũy tiến), flat10 (khấu trừ 10%)' AFTER `assessable_income`,
  ADD COLUMN IF NOT EXISTS `pit_amount` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Thuế TNCN phải nộp' AFTER `tax_method`,
  ADD COLUMN IF NOT EXISTS `compensation_deduction` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Khấu trừ bồi thường thiệt hại (Điều 102 BLLĐ <= 30% lương)' AFTER `deduction`,
  ADD COLUMN IF NOT EXISTS `employer_insurance` DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Doanh nghiệp đóng bảo hiểm & KPCĐ (23.5%)' AFTER `final_salary`,
  ADD COLUMN IF NOT EXISTS `calc_warnings` TEXT NULL COMMENT 'Cảnh báo vi phạm hoặc ngoại lệ pháp lý' AFTER `employer_insurance`,
  ADD COLUMN IF NOT EXISTS `locked_at` DATETIME NULL COMMENT 'Thời điểm chốt bảng lương' AFTER `calc_warnings`,
  ADD COLUMN IF NOT EXISTS `locked_by` INT NULL COMMENT 'ID người thực hiện chốt bảng lương' AFTER `locked_at`;

-- Khóa các bảng lương đã thanh toán trước đó
UPDATE `payrolls` SET `locked_at` = COALESCE(`locked_at`, `created_at`) WHERE `payment_status` = 'paid' AND `locked_at` IS NULL;
UPDATE `payrolls` SET `gross_salary` = `final_salary` WHERE `gross_salary` IS NULL OR `gross_salary` = 0;

-- 3. NÂNG CẤP BẢNG ATTENDANCE: PHÂN BIỆT RÕ LOẠI NGHỈ PHÉP
ALTER TABLE `attendance`
  ADD COLUMN IF NOT EXISTS `leave_type` VARCHAR(20) NULL COMMENT 'Loại nghỉ: annual (phép năm), sick (ốm đau), maternity (thai sản), personal (việc riêng), unpaid (không lương)' AFTER `status`;

-- Đồng bộ leave_type từ các đề xuất đã có
UPDATE `attendance` a
JOIN `proposals` p ON a.proposal_id = p.id
SET a.leave_type = p.leave_type
WHERE a.leave_type IS NULL AND p.type = 'leave';

-- 4. BỔ SUNG VÙNG LƯƠNG TỐI THIỂU CHO BẢNG BRANCHES
ALTER TABLE `branches`
  ADD COLUMN IF NOT EXISTS `wage_region` TINYINT NOT NULL DEFAULT 1 COMMENT 'Vùng lương tối thiểu áp dụng theo NĐ 293/2025/NĐ-CP (1, 2, 3, 4)' AFTER `target_headcount`;

-- Gán vùng lương mặc định cho các chi nhánh thực tế
UPDATE `branches` SET `wage_region` = 1 WHERE `code` IN ('HQ-HN', 'BR-HCM'); -- Hà Nội, TP.HCM: Vùng I
UPDATE `branches` SET `wage_region` = 1 WHERE `code` IN ('BR-DNG', 'BR-CT'); -- Đà Nẵng, Cần Thơ (các quận trung tâm): Vùng I

-- 5. CHUẨN HÓA HÌNH THỨC KỶ LUẬT (ĐIỀU 124 BỘ LUẬT LAO ĐỘNG 2019)
-- Bỏ 'salary_cut' (trừ lương - hành vi bị nghiêm cấm theo Điều 127) chuyển thành 'salary_raise_delay' (Kéo dài thời hạn nâng bậc lương)
ALTER TABLE `disciplines` MODIFY `discipline_type` ENUM('warning', 'reprimand', 'salary_cut', 'salary_raise_delay', 'demotion', 'termination') DEFAULT 'warning';
UPDATE `disciplines` SET `discipline_type` = 'salary_raise_delay' WHERE `discipline_type` = 'salary_cut';
ALTER TABLE `disciplines` MODIFY `discipline_type` ENUM('warning', 'reprimand', 'salary_raise_delay', 'demotion', 'termination') DEFAULT 'warning';

SET FOREIGN_KEY_CHECKS = 1;
