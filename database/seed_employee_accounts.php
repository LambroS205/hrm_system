<?php
// database/seed_employee_accounts.php
// Script tự động khởi tạo tài khoản đăng nhập cho toàn bộ nhân viên chưa có tài khoản

require_once __DIR__ . '/../config/database.php';

const DEMO_PASSWORD = 'WinMart@2026';

// Mapping chức danh (position_id) sang vai trò (role_id)
// Role 2: Giám Đốc Chi Nhánh (Branch Manager / Quản lý điều hành)
// Role 3: Chuyên Viên Nhân Sự (HR Executive)
// Role Employee: Sẽ lấy ID động của role 'Nhân Viên (Employee)'
$ROLE_BY_POSITION = [
    2 => 2, // Giám Đốc Chi Nhánh / Giám Đốc Khu Vực (Regional Director)
    3 => 2, // Giám Đốc Khối / CHRO / CIO / CFO / COO
    4 => 2, // Trưởng Phòng Kinh Doanh & Vận Hành Bán Lẻ (Retail Ops Manager)
    6 => 2, // Trưởng Phòng Chuỗi Cung Ứng & Trung Tâm Phân Phối (DC Manager)
    8 => 2, // Quản Lý Siêu Thị WinMart (Store Manager)
    7 => 3, // Chuyên Viên Quản Trị Nhân Sự & C&B Chuỗi
];

// Quyền hạn tối thiểu cho vai trò 'Nhân Viên (Employee)'
// 9: attendance.view
// 10: attendance.create
// 23: orgchart.view
// 45: proposals.view
// 46: proposals.create
// 49: evaluations.view
// 53: evaluations.self_review
// 54: evaluations.manager_review
$EMPLOYEE_PERMISSIONS = [9, 10, 23, 45, 46, 49, 53, 54];

echo "===============================================================\n";
echo "   WINCOMMERCE PEOPLE - TẠO TÀI KHOẢN ĐĂNG NHẬP NHÂN VIÊN      \n";
echo "===============================================================\n\n";

