<?php
// login.php - Cổng đăng nhập an toàn, giao diện hiện đại & bảo mật cao
require_once __DIR__ . '/core/auth.php';

// Nếu đã đăng nhập thì tự động chuyển vào Dashboard
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$username = '';
$rate_status = get_login_rate_status();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Kiểm tra giới hạn tần suất (Rate Limiting)
    if ($rate_status['is_locked']) {
        $error = "Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau {$rate_status['remaining_minutes']} phút nữa.";
    } else {
        // 2. Xác thực CSRF Token
        verify_csrf();

        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.*, r.name as role_name 
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE u.username = ? AND u.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                // Xác thực mật khẩu theo chuẩn mã hóa an toàn bcrypt (Không dùng backdoor)
                if ($user && password_verify($password, $user['password'])) {
                    // Đổi Session ID mới để triệt tiêu lỗ hổng Session Fixation
                    session_regenerate_id(true);

                    // Xóa đếm đăng nhập thất bại
                    clear_login_rate_attempts();

                    // Lưu session thông tin tài khoản
                    $_SESSION['user'] = [
                        'id' => (int)$user['id'],
                        'username' => $user['username'],
                        'fullname' => $user['fullname'],
                        'email' => $user['email'],
                        'role_id' => $user['role_id'] ? (int)$user['role_id'] : null,
                        'role_name' => $user['role_name'] ?? ($user['is_superadmin'] ? 'Tổng Quản Trị (Super Admin)' : 'Chưa gán vai trò'),
                        'is_superadmin' => (int)$user['is_superadmin']
                    ];

                    // Tải ma trận quyền tương ứng vào session
                    if ($user['role_id']) {
                        reload_user_permissions($pdo, $user['role_id']);
                    } else {
                        $_SESSION['permissions'] = [];
                    }

                    set_flash('success', 'Chào mừng ' . $user['fullname'] . ' đã đăng nhập thành công!');
                    redirect('index.php');
                } else {
                    // Ghi nhận lần đăng nhập sai
                    record_failed_login_attempt();
                    $rate_status = get_login_rate_status();
                    if ($rate_status['is_locked']) {
                        $error = "Bạn đã nhập sai 5 lần liên tiếp. Tài khoản tạm thời bị khóa đăng nhập trong 15 phút.";
                    } else {
                        $remaining_attempts = 5 - $rate_status['attempts'];
                        $error = "Tài khoản hoặc mật khẩu không chính xác. Bạn còn {$remaining_attempts} lần thử.";
                    }
                }
            } catch (PDOException $e) {
                log_system_error("Lỗi truy vấn đăng nhập", $e);
                $error = 'Đã có lỗi hệ thống xảy ra. Vui lòng liên hệ quản trị viên.';
            }
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AURA HRM - Đăng Nhập Hệ Thống Nhân Sự Tập Đoàn</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <!-- Google Fonts Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script>
        // Kiểm tra theme preference ban đầu
        if (localStorage.getItem('hrms_theme') === 'dark' || (!('hrms_theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-slate-700 dark:text-slate-200 bg-slate-50 dark:bg-slate-900 transition-colors duration-200">

<!-- Nút chuyển đổi Dark/Light mode góc màn hình -->
<div class="fixed top-5 right-5">
    <button type="button" id="themeToggleBtn" onclick="toggleTheme()" class="w-10 h-10 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-center text-slate-500 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 transition" title="Chuyển chế độ Sáng/Tối">
        <i class="fa-regular fa-moon dark:hidden text-lg"></i>
        <i class="fa-regular fa-sun hidden dark:inline text-lg"></i>
    </button>
</div>

<div class="max-w-md w-full">
    <!-- Logo & Tiêu đề -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-tr from-indigo-600 to-violet-500 text-white rounded-2xl shadow-lg shadow-indigo-100 dark:shadow-none mb-3">
            <i class="fa-solid fa-users-gear text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white">Cổng Nhân Sự Tập Đoàn Aura</h2>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">"Nâng tầm trải nghiệm bán lẻ thông minh"</p>
    </div>

    <!-- Thông báo lỗi hoặc flash -->
    <?php if (!empty($error)): ?>
        <div class="mb-5 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-circle-exclamation text-base flex-shrink-0 text-rose-500"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="mb-5 p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-sm flex items-center gap-3">
            <i class="fa-solid fa-circle-info text-base flex-shrink-0 text-indigo-500"></i>
            <span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-slate-800 p-8 rounded-3xl shadow-xl shadow-slate-100 dark:shadow-none border border-slate-100 dark:border-slate-700">
        <form action="<?= base_url('login.php') ?>" method="POST" class="space-y-5" onsubmit="handleLoginSubmit(this)">
            <?= csrf_field() ?>

            <div>
                <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2">
                    Tên Đăng Nhập
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-regular fa-user text-sm"></i>
                    </div>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus
                           placeholder="Nhập tên tài khoản"
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition duration-150">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-2">
                    Mật Khẩu
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-regular fa-lock text-sm"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••"
                           class="w-full pl-10 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition duration-150">
                    <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                        <i class="fa-regular fa-eye" id="passwordEyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" id="loginBtn"
                    class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold rounded-xl text-sm shadow-md shadow-indigo-100 dark:shadow-none transition duration-150 flex items-center justify-center gap-2">
                <span>Đăng Nhập Vào Hệ Thống</span>
                <i class="fa-solid fa-arrow-right text-xs" id="loginArrow"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 dark:border-slate-700/60">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3 text-center">
                Tài Khoản Trình Diễn (Mật khẩu chung: <span class="text-indigo-600 dark:text-indigo-400 font-bold">Aura@2026</span>)
            </h4>
            <div class="grid grid-cols-1 gap-2 text-xs">
                <button type="button" onclick="fillAccount('admin@auragroup.vn')"
                        class="p-2.5 rounded-xl bg-amber-50/70 hover:bg-amber-100 dark:bg-amber-950/30 dark:hover:bg-amber-950/50 text-amber-900 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800 flex items-center justify-between text-left transition">
                    <div>
                        <span class="font-bold">admin@auragroup.vn</span>
                        <div class="text-[11px] opacity-80">Nguyễn Hoàng Nam (Tổng Giám Đốc / Toàn quyền)</div>
                    </div>
                    <i class="fa-solid fa-crown text-amber-500"></i>
                </button>
                <button type="button" onclick="fillAccount('tp.hcm@auragroup.vn')"
                        class="p-2.5 rounded-xl bg-sky-50/70 hover:bg-sky-100 dark:bg-sky-950/30 dark:hover:bg-sky-950/50 text-sky-900 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800 flex items-center justify-between text-left transition">
                    <div>
                        <span class="font-bold">tp.hcm@auragroup.vn</span>
                        <div class="text-[11px] opacity-80">Trần Quốc Bảo (Giám Đốc Chi Nhánh TP.HCM)</div>
                    </div>
                    <i class="fa-solid fa-building-user text-sky-500"></i>
                </button>
                <button type="button" onclick="fillAccount('nhanvien@auragroup.vn')"
                        class="p-2.5 rounded-xl bg-emerald-50/70 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:hover:bg-emerald-950/50 text-emerald-900 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800 flex items-center justify-between text-left transition">
                    <div>
                        <span class="font-bold">nhanvien@auragroup.vn</span>
                        <div class="text-[11px] opacity-80">Phạm Thu Thảo (Chuyên Viên Nhân Sự & Tuyển Dụng)</div>
                    </div>
                    <i class="fa-solid fa-id-badge text-emerald-500"></i>
                </button>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 dark:text-slate-500 mt-6">
        &copy; <?= date('Y') ?> Aura Retail Group JSC. Nền tảng Quản trị Nhân sự & Chuỗi Dịch vụ Đa Chi Nhánh.
    </p>
</div>

<script>
function fillAccount(username) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = 'Aura@2026';
}

function togglePasswordVisibility() {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('passwordEyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}

function toggleTheme() {
    if (document.documentElement.classList.contains('dark')) {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('hrms_theme', 'light');
    } else {
        document.documentElement.classList.add('dark');
        localStorage.setItem('hrms_theme', 'dark');
    }
}

function handleLoginSubmit(form) {
    const btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.classList.add('opacity-75', 'cursor-not-allowed');
    btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-sm mr-2"></i><span>Đang xác thực...</span>';
}
</script>
</body>
</html>