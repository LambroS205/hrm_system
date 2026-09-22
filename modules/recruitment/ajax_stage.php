<?php
// modules/recruitment/ajax_stage.php - API cập nhật giai đoạn ứng viên qua Kéo - Thả Kanban Pipeline
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

if (!has_permission('recruitment', 'edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chuyển đổi giai đoạn tuyển dụng.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// Xác thực CSRF Token
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
$session_token = $_SESSION['_csrf_token'] ?? '';
if (empty($token) || empty($session_token) || !hash_equals($session_token, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Mã bảo mật CSRF không hợp lệ hoặc đã hết hạn.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$new_stage = trim($_POST['stage'] ?? '');
$valid_stages = ['applied', 'screening', 'interview', 'offer', 'hired', 'rejected'];

if ($id <= 0 || !in_array($new_stage, $valid_stages)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tham số không hợp lệ.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT c.*, j.title AS job_title FROM candidates c JOIN job_positions j ON c.job_position_id = j.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $candidate = $stmt->fetch();

    if (!$candidate) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ ứng viên này.']);
        exit;
    }

    $upStmt = $pdo->prepare("UPDATE candidates SET stage = ? WHERE id = ?");
    $upStmt->execute([$new_stage, $id]);

    $stageLabels = [
        'applied'   => 'Ứng Tuyển',
        'screening' => 'Sàng Lọc',
        'interview' => 'Phỏng Vấn',
        'offer'     => 'Gửi Offer',
        'hired'     => 'Trúng Tuyển',
        'rejected'  => 'Từ Chối'
    ];

    echo json_encode([
        'success'        => true,
        'message'        => "Đã chuyển ứng viên [{$candidate['fullname']}] sang giai đoạn \"{$stageLabels[$new_stage]}\".",
        'new_stage'      => $new_stage,
        'candidate_code' => $candidate['candidate_code'],
        'candidate_name' => $candidate['fullname'],
        'is_hired'       => ($new_stage === 'hired'),
        'has_employee'   => !empty($candidate['hired_employee_id'])
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
