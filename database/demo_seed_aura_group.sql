-- ==============================================================================
-- DATABASE SEED SCRIPT: AURA RETAIL GROUP JSC (DEMO ENTERPRISE GRADE)
-- Slogan: "Nâng tầm trải nghiệm bán lẻ thông minh"
-- Target: 500+ nhân sự, 4 Chi nhánh toàn quốc, 6 Khối phòng ban trọng yếu
-- File: database/demo_seed_aura_group.sql
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
SET TIME_ZONE = '+07:00';

-- 1. TRUNCATE ALL EXISTING TABLES
TRUNCATE TABLE `attendance`;
TRUNCATE TABLE `candidates`;
TRUNCATE TABLE `job_positions`;
TRUNCATE TABLE `transfer_plan_items`;
TRUNCATE TABLE `transfer_plans`;
TRUNCATE TABLE `transfers`;
TRUNCATE TABLE `rewards`;
TRUNCATE TABLE `disciplines`;
TRUNCATE TABLE `payrolls`;
TRUNCATE TABLE `employee_education`;
TRUNCATE TABLE `employee_certificates`;
TRUNCATE TABLE `employee_contracts`;
TRUNCATE TABLE `employee_insurance`;
TRUNCATE TABLE `employee_dependents`;
TRUNCATE TABLE `employees`;
TRUNCATE TABLE `departments`;
TRUNCATE TABLE `branches`;
TRUNCATE TABLE `role_permissions`;
TRUNCATE TABLE `roles`;
TRUNCATE TABLE `positions`;
TRUNCATE TABLE `users`;

-- 2. POSITIONS (CHỨC DANH / VỊ TRÍ)
INSERT INTO `positions` (`id`, `name`, `base_salary`, `created_at`) VALUES
(1, 'Tổng Giám Đốc (CEO)', 80000000.00, NOW()),
(2, 'Giám Đốc Chi Nhánh (Branch Director)', 45000000.00, NOW()),
(3, 'Giám Đốc Khối / CHRO', 40000000.00, NOW()),
(4, 'Trưởng Phòng Kinh Doanh & Bán Lẻ', 28000000.00, NOW()),
(5, 'Kỹ Sư Phần Mềm Senior (Tech Lead)', 26000000.00, NOW()),
(6, 'Chuyên Viên Quản Lý Chuỗi Cung Ứng', 18000000.00, NOW()),
(7, 'Chuyên Viên Nhân Sự & Tuyển Dụng', 15000000.00, NOW()),
(8, 'Quản Lý Cửa Hàng Flagship (Store Manager)', 16000000.00, NOW());

-- 3. ROLES (VAI TRÒ TRUY CẬP)
INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Admin Toàn Quyền (HR Director)', 'Toàn quyền quản trị chiến lược nhân sự và hệ thống công nghệ toàn tập đoàn', NOW()),
(2, 'Giám Đốc Chi Nhánh (Branch Manager)', 'Quản lý nhân sự, chấm công, điều động và khen thưởng trong phạm vi chi nhánh', NOW()),
(3, 'Chuyên Viên Nhân Sự (HR Executive)', 'Thực thi quản lý hồ sơ, theo dõi chấm công, ứng viên tuyển dụng và hợp đồng lao động', NOW());

-- 4. PERMISSIONS & ROLE PERMISSIONS
-- Đảm bảo danh mục quyền đầy đủ 44 quyền hệ thống
INSERT IGNORE INTO `permissions` (`id`, `module`, `action`, `description`) VALUES
(1, 'employees', 'view', 'Xem danh sách & hồ sơ nhân viên'),
(2, 'employees', 'create', 'Thêm mới nhân viên'),
(3, 'employees', 'edit', 'Cập nhật thông tin nhân viên'),
(4, 'employees', 'delete', 'Xóa nhân viên'),
(5, 'departments', 'view', 'Xem danh sách phòng ban & chức vụ'),
(6, 'departments', 'create', 'Tạo phòng ban & chức vụ mới'),
(7, 'departments', 'edit', 'Chỉnh sửa phòng ban & chức vụ'),
(8, 'departments', 'delete', 'Xóa phòng ban & chức vụ'),
(9, 'attendance', 'view', 'Xem bảng chấm công'),
(10, 'attendance', 'create', 'Điểm danh / Chấm công ngày'),
(11, 'attendance', 'edit', 'Sửa đổi trạng thái chấm công'),
(12, 'attendance', 'delete', 'Xóa dữ liệu chấm công'),
(13, 'payroll', 'view', 'Xem bảng tính lương'),
(14, 'payroll', 'create', 'Tính toán & tạo bảng lương tháng'),
(15, 'payroll', 'edit', 'Điều chỉnh số liệu lương'),
(16, 'payroll', 'delete', 'Hủy bảng lương'),
(17, 'matrix', 'view', 'Xem cấu hình phân quyền'),
(18, 'matrix', 'manage', 'Chỉnh sửa ma trận phân quyền'),
(19, 'branches', 'view', 'Xem danh sách & mạng lưới chi nhánh'),
(20, 'branches', 'create', 'Thêm mới chi nhánh'),
(21, 'branches', 'edit', 'Chỉnh sửa thông tin chi nhánh'),
(22, 'branches', 'delete', 'Xóa chi nhánh'),
(23, 'orgchart', 'view', 'Xem sơ đồ cơ cấu tổ chức & mạng lưới'),
(24, 'transfers', 'view', 'Xem danh sách thuyên chuyển cán bộ'),
(25, 'transfers', 'create', 'Lập phiếu đề xuất thuyên chuyển'),
(26, 'transfers', 'approve', 'Phê duyệt & thực thi quyết định thuyên chuyển'),
(27, 'transfers', 'delete', 'Hủy bỏ đề xuất thuyên chuyển'),
(28, 'planning', 'view', 'Xem kế hoạch quy hoạch nhân sự'),
(29, 'planning', 'create', 'Lập kế hoạch & chạy thuật toán phương án tối ưu'),
(30, 'planning', 'approve', 'Phê duyệt & kích hoạt kế hoạch quy hoạch'),
(31, 'planning', 'delete', 'Xóa kế hoạch quy hoạch'),
(32, 'rewards', 'view', 'Xem danh sách & chi tiết khen thưởng'),
(33, 'rewards', 'create', 'Lập quyết định khen thưởng mới'),
(34, 'rewards', 'approve', 'Phê duyệt & thực thi quyết định khen thưởng'),
(35, 'rewards', 'delete', 'Xóa quyết định khen thưởng'),
(36, 'disciplines', 'view', 'Xem danh sách & chi tiết kỷ luật'),
(37, 'disciplines', 'create', 'Lập biên bản / quyết định kỷ luật mới'),
(38, 'disciplines', 'approve', 'Phê duyệt & thực thi quyết định kỷ luật'),
(39, 'disciplines', 'delete', 'Xóa quyết định kỷ luật'),
(40, 'recruitment', 'view', 'Xem bảng tin tuyển dụng & ứng viên'),
(41, 'recruitment', 'create', 'Tạo vị trí tuyển dụng & thêm hồ sơ ứng viên'),
(42, 'recruitment', 'edit', 'Cập nhật tiến độ & phỏng vấn ứng viên'),
(43, 'recruitment', 'hire', 'Tuyển dụng & chuyển ứng viên thành nhân viên'),
(44, 'recruitment', 'delete', 'Xóa hồ sơ ứng viên & vị trí tuyển dụng');

-- Gán quyền cho Role 1 (Admin Toàn Quyền): Toàn bộ 44 quyền
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, id FROM `permissions`;

-- Gán quyền cho Role 2 (Giám Đốc Chi Nhánh): Xem tổ chức, nhân viên chi nhánh, chấm công, bảng lương, đề xuất thuyên chuyển, quy hoạch, khen thưởng
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 1), (2, 2), (2, 3),        -- employees (view, create, edit)
(2, 5),                        -- departments (view)
(2, 9), (2, 10), (2, 11),      -- attendance (view, create, edit)
(2, 13),                       -- payroll (view)
(2, 19),                       -- branches (view)
(2, 23),                       -- orgchart (view)
(2, 24), (2, 25), (2, 26),      -- transfers (view, create, approve)
(2, 28),                       -- planning (view)
(2, 32), (2, 33),              -- rewards (view, create)
(2, 36), (2, 37),              -- disciplines (view, create)
(2, 40);                       -- recruitment (view)

