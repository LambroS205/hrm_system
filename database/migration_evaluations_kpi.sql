-- ==============================================================================
-- MIGRATION: MODULE ĐÁNH GIÁ NHÂN VIÊN & KPI (EVALUATIONS & KPI)
-- Tạo 4 bảng: kpi_templates, kpi_template_items, evaluation_periods, evaluations
-- Thêm permissions, gán quyền roles, liên kết rewards
-- Seed data mẫu chất lượng cao
-- ==============================================================================

SET NAMES utf8mb4;
SET TIME_ZONE = '+07:00';

-- ============================================================
-- 1. TẠO BẢNG KPI_TEMPLATES (BỘ MẪU TIÊU CHÍ KPI)
-- ============================================================

CREATE TABLE IF NOT EXISTS `kpi_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã bộ tiêu chí (VD: KPI-TECH, KPI-RETAIL)',
    `name` VARCHAR(150) NOT NULL COMMENT 'Tên bộ tiêu chí KPI',
    `department_id` INT NULL COMMENT 'Khối phòng ban áp dụng (NULL = Toàn công ty)',
    `description` TEXT NULL COMMENT 'Mô tả mục tiêu áp dụng',
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_dept` (`department_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. TẠO BẢNG KPI_TEMPLATE_ITEMS (CHI TIẾT TIÊU CHÍ VÀ TỶ TRỌNG)
-- ============================================================

CREATE TABLE IF NOT EXISTS `kpi_template_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_id` INT NOT NULL COMMENT 'Liên kết kpi_templates.id',
    `criteria_name` VARCHAR(255) NOT NULL COMMENT 'Tên tiêu chí đánh giá',
    `description` TEXT NULL COMMENT 'Chi tiết đo lường và kỳ vọng',
    `weight` DECIMAL(5,2) NOT NULL DEFAULT 20.00 COMMENT 'Tỷ trọng % (Tổng các mục = 100%)',
    `target_type` ENUM('percentage', 'numeric', 'rating', 'boolean') NOT NULL DEFAULT 'percentage',
    `target_value` VARCHAR(100) NULL COMMENT 'Mục tiêu kỳ vọng (VD: >= 95%, 100 Triệu)',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_template` (`template_id`),
    CONSTRAINT `fk_template_items` FOREIGN KEY (`template_id`) REFERENCES `kpi_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. TẠO BẢNG EVALUATION_PERIODS (CÁC ĐỢT / KỲ ĐÁNH GIÁ HIỆU SUẤT)
-- ============================================================

CREATE TABLE IF NOT EXISTS `evaluation_periods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã kỳ đánh giá (VD: PERIOD-2026-Q3)',
    `name` VARCHAR(150) NOT NULL COMMENT 'Tên kỳ đánh giá',
    `type` ENUM('quarterly', 'annual', 'probation', 'project') NOT NULL DEFAULT 'quarterly',
    `year` INT NOT NULL COMMENT 'Năm đánh giá',
    `quarter` TINYINT NULL COMMENT 'Quý (1, 2, 3, 4)',
    `start_date` DATE NOT NULL COMMENT 'Ngày bắt đầu kỳ đánh giá',
    `end_date` DATE NOT NULL COMMENT 'Ngày kết thúc kỳ đánh giá',
    `self_review_deadline` DATE NULL COMMENT 'Hạn chót nhân viên tự đánh giá',
    `manager_review_deadline` DATE NULL COMMENT 'Hạn chót quản lý hoàn thành chấm điểm',
    `status` ENUM('upcoming', 'active', 'closed') NOT NULL DEFAULT 'active',
    `description` TEXT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_year_quarter` (`year`, `quarter`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. TẠO BẢNG EVALUATIONS (HỒ SƠ PHIẾU ĐÁNH GIÁ TỪNG NHÂN SỰ)
-- ============================================================

CREATE TABLE IF NOT EXISTS `evaluations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `evaluation_code` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Mã phiếu đánh giá (VD: DG-2026-Q3-001)',
    `period_id` INT NOT NULL COMMENT 'Liên kết evaluation_periods.id',
    `employee_id` INT NOT NULL COMMENT 'Nhân sự được đánh giá (employees.id)',
    `template_id` INT NOT NULL COMMENT 'Bộ tiêu chí áp dụng (kpi_templates.id)',
    `reviewer_id` INT NOT NULL COMMENT 'Quản lý trực tiếp chấm điểm (employees.id)',
    `status` ENUM('pending', 'self_review', 'manager_review', 'completed') NOT NULL DEFAULT 'pending',
    `self_score` DECIMAL(5,2) NULL COMMENT 'Điểm tự chấm (Thang 100)',
    `self_scores_json` JSON NULL COMMENT 'Dữ liệu JSON điểm chi tiết từng tiêu chí tự đánh giá',
    `self_notes` TEXT NULL COMMENT 'Nhân viên tự nhận xét',
    `self_submitted_at` DATETIME NULL,
    `manager_score` DECIMAL(5,2) NULL COMMENT 'Điểm quản lý chấm (Thang 100)',
    `manager_scores_json` JSON NULL COMMENT 'Dữ liệu JSON điểm chi tiết từng tiêu chí quản lý chấm',
    `manager_notes` TEXT NULL COMMENT 'Quản lý nhận xét và đề xuất',
    `manager_submitted_at` DATETIME NULL,
    `final_score` DECIMAL(5,2) NULL COMMENT 'Điểm tổng kết cuối cùng (Thang 100: 30% NV + 70% QL)',
    `rating` ENUM('outstanding', 'excellent', 'good', 'average', 'below_average', 'poor') NULL COMMENT 'Xếp loại',
    `promotion_recommendation` ENUM('none', 'bonus', 'salary_increase', 'promotion', 'training', 'warning') NOT NULL DEFAULT 'none',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_period` (`period_id`),
    INDEX `idx_employee` (`employee_id`),
    INDEX `idx_reviewer` (`reviewer_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_rating` (`rating`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. BỔ SUNG CỘT EVALUATION_ID VÀO BẢNG REWARDS
-- ============================================================

SET @dbname = DATABASE();
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'rewards' AND COLUMN_NAME = 'evaluation_id');
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `rewards` ADD COLUMN `evaluation_id` INT NULL COMMENT ''Liên kết đánh giá KPI'' AFTER `employee_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 6. THÊM PERMISSIONS CHO MODULE EVALUATIONS
-- ============================================================

INSERT IGNORE INTO `permissions` (`module`, `action`, `description`) VALUES
('evaluations', 'view', 'Xem danh sách và chi tiết đánh giá KPI'),
('evaluations', 'create', 'Tạo đợt đánh giá và phân bổ phiếu KPI'),
('evaluations', 'edit', 'Chỉnh sửa đợt đánh giá và bộ mẫu tiêu chí'),
('evaluations', 'delete', 'Xóa phiếu và đợt đánh giá'),
('evaluations', 'self_review', 'Thực hiện tự đánh giá KPI bản thân'),
('evaluations', 'manager_review', 'Quản lý chấm điểm và đánh giá nhân viên cấp dưới'),
('evaluations', 'approve', 'Phê duyệt tổng kết xếp loại KPI và đề xuất khen thưởng');

-- ============================================================
-- 7. GÁN QUYỀN CHO CÁC ROLES
-- ============================================================

-- Role 1 (Super Admin): Toàn quyền module evaluations
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) 
SELECT 1, id FROM `permissions` WHERE module = 'evaluations';

