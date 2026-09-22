<?php
// login.php - Trang đăng nhập hệ thống với giao diện Modern Light UI
require_once __DIR__ . '/core/auth.php';

// Nếu đã đăng nhập thì tự động chuyển vào Dashboard
if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            // Kiểm tra mật khẩu (hỗ trợ password_verify chuẩn và fallback test 'admin123')
            if ($user && (password_verify($password, $user['password']) || $password === 'admin123')) {
                // Đăng nhập thành công, lưu session
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'email' => $user['email'],
                    'role_id' => $user['role_id'],
                    'role_name' => $user['role_name'] ?? ($user['is_superadmin'] ? 'Super Administrator' : 'Chưa gán vai trò'),
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
                $error = 'Tài khoản hoặc mật khẩu không chính xác.';
            }
        } catch (PDOException $e) {
            $error = 'Lỗi hệ thống: ' . $e->getMessage();
        }
    }
}

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="vi" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Hệ Thống Quản Lý Nhân Sự HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 antialiased text-slate-700">

<div class="max-w-md w-full">
    <!-- Logo & Tiêu đề -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-tr from-indigo-600 to-violet-500 text-white rounded-2xl shadow-lg shadow-indigo-100 mb-3">
            <i class="fa-solid fa-users-gear text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold tracking-tight text-slate-800">Cổng Quản Trị Nhân Sự</h2>
        <p class="text-sm text-slate-500 mt-1">Hệ thống phân quyền ma trận đa cấp (HRMS)</p>
    </div>

    <!-- Thông báo lỗi hoặc flash -->
    <?php if (!empty($error)): ?>
        <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center gap-3 animate-fade">
            <i class="fa-solid fa-circle-exclamation text-base flex-shrink-0"></i>
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="mb-5 p-4 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 text-sm flex items-center gap-3">
            <i class="fa-solid fa-circle-info text-base flex-shrink-0"></i>
            <span><?= e($flash['message']) ?></span>
        </div>
    <?php endif; ?>

    <div class="bg-white p-8 rounded-3xl shadow-xl shadow-slate-100 border border-slate-100">
        <form action="<?= base_url('login.php') ?>" method="POST" class="space-y-5">
            <div>
                <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">
                    Tên Đăng Nhập
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-regular fa-user text-sm"></i>
                    </div>
                    <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus
                           placeholder="Nhập username"
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
                </div>
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-2">
                    Mật Khẩu
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <i class="fa-regular fa-lock text-sm"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••"
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white transition duration-150">
                </div>
            </div>

            <button type="submit"
                    class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold rounded-xl text-sm shadow-md shadow-indigo-100 transition duration-150 flex items-center justify-center gap-2">
                <span>Đăng Nhập Vào Hệ Thống</span>
                <i class="fa-solid fa-arrow-right text-xs"></i>
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3 text-center">
                Tài Khoản Mẫu Để Kiểm Thử (Mật khẩu: <span class="text-indigo-600 font-bold">admin123</span>)
            </h4>
            <div class="grid grid-cols-1 gap-2 text-xs">
                <button type="button" onclick="fillAccount('superadmin')"
                        class="p-2.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 flex items-center justify-between text-left transition">
                    <div>
                        <span class="font-bold">superadmin</span> (Admin Tổng - Toàn quyền)
                    </div>
                    <i class="fa-solid fa-crown text-amber-500"></i>
                </button>
                <button type="button" onclick="fillAccount('admin_nhansu')"
                        class="p-2.5 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-900 border border-sky-200 flex items-center justify-between text-left transition">
                    <div>
                        <span class="font-bold">admin_nhansu</span> (Admin 1 - Chỉ Nhân Sự & Phòng Ban)
                    </div>
                    <i class="fa-solid fa-id-badge text-sky-500"></i>
                </button>
                <button type="button" onclick="fillAccount('admin_luong')"
                        class="p-2.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-900 border border-emerald-200 flex items-center justify-between text-left transition">
                    <div>
                        <span class="font-bold">admin_luong</span> (Admin 2 - Chỉ Chấm Công & Lương)
                    </div>
                    <i class="fa-solid fa-wallet text-emerald-500"></i>
                </button>
            </div>
        </div>
    </div>

    <p class="text-center text-xs text-slate-400 mt-6">
        &copy; <?= date('Y') ?> HRMS Portal. Chạy trên môi trường XAMPP nội bộ.
    </p>
</div>

<script>
function fillAccount(username) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = 'admin123';
}
</script>
</body>
</html>