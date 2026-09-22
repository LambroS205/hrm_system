<?php
// core/helpers.php - Các hàm tiện ích hỗ trợ toàn hệ thống

if (session_status() === PHP_SESSION_NONE) {
    // Cấu hình cookie an toàn
    if (!headers_sent()) {
        @ini_set('session.cookie_httponly', 1);
        @ini_set('session.use_only_cookies', 1);
        session_start();
    } else {
        @session_start();
    }
}

/**
 * Lấy đường dẫn gốc của ứng dụng (Base URL động)
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Chuẩn hóa đường dẫn thư mục
    $base = rtrim(str_replace('\\', '/', $script), '/');
    
    // Đảm bảo đưa về gốc dự án nếu đang ở trong thư mục con như modules/matrix hay api/
    if (strpos($base, '/modules') !== false) {
        $base = substr($base, 0, strpos($base, '/modules'));
    }
    if (strpos($base, '/assets') !== false) {
        $base = substr($base, 0, strpos($base, '/assets'));
    }

    $url = $protocol . $host . $base;
    return rtrim($url, '/') . '/' . ltrim($path, '/');
}

/**
 * Đường dẫn nhanh tới thư mục assets
 */
function asset($path = '') {
    return base_url('assets/' . ltrim($path, '/'));
}

/**
 * Chống tấn công XSS khi hiển thị dữ liệu ra màn hình
 */
function e($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Chuyển hướng an toàn
 */
function redirect($path) {
    header("Location: " . base_url($path));
    exit;
}

/**
 * Thiết lập thông báo Flash (hiển thị 1 lần)
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Hiển thị thông báo Flash nếu có
 */
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * ----------------------------------------------------
 * BẢO MẬT: CSRF PROTECTION SYSTEM
 * ----------------------------------------------------
 */

/**
 * Lấy hoặc tạo mới CSRF Token cho phiên làm việc
 */
function csrf_token() {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

/**
 * Render thẻ input hidden chứa CSRF Token
 */
function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

/**
 * Xác thực CSRF Token khi nhận request POST
 * Nếu không hợp lệ sẽ hủy request ngay lập tức và ghi log
 */
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $session_token = $_SESSION['_csrf_token'] ?? '';

        if (empty($token) || empty($session_token) || !hash_equals($session_token, $token)) {
            log_system_error("CSRF Verification Failed. IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'));
            http_response_code(403);
            die('
                <div style="font-family:system-ui,-apple-system,sans-serif;max-width:500px;margin:50px auto;padding:24px;border:1px solid #fee2e2;background:#fff5f5;border-radius:12px;color:#991b1b;text-align:center;">
                    <h2 style="margin-top:0;font-size:20px;">Phiên Làm Việc Hết Hạn / Yêu Cầu Không Hợp Lệ (403 CSRF)</h2>
                    <p style="font-size:14px;color:#7f1d1d;">Mã bảo mật của biểu mẫu đã hết hạn hoặc không chính xác để bảo vệ tài khoản của bạn khỏi tấn công giả mạo.</p>
                    <a href="javascript:history.back()" style="display:inline-block;padding:8px 16px;background:#dc2626;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;font-weight:bold;">Quay Lại & Thử Lại</a>
                </div>
            ');
        }
    }
}

/**
 * Ghi log lỗi hệ thống an toàn
 */
function log_system_error($message, $exception = null) {
    $log_text = "[" . date('Y-m-d H:i:s') . "] " . $message;
    if ($exception instanceof Throwable) {
        $log_text .= " | Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
    }
    error_log($log_text);
}

/**
 * Định dạng tiền tệ Việt Nam (VND)
 */
function format_money($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

/**
 * Định dạng ngày tháng kiểu Việt Nam (dd/mm/YYYY)
 */
function format_date($date_string) {
    if (empty($date_string)) return '---';
    $time = strtotime($date_string);
    return date('d/m/Y', $time);
}

/**
 * Định dạng ngày giờ kiểu Việt Nam (HH:ii dd/mm/YYYY)
 */
function format_datetime($date_string) {
    if (empty($date_string)) return '---';
    $time = strtotime($date_string);
    return date('H:i d/m/Y', $time);
}

/**
 * Render Avatar placeholder thông minh với gradient
 */
function render_avatar($name, $avatar_file = null, $size = 10) {
    $first_letter = mb_strtoupper(mb_substr(trim($name ?? 'User'), 0, 1, 'UTF-8'), 'UTF-8');
    if (!empty($avatar_file) && file_exists(__DIR__ . '/../assets/uploads/' . $avatar_file)) {
        return '<img src="' . asset('uploads/' . e($avatar_file)) . '" class="w-' . $size . ' h-' . $size . ' rounded-full object-cover border border-slate-200 dark:border-slate-700 shadow-sm" alt="' . e($name) . '">';
    }
    return '<div class="w-' . $size . ' h-' . $size . ' rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-sm shadow-sm flex-shrink-0">' . e($first_letter) . '</div>';
}