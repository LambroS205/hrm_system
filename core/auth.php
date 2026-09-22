<?php
// core/auth.php - Động cơ xác thực, bảo mật và ma trận phân quyền

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
 * Quản lý Rate Limiting đăng nhập để chống Brute-Force
 */
function get_login_rate_status() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'rate_login_' . md5($ip);
    
    $attempts = $_SESSION[$key]['attempts'] ?? 0;
    $locked_until = $_SESSION[$key]['locked_until'] ?? 0;
    $now = time();

    if ($locked_until > $now) {
        $remaining = ceil(($locked_until - $now) / 60);
        return [
            'is_locked' => true,
            'remaining_minutes' => $remaining,
            'attempts' => $attempts
        ];
    }

    // Nếu thời gian khóa đã trôi qua thì reset
    if ($locked_until > 0 && $locked_until <= $now) {
        unset($_SESSION[$key]);
        $attempts = 0;
    }

    return [
        'is_locked' => false,
        'remaining_minutes' => 0,
        'attempts' => $attempts
    ];
}

function record_failed_login_attempt() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'rate_login_' . md5($ip);
    
    $attempts = ($_SESSION[$key]['attempts'] ?? 0) + 1;
    $locked_until = 0;

    // Giới hạn 5 lần đăng nhập thất bại thì khóa 15 phút
    if ($attempts >= 5) {
        $locked_until = time() + (15 * 60);
    }

    $_SESSION[$key] = [
        'attempts' => $attempts,
        'locked_until' => $locked_until
    ];
}

function clear_login_rate_attempts() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $key = 'rate_login_' . md5($ip);
    unset($_SESSION[$key]);
}

/**
 * Hàm cốt lõi: Kiểm tra quyền truy cập theo Ma trận Phân quyền
 * @param string $module Tên module (employees, departments, attendance, payroll, matrix, branches, orgchart, transfers, planning)
 * @param string $action Hành vi (view, create, edit, delete, manage, approve)
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
 * Chặn truy cập nếu không có quyền trong ma trận (hiển thị trang 403 hiện đại)
 */
function require_permission($module, $action) {
    require_login();
    if (!has_permission($module, $action)) {
        http_response_code(403);
        echo '<!DOCTYPE html>
        <html lang="vi" class="h-full">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Quyền Truy Cập Bị Từ Chối</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-slate-50 dark:bg-slate-900 flex items-center justify-center min-h-screen p-4 font-sans text-slate-800 dark:text-slate-100">
            <div class="max-w-md w-full bg-white dark:bg-slate-800 rounded-3xl shadow-xl p-8 text-center border border-slate-100 dark:border-slate-700 transition-all">
                <div class="w-16 h-16 bg-rose-100 dark:bg-rose-900/40 text-rose-600 dark:text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-sm">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Truy Cập Bị Từ Chối (403)</h1>
                <p class="text-slate-600 dark:text-slate-300 text-sm mb-6 leading-relaxed">
                    Tài khoản của bạn không có thẩm quyền thao tác <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-semibold text-rose-600 dark:text-rose-400">[' . e($action) . ']</span> trên phân hệ <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-semibold text-indigo-600 dark:text-indigo-400">[' . e($module) . ']</span>.
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
        log_system_error("Lỗi tải quyền hạn", $e);
    }
}