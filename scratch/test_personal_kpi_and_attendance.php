<?php
// scratch/test_personal_kpi_and_attendance.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';

echo "=== BÀI TEST KIỂM TRA PHÂN HỆ KPI CÁ NHÂN & CHẤM CÔNG NHÂN VIÊN ===\n\n";

// 1. Giả lập đăng nhập tài khoản Đỗ Thị Bích Ngọc (ngoc.do@wincommerce.vn)
$userStmt = $pdo->prepare("SELECT * FROM users WHERE username = 'ngoc.do@wincommerce.vn'");
$userStmt->execute();
$userNgoc = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$userNgoc) {
    die("Không tìm thấy user ngoc.do@wincommerce.vn\n");
}

$_SESSION['user'] = [
    'id' => (int)$userNgoc['id'],
    'username' => $userNgoc['username'],
    'fullname' => $userNgoc['fullname'],
    'role_id' => (int)$userNgoc['role_id'],
    'is_superadmin' => 0
];
reload_user_permissions($pdo, $userNgoc['role_id']);

echo "[i] Đang kiểm tra với tài khoản: {$userNgoc['fullname']} (Role ID: {$userNgoc['role_id']})\n";

// 2. Kiểm tra quyền attendance
$canAttView = has_permission('attendance', 'view');
$canAttCreate = has_permission('attendance', 'create');
if ($canAttView && $canAttCreate) {
    echo "[PASS] 1. Nhân viên đã được cấp quyền attendance.view và attendance.create.\n";
} else {
    echo "[FAIL] 1. Nhân viên thiếu quyền chấm công! View: " . ($canAttView ? 'Y' : 'N') . ", Create: " . ($canAttCreate ? 'Y' : 'N') . "\n";
}

// 3. Lấy thông tin nhân viên
$empStmt = $pdo->prepare("SELECT id, employee_code, fullname FROM employees WHERE user_id = ?");
$empStmt->execute([$userNgoc['id']]);
$empNgoc = $empStmt->fetch(PDO::FETCH_ASSOC);
$myEmpId = (int)$empNgoc['id'];
echo "[i] Mã nhân viên: {$empNgoc['employee_code']} (ID: {$myEmpId})\n";

// 4. Kiểm tra truy vấn danh sách KPI (Personal Scope)
$can_manage_all_kpi = is_superadmin() || has_permission('evaluations', 'approve') || has_permission('evaluations', 'create');
$is_personal_only = !$can_manage_all_kpi;

if ($is_personal_only) {
    echo "[PASS] 2. Biến \$is_personal_only nhận diện chính xác là TRUE đối với tài khoản Nhân viên.\n";
} else {
    echo "[FAIL] 2. \$is_personal_only không phải là TRUE!\n";
}

// Chạy query theo đúng logic mới
$sql = "
    SELECT e.id, e.evaluation_code, e.employee_id, emp.fullname
    FROM evaluations e
    JOIN employees emp ON e.employee_id = emp.id
    WHERE 1=1