try {
    $pdo->beginTransaction();

    // 1. Kiểm tra hoặc tạo vai trò 'Nhân Viên (Employee)'
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
    $roleStmt->execute(['Nhân Viên (Employee)']);
    $employeeRoleId = $roleStmt->fetchColumn();

    if (!$employeeRoleId) {
        $insertRole = $pdo->prepare("
            INSERT INTO roles (name, description) 
            VALUES (?, ?)
        ");
        $insertRole->execute([
            'Nhân Viên (Employee)',
            'Nhân viên tác nghiệp chuỗi WinMart/WinCommerce: tự đánh giá KPI, gửi đề xuất & xem sơ đồ tổ chức'
        ]);
        $employeeRoleId = (int)$pdo->lastInsertId();
        echo "[+] Đã tạo vai trò mới: 'Nhân Viên (Employee)' (ID: {$employeeRoleId})\n";
    } else {
        $employeeRoleId = (int)$employeeRoleId;
        echo "[i] Vai trò 'Nhân Viên (Employee)' đã tồn tại (ID: {$employeeRoleId})\n";
    }

    // 2. Gán các quyền tối thiểu cho role 'Nhân Viên'
    $permStmt = $pdo->prepare("
        INSERT IGNORE INTO role_permissions (role_id, permission_id) 
        VALUES (?, ?)
    ");
    $assignedPerms = 0;
    foreach ($EMPLOYEE_PERMISSIONS as $permId) {
        $permStmt->execute([$employeeRoleId, $permId]);
        if ($permStmt->rowCount() > 0) {
            $assignedPerms++;
        }
    }
    if ($assignedPerms > 0) {
        echo "[+] Đã gán {$assignedPerms} quyền hạn cốt lõi cho vai trò 'Nhân Viên (Employee)'\n";
    } else {
        echo "[i] Toàn bộ quyền hạn của vai trò 'Nhân Viên (Employee)' đã được thiết lập đầy đủ\n";
    }

    // 3. Chuẩn bị Hash mật khẩu chung
    $passwordHash = password_hash(DEMO_PASSWORD, PASSWORD_BCRYPT);

    // 4. Lấy danh sách nhân viên chưa có tài khoản (user_id IS NULL)
    $empStmt = $pdo->query("
        SELECT e.id, e.employee_code, e.fullname, e.email, e.position_id, e.employment_status,
               p.name AS position_name
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.id
        WHERE e.user_id IS NULL
        ORDER BY e.id ASC
    ");
    $unlinkedEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalToProcess = count($unlinkedEmployees);

    echo "\n[i] Tìm thấy {$totalToProcess} nhân viên chưa có tài khoản người dùng liên kết.\n";

    if ($totalToProcess === 0) {
        echo "[✓] Tất cả nhân viên hiện tại đều đã có tài khoản người dùng! Không cần tạo mới.\n";
        $pdo->commit();
        exit(0);
    }

    // Statements chuẩn bị sẵn
    $checkUserStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
    $insertUserStmt = $pdo->prepare("
        INSERT INTO users (role_id, username, password, fullname, email, is_superadmin, status)
        VALUES (?, ?, ?, ?, ?, 0, ?)
    ");
    $updateEmpStmt = $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?");

    $createdCount = 0;
    $linkedCount = 0;
    $roleStats = [
        'branch_mgr' => 0,
        'hr_exec' => 0,
        'employee' => 0
    ];
    $statusStats = [
        'active' => 0,
        'inactive' => 0
    ];

    echo "\nĐang tiến hành tạo tài khoản và liên kết dữ liệu...\n";
    echo str_repeat("-", 75) . "\n";
    printf("%-8s | %-24s | %-25s | %-10s\n", "Mã NV", "Họ Tên", "Email Đăng Nhập", "Trạng Thái");
    echo str_repeat("-", 75) . "\n";

    foreach ($unlinkedEmployees as $emp) {
        $email = trim($emp['email']);
        if (empty($email)) {
            // Dự phòng trường hợp không có email thì sinh theo mã NV
            $email = strtolower($emp['employee_code']) . '@wincommerce.vn';
        }

        $empStatus = ($emp['employment_status'] === 'resigned') ? 'inactive' : 'active';
        $statusStats[$empStatus]++;

        // Xác định vai trò
        $posId = (int)$emp['position_id'];
        if (isset($ROLE_BY_POSITION[$posId])) {
            $assignedRoleId = $ROLE_BY_POSITION[$posId];
            if ($assignedRoleId === 2) $roleStats['branch_mgr']++;
            elseif ($assignedRoleId === 3) $roleStats['hr_exec']++;
        } else {
            $assignedRoleId = $employeeRoleId;
            $roleStats['employee']++;
        }

        // Kiểm tra xem User đã tồn tại với email này chưa
        $checkUserStmt->execute([$email, $email]);
        $existingUserId = $checkUserStmt->fetchColumn();

        if ($existingUserId) {
            $userId = (int)$existingUserId;
            $linkedCount++;
        } else {
            $insertUserStmt->execute([
                $assignedRoleId,
                $email,
                $passwordHash,
                $emp['fullname'],
                $email,
                $empStatus
            ]);
            $userId = (int)$pdo->lastInsertId();
            $createdCount++;
        }

        // Cập nhật user_id vào employees
        $updateEmpStmt->execute([$userId, $emp['id']]);

        printf("%-8s | %-24s | %-25s | %-10s\n", 
            $emp['employee_code'], 
            mb_substr($emp['fullname'], 0, 24), 
            $email, 
            $empStatus
        );
    }

    $pdo->commit();

    echo str_repeat("-", 75) . "\n\n";
    echo "===============================================================\n";
    echo "                      TỔNG KẾT KẾT QUẢ                        \n";
    echo "===============================================================\n";
    echo "• Tổng số nhân viên được xử lý        : {$totalToProcess}\n";
    echo "• Tài khoản người dùng mới tạo        : {$createdCount}\n";
    echo "• Tài khoản có sẵn được liên kết lại  : {$linkedCount}\n";
    echo "• Phân loại trạng thái tài khoản:\n";
    echo "    - Hoạt động (active)              : {$statusStats['active']}\n";
    echo "    - Tạm dừng/Đã nghỉ (inactive)     : {$statusStats['inactive']}\n";
    echo "• Phân bổ vai trò (Role mapping):\n";
    echo "    - Giám Đốc Chi Nhánh / Quản lý    : {$roleStats['branch_mgr']}\n";
    echo "    - Chuyên Viên Nhân Sự             : {$roleStats['hr_exec']}\n";
    echo "    - Nhân Viên (Employee)            : {$roleStats['employee']}\n";
    echo "• Mật khẩu đăng nhập mặc định         : " . DEMO_PASSWORD . "\n";
    echo "===============================================================\n";
    echo "[✓] ĐÃ HOÀN TẤT THÀNH CÔNG!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[!] LỖI TRONG QUÁ TRÌNH THỰC HIỆN: " . $e->getMessage() . "\n";
    echo "Đã rollback toàn bộ dữ liệu an toàn.\n";
    exit(1);
}