-- Gán quyền cho Role 3 (Chuyên Viên Nhân Sự): Hồ sơ, chấm công, tuyển dụng, xem khen thưởng/kỷ luật
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 1), (3, 2), (3, 3),        -- employees (view, create, edit)
(3, 5),                        -- departments (view)
(3, 9), (3, 10), (3, 11),      -- attendance (view, create, edit)
(3, 19),                       -- branches (view)
(3, 23),                       -- orgchart (view)
(3, 32),                       -- rewards (view)
(3, 36),                       -- disciplines (view)
(3, 40), (3, 41), (3, 42);     -- recruitment (view, create, edit)

-- 5. USERS (TÀI KHOẢN ĐĂNG NHẬP HỆ THỐNG)
-- Mật khẩu chung: Aura@2026 (Bcrypt hash: $2y$10$hWuVVomxkyrDkNghRgiUNObGgjT5MGOF9VDch2lraeRcDD84RlQ7e)
INSERT INTO `users` (`id`, `role_id`, `username`, `password`, `fullname`, `email`, `is_superadmin`, `status`, `created_at`) VALUES
(1, 1, 'admin@auragroup.vn', '$2y$10$hWuVVomxkyrDkNghRgiUNObGgjT5MGOF9VDch2lraeRcDD84RlQ7e', 'Nguyễn Hoàng Nam', 'admin@auragroup.vn', 1, 'active', NOW()),
(2, 2, 'tp.hcm@auragroup.vn', '$2y$10$hWuVVomxkyrDkNghRgiUNObGgjT5MGOF9VDch2lraeRcDD84RlQ7e', 'Trần Quốc Bảo', 'tp.hcm@auragroup.vn', 0, 'active', NOW()),
(3, 3, 'nhanvien@auragroup.vn', '$2y$10$hWuVVomxkyrDkNghRgiUNObGgjT5MGOF9VDch2lraeRcDD84RlQ7e', 'Phạm Thu Thảo', 'nhanvien@auragroup.vn', 0, 'active', NOW());

-- 6. BRANCHES (MẠNG LƯỚI CHI NHÁNH TẬP ĐOÀN)
INSERT INTO `branches` (`id`, `code`, `name`, `address`, `phone`, `email`, `manager_id`, `target_headcount`, `is_headquarter`, `status`, `notes`, `created_at`) VALUES
(1, 'HN-HQ', 'Hội Sở Tập Đoàn Hà Nội', 'Tầng 32, Tòa nhà Keangnam Landmark 72, Đường Phạm Hùng, Q. Nam Từ Liêm, Hà Nội', '024-3998-8888', 'hanoi.hq@auragroup.vn', 1, 250, 1, 'active', 'Trụ sở chính & Trung tâm điều hành chiến lược toàn quốc', NOW()),
(2, 'HCM-BR', 'Chi Nhánh TP. Hồ Chí Minh', 'Tầng 18, Tháp Tài Chính Bitexco, Số 2 Hải Triều, P. Bến Nghé, Quận 1, TP. Hồ Chí Minh', '028-3821-6868', 'hcm.branch@auragroup.vn', 3, 180, 0, 'active', 'Trung tâm kinh doanh & phát triển chuỗi bán lẻ khu vực phía Nam', NOW()),
(3, 'DN-HUB', 'Chi Nhánh Đà Nẵng - Hub Logistics', 'Số 125 Đường 2 Tháng 9, P. Hòa Cường Nam, Q. Hải Châu, TP. Đà Nẵng', '0236-388-9999', 'danang.hub@auragroup.vn', 15, 80, 0, 'active', 'Hub logistics & trung tâm điều phối chuỗi cung ứng miền Trung', NOW()),
(4, 'CT-HUB', 'Chi Nhánh Cần Thơ - Trung Tâm Phân Phối Mekong', 'Khu Công Nghiệp Trà Nóc 1, P. Trà Nóc, Q. Bình Thủy, TP. Cần Thơ', '0292-376-8888', 'cantho.hub@auragroup.vn', 20, 60, 0, 'active', 'Trung tâm phân phối vùng Đồng Bằng Sông Cửu Long', NOW());

-- 7. DEPARTMENTS (KHỐI PHÒNG BAN CHỨC NĂNG)
INSERT INTO `departments` (`id`, `name`, `code`, `description`, `branch_id`, `parent_id`, `manager_id`, `created_at`) VALUES
(1, 'Ban Tổng Giám Đốc', 'BGD', 'Ban điều hành chiến lược kinh doanh và đầu tư mở rộng chuỗi bán lẻ', 1, NULL, 1, NOW()),
(2, 'Khối Quản Trị Nhân Sự & Đào Tạo', 'HR', 'Quản trị nhân sự, văn hóa tập đoàn, đào tạo nghiệp vụ và đãi ngộ C&B', 1, NULL, 2, NOW()),
(3, 'Khối Công Nghệ & Chuyển Đổi Số', 'IT', 'Phát triển hạ tầng Omni-channel Retail, Core ERP và giải pháp số hóa chuỗi cửa hàng', 1, NULL, 4, NOW()),
(4, 'Khối Vận Hành Chuỗi Bán Lẻ (Retail Operations)', 'RETAIL', 'Quản lý chuỗi siêu thị, cửa hàng flagship và trải nghiệm khách hàng tiêu dùng', 2, NULL, 3, NOW()),
(5, 'Khối Chuỗi Cung Ứng & Logistics (SCM)', 'SCM', 'Quản lý hệ thống kho bãi liên vùng, chuỗi cung ứng lạnh và điều phối giao nhận', 3, NULL, 15, NOW()),
(6, 'Khối Tài Chính - Kế Toán', 'FIN', 'Quản trị dòng tiền, kiểm soát chi phí bán lẻ và quyết toán thuế tập đoàn', 1, NULL, 6, NOW());

-- 8. EMPLOYEES (25 NHÂN SỰ TIÊU BIỂU TRÊN 4 CHI NHÁNH & 6 KHỐI)
INSERT INTO `employees` (`id`, `employee_code`, `fullname`, `gender`, `birth_date`, `identity_card`, `phone`, `email`, `bank_account`, `address`, `avatar`, `branch_id`, `department_id`, `position_id`, `user_id`, `hire_date`, `employment_status`, `created_at`) VALUES
-- Ban Giám Đốc & Lãnh Đạo Trụ Sở Hà Nội (HN-HQ)
(1, 'AUR001', 'Nguyễn Hoàng Nam', 'Nam', '1980-05-15', '001080009988', '0903112233', 'nam.nguyen@auragroup.vn', '19028889998888 - Techcombank', 'Penthouse Starlake Tây Hồ Tây, Bắc Từ Liêm, Hà Nội', NULL, 1, 1, 1, 1, '2018-03-01', 'official', NOW()),
(2, 'AUR002', 'Lê Mai Phương', 'Nu', '1985-09-20', '001185002233', '0912334455', 'phuong.le@auragroup.vn', '101889922334 - Vietcombank', 'Biệt thự Khu Đô Thị Ciputra, Q. Tây Hồ, Hà Nội', NULL, 1, 2, 3, NULL, '2019-06-15', 'official', NOW()),
(3, 'AUR003', 'Trần Quốc Bảo', 'Nam', '1983-11-12', '079083005566', '0908889911', 'bao.tran@auragroup.vn', '0071000998877 - Vietcombank HCM', 'Vinhomes Central Park, Q. Bình Thạnh, TP. Hồ Chí Minh', NULL, 2, 4, 2, 2, '2020-02-01', 'official', NOW()),
(4, 'AUR004', 'Vũ Hoàng Long', 'Nam', '1989-04-18', '001089004455', '0977889900', 'long.vu@auragroup.vn', '21510001234567 - BIDV', 'Goldmark City, 136 Hồ Tùng Mậu, Q. Bắc Từ Liêm, Hà Nội', NULL, 1, 3, 5, NULL, '2020-08-15', 'official', NOW()),
(5, 'AUR005', 'Phạm Thu Thảo', 'Nu', '1994-07-25', '001194006677', '0988112299', 'thao.pham@auragroup.vn', '19034567890123 - Techcombank', 'Chung cư Mulberry Lane, Q. Hà Đông, Hà Nội', NULL, 1, 2, 7, 3, '2021-04-01', 'official', NOW()),
(6, 'AUR006', 'Hoàng Minh Tuấn', 'Nam', '1982-12-05', '001082001122', '0913556677', 'tuan.hoang@auragroup.vn', '0451000334455 - Vietcombank', 'Tòa Ngoại Giao Đoàn, P. Xuân Tảo, Q. Bắc Từ Liêm, Hà Nội', NULL, 1, 6, 4, NULL, '2019-01-10', 'official', NOW()),
(7, 'AUR007', 'Đỗ Thị Bích Ngọc', 'Nu', '1992-03-30', '001192003344', '0966223344', 'ngoc.do@auragroup.vn', '19031122556677 - Techcombank', 'Mandarin Garden, Đường Hoàng Minh Giám, Q. Cầu Giấy, Hà Nội', NULL, 1, 3, 5, NULL, '2021-09-01', 'official', NOW()),
(8, 'AUR008', 'Nguyễn Hải Đăng', 'Nam', '1995-10-14', '001095007788', '0971234567', 'dang.nguyen@auragroup.vn', '102988776655 - MBBank', 'The Matrix One, Đường Lê Quang Đạo, Q. Nam Từ Liêm, Hà Nội', NULL, 1, 3, 5, NULL, '2022-03-15', 'official', NOW()),
(9, 'AUR009', 'Trần Thị Hương Giang', 'Nu', '1993-06-18', '001193008899', '0944556677', 'giang.tran@auragroup.vn', '101766554433 - VietinBank', 'D\'Capitale Trần Duy Hưng, Q. Cầu Giấy, Hà Nội', NULL, 1, 2, 7, NULL, '2022-07-01', 'official', NOW()),
(10, 'AUR010', 'Bùi Thanh Tùng', 'Nam', '1990-08-22', '001090004433', '0933445566', 'tung.bui@auragroup.vn', '12210000987654 - BIDV', 'Chung cư Golden Palace, P. Mễ Trì, Q. Nam Từ Liêm, Hà Nội', NULL, 1, 6, 7, NULL, '2021-11-15', 'official', NOW()),