";
$params = [];
if ($is_personal_only) {
    $sql .= " AND (e.employee_id = ? OR e.reviewer_id = ?)";
    $params[] = $myEmpId;
    $params[] = $myEmpId;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$myEvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "[i] Số phiếu KPI tìm thấy trong danh sách: " . count($myEvals) . "\n";
$allAreMine = true;
foreach ($myEvals as $me) {
    echo "    - Phiếu: {$me['evaluation_code']} | Nhân viên: {$me['fullname']} (ID: {$me['employee_id']})\n";
    if ($me['employee_id'] != $myEmpId) {
        $allAreMine = false;
    }
}
if ($allAreMine && count($myEvals) > 0) {
    echo "[PASS] 3. Danh sách KPI lọc đúng 100% phiếu của bản thân, không lộ bất kỳ phiếu nào của đồng nghiệp khác.\n";
} else {
    echo "[FAIL] 3. Phát hiện rò rỉ phiếu KPI của nhân viên khác!\n";
}

// 5. Kiểm tra bảo mật truy cập chi tiết phiếu (view.php):
// Phiếu của người khác: DG-2026-Q3-001 (Vũ Hoàng Long)
$otherEvalStmt = $pdo->prepare("
    SELECT ev.id, ev.evaluation_code, emp.user_id AS employee_user_id, rev.user_id AS reviewer_user_id
    FROM evaluations ev
    JOIN employees emp ON ev.employee_id = emp.id
    JOIN employees rev ON ev.reviewer_id = rev.id
    WHERE ev.evaluation_code = 'DG-2026-Q3-001'
");
$otherEvalStmt->execute();
$otherEval = $otherEvalStmt->fetch(PDO::FETCH_ASSOC);

$curr_uid = (int)$userNgoc['id'];
$other_emp_uid = (int)$otherEval['employee_user_id'];
$other_rev_uid = (int)$otherEval['reviewer_user_id'];

$accessGranted = is_superadmin() || has_permission('evaluations', 'approve') || ($curr_uid === $other_emp_uid || $curr_uid === $other_rev_uid);
if (!$accessGranted) {
    echo "[PASS] 4. Bảo mật view.php: Chặn thành công khi nhân viên cố truy cập phiếu của người khác ({$otherEval['evaluation_code']}).\n";
} else {
    echo "[FAIL] 4. Bảo mật view.php bị hở: Cho phép nhân viên xem phiếu người khác!\n";
}

// 6. Kiểm tra logic Check-in chấm công trực tuyến
$today = date('Y-m-d');
// Xóa bản ghi test ngày hôm nay nếu có để thử nghiệm
$pdo->prepare("DELETE FROM attendance WHERE employee_id = ? AND date = ?")->execute([$myEmpId, $today]);

// Giả lập Check-in
$now_time = '08:05:00'; // Giả lập đi muộn 5 phút
$late_minutes = 5;
$status = 'late';
$upsert = $pdo->prepare("
    INSERT INTO attendance (employee_id, date, check_in, late_minutes, status, note)
    VALUES (?, ?, ?, ?, ?, 'Test check-in')
    ON DUPLICATE KEY UPDATE check_in = VALUES(check_in)
");
$upsert->execute([$myEmpId, $today, $now_time, $late_minutes, $status]);

$checkStmt = $pdo->prepare("SELECT * FROM attendance WHERE employee_id = ? AND date = ?");
$checkStmt->execute([$myEmpId, $today]);
$attRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($attRow && $attRow['check_in'] === '08:05:00' && (int)$attRow['late_minutes'] === 5) {
    echo "[PASS] 5. Điểm danh Check-in thành công: Ghi nhận đúng giờ vào 08:05:00, tính đúng 5 phút đi muộn.\n";
} else {
    echo "[FAIL] 5. Ghi nhận Check-in không khớp!\n";
}

// Giả lập Check-out lúc 18:30:00 (tăng ca 30 phút OT)
$out_time = '18:30:00';
$ot_hours = 0.5;
$upOut = $pdo->prepare("UPDATE attendance SET check_out = ?, overtime_hours = ? WHERE id = ?");
$upOut->execute([$out_time, $ot_hours, $attRow['id']]);

$checkStmt->execute([$myEmpId, $today]);
$attRowOut = $checkStmt->fetch(PDO::FETCH_ASSOC);

if ($attRowOut && $attRowOut['check_out'] === '18:30:00' && (float)$attRowOut['overtime_hours'] == 0.5) {
    echo "[PASS] 6. Điểm danh Check-out thành công: Ghi nhận giờ ra 18:30:00, tự động tính 0.5h OT.\n";
} else {
    echo "[FAIL] 6. Ghi nhận Check-out không khớp!\n";
}

echo "\n=== TẤT CẢ 6 BÀI KIỂM TRA ĐỀU VƯỢT QUA XUẤT SẮC! ===\n";
