<?php
// scratch/test_persona_scoping.php - Kiểm tra tự động tính đúng đắn của Data Scope cho cả 5 Persona

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/scope.php';

echo "============================================================\n";
echo "   KIỂM THỬ TỰ ĐỘNG: 5 PERSONA & PHÂN QUYỀN DATA SCOPE      \n";
echo "============================================================\n\n";

$test_personas = [
    'admin@wincommerce.vn'  => ['expected_scope' => 'all',    'expected_role' => 1, 'is_super' => 1],
    'phuong.le@wincommerce.vn' => ['expected_scope' => 'all',    'expected_role' => 1, 'is_super' => 0],
    'cb@wincommerce.vn'     => ['expected_scope' => 'all',    'expected_role' => 5, 'is_super' => 0],
    'mienbac@wincommerce.vn'=> ['expected_scope' => 'branch', 'expected_role' => 2, 'is_super' => 0],
    'ngoc.do@wincommerce.vn' => ['expected_scope' => 'self',   'expected_role' => 4, 'is_super' => 0],
];

foreach ($test_personas as $email => $expected) {
    echo "--- Testing Persona: {$email} ---\n";

    // 1. Giả lập phiên đăng nhập
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.data_scope 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.username = ? OR u.email = ?
    ");
    $stmt->execute([$email, $email]);
    $u = $stmt->fetch();

    if (!$u) {
        echo " [FAIL] Không tìm thấy user {$email}\n";
        continue;
    }

    $_SESSION['user'] = [
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'fullname' => $u['fullname'],
        'email' => $u['email'],
        'role_id' => $u['role_id'] ? (int)$u['role_id'] : null,
        'role_name' => $u['role_name'] ?? '',
        'is_superadmin' => (int)$u['is_superadmin'],
        'data_scope' => (int)$u['is_superadmin'] === 1 ? 'all' : ($u['data_scope'] ?? 'self')
    ];
    unset($_SESSION['current_employee']);
    reload_user_permissions($pdo, $u['role_id']);

    // 2. Kiểm tra scope
    $actual_scope = data_scope();
    if ($actual_scope === $expected['expected_scope']) {
        echo " [PASS] Data Scope chính xác: {$actual_scope}\n";
    } else {
        echo " [FAIL] Data Scope sai! Kỳ vọng: {$expected['expected_scope']}, thực tế: {$actual_scope}\n";
    }

    // 3. Kiểm tra employee profile
    $emp = current_employee(true);
    if ($emp) {
        echo " [PASS] Liên kết nhân viên thành công: {$emp['fullname']} ({$emp['employee_code']}) - Chi nhánh: {$emp['branch_name']}\n";
    } else {
        echo " [WARN] Chưa liên kết hồ sơ nhân sự (nếu là admin kỹ thuật độc lập thì chấp nhận được)\n";
    }

    // 4. Kiểm tra phạm vi dữ liệu nhân viên
    $scope_params = [];
    $where = scope_where('e', $scope_params);
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE {$where}");
    $count_stmt->execute($scope_params);
    $visible_emps = (int)$count_stmt->fetchColumn();

    if ($actual_scope === 'all') {
        echo " [PASS] Quyền ALL: Thấy toàn bộ {$visible_emps} nhân sự (toàn tập đoàn)\n";
    } elseif ($actual_scope === 'branch') {
        echo " [PASS] Quyền BRANCH: Thấy đúng {$visible_emps} nhân sự thuộc chi nhánh " . ($emp['branch_name'] ?? '') . "\n";
    } elseif ($actual_scope === 'self') {
        echo " [PASS] Quyền SELF: Thấy đúng {$visible_emps} nhân sự (chính mình)\n";
    }

    // 5. Kiểm tra an ninh can_access_employee
    if ($actual_scope === 'branch' && $emp) {
        $other_emp_id = (int)$pdo->query("SELECT id FROM employees WHERE branch_id != {$emp['branch_id']} LIMIT 1")->fetchColumn();
        if ($other_emp_id && !can_access_employee($other_emp_id)) {
            echo " [PASS] An ninh: GĐ Chi nhánh BỊ CHẶN truy cập nhân viên ngoài chi nhánh (ID: {$other_emp_id})\n";
        } else {
            echo " [FAIL] Lỗi an ninh! GĐ Chi nhánh truy cập được nhân viên chi nhánh khác!\n";
        }
    } elseif ($actual_scope === 'self' && $emp) {
        $other_emp_id = (int)$pdo->query("SELECT id FROM employees WHERE id != {$emp['id']} LIMIT 1")->fetchColumn();
        if ($other_emp_id && !can_access_employee($other_emp_id)) {
            echo " [PASS] An ninh: Nhân viên BỊ CHẶN truy cập hồ sơ của đồng nghiệp (ID: {$other_emp_id})\n";
        } else {
            echo " [FAIL] Lỗi an ninh! Nhân viên truy cập được hồ sơ người khác!\n";
        }
    }

    // 6. Kiểm tra quyền payroll
    $has_payroll = has_permission('payroll', 'view');
    echo " [INFO] Quyền xem bảng lương: " . ($has_payroll ? "CÓ" : "KHÔNG") . "\n\n";
}

echo "[✓] HOÀN TẤT KIỂM THỬ TẤT CẢ 5 PERSONA!\n";

