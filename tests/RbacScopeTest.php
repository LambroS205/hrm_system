<?php
// tests/RbacScopeTest.php - Kiểm thử RBAC 6 Vai Trò, Ma Trận Phân Quyền & Data Scope
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/scope.php';
require_once __DIR__ . '/../core/KpiService.php';

$passed = 0;
$failed = 0;

function rbac_assert(bool $cond, string $message): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "============================================================\n";
echo "   KIỂM THỬ RBAC (6 VAI TRÒ), MA TRẬN & DATA SCOPE          \n";
echo "============================================================\n\n";

// 1. Kiểm tra độ phủ Ma trận phân quyền (14 modules)
$allPerms = $pdo->query("SELECT id, module, action FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
$matrixModules = [
    'employees', 'recruitment', 'evaluations', 'proposals', 'rewards',
    'disciplines', 'departments', 'branches', 'orgchart', 'transfers',
    'planning', 'attendance', 'payroll', 'matrix'
];
$handledActions = ['view', 'create', 'edit', 'manage', 'delete', 'approve', 'hire', 'request', 'self_review', 'manager_review'];

$uncovered = [];
foreach ($allPerms as $p) {
    if (!in_array($p['module'], $matrixModules, true) || !in_array($p['action'], $handledActions, true)) {
        $uncovered[] = $p['module'] . ':' . $p['action'];
    }
}
rbac_assert(count($uncovered) === 0, 'Ma trận phân quyền phủ 100% quyền hệ thống (' . count($allPerms) . ' permissions)');

// 2. Kiểm tra bất biến phân quyền trọng yếu theo 6 Vai Trò
$checkRolePerm = $pdo->prepare("
    SELECT COUNT(*) FROM role_permissions rp
    JOIN permissions p ON rp.permission_id = p.id
    WHERE rp.role_id = ? AND p.module = ? AND p.action = ?
");
$roleHasPerm = function(int $roleId, string $mod, string $act) use ($checkRolePerm): bool {
    $checkRolePerm->execute([$roleId, $mod, $act]);
    return (int)$checkRolePerm->fetchColumn() > 0;
};

rbac_assert(!$roleHasPerm(4, 'evaluations', 'manager_review'), 'Role #4 (Nhân Viên) không có evaluations:manager_review');
rbac_assert($roleHasPerm(4, 'evaluations', 'self_review'), 'Role #4 (Nhân Viên) có evaluations:self_review');
rbac_assert(!$roleHasPerm(2, 'evaluations', 'create'), 'Role #2 (GĐ Chi nhánh) không tạo kỳ/bộ đề KPI toàn cục');
rbac_assert($roleHasPerm(2, 'evaluations', 'approve'), 'Role #2 (GĐ Chi nhánh) có evaluations:approve');
rbac_assert($roleHasPerm(3, 'recruitment', 'hire'), 'Role #3 (HR Exec) có recruitment:hire');
rbac_assert($roleHasPerm(3, 'evaluations', 'create'), 'Role #3 (HR Exec) có evaluations:create');
rbac_assert($roleHasPerm(5, 'payroll', 'edit'), 'Role #5 (C&B / Payroll) có payroll:edit');
rbac_assert($roleHasPerm(6, 'proposals', 'approve'), 'Role #6 (Trưởng Phòng) có proposals:approve');
rbac_assert($roleHasPerm(6, 'evaluations', 'manager_review'), 'Role #6 (Trưởng Phòng) có evaluations:manager_review');
rbac_assert(!$roleHasPerm(6, 'payroll', 'view'), 'Role #6 (Trưởng Phòng) không xem bảng lương');

// 3. Kiểm tra tính toàn vẹn dữ liệu CEO & Trưởng phòng
$ceoCount = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE position_id = 1")->fetchColumn();
$nullMgrDepts = (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE manager_id IS NULL")->fetchColumn();
rbac_assert($ceoCount === 1, 'Hệ thống chỉ có duy nhất 1 CEO (position_id=1)');
rbac_assert($nullMgrDepts === 0, '100% phòng ban đã được chỉ định manager_id');

// 4. Kiểm tra KpiService::getDepartmentProgressMatrix lọc theo deptId
$activePeriodId = (int)$pdo->query("SELECT id FROM evaluation_periods WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
if ($activePeriodId <= 0) {
    $activePeriodId = (int)$pdo->query("SELECT id FROM evaluation_periods ORDER BY id DESC LIMIT 1")->fetchColumn();
}
$dept3Matrix = KpiService::getDepartmentProgressMatrix($pdo, $activePeriodId, null, 3);
rbac_assert(count($dept3Matrix) === 1 && (int)$dept3Matrix[0]['department_id'] === 3, 'KpiService::getDepartmentProgressMatrix lọc đúng theo deptId');

// 5. Kiểm tra Data Scope qua 7 Persona đại diện cả 6 Vai Trò
$personas = [
    'admin@wincommerce.vn'      => ['scope' => 'all',        'role' => 1],
    'phuong.le@wincommerce.vn'  => ['scope' => 'all',        'role' => 1],
    'mienbac@wincommerce.vn'    => ['scope' => 'branch',     'role' => 2],
    'giang.tran@wincommerce.vn' => ['scope' => 'all',        'role' => 3],
    'ngoc.do@wincommerce.vn'    => ['scope' => 'self',       'role' => 4],
    'cb@wincommerce.vn'         => ['scope' => 'all',        'role' => 5],
    'long.vu@wincommerce.vn'    => ['scope' => 'department', 'role' => 6],
];

$userStmt = $pdo->prepare("SELECT u.*, r.name AS role_name, r.data_scope FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
foreach ($personas as $email => $exp) {
    $userStmt->execute([$email]);
    $u = $userStmt->fetch(PDO::FETCH_ASSOC);
    rbac_assert((bool)$u, "Tồn tại tài khoản {$email}");
    if (!$u) continue;

    $_SESSION['user'] = [
        'id'            => (int)$u['id'],
        'username'      => $u['username'],
        'fullname'      => $u['fullname'],
        'email'         => $u['email'],
        'role_id'       => (int)$u['role_id'],
        'role_name'     => $u['role_name'] ?? '',
        'is_superadmin' => (int)$u['is_superadmin'],
        'data_scope'    => (int)$u['is_superadmin'] === 1 ? 'all' : ($u['data_scope'] ?? 'self'),
    ];
    unset($_SESSION['current_employee']);
    reload_user_permissions($pdo, (int)$u['role_id']);
    $emp = current_employee(true);

    rbac_assert((int)$u['role_id'] === $exp['role'], "{$email} thuộc đúng Role #{$exp['role']}");
    rbac_assert(data_scope() === $exp['scope'], "{$email} có data_scope={$exp['scope']}");

    $scopeParams = [];
    $whereSql = scope_where('e', $scopeParams);
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE e.employment_status != 'resigned' AND ({$whereSql})");
    $cntStmt->execute($scopeParams);
    $visibleCount = (int)$cntStmt->fetchColumn();

    if ($exp['scope'] === 'branch' && $emp) {
        $otherId = (int)$pdo->query("SELECT id FROM employees WHERE branch_id != {$emp['branch_id']} LIMIT 1")->fetchColumn();
        rbac_assert($otherId > 0 && !can_access_employee($otherId), "{$email} (branch) bị chặn truy cập NV chi nhánh khác");
    } elseif ($exp['scope'] === 'department' && $emp) {
        $sameId  = (int)$pdo->query("SELECT id FROM employees WHERE department_id = {$emp['department_id']} AND id != {$emp['id']} LIMIT 1")->fetchColumn();
        $otherId = (int)$pdo->query("SELECT id FROM employees WHERE department_id != {$emp['department_id']} LIMIT 1")->fetchColumn();
        rbac_assert((!$sameId || can_access_employee($sameId)) && $otherId > 0 && !can_access_employee($otherId),
            "{$email} (department) xem được NV cùng phòng ({$visibleCount}) và bị chặn NV phòng khác");
    } elseif ($exp['scope'] === 'self' && $emp) {
        $otherId = (int)$pdo->query("SELECT id FROM employees WHERE id != {$emp['id']} LIMIT 1")->fetchColumn();
        rbac_assert($visibleCount === 1 && $otherId > 0 && !can_access_employee($otherId), "{$email} (self) chỉ thấy 1 hồ sơ của chính mình");
    }
}

echo "\n[3] Kiểm tra DemoSyncService & Danh Sách 7 Persona Switcher:\n";
require_once __DIR__ . '/../core/DemoSyncService.php';
$syncRes = DemoSyncService::ensureLiveDemoFreshness($pdo, true);
rbac_assert(isset($syncRes['skipped']) && $syncRes['skipped'] === false, "DemoSyncService::ensureLiveDemoFreshness(true) thực thi thành công");

$attTodayCount = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE()")->fetchColumn();
rbac_assert($attTodayCount >= 100, "Chấm công hôm nay được đồng bộ tự động (hiện có {$attTodayCount} bản ghi)");

$expiringCount = (int)$pdo->query("
    SELECT COUNT(*)
    FROM employee_contracts ec
    JOIN employees e ON ec.employee_id = e.id
    WHERE ec.status = 'active'
      AND e.employment_status != 'resigned'
      AND ec.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
")->fetchColumn();
rbac_assert($expiringCount >= 3, "Có {$expiringCount} hợp đồng sắp hết hạn trong 30 ngày tới để hiển thị cảnh báo");

$demoSwitchSource = file_get_contents(__DIR__ . '/../demo_switch.php');
foreach (['admin@wincommerce.vn', 'phuong.le@wincommerce.vn', 'giang.tran@wincommerce.vn', 'cb@wincommerce.vn', 'mienbac@wincommerce.vn', 'long.vu@wincommerce.vn', 'ngoc.do@wincommerce.vn'] as $pEmail) {
    rbac_assert(strpos($demoSwitchSource, $pEmail) !== false, "demo_switch.php cho phép chuyển nhanh {$pEmail}");
}

echo "\n============================================================\n";
echo "KẾT QUẢ KIỂM THỬ RBAC & DATA SCOPE: {$passed} / " . ($passed + $failed) . " PASSED\n";
echo "============================================================\n";
exit($failed > 0 ? 1 : 0);
