-- database/migration_data_scope.sql
-- Thêm phạm vi dữ liệu (data_scope) vào bảng roles
-- Hỗ trợ phân quyền ma trận 2 chiều: Chức năng (RBAC) x Phạm vi dữ liệu (Data Scope)

ALTER TABLE roles 
ADD COLUMN data_scope ENUM('all', 'branch', 'department', 'self') NOT NULL DEFAULT 'self' AFTER description;

-- Cập nhật phạm vi mặc định cho các vai trò hiện có:
UPDATE roles SET data_scope = 'all' WHERE id = 1; -- Admin Toàn Quyền / HR Director: xem toàn tập đoàn
UPDATE roles SET data_scope = 'branch' WHERE id = 2; -- Giám Đốc Chi Nhánh: phạm vi chi nhánh của mình
UPDATE roles SET data_scope = 'all' WHERE id = 3; -- Chuyên Viên Nhân Sự: phạm vi toàn tập đoàn (thực thi tác vụ HR)
UPDATE roles SET data_scope = 'self' WHERE id = 4; -- Nhân Viên: chỉ xem và tác nghiệp dữ liệu của chính mình

