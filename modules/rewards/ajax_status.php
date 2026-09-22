<?php
// modules/rewards/ajax_status.php - API cập nhật trạng thái Khen thưởng qua Kéo - Thả Kanban
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

if (!has_permission('rewards', 'approve')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền phê duyệt hoặc thực thi quyết định khen thưởng.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// Kiểm tra CSRF Token từ Header hoặc POST
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
$session_token = $_SESSION['_csrf_token'] ?? '';
if (empty($token) || empty($session_token) || !hash_equals($session_token, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Mã bảo mật CSRF không hợp lệ hoặc đã hết hạn.']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$new_status = trim($_POST['status'] ?? '');
$valid_statuses = ['pending', 'approved', 'executed', 'rejected'];

if ($id <= 0 || !in_array($new_status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Tham số không hợp lệ.']);
    exit;
}

try {
    $user = current_user();
    $userId = $user['id'] ?? null;
    $now = date('Y-m-d H:i:s');

    // Lấy thông tin hiện tại
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
    $stmt->execute([$id]);
    $reward = $stmt->fetch();

    if (!$reward) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy quyết định khen thưởng này.']);
        exit;
    }

    $updateSql = "UPDATE rewards SET status = :status";
    $params = [':status' => $new_status, ':id' => $id];

    if ($new_status === 'approved') {
        $updateSql .= ", approved_by = :approved_by, approved_at = :approved_at";
        $params[':approved_by'] = $userId;
        $params[':approved_at'] = $now;
    } elseif ($new_status === 'executed') {
        $updateSql .= ", executed_at = :executed_at";
        $params[':executed_at'] = $now;
        if (empty($reward['approved_by'])) {
            $updateSql .= ", approved_by = :approved_by, approved_at = :approved_at";
            $params[':approved_by'] = $userId;
            $params[':approved_at'] = $now;
        }
    } elseif ($new_status === 'pending') {
        $updateSql .= ", approved_by = NULL, approved_at = NULL, executed_at = NULL";
    }

    $updateSql .= " WHERE id = :id";
    $upStmt = $pdo->prepare($updateSql);
    $upStmt->execute($params);

    $statusLabels = [
        'pending'  => 'Chờ Phê Duyệt',
        'approved' => 'Đã Phê Duyệt',
        'executed' => 'Đã Thực Thi',
        'rejected' => 'Từ Chối'
    ];

    echo json_encode([
        'success' => true,
        'message' => "Đã chuyển [{$reward['reward_code']}] sang trạng thái \"{$statusLabels[$new_status]}\".",
        'new_status' => $new_status,
        'reward_code' => $reward['reward_code']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi máy chủ: ' . $e->getMessage()]);
}
