<?php
// demo_switch.php - Chuyển đổi nhanh Persona trình diễn (Chỉ hoạt động khi DEMO_MODE=true)

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/brand.php';
require_once __DIR__ . '/core/helpers.php';
require_once __DIR__ . '/core/auth.php';

if (!is_demo_mode()) {
    http_response_code(403);
    die('Tính năng chuyển đổi Persona chỉ khả dụng khi hệ thống được cấu hình DEMO_MODE=true.');
}

$allowed_personas = [
    'admin@wincommerce.vn',
    'phuong.le@wincommerce.vn',
    'cb@wincommerce.vn',
    'nhanvien@wincommerce.vn',
    'mienbac@wincommerce.vn',
    'ngoc.do@wincommerce.vn'
];

$target = $_REQUEST['account'] ?? '';
$redirect_to = $_REQUEST['redirect'] ?? 'index.php';

if (!in_array($target, $allowed_personas, true)) {
    set_flash('danger', 'Tài khoản trình diễn không nằm trong danh sách được phép.');
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, r.name as role_name, r.data_scope 
        FROM users u
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$target, $target]);
    $user = $stmt->fetch();

    if ($user) {
        session_regenerate_id(true);

        unset($_SESSION['current_employee']);

        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'fullname' => $user['fullname'],
            'email' => $user['email'],
            'role_id' => $user['role_id'] ? (int)$user['role_id'] : null,
            'role_name' => $user['role_name'] ?? ($user['is_superadmin'] ? 'Tổng Quản Trị (Super Admin)' : 'Chưa gán vai trò'),
            'is_superadmin' => (int)$user['is_superadmin'],
            'data_scope' => (int)$user['is_superadmin'] === 1 ? 'all' : ($user['data_scope'] ?? 'self')
        ];

        if ($user['role_id']) {
            reload_user_permissions($pdo, $user['role_id']);
        } else {
            $_SESSION['permissions'] = [];
        }

        $scopeLabel = strtoupper($_SESSION['user']['data_scope']);
        set_flash('info', "Đang trình diễn với vai trò: <b>{$user['fullname']}</b> — {$user['role_name']} [Scope: {$scopeLabel}]");
    } else {
        set_flash('danger', "Không tìm thấy người dùng demo {$target}.");
    }
} catch (Exception $e) {
    set_flash('danger', "Lỗi chuyển đổi persona: " . $e->getMessage());
}

redirect($redirect_to);

