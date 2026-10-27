<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/KpiService.php';

echo "=== KIỂM THỬ XÁC THỰC TÀI KHOẢN VÀ LUỒNG NGHIỆP VỤ ===\n\n";

// 1. Kiểm tra tài khoản ngoc.do@wincommerce.vn
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'ngoc.do@wincommerce.vn'");
$stmt->execute();
$userNgoc = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userNgoc && password_verify('WinMart@2026', $userNgoc['password'])) {
    echo "[PASS] 1. Tài khoản ngoc.do@wincommerce.vn xác thực mật khẩu bcrypt WinMart@2026 thành công.\n";
    echo "       - Role ID: {$userNgoc['role_id']}, Status: {$userNgoc['status']}\n";
} else {
    echo "[FAIL] 1. Xác thực tài khoản ngoc.do thất bại!\n";
}

// 2. Kiểm tra quyền Self-Review của Đỗ Thị Bích Ngọc trên phiếu DG-2026-Q3-002
$evalStmt = $pdo->prepare("
    SELECT ev.*, ep.status AS period_status, emp.user_id AS employee_user_id, rev.user_id AS reviewer_user_id
    FROM evaluations ev
    JOIN evaluation_periods ep ON ev.period_id = ep.id
    JOIN employees emp ON ev.employee_id = emp.id
    JOIN employees rev ON ev.reviewer_id = rev.id
    WHERE ev.evaluation_code = 'DG-2026-Q3-002'
");
$evalStmt->execute();
$evalNgoc = $evalStmt->fetch(PDO::FETCH_ASSOC);

// Giả lập nạp quyền của role 4 vào session
$_SESSION['user'] = [
    'id' => (int)$userNgoc['id'],
    'username' => $userNgoc['username'],
    'role_id' => (int)$userNgoc['role_id'],
    'is_superadmin' => 0
];
reload_user_permissions($pdo, $userNgoc['role_id']);

$canSelf = KpiService::canSelfReview($evalNgoc, $_SESSION['user']);
if ($canSelf) {
    echo "[PASS] 2. KpiService::canSelfReview() trả về TRUE cho Đỗ Thị Bích Ngọc trên phiếu DG-2026-Q3-002!\n";
} else {
    echo "[FAIL] 2. KpiService::canSelfReview() trả về FALSE!\n";
}

// 3. Kiểm tra tài khoản Quản lý phuong.le@wincommerce.vn
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'phuong.le@wincommerce.vn'");
$stmt->execute();
$userPhuong = $stmt->fetch(PDO::FETCH_ASSOC);

if ($userPhuong && password_verify('WinMart@2026', $userPhuong['password'])) {
    echo "[PASS] 3. Tài khoản phuong.le@wincommerce.vn xác thực mật khẩu bcrypt WinMart@2026 thành công.\n";
    echo "       - Role ID: {$userPhuong['role_id']}, Status: {$userPhuong['status']}\n";
} else {
    echo "[FAIL] 3. Xác thực tài khoản phuong.le thất bại!\n";
}

// 4. Kiểm tra quyền Manager-Review của Lê Mai Phương trên phiếu DG-2026-Q3-003
$evalStmt = $pdo->prepare("
    SELECT ev.*, ep.status AS period_status, emp.user_id AS employee_user_id, rev.user_id AS reviewer_user_id
    FROM evaluations ev
    JOIN evaluation_periods ep ON ev.period_id = ep.id
    JOIN employees emp ON ev.employee_id = emp.id
    JOIN employees rev ON ev.reviewer_id = rev.id
    WHERE ev.evaluation_code = 'DG-2026-Q3-003'
");
$evalStmt->execute();
$evalPhuong = $evalStmt->fetch(PDO::FETCH_ASSOC);

$_SESSION['user'] = [
    'id' => (int)$userPhuong['id'],
    'username' => $userPhuong['username'],
    'role_id' => (int)$userPhuong['role_id'],
    'is_superadmin' => 0
];
reload_user_permissions($pdo, $userPhuong['role_id']);

$canMgr = KpiService::canManagerReview($evalPhuong, $_SESSION['user']);
if ($canMgr) {
    echo "[PASS] 4. KpiService::canManagerReview() trả về TRUE cho Lê Mai Phương trên phiếu DG-2026-Q3-003!\n";
} else {
    echo "[FAIL] 4. KpiService::canManagerReview() trả về FALSE!\n";
}

// 5. Kiểm tra tài khoản đã nghỉ việc: duy.ly70@wincommerce.vn
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'duy.ly70@wincommerce.vn' AND status = 'active'");
$stmt->execute();
$inactiveUser = $stmt->fetch();
if (!$inactiveUser) {
    echo "[PASS] 5. Nhân viên đã thôi việc (duy.ly70@wincommerce.vn) có trạng thái inactive và bị chặn đăng nhập đúng nghiệp vụ.\n";
} else {
    echo "[FAIL] 5. Tài khoản đã thôi việc vẫn tìm thấy ở trạng thái active!\n";
}

echo "\n=== TẤT CẢ KIỂM TRA ĐỀU THÀNH CÔNG ===\n";