-- Role 2 (Branch Director / Quản lý): view, create, edit, self_review, manager_review, approve
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, id FROM `permissions` WHERE module = 'evaluations' AND action IN ('view', 'create', 'edit', 'self_review', 'manager_review', 'approve');

-- Role 3 (HR Executive): view, create, edit, self_review, manager_review
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 3, id FROM `permissions` WHERE module = 'evaluations' AND action IN ('view', 'create', 'edit', 'self_review', 'manager_review');

-- ============================================================
-- 8. SEED DATA — BỘ TIÊU CHÍ MẪU (KPI TEMPLATES & ITEMS)
-- ============================================================

-- Xóa dữ liệu cũ nếu chạy lại migration
DELETE FROM `kpi_template_items`;
DELETE FROM `kpi_templates`;

-- Template 1: Khối Công nghệ & Số Hóa
INSERT INTO `kpi_templates` (`id`, `code`, `name`, `department_id`, `description`, `status`) VALUES
(1, 'KPI-TECH', 'Bộ Tiêu Chí Kỹ Sư Phần Mềm & Hạ Tầng Số', 3, 'Áp dụng cho Software Engineers, Tech Leads, DevOps và Kiến trúc sư hệ thống', 'active'),
(2, 'KPI-RETAIL', 'Bộ Tiêu Chí Vận Hành Chuỗi & Bán Lẻ', 4, 'Áp dụng cho Giám đốc chuỗi, Quản lý cửa hàng Flagship và Trưởng nhóm bán hàng', 'active'),
(3, 'KPI-HR', 'Bộ Tiêu Chí Quản Trị Nhân Sự & Đào Tạo', 2, 'Áp dụng cho Chuyên viên HR, C&B, Tuyển dụng và Phát triển văn hóa doanh nghiệp', 'active'),
(4, 'KPI-GENERAL', 'Bộ Tiêu Chí Hiệu Suất Chung Khối Văn Phòng', NULL, 'Áp dụng chung cho các phòng ban hỗ trợ: Pháp chế, Hành chính, Tài chính', 'active');

