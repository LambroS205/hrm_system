<?php
// core/scope.php - Quản lý phạm vi dữ liệu đa chiều (Data Scope: all, branch, department, self)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Lấy hồ sơ nhân viên liên kết với tài khoản người dùng hiện tại
 * @param bool $refresh
 * @return array|null
 */
function current_employee(bool $refresh = false): ?array {
    if (!isset($_SESSION['user']['id'])) {
        return null;
    }

    if (!$refresh && isset($_SESSION['current_employee'])) {
        return $_SESSION['current_employee'];
    }

    global $pdo;
    if (!$pdo) {
        return null;
    }

    $uid = (int)$_SESSION['user']['id'];
    $email = $_SESSION['user']['email'] ?? '';

    try {
        $stmt = $pdo->prepare("
            SELECT e.*, 
                   b.name AS branch_name, b.code AS branch_code,
                   d.name AS department_name, d.code AS department_code,
                   p.name AS position_name
            FROM employees e
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE e.user_id = ? 
               OR (e.email = ? AND ? != '')
               OR (? IN ('cb@wincommerce.vn', 'nhanvien@wincommerce.vn') AND e.employee_code = 'WCM005')
            ORDER BY (e.user_id = ?) DESC
            LIMIT 1
        ");
        $stmt->execute([$uid, $email, $email, $email, $uid]);
        $emp = $stmt->fetch();

        if ($emp) {
            $_SESSION['current_employee'] = $emp;
            return $emp;
        }
    } catch (PDOException $e) {
        log_system_error("Lỗi lấy thông tin current_employee", $e);
    }

    $_SESSION['current_employee'] = null;
    return null;
}

/**
 * Lấy phạm vi dữ liệu hiện tại của phiên đăng nhập
 * @return string 'all' | 'branch' | 'department' | 'self'
 */
function data_scope(): string {
    if (is_superadmin()) {
        return 'all';
    }
    return $_SESSION['user']['data_scope'] ?? 'self';
}

function is_self_scope(): bool {
    return data_scope() === 'self';
}

function is_branch_scope(): bool {
    return data_scope() === 'branch';
}

function is_all_scope(): bool {
    return data_scope() === 'all';
}

/**
 * Lấy ID chi nhánh của người dùng hiện tại
 */
function current_user_branch_id(): ?int {
    $emp = current_employee();
    return $emp ? (int)$emp['branch_id'] : null;
}

/**
 * Lấy ID phòng ban của người dùng hiện tại
 */
function current_user_dept_id(): ?int {
    $emp = current_employee();
    return $emp ? (int)$emp['department_id'] : null;
}

/**
 * Sinh mệnh đề WHERE lọc phạm vi dữ liệu và nạp tham số PDO
 * 
 * @param string $alias Tên alias của bảng employees (vd: 'e') hoặc bảng có chứa branch_id/employee_id
 * @param array &$params Mảng tham số truyền vào execute()
 * @param string $branchCol Tên cột chi nhánh (mặc định 'branch_id')
 * @param string $deptCol Tên cột phòng ban (mặc định 'department_id')
 * @param string $empIdCol Tên cột ID nhân viên (mặc định 'id', nếu ở bảng khác có thể là 'employee_id')
 * @return string Mệnh đề SQL an toàn
 */
function scope_where(string $alias, array &$params, string $branchCol = 'branch_id', string $deptCol = 'department_id', string $empIdCol = 'id'): string {
    $scope = data_scope();

    if ($scope === 'all') {
        return '1=1';
    }

    $prefix = $alias ? "{$alias}." : "";

    if ($scope === 'branch') {
        $branchId = current_user_branch_id();
        if ($branchId) {
            $params[] = $branchId;
            return "{$prefix}{$branchCol} = ?";
        }
        return '1=1';
    }

    if ($scope === 'department') {
        $deptId = current_user_dept_id();
        if ($deptId) {
            $params[] = $deptId;
            return "{$prefix}{$deptCol} = ?";
        }
        return '1=1';
    }

    if ($scope === 'self') {
        $emp = current_employee();
        if ($emp) {
            $params[] = (int)$emp['id'];
            return "{$prefix}{$empIdCol} = ?";
        }
        return '1=0'; // Không tìm thấy hồ sơ nhân viên -> từ chối an toàn
    }

    return '1=1';
}

/**
 * Kiểm tra xem người dùng hiện tại có quyền truy cập hồ sơ của một nhân viên mục tiêu không
 */
function can_access_employee(int $targetEmployeeId): bool {
    $scope = data_scope();
    if ($scope === 'all') {
        return true;
    }

    if ($targetEmployeeId <= 0) {
        return false;
    }

    $emp = current_employee();
    if (!$emp) {
        return false;
    }

    if ($scope === 'self') {
        return (int)$emp['id'] === $targetEmployeeId;
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT branch_id, department_id FROM employees WHERE id = ?");
        $stmt->execute([$targetEmployeeId]);
        $target = $stmt->fetch();

        if (!$target) {
            return false;
        }

        if ($scope === 'branch') {
            return (int)$target['branch_id'] === (int)$emp['branch_id'];
        }

        if ($scope === 'department') {
            return (int)$target['department_id'] === (int)$emp['department_id'];
        }
    } catch (PDOException $e) {
        return false;
    }

    return false;
}

/**
 * Bắt buộc người dùng phải có quyền truy cập nhân viên mục tiêu trong phạm vi dữ liệu
 */
function require_employee_access(int $targetEmployeeId): void {
    require_login();
    if (!can_access_employee($targetEmployeeId)) {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="vi" class="h-full">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Ngoài Phạm Vi Phân Quyền Dữ Liệu</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-slate-50 dark:bg-slate-900 flex items-center justify-center min-h-screen p-4 font-sans text-slate-800 dark:text-slate-100">
            <div class="max-w-md w-full bg-white dark:bg-slate-800 rounded-3xl shadow-xl p-8 text-center border border-slate-100 dark:border-slate-700 transition-all">
                <div class="w-16 h-16 bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-sm">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Giới Hạn Phạm Vi Dữ Liệu</h1>
                <p class="text-slate-600 dark:text-slate-300 text-sm mb-6 leading-relaxed">
                    Hồ sơ nhân sự này thuộc đơn vị khác ngoài phạm vi quyền hạn được cấp của bạn (<span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-semibold text-amber-600 dark:text-amber-400">' . e(data_scope()) . '</span>). Tuân thủ nguyên tắc bảo mật thông tin nội bộ.
                </p>
                <div class="flex items-center justify-center gap-3">
                    <a href="javascript:history.back()" class="px-4 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-medium rounded-xl text-sm transition">
                        <i class="fa-solid fa-arrow-left mr-1.5"></i> Quay Lại
                    </a>
                    <a href="' . base_url('index.php') . '" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl text-sm transition shadow-md shadow-indigo-200 dark:shadow-none">
                        <i class="fa-solid fa-house mr-1.5"></i> Về Trang Chủ
                    </a>
                </div>
            </div>
        </body>
        </html>';
        exit;
    }
}