-- Nhân sự Chi Nhánh TP. Hồ Chí Minh (HCM-BR)
(11, 'AUR011', 'Nguyễn Văn Hùng', 'Nam', '1987-02-28', '079087002233', '0909112244', 'hung.nguyen@auragroup.vn', '0071001234567 - Vietcombank HCM', 'Masteri Thảo Điền, TP. Thủ Đức, TP. Hồ Chí Minh', NULL, 2, 4, 4, NULL, '2020-05-10', 'official', NOW()),
(12, 'AUR012', 'Lý Thảo My', 'Nu', '1991-11-09', '079191004455', '0903334488', 'my.ly@auragroup.vn', '19038877661122 - Techcombank HCM', 'The Sun Avenue, Mai Chí Thọ, TP. Thủ Đức, TP. Hồ Chí Minh', NULL, 2, 4, 8, NULL, '2021-01-15', 'official', NOW()),
(13, 'AUR013', 'Phan Trọng Nghĩa', 'Nam', '1994-05-19', '079094006677', '0902778899', 'nghia.phan@auragroup.vn', '0181003456789 - Vietcombank Tân Định', 'Chung cư Sunrise City, Nguyễn Hữu Thọ, Quận 7, TP. Hồ Chí Minh', NULL, 2, 4, 8, NULL, '2022-04-01', 'official', NOW()),
(14, 'AUR014', 'Ngô Thu Thủy', 'Nu', '1992-09-14', '079192008899', '0908112233', 'thuy.ngo@auragroup.vn', '0251002233445 - Vietcombank Bến Thành', 'Botanica Premier, Hồng Hà, Q. Tân Bình, TP. Hồ Chí Minh', NULL, 2, 6, 7, NULL, '2022-08-15', 'official', NOW()),

-- Nhân sự Chi Nhánh Đà Nẵng - Hub Logistics (DN-HUB)
(15, 'AUR015', 'Đặng Hữu Phước', 'Nam', '1984-07-08', '048084001122', '0905123456', 'phuoc.dang@auragroup.vn', '0041000556677 - Vietcombank Đà Nẵng', 'Khu Đô Thị Nam Hòa Xuân, Q. Ngũ Hành Sơn, TP. Đà Nẵng', NULL, 3, 5, 2, NULL, '2020-09-01', 'official', NOW()),
(16, 'AUR016', 'Trịnh Quốc Huy', 'Nam', '1991-04-12', '048091003344', '0905987654', 'huy.trinh@auragroup.vn', '20110000887766 - BIDV Sông Hàn', 'Chung cư Monarchy, Đường Trần Hưng Đạo, Q. Sơn Trà, TP. Đà Nẵng', NULL, 3, 5, 6, NULL, '2021-03-20', 'official', NOW()),
(17, 'AUR017', 'Lâm Thùy Dung', 'Nu', '1995-12-03', '048195005566', '0905667788', 'dung.lam@auragroup.vn', '190366554411 - Techcombank Đà Nẵng', 'Số 48 Nguyễn Tri Phương, Q. Thanh Khê, TP. Đà Nẵng', NULL, 3, 5, 6, NULL, '2022-06-01', 'official', NOW()),
(18, 'AUR018', 'Mai Văn Khánh', 'Nam', '1988-10-25', '048088007788', '0905332211', 'khanh.mai@auragroup.vn', '0041000112299 - Vietcombank Đà Nẵng', 'Khu Đảo Xanh, P. Hòa Cường Bắc, Q. Hải Châu, TP. Đà Nẵng', NULL, 3, 4, 4, NULL, '2021-08-10', 'official', NOW()),
(19, 'AUR019', 'Đinh Ngọc Trâm', 'Nu', '1996-03-17', '048196008899', '0905443322', 'tram.dinh@auragroup.vn', '104887766554 - MBBank Đà Nẵng', 'KDC An Cư 3, P. Phước Mỹ, Q. Sơn Trà, TP. Đà Nẵng', NULL, 3, 2, 7, NULL, '2023-02-01', 'official', NOW()),

-- Nhân sự Chi Nhánh Cần Thơ - Trung Tâm Mekong (CT-HUB)
(20, 'AUR020', 'Võ Thành Trung', 'Nam', '1986-06-21', '092086001122', '0918112233', 'trung.vo@auragroup.vn', '0111000667788 - Vietcombank Cần Thơ', 'Vincom Shophouse, Đường 30 Tháng 4, Q. Ninh Kiều, TP. Cần Thơ', NULL, 4, 5, 2, NULL, '2021-10-01', 'official', NOW()),
(21, 'AUR021', 'Huỳnh Kim Ngân', 'Nu', '1993-08-14', '092193003344', '0919223344', 'ngan.huynh@auragroup.vn', '19039988774411 - Techcombank Cần Thơ', 'Khu Dân Cư Hưng Phú 1, Q. Cái Răng, TP. Cần Thơ', NULL, 4, 5, 6, NULL, '2022-02-15', 'official', NOW()),
(22, 'AUR022', 'Dương Hoàng Phúc', 'Nam', '1995-01-30', '092095005566', '0917334455', 'phuc.duong@auragroup.vn', '21110000554433 - BIDV Tây Đô', 'Khu Đô Thị Stella Mega City, Q. Bình Thủy, TP. Cần Thơ', NULL, 4, 4, 8, NULL, '2022-10-01', 'official', NOW()),
(23, 'AUR023', 'Cao Thị Lan Anh', 'Nu', '1997-05-12', '092197007788', '0916445566', 'anh.cao@auragroup.vn', '0111000889911 - Vietcombank Ninh Kiều', 'Số 142 Nguyễn Văn Cừ Nối Dài, P. An Khánh, Q. Ninh Kiều, TP. Cần Thơ', NULL, 4, 6, 7, NULL, '2023-05-01', 'official', NOW()),

-- Nhân sự Mới / Đang Thử Việc (Probation)
(24, 'AUR024', 'Tạ Quang Huy', 'Nam', '1998-09-05', '001098006655', '0968991122', 'huy.ta@auragroup.vn', '109887766332 - Techcombank', 'Khu Đô Thị Ngoại Giao Đoàn, Q. Bắc Từ Liêm, Hà Nội', NULL, 1, 3, 5, NULL, '2026-08-01', 'probation', NOW()),
(25, 'AUR025', 'Nguyễn Quỳnh Nga', 'Nu', '1999-12-19', '079199002211', '0901223344', 'nga.nguyen@auragroup.vn', '0071005566778 - Vietcombank HCM', 'Chung cư Vinhomes Grand Park, TP. Thủ Đức, TP. Hồ Chí Minh', NULL, 2, 4, 8, NULL, '2026-08-15', 'probation', NOW());

