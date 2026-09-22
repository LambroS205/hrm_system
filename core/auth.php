<?php
// core/auth.php - Động cơ xác thực và ma trận phân quyền

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Kiểm tra xem người dùng đã đăng nhập chưa
 */
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Lấy thông tin người dùng hiện tại đang đăng nhập
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Kiểm tra xem có phải là Super Admin toàn quyền không
 */
function is_superadmin() {
    return isset($_SESSION['user']) && (int)($_SESSION['user']['is_superadmin'] ?? 0) === 1;
}

/**
 * Bắt buộc người dùng phải đăng nhập mới được truy cập
 */
function require_login() {
    if (!is_logged_in()) {
        set_flash('danger', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('login.php');
    }
}

/**
 * Hàm cốt lõi: Kiểm tra quyền truy cập theo Ma trận Phân quyền
 * @param string $module Tên module (employees, departments, attendance, payroll, matrix)
 * @param string $action Hành vi (view, create, edit, delete, manage)
 * @return bool
 */
function has_permission($module, $action) {
    // 1. Nếu là Super Admin, luôn luôn có quyền
    if (is_superadmin()) {
        return true;
    }

    // 2. Nếu chưa đăng nhập, không có quyền
    if (!is_logged_in()) {
        return false;
    }

    // 3. Kiểm tra trong bộ nhớ Session quyền đã nạp khi đăng nhập
    if (isset($_SESSION['permissions'][$module][$action])) {
        return true;
    }

    return false;
}

/**
 * Chặn truy cập nếu không có quyền trong ma trận (hiển thị trang 403)
 */
function require_permission($module, $action) {
    require_login();
    if (!has_permission($module, $action)) {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Quyền truy cập bị từ chối</title>
            <script src="https://cdn.tailwindcss.com"></script>
        </head>
        <body class="bg-slate-50 flex items-center justify-center min-h-screen p-4 font-sans">
            <div class="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 text-center border border-slate-100">
                <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl font-bold">
                    !
                </div>
                <h1 class="text-2xl font-bold text-slate-800 mb-2">Truy Cập Bị Từ Chối</h1>
                <p class="text-slate-600 text-sm mb-6">Bạn không có quyền thực hiện thao tác <strong class="text-slate-800">[' . e($action) . ']</strong> trên phân hệ <strong class="text-slate-800">[' . e($module) . ']</strong>. Vui lòng liên hệ Super Admin để được cấp quyền.</p>
                <a href="' . base_url('index.php') . '" class="inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-xl transition duration-200">
                    Về Trang Chủ
                </a>
            </div>
        </body>
        </html>';
        exit;
    }
}

/**
 * Nạp toàn bộ quyền của Role vào Session khi đăng nhập thành công
 */
function reload_user_permissions($pdo, $role_id) {
    $_SESSION['permissions'] = [];
    if (!$role_id) return;

    try {
        $stmt = $pdo->prepare("
            SELECT p.module, p.action 
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ?
        ");
        $stmt->execute([$role_id]);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $_SESSION['permissions'][$row['module']][$row['action']] = true;
        }
    } catch (PDOException $e) {
        error_log("Lỗi tải quyền: " . $e->getMessage());
    }
}