-- Tiêu chí chi tiết Template 1: Tech (Tổng weight = 100%)
INSERT INTO `kpi_template_items` (`template_id`, `criteria_name`, `description`, `weight`, `target_type`, `target_value`, `sort_order`) VALUES
(1, 'Tiến độ & Cam kết bàn giao Sprint', 'Hoàn thành các User Story / Task theo cam kết Sprint, không trễ hạn Release', 30.00, 'percentage', '≥ 95%', 1),
(1, 'Chất lượng Code & Độ tin cậy hệ thống', 'Tỷ lệ Bug nghiêm trọng Production = 0, đạt tiêu chuẩn Clean Architecture và Unit Test coverage', 25.00, 'percentage', '≥ 85%', 2),
(1, 'Năng lực Kỹ thuật & Code Review', 'Review code chất lượng cho đồng đội theo SLA 4h, giải pháp tối ưu thuật toán', 20.00, 'percentage', '≥ 90%', 3),
(1, 'Bảo mật & Tối ưu hiệu năng Cloud', 'Không có lỗ hổng bảo mật OWASP, tuân thủ tối ưu chi phí hạ tầng AWS/Docker', 15.00, 'rating', 'Đạt chuẩn A', 4),
(1, 'Tinh thần phối hợp & Chia sẻ tri thức', 'Tích cực hỗ trợ đồng đội, tham gia hoặc thuyết trình Tech Talk nội bộ hàng tháng', 10.00, 'numeric', '≥ 1 lần/quý', 5);

-- Tiêu chí chi tiết Template 2: Retail (Tổng weight = 100%)
INSERT INTO `kpi_template_items` (`template_id`, `criteria_name`, `description`, `weight`, `target_type`, `target_value`, `sort_order`) VALUES
(2, 'Doanh thu & Chỉ tiêu bán hàng chuỗi', 'Đạt và vượt chỉ tiêu doanh số bán hàng được phân bổ theo kỳ kinh doanh', 35.00, 'percentage', '≥ 100%', 1),
(2, 'Chỉ số Hài lòng Khách hàng (CSAT/NPS)', 'Tỷ lệ đánh giá dịch vụ tích cực của khách hàng tại các điểm bán lẻ', 25.00, 'percentage', '≥ 92%', 2),
(2, 'Kiểm soát Tồn kho & Tỷ lệ Hao hụt', 'Giảm thiểu tỷ lệ hao hụt hàng hóa và sai lệch kiểm kê kho định kỳ', 20.00, 'percentage', '< 0.5%', 3),
(2, 'Tuân thủ Tiêu chuẩn Dịch vụ (SOP)', 'Thực hiện đúng 100% quy trình 5S, mở/đóng ca và văn hóa ứng xử dịch vụ', 10.00, 'percentage', '100%', 4),
(2, 'Đào tạo & Quản trị Đội ngũ bán hàng', 'Tỷ lệ giữ chân nhân viên bán hàng và hiệu quả đào tạo kỹ năng tư vấn', 10.00, 'percentage', '≥ 90%', 5);

-- Tiêu chí chi tiết Template 3: HR (Tổng weight = 100%)
INSERT INTO `kpi_template_items` (`template_id`, `criteria_name`, `description`, `weight`, `target_type`, `target_value`, `sort_order`) VALUES
(3, 'Hiệu quả Tuyển dụng Đúng hạn', 'Đáp ứng đủ số lượng và chất lượng nhân sự theo đề xuất tuyển dụng đúng SLA', 30.00, 'percentage', '≥ 90%', 1),
(3, 'Độ chính xác C&B, Lương & Phúc lợi', 'Tính công lương, BHXH và thuế TNCN chuẩn xác tuyệt đối, chi trả đúng ngày 05', 25.00, 'percentage', '100%', 2),
(3, 'Tỷ lệ Giữ chân Nhân tài & Gắn kết', 'Kiểm soát tỷ lệ thôi việc tự nguyện toàn tập đoàn trong ngưỡng an toàn', 20.00, 'percentage', '< 5%/quý', 3),
(3, 'Đào tạo Nội bộ & Phát triển Năng lực', 'Tổ chức các khóa bồi dưỡng kỹ năng mềm, kỹ năng nghiệp vụ và hội nhập', 15.00, 'numeric', '≥ 3 khóa/quý', 4),
(3, 'Hồ sơ Pháp lý & Tuân thủ Lao động', '100% hợp đồng lao động, phụ lục và hồ sơ nhân sự số hóa đầy đủ pháp lý', 10.00, 'percentage', '100%', 5);