-- 9. EMPLOYEE PROFILE SUB-TABLES (HỒ SƠ NĂNG LỰC DOANH NGHIỆP)
-- Học Vấn (employee_education)
INSERT INTO `employee_education` (`employee_id`, `degree`, `institution`, `major`, `graduation_year`, `gpa`, `certificate_file`, `created_at`) VALUES
(1, 'Thạc Sĩ Quản Trị Kinh Doanh (MBA)', 'Đại Học Ngoại Thương & University of Hawaii (Mỹ)', 'Quản Trị Kinh Doanh Quốc Tế', 2006, '3.85/4.0', NULL, NOW()),
(2, 'Thạc Sĩ Nhân Sự', 'Đại Học Kinh Tế Quốc Dân (NEU)', 'Quản Trị Nhân Lực & Phát Triển Tổ Chức', 2010, '3.75/4.0', NULL, NOW()),
(3, 'Cử Nhân Quản Trị Kinh Doanh', 'Đại Học Kinh Tế TP. Hồ Chí Minh (UEH)', 'Thương Mại & Quản Trị Bán Lẻ', 2005, 'Khá Giỏi', NULL, NOW()),
(4, 'Kỹ Sư Công Nghệ Thông Tin', 'Đại Học Bách Khoa Hà Nội (HUST)', 'Khoa Học Máy Tính & Hệ Thống Thông Tin', 2012, '3.62/4.0', NULL, NOW()),
(5, 'Cử Nhân Quản Trị Nhân Sự', 'Đại Học Lao Động Xã Hội', 'Quản Trị Nhân Sự Doanh Nghiệp', 2016, '8.2/10', NULL, NOW()),
(6, 'Cử Nhân Kế Toán Kiểm Toán', 'Học Viện Tài Chính', 'Kế Toán Doanh Nghiệp', 2004, 'Xuất Sắc', NULL, NOW()),
(7, 'Kỹ Sư Phần Mềm', 'Đại Học Bách Khoa Hà Nội (HUST)', 'Công Nghệ Phần Mềm', 2014, 'Khá', NULL, NOW()),
(8, 'Kỹ Sư Hệ Thống', 'Học Viện Công Nghệ Bưu Chính Viễn Thông (PTIT)', 'An Toàn Thông Tin & Mạng Máy Tính', 2017, 'Giỏi', NULL, NOW()),
(15, 'Kỹ Sư Logistics & Vận Tải', 'Đại Học Bách Khoa Đà Nẵng (DUT)', 'Kỹ Thuật Hệ Thống Công Nghiệp & SCM', 2007, 'Khá Giỏi', NULL, NOW()),
(20, 'Cử Nhân Kinh Tế Nông Nghiệp & SCM', 'Đại Học Cần Thơ', 'Kinh Tế & Chuỗi Cung Ứng Nông Sản Mekong', 2008, 'Giỏi', NULL, NOW());

-- Chứng Chỉ Nghề Nghiệp (employee_certificates)
INSERT INTO `employee_certificates` (`employee_id`, `name`, `issuer`, `issue_date`, `expiry_date`, `certificate_file`, `created_at`) VALUES
(1, 'Executive Leadership & Strategic Retail Management', 'Wharton Executive Education', '2019-05-10', NULL, NULL, NOW()),
(2, 'Senior Certified Professional (SHRM-SCP)', 'Society for Human Resource Management (USA)', '2021-08-15', '2027-08-15', NULL, NOW()),
(4, 'AWS Certified Solutions Architect - Professional', 'Amazon Web Services', '2022-04-10', '2025-04-10', NULL, NOW()),
(4, 'Certified Scrum Master (CSM)', 'Scrum Alliance', '2021-11-20', '2026-11-20', NULL, NOW()),
(6, 'Chứng Chỉ Kiểm Toán Viên Quốc Gia (CPA Vietnam)', 'Bộ Tài Chính Việt Nam', '2012-07-25', NULL, NULL, NOW()),
(6, 'Chuyên Gia Quản Trị Tài Chính Quốc Tế (CMA)', 'Institute of Management Accountants (IMA)', '2018-09-12', NULL, NULL, NOW()),
(7, 'Google Cloud Professional Cloud Architect', 'Google Cloud', '2023-03-01', '2026-03-01', NULL, NOW()),
(15, 'Certified Supply Chain Professional (CSCP)', 'ASCM / APICS', '2020-10-15', '2026-10-15', NULL, NOW());

-- Hợp Đồng Lao Động (employee_contracts)
INSERT INTO `employee_contracts` (`employee_id`, `contract_number`, `contract_type`, `start_date`, `end_date`, `base_salary`, `allowance`, `contract_file`, `status`, `notes`, `created_at`) VALUES
(1, 'HDLD-2018-001', 'indefinite', '2018-03-01', NULL, 80000000.00, 20000000.00, NULL, 'active', 'Hợp đồng cán bộ điều hành cấp cao', NOW()),
(2, 'HDLD-2019-012', 'indefinite', '2019-06-15', NULL, 40000000.00, 10000000.00, NULL, 'active', 'Hợp đồng không xác định thời hạn', NOW()),
(3, 'HDLD-2020-008', 'indefinite', '2020-02-01', NULL, 45000000.00, 12000000.00, NULL, 'active', 'Hợp đồng Giám Đốc Chi Nhánh HCM', NOW()),
(4, 'HDLD-2020-035', 'indefinite', '2020-08-15', NULL, 26000000.00, 5000000.00, NULL, 'active', 'Hợp đồng Tech Lead R&D', NOW()),
(5, 'HDLD-2021-019', 'fixed_term', '2021-04-01', '2027-04-01', 15000000.00, 3000000.00, NULL, 'active', 'Hợp đồng xác định thời hạn 36 tháng', NOW()),
(6, 'HDLD-2019-002', 'indefinite', '2019-01-10', NULL, 28000000.00, 6000000.00, NULL, 'active', 'Hợp đồng Kế Toán Trưởng', NOW()),
(7, 'HDLD-2021-044', 'fixed_term', '2021-09-01', '2026-09-01', 26000000.00, 4000000.00, NULL, 'active', 'Hợp đồng Kỹ sư phần mềm', NOW()),
(8, 'HDLD-2022-015', 'fixed_term', '2022-03-15', '2027-03-15', 26000000.00, 4000000.00, NULL, 'active', 'Hợp đồng Cloud & DevOps', NOW()),
(11, 'HDLD-2020-022', 'indefinite', '2020-05-10', NULL, 28000000.00, 8000000.00, NULL, 'active', 'Hợp đồng Quản lý kinh doanh phía Nam', NOW()),
(15, 'HDLD-2020-041', 'indefinite', '2020-09-01', NULL, 45000000.00, 10000000.00, NULL, 'active', 'Hợp đồng Giám Đốc Hub Logistics Miền Trung', NOW()),
(20, 'HDLD-2021-055', 'indefinite', '2021-10-01', NULL, 45000000.00, 8000000.00, NULL, 'active', 'Hợp đồng Trưởng Trung Tâm Mekong', NOW()),
(24, 'HDTV-2026-003', 'probation', '2026-08-01', '2026-09-30', 22100000.00, 2000000.00, NULL, 'active', 'Hợp đồng thử việc 02 tháng (85% lương chính thức)', NOW()),
(25, 'HDTV-2026-004', 'probation', '2026-08-15', '2026-10-15', 13600000.00, 1500000.00, NULL, 'active', 'Hợp đồng thử việc Quản trị bán lẻ', NOW());

