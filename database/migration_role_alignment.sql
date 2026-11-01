-- database/migration_role_alignment.sql
-- Chuẩn hóa vai trò người dùng và tạo vai trò Chuyên Viên C&B

-- 1. Tạo vai trò mới: Chuyên Viên C&B (Lương & Phúc Lợi)
INSERT INTO roles (id, name, description, data_scope)
VALUES (5, 'Chuyên Viên C&B (Lương & Phúc Lợi)', 'Chuyên trách tính bảng lương, BHXH, thuế TNCN, phúc lợi và chính sách đãi ngộ toàn chuỗi', 'all')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    description = VALUES(description), 
    data_scope = VALUES(data_scope);

-- 2. Gán quyền cho Chuyên Viên C&B (Role 5)
DELETE FROM role_permissions WHERE role_id = 5;
INSERT INTO role_permissions (role_id, permission_id) VALUES
(5, 1),   -- employees.view
(5, 5),   -- departments.view
(5, 9),   -- attendance.view
(5, 10),  -- attendance.create
(5, 11),  -- attendance.edit
(5, 13),  -- payroll.view
(5, 14),  -- payroll.create
(5, 15),  -- payroll.edit
(5, 19),  -- branches.view
(5, 23),  -- orgchart.view
(5, 32),  -- rewards.view
(5, 33),  -- rewards.create
(5, 36),  -- disciplines.view
(5, 45),  -- proposals.view
(5, 46),  -- proposals.create
(5, 49),  -- evaluations.view
(5, 53);  -- evaluations.self_review

-- 3. Cập nhật tài khoản CHRO phuong.le@wincommerce.vn
-- Chuyển từ Role 2 (Branch Manager) sang Role 1 (HR Director / Admin Toàn Quyền), is_superadmin = 0
UPDATE users 
SET role_id = 1, is_superadmin = 0 
WHERE username = 'phuong.le@wincommerce.vn';

-- 4. Cập nhật tài khoản nhanvien@wincommerce.vn (Phạm Thu Thảo) thành vai trò C&B demo (Role 5)
UPDATE users 
SET role_id = 5 
WHERE username = 'nhanvien@wincommerce.vn';

-- Tạo thêm tài khoản cb@wincommerce.vn (chia sẻ mật khẩu WinMart@2026 với nhanvien) để thuận tiện gõ demo
INSERT INTO users (role_id, username, password, fullname, email, is_superadmin, status)
SELECT 5, 'cb@wincommerce.vn', password, fullname, 'cb@wincommerce.vn', 0, 'active'
FROM users WHERE username = 'nhanvien@wincommerce.vn'
ON DUPLICATE KEY UPDATE role_id = 5;