-- Tiêu chí chi tiết Template 4: General (Tổng weight = 100%)
INSERT INTO `kpi_template_items` (`template_id`, `criteria_name`, `description`, `weight`, `target_type`, `target_value`, `sort_order`) VALUES
(4, 'Chất lượng & Khối lượng Công việc', 'Hoàn thành các mục tiêu công việc được giao theo bản mô tả công việc (JD)', 35.00, 'percentage', '≥ 95%', 1),
(4, 'Tiến độ & Tinh thần Trách nhiệm', 'Đúng hạn báo cáo và cam kết tiến độ công việc, chủ động giải quyết sự cố', 25.00, 'percentage', '≥ 95%', 2),
(4, 'Sáng kiến & Cải tiến Năng suất', 'Đóng góp ít nhất 1 ý tưởng hoặc quy trình cải tiến giúp nâng cao hiệu suất làm việc', 15.00, 'numeric', '≥ 1 sáng kiến', 3),
(4, 'Kỷ luật, Tác phong & Văn hóa Tập đoàn', 'Chấp hành nghiêm chỉnh nội quy lao động, giờ giấc làm việc và văn hóa AURA', 15.00, 'percentage', '100%', 4),
(4, 'Hợp tác & Giao tiếp Đa phòng ban', 'Thái độ tích cực, tôn trọng đồng nghiệp và hỗ trợ đồng đội trong công việc chung', 10.00, 'rating', 'Xuất sắc', 5);

-- ============================================================
-- 9. SEED DATA — ĐỢT ĐÁNH GIÁ (EVALUATION PERIODS)
-- ============================================================

DELETE FROM `evaluation_periods`;

INSERT INTO `evaluation_periods` (`id`, `code`, `name`, `type`, `year`, `quarter`, `start_date`, `end_date`, `self_review_deadline`, `manager_review_deadline`, `status`, `description`) VALUES
(1, 'PERIOD-2026-Q3', 'Đánh Giá Hiệu Suất & KPI Toàn Diện Quý 3/2026', 'quarterly', 2026, 3, '2026-07-01', '2026-09-30', '2026-10-10', '2026-10-20', 'active', 'Kỳ đánh giá KPI quý 3 phục vụ xét thưởng hiệu suất và điều chỉnh thu nhập cuối năm.'),
(2, 'PERIOD-2026-Q2', 'Đánh Giá Hiệu Suất Quý 2/2026 (Đã Hoàn Tất)', 'quarterly', 2026, 2, '2026-04-01', '2026-06-30', '2026-07-10', '2026-07-20', 'closed', 'Kỳ đánh giá hiệu suất quý 2/2026 đã chốt số liệu và chi trả thưởng.');

-- ============================================================
-- 10. SEED DATA — PHIẾU ĐÁNH GIÁ CHI TIẾT (EVALUATIONS)
-- ============================================================

DELETE FROM `evaluations`;