-- Bảo Hiểm Xã Hội & Y Tế & Chăm Sóc Sức Khỏe Cao Cấp (employee_insurance)
INSERT INTO `employee_insurance` (`employee_id`, `insurance_type`, `insurance_number`, `start_date`, `hospital_name`, `status`, `notes`, `created_at`) VALUES
(1, 'social', 'BHXH-0108000998', '2018-03-01', 'Bệnh viện Bạch Mai - Hà Nội', 'active', 'Đóng mức trần quy định', NOW()),
(1, 'commercial', 'AON-AURA-VIP01', '2018-03-01', 'Bệnh viện Quốc Tế Vinmec Times City', 'active', 'Bảo hiểm sức khỏe đặc biệt cấp C-Level', NOW()),
(2, 'social', 'BHXH-0111850022', '2019-06-15', 'Bệnh viện Hữu Nghị Việt Đức', 'active', 'Bảo hiểm bắt buộc', NOW()),
(3, 'social', 'BHXH-0790830055', '2020-02-01', 'Bệnh viện Đại Học Y Dược TP.HCM', 'active', 'Bảo hiểm bắt buộc', NOW()),
(3, 'commercial', 'AON-AURA-VIP03', '2020-02-01', 'Bệnh viện FV (Pháp - Việt) Q7 TP.HCM', 'active', 'Gói bảo hiểm sức khỏe VIP lãnh đạo', NOW()),
(4, 'social', 'BHXH-0010890044', '2020-08-15', 'Bệnh viện Quân Y 108', 'active', 'Bảo hiểm bắt buộc', NOW()),
(5, 'social', 'BHXH-0011940066', '2021-04-01', 'Bệnh viện Đa Khoa Hà Đông', 'active', 'Bảo hiểm bắt buộc', NOW()),
(15, 'social', 'BHXH-0480840011', '2020-09-01', 'Bệnh viện C Đà Nẵng', 'active', 'Bảo hiểm bắt buộc', NOW()),
(20, 'social', 'BHXH-0920860011', '2021-10-01', 'Bệnh viện Đa Khoa Trung Ương Cần Thơ', 'active', 'Bảo hiểm bắt buộc', NOW());

-- Người Phụ Thuộc Giảm Trừ Gia Cảnh (employee_dependents)
INSERT INTO `employee_dependents` (`employee_id`, `fullname`, `relationship`, `birth_date`, `phone`, `tax_deductible`, `is_emergency_contact`, `notes`, `created_at`) VALUES
(1, 'Nguyễn Hoàng Minh', 'child', '2010-04-12', NULL, 1, 0, 'Con trai cả', NOW()),
(1, 'Nguyễn Mai Chi', 'child', '2014-08-20', NULL, 1, 0, 'Con gái thứ hai', NOW()),
(1, 'Trần Minh Hằng', 'spouse', '1982-10-05', '0903889977', 0, 1, 'Vợ - Liên hệ khẩn cấp', NOW()),
(2, 'Lê Hoàng Khôi', 'child', '2015-11-18', NULL, 1, 0, 'Con trai', NOW()),
(3, 'Trần Bảo Ngọc', 'child', '2012-06-25', NULL, 1, 0, 'Con gái', NOW()),
(3, 'Lê Thị Thu Thủy', 'spouse', '1986-03-14', '0908776655', 0, 1, 'Vợ - Liên hệ khẩn cấp', NOW()),
(4, 'Vũ Long Nhật', 'child', '2020-01-09', NULL, 1, 0, 'Con trai', NOW()),
(6, 'Hoàng Minh Châu', 'child', '2013-09-15', NULL, 1, 0, 'Con gái', NOW());

-- 10. RECRUITMENT PIPELINE (TUYỂN DỤNG & CHIẾN DỊCH THU HÚT NHÂN TÀI)
INSERT INTO `job_positions` (`id`, `job_code`, `title`, `department_id`, `branch_id`, `position_id`, `quantity`, `salary_range_min`, `salary_range_max`, `requirements`, `description`, `deadline`, `status`, `created_by`, `created_at`) VALUES
(1, 'JOB-2026-001', 'Kỹ Sư Trí Tuệ Nhân Tạo & Phân Tích Bán Lẻ (AI & Retail Data Scientist)', 3, 1, 5, 2, 30000000.00, 45000000.00, 'Tối thiểu 3 năm kinh nghiệm Python, PyTorch/TensorFlow, Big Data Pipeline, am hiểu phân tích dự báo nhu cầu bán lẻ (Demand Forecasting).', 'Xây dựng thuật toán AI gợi ý giỏ hàng thông minh, tối ưu giá linh hoạt (Dynamic Pricing) và tự động hóa điều phối tồn kho chuỗi siêu thị.', '2026-10-31', 'open', 1, NOW()),
(2, 'JOB-2026-002', 'Giám Đốc Cửa Hàng Flagship Bitexco Q1 (Store Manager)', 4, 2, 8, 3, 22000000.00, 35000000.00, 'Tốt nghiệp Đại học, ít nhất 4 năm kinh nghiệm quản lý cửa hàng bán lẻ cao cấp / chuỗi siêu thị quy mô > 50 nhân viên.', 'Chịu trách nhiệm toàn diện doanh thu P&L, chất lượng dịch vụ khách hàng và đào tạo đội ngũ tư vấn viên tại trung tâm thương mại Bitexco.', '2026-10-15', 'open', 1, NOW()),
(3, 'JOB-2026-003', 'Trưởng Nhóm Điều Phối Logistics Vùng Mekong', 5, 4, 6, 1, 18000000.00, 26000000.00, 'Có kinh nghiệm điều hành kho lạnh và mạng lưới xe tải giao hàng chặng cuối (Last-mile delivery) tại Đồng Bằng Sông Cửu Long.', 'Giám sát điều vận đội xe, tối ưu thời gian giao hàng tươi sống từ kho Cần Thơ về 13 tỉnh Tây Nam Bộ trong vòng 4 giờ.', '2026-11-15', 'open', 1, NOW());

INSERT INTO `candidates` (`id`, `candidate_code`, `job_position_id`, `fullname`, `email`, `phone`, `gender`, `birth_date`, `education`, `experience_years`, `cv_file`, `cover_letter`, `source`, `stage`, `rating`, `interview_date`, `interview_notes`, `hired_employee_id`, `created_at`) VALUES
-- Ứng viên cho JOB-2026-001 (AI & Data)
(1, 'CAN-2026-001', 1, 'Nguyễn Phan Anh', 'anh.nguyenphan@gmail.com', '0934112233', 'Nam', '1993-05-10', 'Thạc Sĩ Khoa Học Máy Tính - ĐH Bách Khoa HN', 5, NULL, 'Tôi có 5 năm làm việc tại các sàn TMĐT lớn, chuyên sâu về thuật toán Recommender System.', 'headhunt', 'interview', 5, '2026-09-25 09:30:00', 'Ứng viên xuất sắc, giải quyết bài toán tối ưu gợi ý theo thời gian thực rất tốt.', NULL, NOW()),
(2, 'CAN-2026-002', 1, 'Trần Minh Đức', 'duc.tran95@outlook.com', '0945223344', 'Nam', '1995-08-22', 'Cử Nhân CNTT - ĐH Quốc Gia Hà Nội', 4, NULL, 'Mong muốn phát triển mô hình dự báo tồn kho chuỗi phân phối.', 'job_board', 'screening', 4, NULL, 'Hồ sơ đạt yêu cầu, xếp lịch phỏng vấn vòng 1 với Tech Lead Vũ Hoàng Long.', NULL, NOW()),
(3, 'CAN-2026-003', 1, 'Lê Thị Thu Trang', 'trang.lethu@gmail.com', '0912889900', 'Nu', '1996-01-15', 'Cử Nhân Toán Tin - ĐH Bách Khoa HN', 3, NULL, 'Đam mê khai phá dữ liệu khách hàng bán lẻ.', 'website', 'applied', 3, NULL, 'Mới nộp CV qua website tuyển dụng tập đoàn.', NULL, NOW()),
(4, 'CAN-2026-004', 1, 'Tạ Quang Huy', 'huy.ta.candidate@gmail.com', '0968991122', 'Nam', '1998-09-05', 'Cử Nhân Hệ Thống Thông Tin - NEU', 2, NULL, 'Ứng tuyển vị trí Junior Data Analyst.', 'referral', 'hired', 5, '2026-07-20 14:00:00', 'Đã vượt qua thử việc thành công và bổ nhiệm mã nhân viên AUR024.', 24, NOW()),

