-- database/migration_performance_indexes.sql
-- Bổ sung các chỉ mục tổng hợp (Composite Indexes) nhằm tối ưu hóa truy vấn Dashboard, Chấm công, Bảng lương, KPI và Đề xuất.

CREATE INDEX idx_employees_scope ON employees (branch_id, department_id, employment_status);
CREATE INDEX idx_attendance_date_status ON attendance (date, status);
CREATE INDEX idx_payrolls_period_status ON payrolls (year, month, payment_status, locked_at);
CREATE INDEX idx_evaluations_period_status ON evaluations (period_id, status);
CREATE INDEX idx_evaluations_emp_status ON evaluations (employee_id, status);
CREATE INDEX idx_proposals_status_emp ON proposals (status, employee_id);
CREATE INDEX idx_proposals_emp_type_status ON proposals (employee_id, type, status);