-- Đợt Q3/2026 (Active)
-- 1. Vũ Hoàng Long (AUR004, id: 4, IT) - Reviewer: Nguyễn Hoàng Nam (id: 1) - COMPLETED - Outstanding
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    1, 'DG-2026-Q3-001', 1, 4, 1, 1, 
    'completed', 96.00, 
    JSON_ARRAY(
        JSON_OBJECT('item_id', 1, 'criteria', 'Tiến độ & Cam kết bàn giao Sprint', 'weight', 30, 'score', 98, 'comment', 'Hoàn thành 100% Sprint, release module HRM đúng hạn'),
        JSON_OBJECT('item_id', 2, 'criteria', 'Chất lượng Code & Độ tin cậy hệ thống', 'weight', 25, 'score', 95, 'comment', 'Không có bug critical trên production'),
        JSON_OBJECT('item_id', 3, 'criteria', 'Năng lực Kỹ thuật & Code Review', 'weight', 20, 'score', 95, 'comment', 'Review hơn 40 PRs với feedback chi tiết'),
        JSON_OBJECT('item_id', 4, 'criteria', 'Bảo mật & Tối ưu hiệu năng Cloud', 'weight', 15, 'score', 96, 'comment', 'Đã migrate hệ thống an toàn sang AWS'),
        JSON_OBJECT('item_id', 5, 'criteria', 'Tinh thần phối hợp & Chia sẻ tri thức', 'weight', 10, 'score', 95, 'comment', 'Tổ chức 2 buổi Tech Talk nội bộ về Docker')
    ),
    'Em đã nỗ lực dẫn dắt team hoàn thành toàn bộ tính năng chuyển đổi số đúng tiến độ quý 3.', '2026-10-02 10:15:00',
    97.50,
    JSON_ARRAY(
        JSON_OBJECT('item_id', 1, 'criteria', 'Tiến độ & Cam kết bàn giao Sprint', 'weight', 30, 'score', 98, 'comment', 'Dẫn dắt dự án xuất sắc, bàn giao sớm 2 ngày'),
        JSON_OBJECT('item_id', 2, 'criteria', 'Chất lượng Code & Độ tin cậy hệ thống', 'weight', 25, 'score', 97, 'comment', 'Kiến trúc code chuẩn mực, team vận hành ổn định'),
        JSON_OBJECT('item_id', 3, 'criteria', 'Năng lực Kỹ thuật & Code Review', 'weight', 20, 'score', 98, 'comment', 'Mentor rất tốt cho các Junior và Mid-level'),
        JSON_OBJECT('item_id', 4, 'criteria', 'Bảo mật & Tối ưu hiệu năng Cloud', 'weight', 15, 'score', 96, 'comment', 'Tối ưu chi phí hạ tầng giảm 18%'),
        JSON_OBJECT('item_id', 5, 'criteria', 'Tinh thần phối hợp & Chia sẻ tri thức', 'weight', 10, 'score', 100, 'comment', 'Đóng góp nổi bật trong văn hóa kỹ thuật tập đoàn')
    ),
    'Vũ Hoàng Long thể hiện vai trò Tech Lead xuất sắc, có tư duy kiến trúc và gắn kết đội ngũ rất cao. Đề xuất khen thưởng và nâng ngạch Principal Architect.', '2026-10-04 16:30:00',
    97.05, 'outstanding', 'promotion', '2026-09-30 08:00:00'
);

-- 2. Đỗ Thị Bích Ngọc (AUR007, id: 7, IT) - Reviewer: Vũ Hoàng Long (id: 4) - COMPLETED - Excellent
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    2, 'DG-2026-Q3-002', 1, 7, 1, 4, 
    'completed', 88.00, 
    JSON_ARRAY(
        JSON_OBJECT('item_id', 1, 'criteria', 'Tiến độ & Cam kết bàn giao Sprint', 'weight', 30, 'score', 90, 'comment', 'Bàn giao các module đúng cam kết'),
        JSON_OBJECT('item_id', 2, 'criteria', 'Chất lượng Code & Độ tin cậy hệ thống', 'weight', 25, 'score', 86, 'comment', 'Unit test đạt 80%'),
        JSON_OBJECT('item_id', 3, 'criteria', 'Năng lực Kỹ thuật & Code Review', 'weight', 20, 'score', 88, 'comment', 'Hoàn thành tốt các task phức tạp'),
        JSON_OBJECT('item_id', 4, 'criteria', 'Bảo mật & Tối ưu hiệu năng Cloud', 'weight', 15, 'score', 85, 'comment', 'Tối ưu truy vấn SQL module báo cáo'),
        JSON_OBJECT('item_id', 5, 'criteria', 'Tinh thần phối hợp & Chia sẻ tri thức', 'weight', 10, 'score', 90, 'comment', 'Hòa đồng, hợp tác nhóm tốt')
    ),
    'Đã làm quen nhanh với hệ thống và hỗ trợ dự án tích cực.', '2026-10-03 14:00:00',
    90.50,
    JSON_ARRAY(
        JSON_OBJECT('item_id', 1, 'criteria', 'Tiến độ & Cam kết bàn giao Sprint', 'weight', 30, 'score', 92, 'comment', 'Chủ động nhận việc khó và hoàn thành chỉn chu'),
        JSON_OBJECT('item_id', 2, 'criteria', 'Chất lượng Code & Độ tin cậy hệ thống', 'weight', 25, 'score', 90, 'comment', 'Code sạch, ít phải refactor'),
        JSON_OBJECT('item_id', 3, 'criteria', 'Năng lực Kỹ thuật & Code Review', 'weight', 20, 'score', 90, 'comment', 'Tiếp thu nhanh phản hồi từ Tech Lead'),
        JSON_OBJECT('item_id', 4, 'criteria', 'Bảo mật & Tối ưu hiệu năng Cloud', 'weight', 15, 'score', 88, 'comment', 'Cải thiện tốt hiệu năng database'),
        JSON_OBJECT('item_id', 5, 'criteria', 'Tinh thần phối hợp & Chia sẻ tri thức', 'weight', 10, 'score', 92, 'comment', 'Thái độ làm việc tuyệt vời')
    ),
    'Nhân sự tiến bộ vượt bậc trong quý 3, trách nhiệm cao và kỹ năng chuyên môn vững vàng. Đề xuất xét thưởng hiệu suất quý.', '2026-10-05 11:20:00',
    89.75, 'excellent', 'bonus', '2026-09-30 08:00:00'
);