-- Ứng viên cho JOB-2026-002 (Store Manager HCM)
(5, 'CAN-2026-005', 2, 'Võ Hoàng Yến Nhi', 'nhi.vo@gmail.com', '0908667788', 'Nu', '1991-03-25', 'Cử Nhân Quản Trị - ĐH Kinh Tế TP.HCM', 6, NULL, 'Cựu Store Manager chuỗi Zara Vietnam.', 'headhunt', 'offer', 5, '2026-09-18 10:00:00', 'Đã gửi thư mời nhận việc (Offer Letter) mức lương 32 triệu + KPI thưởng quý.', NULL, NOW()),
(6, 'CAN-2026-006', 2, 'Bùi Quốc An', 'an.buiquoc@yahoo.com', '0903556677', 'Nam', '1989-11-14', 'Cử Nhân Ngoại Thương - FTU TP.HCM', 7, NULL, 'Từng điều hành chuỗi siêu thị tiện lợi 24h.', 'job_board', 'interview', 4, '2026-09-24 14:30:00', 'Phỏng vấn trực tiếp với Giám Đốc Chi Nhánh Trần Quốc Bảo tại Bitexco.', NULL, NOW()),
(7, 'CAN-2026-007', 2, 'Nguyễn Thanh Tùng', 'tung.nguyen.retail@gmail.com', '0909443322', 'Nam', '1993-07-08', 'Cử Nhân Thương Mại - ĐH Tôn Đức Thắng', 4, NULL, 'Kinh nghiệm quản lý ngành hàng thời trang & phụ kiện.', 'social', 'screening', 3, NULL, 'Chờ kiểm tra xác minh thông tin tham chiếu (Reference Check).', NULL, NOW()),
(8, 'CAN-2026-008', 2, 'Trịnh Thị Mai Lan', 'lan.trinhmai@gmail.com', '0902113355', 'Nu', '1995-09-30', 'Cử Nhân Quản Trị Khách Sạn - RMIT', 3, NULL, 'Tập trung trải nghiệm khách hàng tiêu chuẩn 5 sao.', 'referral', 'offer', 4, '2026-09-15 15:00:00', 'Đã chốt offer ngày nhận việc dự kiến 01/10/2026.', NULL, NOW()),
(9, 'CAN-2026-009', 2, 'Đào Văn Hùng', 'hung.daovan@gmail.com', '0907889911', 'Nam', '1988-12-02', 'Cao Đẳng Kinh Tế Đối Ngoại', 2, NULL, 'Ứng tuyển quản trị cửa hàng.', 'job_board', 'rejected', 2, '2026-09-10 11:00:00', 'Kinh nghiệm chưa phù hợp với định vị thương hiệu cao cấp.', NULL, NOW()),

-- Ứng viên cho JOB-2026-003 (Logistics Mekong)
(10, 'CAN-2026-010', 3, 'Trần Minh Khang', 'khang.tran.logistics@gmail.com', '0918776655', 'Nam', '1990-06-18', 'Kỹ Sư Vận Tải - ĐH Cần Thơ', 5, NULL, 'Có kinh nghiệm quản lý 30 đầu xe tải lạnh.', 'headhunt', 'interview', 4, '2026-09-26 10:00:00', 'Hẹn phỏng vấn trực tuyến với Trưởng Trung Tâm Võ Thành Trung.', NULL, NOW()),
(11, 'CAN-2026-011', 3, 'Nguyễn Phúc Hậu', 'hau.nguyenphuc@gmail.com', '0919332211', 'Nam', '1992-02-14', 'Cử Nhân Logistics - ĐH Giao Thông Vận Tải TP.HCM', 4, NULL, 'Chuyên trách tối ưu tuyến đường giao vận.', 'job_board', 'screening', 4, NULL, 'Đang chấm bài test chuyên môn quản trị chuỗi cung ứng.', NULL, NOW()),
(12, 'CAN-2026-012', 3, 'Lê Hoàng Sơn', 'son.lehoang94@gmail.com', '0917665544', 'Nam', '1994-10-20', 'Cử Nhân Quản Trị Kinh Doanh', 3, NULL, 'Kinh nghiệm điều phối kho vận miền Tây.', 'website', 'applied', 3, NULL, 'Hồ sơ mới tiếp nhận, chờ duyệt vòng sơ khảo.', NULL, NOW());

-- 11. INTERNAL TRANSFERS & STRATEGIC PLANNING (ĐIỀU ĐỘNG CÔNG TÁC & QUY HOẠCH NHÂN SỰ)
INSERT INTO `transfers` (`id`, `transfer_code`, `employee_id`, `from_branch_id`, `to_branch_id`, `from_department_id`, `to_department_id`, `from_position_id`, `to_position_id`, `transfer_type`, `reason`, `effective_date`, `decision_number`, `allowance_support`, `status`, `requested_by`, `approved_by`, `approved_at`, `executed_at`, `notes`, `created_at`) VALUES
(1, 'TF-2026-001', 16, 3, 4, 5, 5, 6, 6, 'temporary', 'Biệt phái chuyên viên cao cấp hỗ trợ chuẩn hóa quy trình tiếp nhận & lưu kho tại Trung Tâm Phân Phối Mekong mới vận hành', '2026-10-01', 'QĐ-AURA/2026/088-ĐĐ', 5000000.00, 'approved', 1, 1, '2026-09-15 10:00:00', NULL, 'Hỗ trợ chi phí nhà ở và phụ cấp xa nhà 5,000,000 VNĐ/tháng', NOW()),
(2, 'TF-2026-002', 11, 2, 3, 4, 4, 4, 4, 'promotion', 'Điều động & bổ nhiệm Trưởng Phòng Phát Triển Thị Trường Miền Trung phục vụ mở rộng mạng lưới 10 điểm bán mới tại Đà Nẵng', '2026-08-01', 'QĐ-AURA/2026/065-ĐĐ', 6000000.00, 'executed', 1, 1, '2026-07-25 15:30:00', '2026-08-01 08:00:00', 'Đã hoàn tất bàn giao tại HCM và nhận nhiệm vụ thành công tại Đà Nẵng', NOW()),
(3, 'TF-2026-003', 7, 1, 2, 3, 4, 5, 5, 'relocation', 'Luân chuyển công tác kỹ sư giải pháp để trực tiếp triển khai kiến trúc Smart POS Omni-channel tại chuỗi cửa hàng TP.HCM', '2026-10-15', 'QĐ-AURA/2026/092-ĐĐ', 4500000.00, 'pending', 1, NULL, NULL, NULL, 'Đang lấy ý kiến thống nhất giữa Ban Giám Đốc CNTT và Chi Nhánh TP.HCM', NOW()),
(4, 'TF-2026-004', 13, 2, 1, 4, 4, 8, 4, 'regular', 'Đề xuất chuyển công tác về Hội Sở Hà Nội theo nguyện vọng cá nhân', '2026-09-01', 'QĐ-AURA/2026/079-ĐĐ', 0.00, 'rejected', 2, 1, '2026-08-28 14:00:00', NULL, 'Tạm hoãn do Chi Nhánh TP.HCM đang trong đợt cao điểm khai trương quý 3', NOW());

INSERT INTO `transfer_plans` (`id`, `plan_code`, `title`, `description`, `plan_type`, `target_branch_id`, `status`, `start_date`, `end_date`, `created_by`, `created_at`) VALUES
(1, 'PLAN-2026-MEKONG', 'Chiến Lược Điều Động & Tăng Cường Nhân Sự Mở Rộng Thị Trường Tây Nam Bộ', 'Kế hoạch bổ sung 15 nhân sự nòng cốt và điều chuyển 3 cán bộ kỹ thuật cao cấp hỗ trợ khai trương Trung Tâm Phân Phối Mekong tại Cần Thơ', 'branch_expansion', 4, 'active', '2026-07-01', '2026-12-31', 1, NOW());

INSERT INTO `transfer_plan_items` (`id`, `plan_id`, `employee_id`, `from_branch_id`, `to_branch_id`, `from_department_id`, `to_department_id`, `from_position_id`, `to_position_id`, `priority`, `rationale`, `estimated_allowance`, `transfer_id`, `created_at`) VALUES
(1, 1, 16, 3, 4, 5, 5, 6, 6, 'high', 'Chuyên gia giàu kinh nghiệm thiết lập hệ thống WMS kho lạnh', 5000000.00, 1, NOW()),
(2, 1, 13, 2, 4, 4, 4, 8, 8, 'medium', 'Hỗ trợ đào tạo nghiệp vụ quản lý quầy hàng và thu ngân chuẩn tập đoàn', 4000000.00, NULL, NOW());

-- 12. REWARDS & DISCIPLINES (KHEN THƯỞNG & KỶ LUẬT DOANH NGHIỆP)
INSERT INTO `rewards` (`id`, `reward_code`, `employee_id`, `reward_type`, `title`, `description`, `amount`, `decision_number`, `reward_date`, `status`, `attachment`, `created_by`, `approved_by`, `approved_at`, `executed_at`, `created_at`) VALUES
(1, 'REW-2026-001', 4, 'achievement', 'Thành tích xuất sắc triển khai Omni-channel ERP Retail 4 Chi Nhánh vượt tiến độ 15 ngày', 'Khen thưởng Tech Lead và đội ngũ kỹ thuật đã hoàn thành vượt tiến độ hệ thống đồng bộ dữ liệu thời gian thực giữa Hội Sở và các Trung tâm phân phối', 15000000.00, 'KT-AURA/2026/015', '2026-08-30', 'approved', NULL, 1, 1, '2026-08-30 11:00:00', '2026-09-05 09:00:00', NOW()),
(2, 'REW-2026-002', 12, 'bonus', 'Khen thưởng Đạt kỷ lục doanh thu tháng khai trương Flagship Bitexco Q1', 'Quản lý cửa hàng xuất sắc dẫn dắt doanh thu vượt 145% chỉ tiêu tháng đầu mở bán', 10000000.00, 'KT-AURA/2026/018', '2026-09-10', 'approved', NULL, 1, 1, '2026-09-10 16:00:00', '2026-09-15 10:00:00', NOW()),
(3, 'REW-2026-003', 15, 'achievement', 'Sáng kiến tối ưu hóa lộ trình chuỗi cung ứng Bắc - Trung - Nam tiết kiệm 12% chi phí', 'Tối ưu luồng xe tải lạnh liên vận giúp giảm chi phí nhiên liệu và tỷ lệ hao hụt hàng nông sản tươi sống', 8000000.00, 'KT-AURA/2026/020', '2026-09-15', 'approved', NULL, 1, 1, '2026-09-15 14:00:00', '2026-09-20 09:00:00', NOW());

INSERT INTO `disciplines` (`id`, `discipline_code`, `employee_id`, `discipline_type`, `title`, `description`, `penalty_amount`, `decision_number`, `discipline_date`, `status`, `attachment`, `created_by`, `approved_by`, `approved_at`, `executed_at`, `created_at`) VALUES
(1, 'DIS-2026-001', 17, 'warning', 'Nhắc nhở nội bộ: Vi phạm quy trình bàn giao chứng từ nhập xuất kho lạnh', 'Chậm trễ nộp biên bản đối soát kho ngày 05/09, gây ảnh hưởng tiến độ chốt số liệu kế toán tuần', 0.00, 'KL-AURA/2026/004', '2026-09-08', 'approved', NULL, 1, 1, '2026-09-08 15:00:00', '2026-09-09 08:30:00', NOW()),
(2, 'DIS-2026-002', 22, 'reprimand', 'Khiển trách bằng văn bản: Vắng mặt không phép 02 ngày trong đợt cao điểm mở bán', 'Tự ý nghỉ việc không báo cáo quản lý trực tiếp trong hai ngày khuyến mãi lớn của siêu thị', 1000000.00, 'KL-AURA/2026/007', '2026-09-12', 'approved', NULL, 1, 1, '2026-09-12 17:00:00', '2026-09-13 09:00:00', NOW());

-- 13. ATTENDANCE (CHẤM CÔNG THÁNG 9/2026 - SINH ĐỘNG, ĐỦ CÁC TRẠNG THÁI)
-- Chấm công các ngày làm việc tiêu biểu trong tháng 9/2026: 2026-09-15 -> 2026-09-22
INSERT INTO `attendance` (`employee_id`, `date`, `check_in`, `check_out`, `status`, `note`) VALUES
-- Ngày 2026-09-21 (Thứ Hai)
(1, '2026-09-21', '08:15:00', '18:30:00', 'present', 'Làm việc bình thường tại Trụ sở Keangnam'),
(2, '2026-09-21', '08:20:00', '17:45:00', 'present', 'Làm việc bình thường'),
(3, '2026-09-21', '08:25:00', '18:00:00', 'present', 'Làm việc tại Bitexco HCM'),
(4, '2026-09-21', '08:10:00', '19:15:00', 'present', 'Họp kỹ thuật Sprint Review'),
(5, '2026-09-21', '08:28:00', '17:35:00', 'present', 'Tiếp nhận hồ sơ ứng viên'),
(6, '2026-09-21', '08:22:00', '17:40:00', 'present', 'Làm việc bình thường'),
(7, '2026-09-21', '08:42:00', '18:10:00', 'late', 'Đến muộn 12 phút do tắc đường Phạm Hùng'),
(8, '2026-09-21', '08:18:00', '17:50:00', 'present', 'Làm việc bình thường'),
(9, '2026-09-21', '08:26:00', '17:30:00', 'present', 'Làm việc bình thường'),
(10, '2026-09-21', '08:20:00', '17:35:00', 'present', 'Làm việc bình thường'),
(11, '2026-09-21', '08:15:00', '18:00:00', 'present', 'Công tác tại Đà Nẵng'),
(12, '2026-09-21', '08:30:00', '21:30:00', 'present', 'Trực ca quản lý Flagship'),
(13, '2026-09-21', '08:25:00', '17:30:00', 'present', 'Làm việc bình thường'),
(14, '2026-09-21', '08:20:00', '17:35:00', 'present', 'Làm việc bình thường'),
(15, '2026-09-21', '08:10:00', '17:45:00', 'present', 'Giám sát vận hành Hub Đà Nẵng'),
(16, '2026-09-21', '08:15:00', '17:50:00', 'present', 'Làm việc bình thường'),
(17, '2026-09-21', '08:48:00', '17:30:00', 'late', 'Đi muộn 18 phút do xe hỏng'),
(18, '2026-09-21', '08:25:00', '17:30:00', 'present', 'Làm việc bình thường'),
(19, '2026-09-21', NULL, NULL, 'leave_with_permit', 'Nghỉ phép thường niên có đơn phê duyệt'),
(20, '2026-09-21', '08:05:00', '18:00:00', 'present', 'Điều hành kho Mekong Cần Thơ'),
(21, '2026-09-21', '08:15:00', '17:30:00', 'present', 'Kiểm kê kho hàng tuần'),
(22, '2026-09-21', '08:20:00', '17:30:00', 'present', 'Làm việc bình thường'),
(23, '2026-09-21', '08:10:00', '17:30:00', 'present', 'Làm việc bình thường'),
(24, '2026-09-21', '08:25:00', '17:45:00', 'present', 'Nhân viên thử việc - đúng giờ'),
(25, '2026-09-21', '08:20:00', '17:30:00', 'present', 'Nhân viên thử việc - đúng giờ'),

-- Ngày 2026-09-22 (Hôm nay)
(1, '2026-09-22', '08:10:00', '18:00:00', 'present', 'Chủ trì cuộc họp giao ban toàn quốc'),
(2, '2026-09-22', '08:15:00', '17:45:00', 'present', 'Làm việc bình thường'),
(3, '2026-09-22', '08:20:00', '18:00:00', 'present', 'Điều hành chi nhánh TP.HCM'),
(4, '2026-09-22', '08:12:00', '18:30:00', 'present', 'Làm việc bình thường'),
(5, '2026-09-22', '08:25:00', '17:35:00', 'present', 'Làm việc bình thường'),
(6, '2026-09-22', '08:18:00', '17:30:00', 'present', 'Làm việc bình thường'),
(7, '2026-09-22', '08:15:00', '17:30:00', 'present', 'Đúng giờ'),
(8, '2026-09-22', '08:22:00', '17:45:00', 'present', 'Làm việc bình thường'),
(9, '2026-09-22', '08:20:00', '17:30:00', 'present', 'Làm việc bình thường'),
(10, '2026-09-22', '08:24:00', '17:30:00', 'present', 'Làm việc bình thường'),
(11, '2026-09-22', '08:15:00', '17:30:00', 'present', 'Làm việc tại Đà Nẵng'),
(12, '2026-09-22', '08:28:00', '17:30:00', 'present', 'Làm việc bình thường'),
(13, '2026-09-22', '08:45:00', '17:30:00', 'late', 'Đến muộn 15 phút do thời tiết mưa lớn'),
(14, '2026-09-22', '08:19:00', '17:30:00', 'present', 'Làm việc bình thường'),
(15, '2026-09-22', '08:08:00', '17:30:00', 'present', 'Làm việc bình thường'),
(16, '2026-09-22', '08:12:00', '17:30:00', 'present', 'Làm việc bình thường'),
(17, '2026-09-22', '08:22:00', '17:30:00', 'present', 'Làm việc bình thường'),
(18, '2026-09-22', '08:15:00', '17:30:00', 'present', 'Làm việc bình thường'),
(19, '2026-09-22', '08:25:00', '17:30:00', 'present', 'Đã đi làm trở lại sau nghỉ phép'),
(20, '2026-09-22', '08:06:00', '17:30:00', 'present', 'Làm việc bình thường'),
(21, '2026-09-22', '08:18:00', '17:30:00', 'present', 'Làm việc bình thường'),
(22, '2026-09-22', NULL, NULL, 'absent', 'Vắng mặt không lý do'),
(23, '2026-09-22', '08:14:00', '17:30:00', 'present', 'Làm việc bình thường'),
(24, '2026-09-22', '08:22:00', '17:30:00', 'present', 'Thử việc'),
(25, '2026-09-22', '08:20:00', '17:30:00', 'present', 'Thử việc');