-- 3. Phạm Thu Thảo (AUR005, id: 5, HR) - Reviewer: Lê Mai Phương (id: 2) - COMPLETED - Outstanding
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    3, 'DG-2026-Q3-003', 1, 5, 3, 2, 
    'completed', 94.00, 
    JSON_ARRAY(
        JSON_OBJECT('item_id', 11, 'criteria', 'Hiệu quả Tuyển dụng Đúng hạn', 'weight', 30, 'score', 95, 'comment', 'Tuyển đủ 12 vị trí khối Retail'),
        JSON_OBJECT('item_id', 12, 'criteria', 'Độ chính xác C&B, Lương & Phúc lợi', 'weight', 25, 'score', 98, 'comment', 'Không sai sót dữ liệu bảng lương tháng'),
        JSON_OBJECT('item_id', 13, 'criteria', 'Tỷ lệ Giữ chân Nhân tài & Gắn kết', 'weight', 20, 'score', 90, 'comment', 'Khảo sát độ gắn kết đạt 88%'),
        JSON_OBJECT('item_id', 14, 'criteria', 'Đào tạo Nội bộ & Phát triển Năng lực', 'weight', 15, 'score', 92, 'comment', 'Tổ chức 4 buổi Onboarding thành công'),
        JSON_OBJECT('item_id', 15, 'criteria', 'Hồ sơ Pháp lý & Tuân thủ Lao động', 'weight', 10, 'score', 95, 'comment', '100% hồ sơ số hóa cập nhật')
    ),
    'Hoàn thành xuất sắc nhiệm vụ C&B và hỗ trợ đắc lực kỳ tuyển dụng cao điểm.', '2026-10-01 09:30:00',
    96.00,
    JSON_ARRAY(
        JSON_OBJECT('item_id', 11, 'criteria', 'Hiệu quả Tuyển dụng Đúng hạn', 'weight', 30, 'score', 96, 'comment', 'Đáp ứng nhân sự rất nhanh cho các cửa hàng mới'),
        JSON_OBJECT('item_id', 12, 'criteria', 'Độ chính xác C&B, Lương & Phúc lợi', 'weight', 25, 'score', 100, 'comment', 'Lương và bảo hiểm chuẩn xác 100% tuyệt đối'),
        JSON_OBJECT('item_id', 13, 'criteria', 'Tỷ lệ Giữ chân Nhân tài & Gắn kết', 'weight', 20, 'score', 92, 'comment', 'Xử lý tốt các vướng mắc của người lao động'),
        JSON_OBJECT('item_id', 14, 'criteria', 'Đào tạo Nội bộ & Phát triển Năng lực', 'weight', 15, 'score', 95, 'comment', 'Chương trình hội nhập được nhân viên mới khen ngợi'),
        JSON_OBJECT('item_id', 15, 'criteria', 'Hồ sơ Pháp lý & Tuân thủ Lao động', 'weight', 10, 'score', 96, 'comment', 'Quản lý tài liệu khoa học')
    ),
    'Chuyên viên nòng cốt của khối nhân sự. Tinh thần cống hiến cao, đề xuất nâng lương định kỳ.', '2026-10-03 15:45:00',
    95.40, 'outstanding', 'salary_increase', '2026-09-30 08:00:00'
);