-- 14. PAYROLLS (BẢNG LƯƠNG ĐA THÁNG - QUY MÔ DOANH NGHIỆP LỚN)
-- Tháng 7/2026 (Đã thanh toán)
INSERT INTO `payrolls` (`employee_id`, `month`, `year`, `basic_salary`, `work_days`, `allowance`, `deduction`, `final_salary`, `payment_status`, `created_at`) VALUES
(1, 7, 2026, 80000000.00, 22.0, 20000000.00, 18500000.00, 81500000.00, 'paid', '2026-08-05 10:00:00'),
(2, 7, 2026, 40000000.00, 22.0, 10000000.00, 7200000.00, 42800000.00, 'paid', '2026-08-05 10:00:00'),
(3, 7, 2026, 45000000.00, 22.0, 12000000.00, 8500000.00, 48500000.00, 'paid', '2026-08-05 10:00:00'),
(4, 7, 2026, 26000000.00, 22.0, 5000000.00, 3200000.00, 27800000.00, 'paid', '2026-08-05 10:00:00'),
(5, 7, 2026, 15000000.00, 22.0, 3000000.00, 1575000.00, 16425000.00, 'paid', '2026-08-05 10:00:00'),
(6, 7, 2026, 28000000.00, 22.0, 6000000.00, 3800000.00, 30200000.00, 'paid', '2026-08-05 10:00:00'),
(7, 7, 2026, 26000000.00, 21.5, 4000000.00, 3100000.00, 26309090.00, 'paid', '2026-08-05 10:00:00'),
(8, 7, 2026, 26000000.00, 22.0, 4000000.00, 3100000.00, 26900000.00, 'paid', '2026-08-05 10:00:00'),
(11, 7, 2026, 28000000.00, 22.0, 8000000.00, 4200000.00, 31800000.00, 'paid', '2026-08-05 10:00:00'),
(12, 7, 2026, 16000000.00, 22.0, 5000000.00, 1800000.00, 19200000.00, 'paid', '2026-08-05 10:00:00'),
(15, 7, 2026, 45000000.00, 22.0, 10000000.00, 8200000.00, 46800000.00, 'paid', '2026-08-05 10:00:00'),
(16, 7, 2026, 18000000.00, 22.0, 4000000.00, 2100000.00, 19900000.00, 'paid', '2026-08-05 10:00:00'),
(20, 7, 2026, 45000000.00, 22.0, 8000000.00, 7800000.00, 45200000.00, 'paid', '2026-08-05 10:00:00');

-- Tháng 8/2026 (Đã thanh toán)
INSERT INTO `payrolls` (`employee_id`, `month`, `year`, `basic_salary`, `work_days`, `allowance`, `deduction`, `final_salary`, `payment_status`, `created_at`) VALUES
(1, 8, 2026, 80000000.00, 22.0, 20000000.00, 18500000.00, 81500000.00, 'paid', '2026-09-05 10:00:00'),
(2, 8, 2026, 40000000.00, 22.0, 10000000.00, 7200000.00, 42800000.00, 'paid', '2026-09-05 10:00:00'),
(3, 8, 2026, 45000000.00, 22.0, 12000000.00, 8500000.00, 48500000.00, 'paid', '2026-09-05 10:00:00'),
(4, 8, 2026, 26000000.00, 22.0, 20000000.00, 4500000.00, 41500000.00, 'paid', '2026-09-05 10:00:00'), -- Có thưởng dự án 15M
(5, 8, 2026, 15000000.00, 22.0, 3000000.00, 1575000.00, 16425000.00, 'paid', '2026-09-05 10:00:00'),
(6, 8, 2026, 28000000.00, 22.0, 6000000.00, 3800000.00, 30200000.00, 'paid', '2026-09-05 10:00:00'),
(7, 8, 2026, 26000000.00, 22.0, 4000000.00, 3100000.00, 26900000.00, 'paid', '2026-09-05 10:00:00'),
(8, 8, 2026, 26000000.00, 22.0, 4000000.00, 3100000.00, 26900000.00, 'paid', '2026-09-05 10:00:00'),
(11, 8, 2026, 28000000.00, 22.0, 14000000.00, 4800000.00, 37200000.00, 'paid', '2026-09-05 10:00:00'), -- Phụ cấp biệt phái 6M
(12, 8, 2026, 16000000.00, 22.0, 15000000.00, 2600000.00, 28400000.00, 'paid', '2026-09-05 10:00:00'), -- Thưởng kỷ lục mở bán 10M
(15, 8, 2026, 45000000.00, 22.0, 10000000.00, 8200000.00, 46800000.00, 'paid', '2026-09-05 10:00:00'),
(16, 8, 2026, 18000000.00, 22.0, 4000000.00, 2100000.00, 19900000.00, 'paid', '2026-09-05 10:00:00'),
(20, 8, 2026, 45000000.00, 22.0, 8000000.00, 7800000.00, 45200000.00, 'paid', '2026-09-05 10:00:00'),
(24, 8, 2026, 22100000.00, 22.0, 2000000.00, 2100000.00, 22000000.00, 'paid', '2026-09-05 10:00:00'), -- Lương thử việc
(25, 8, 2026, 13600000.00, 11.0, 750000.00, 700000.00, 6850000.00, 'paid', '2026-09-05 10:00:00');  -- Vào làm từ 15/08

-- Tháng 9/2026 (Đang lập & chờ phê duyệt - Pending)
INSERT INTO `payrolls` (`employee_id`, `month`, `year`, `basic_salary`, `work_days`, `allowance`, `deduction`, `final_salary`, `payment_status`, `created_at`) VALUES
(1, 9, 2026, 80000000.00, 22.0, 20000000.00, 18500000.00, 81500000.00, 'pending', NOW()),
(2, 9, 2026, 40000000.00, 22.0, 10000000.00, 7200000.00, 42800000.00, 'pending', NOW()),
(3, 9, 2026, 45000000.00, 22.0, 12000000.00, 8500000.00, 48500000.00, 'pending', NOW()),
(4, 9, 2026, 26000000.00, 22.0, 5000000.00, 3200000.00, 27800000.00, 'pending', NOW()),
(5, 9, 2026, 15000000.00, 22.0, 3000000.00, 1575000.00, 16425000.00, 'pending', NOW()),
(6, 9, 2026, 28000000.00, 22.0, 6000000.00, 3800000.00, 30200000.00, 'pending', NOW()),
(7, 9, 2026, 26000000.00, 22.0, 4000000.00, 3100000.00, 26900000.00, 'pending', NOW()),
(8, 9, 2026, 26000000.00, 22.0, 4000000.00, 3100000.00, 26900000.00, 'pending', NOW()),
(11, 9, 2026, 28000000.00, 22.0, 14000000.00, 4800000.00, 37200000.00, 'pending', NOW()),
(12, 9, 2026, 16000000.00, 22.0, 5000000.00, 1800000.00, 19200000.00, 'pending', NOW()),
(15, 9, 2026, 45000000.00, 22.0, 18000000.00, 8900000.00, 54100000.00, 'pending', NOW()), -- Thưởng sáng kiến SCM 8M
(16, 9, 2026, 18000000.00, 22.0, 4000000.00, 2100000.00, 19900000.00, 'pending', NOW()),
(20, 9, 2026, 45000000.00, 22.0, 8000000.00, 7800000.00, 45200000.00, 'pending', NOW()),
(24, 9, 2026, 22100000.00, 22.0, 2000000.00, 2100000.00, 22000000.00, 'pending', NOW()),
(25, 9, 2026, 13600000.00, 22.0, 1500000.00, 1400000.00, 13700000.00, 'pending', NOW());

SET FOREIGN_KEY_CHECKS = 1;