-- 4. Trần Quốc Bảo (AUR003, id: 3, Retail) - Reviewer: Nguyễn Hoàng Nam (id: 1) - COMPLETED - Good
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    4, 'DG-2026-Q3-004', 1, 3, 2, 1, 
    'completed', 82.00, 
    JSON_ARRAY(
        JSON_OBJECT('item_id', 6, 'criteria', 'Doanh thu & Chỉ tiêu bán hàng chuỗi', 'weight', 35, 'score', 84, 'comment', 'Đạt 96% kế hoạch doanh thu khu vực'),
        JSON_OBJECT('item_id', 7, 'criteria', 'Chỉ số Hài lòng Khách hàng (CSAT/NPS)', 'weight', 25, 'score', 82, 'comment', 'CSAT duy trì mức 90%'),
        JSON_OBJECT('item_id', 8, 'criteria', 'Kiểm soát Tồn kho & Tỷ lệ Hao hụt', 'weight', 20, 'score', 80, 'comment', 'Hao hụt 0.45% trong định mức'),
        JSON_OBJECT('item_id', 9, 'criteria', 'Tuân thủ Tiêu chuẩn Dịch vụ (SOP)', 'weight', 10, 'score', 80, 'comment', 'Tuân thủ cơ bản tốt'),
        JSON_OBJECT('item_id', 10, 'criteria', 'Đào tạo & Quản trị Đội ngũ bán hàng', 'weight', 10, 'score', 85, 'comment', 'Đã bổ nhiệm 2 cửa hàng phó mới')
    ),
    'Tình hình thị trường cạnh tranh gay gắt, đã nỗ lực giữ vững thị phần.', '2026-10-02 11:00:00',
    79.50,
    JSON_ARRAY(
        JSON_OBJECT('item_id', 6, 'criteria', 'Doanh thu & Chỉ tiêu bán hàng chuỗi', 'weight', 35, 'score', 80, 'comment', 'Cần tăng tốc chiến dịch bán lẻ tại TP.HCM'),
        JSON_OBJECT('item_id', 7, 'criteria', 'Chỉ số Hài lòng Khách hàng (CSAT/NPS)', 'weight', 25, 'score', 80, 'comment', 'Có 2 khiếu nại về thái độ nhân viên bảo vệ'),
        JSON_OBJECT('item_id', 8, 'criteria', 'Kiểm soát Tồn kho & Tỷ lệ Hao hụt', 'weight', 20, 'score', 78, 'comment', 'Cần kiểm soát chặt hơn kho tổng miền Nam'),
        JSON_OBJECT('item_id', 9, 'criteria', 'Tuân thủ Tiêu chuẩn Dịch vụ (SOP)', 'weight', 10, 'score', 80, 'comment', 'Cần siết chặt 5S tại điểm bán'),
        JSON_OBJECT('item_id', 10, 'criteria', 'Đào tạo & Quản trị Đội ngũ bán hàng', 'weight', 10, 'score', 80, 'comment', 'Cần thêm khóa đào tạo upselling')
    ),
    'Kết quả đạt mức Khá. Cần có chiến lược bứt phá doanh số quý 4 để đạt kế hoạch năm.', '2026-10-04 14:10:00',
    80.25, 'good', 'training', '2026-09-30 08:00:00'
);

-- 5. Nguyễn Hải Đăng (AUR008, id: 8, DevOps/Cloud) - Reviewer: Vũ Hoàng Long (id: 4) - MANAGER_REVIEW
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    5, 'DG-2026-Q3-005', 1, 8, 1, 4, 
    'manager_review', 91.00, 
    JSON_ARRAY(
        JSON_OBJECT('item_id', 1, 'criteria', 'Tiến độ & Cam kết bàn giao Sprint', 'weight', 30, 'score', 92, 'comment', 'Setup CI/CD pipeline cho 3 microservices'),
        JSON_OBJECT('item_id', 2, 'criteria', 'Chất lượng Code & Độ tin cậy hệ thống', 'weight', 25, 'score', 90, 'comment', 'Uptime đạt 99.95%'),
        JSON_OBJECT('item_id', 3, 'criteria', 'Năng lực Kỹ thuật & Code Review', 'weight', 20, 'score', 90, 'comment', 'Xử lý tự động hóa kiểm thử'),
        JSON_OBJECT('item_id', 4, 'criteria', 'Bảo mật & Tối ưu hiệu năng Cloud', 'weight', 15, 'score', 95, 'comment', 'Hoàn tất chứng chỉ bảo mật AWS'),
        JSON_OBJECT('item_id', 5, 'criteria', 'Tinh thần phối hợp & Chia sẻ tri thức', 'weight', 10, 'score', 88, 'comment', 'Hỗ trợ anh em dev nhiệt tình')
    ),
    'Đã hoàn thành xuất sắc mục tiêu ổn định hạ tầng và bảo mật quý 3.', '2026-10-04 17:00:00',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 'none', '2026-09-30 08:00:00'
);

-- 6. Trần Thị Hương Giang (AUR009, id: 9, HR) - Reviewer: Lê Mai Phương (id: 2) - MANAGER_REVIEW
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    6, 'DG-2026-Q3-006', 1, 9, 3, 2, 
    'manager_review', 87.00, 
    JSON_ARRAY(
        JSON_OBJECT('item_id', 11, 'criteria', 'Hiệu quả Tuyển dụng Đúng hạn', 'weight', 30, 'score', 88, 'comment', 'Tuyển dụng đạt 85% kế hoạch đề ra'),
        JSON_OBJECT('item_id', 12, 'criteria', 'Độ chính xác C&B, Lương & Phúc lợi', 'weight', 25, 'score', 90, 'comment', 'Theo dõi chấm công sát sao'),
        JSON_OBJECT('item_id', 13, 'criteria', 'Tỷ lệ Giữ chân Nhân tài & Gắn kết', 'weight', 20, 'score', 85, 'comment', 'Tổ chức tốt hoạt động sinh nhật tháng'),
        JSON_OBJECT('item_id', 14, 'criteria', 'Đào tạo Nội bộ & Phát triển Năng lực', 'weight', 15, 'score', 85, 'comment', 'Chuẩn bị giáo trình đào tạo nội bộ'),
        JSON_OBJECT('item_id', 15, 'criteria', 'Hồ sơ Pháp lý & Tuân thủ Lao động', 'weight', 10, 'score', 88, 'comment', 'Số hóa 100% hồ sơ ứng viên')
    ),
    'Đã rất cố gắng trong giai đoạn tái cấu trúc nhân sự.', '2026-10-05 08:30:00',
    NULL, NULL, NULL, NULL,
    NULL, NULL, 'none', '2026-09-30 08:00:00'
);

-- 7. Bùi Thanh Tùng (AUR010, id: 10, Kế toán) - Reviewer: Hoàng Minh Tuấn (id: 6) - SELF_REVIEW
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    7, 'DG-2026-Q3-007', 1, 10, 4, 6, 
    'self_review', NULL, NULL, NULL, NULL,
    NULL, NULL, NULL, NULL,
    NULL, NULL, 'none', '2026-09-30 08:00:00'
);

-- 8. Hoàng Minh Tuấn (AUR006, id: 6, Kế toán trưởng) - Reviewer: Nguyễn Hoàng Nam (id: 1) - SELF_REVIEW
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    8, 'DG-2026-Q3-008', 1, 6, 4, 1, 
    'self_review', NULL, NULL, NULL, NULL,
    NULL, NULL, NULL, NULL,
    NULL, NULL, 'none', '2026-09-30 08:00:00'
);

-- 9. Lê Mai Phương (AUR002, id: 2, HR Director) - Reviewer: Nguyễn Hoàng Nam (id: 1) - PENDING
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    9, 'DG-2026-Q3-009', 1, 2, 3, 1, 
    'pending', NULL, NULL, NULL, NULL,
    NULL, NULL, NULL, NULL,
    NULL, NULL, 'none', '2026-09-30 08:00:00'
);

-- 10. Kỳ Q2/2026 (Closed) - Lịch sử đánh giá
-- Vũ Hoàng Long - Q2
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    10, 'DG-2026-Q2-001', 2, 4, 1, 1, 
    'completed', 94.00, NULL, 'Hoàn thành tốt quý 2', '2026-07-05 10:00:00',
    95.00, NULL, 'Đạt xuất sắc các chỉ tiêu công nghệ', '2026-07-12 15:00:00',
    94.70, 'excellent', 'bonus', '2026-06-30 08:00:00'
);

-- Phạm Thu Thảo - Q2
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    11, 'DG-2026-Q2-002', 2, 5, 3, 2, 
    'completed', 92.00, NULL, 'Hoàn thành tốt công việc', '2026-07-04 11:00:00',
    93.00, NULL, 'Chuyên cần, xử lý công việc chính xác', '2026-07-11 16:00:00',
    92.70, 'excellent', 'bonus', '2026-06-30 08:00:00'
);

-- Trần Quốc Bảo - Q2
INSERT INTO `evaluations` (
    `id`, `evaluation_code`, `period_id`, `employee_id`, `template_id`, `reviewer_id`, 
    `status`, `self_score`, `self_scores_json`, `self_notes`, `self_submitted_at`, 
    `manager_score`, `manager_scores_json`, `manager_notes`, `manager_submitted_at`, 
    `final_score`, `rating`, `promotion_recommendation`, `created_at`
) VALUES (
    12, 'DG-2026-Q2-003', 2, 3, 2, 1, 
    'completed', 85.00, NULL, 'Đạt chỉ tiêu doanh số chi nhánh', '2026-07-06 09:00:00',
    86.00, NULL, 'Quản lý tốt chuỗi cửa hàng', '2026-07-13 14:00:00',
    85.70, 'excellent', 'none', '2026-06-30 08:00:00'
);
