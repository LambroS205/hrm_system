<?php
// profile.php - Quản lý Hồ Sơ Cá Nhân & Thay Đổi Mật Khẩu Bảo Mật
$page_title = 'Hồ Sơ & Bảo Mật Tài Khoản';

require_once __DIR__ . '/core/auth.php';
require_login();

$user = current_user();
$user_id = (int)$user['id'];

// Lấy lại thông tin user mới nhất từ DB
$stmt = $pdo->prepare("
    SELECT u.*, r.name as role_name 
    FROM users u 
    LEFT JOIN roles r ON u.role_id = r.id 
    WHERE u.id = ? 
    LIMIT 1
");
$stmt->execute([$user_id]);
$db_user = $stmt->fetch();

if (!$db_user) {
    set_flash('danger', 'Không tìm thấy tài khoản người dùng.');
    redirect('login.php');
}

// Xử lý Cập nhật thông tin cá nhân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'update_profile') {
    verify_csrf();

    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (empty($fullname)) {
        set_flash('danger', 'Họ và tên không được để trống.');
    } else {
        try {
            $up = $pdo->prepare("UPDATE users SET fullname = ?, email = ? WHERE id = ?");
            $up->execute([$fullname, $email, $user_id]);

            $_SESSION['user']['fullname'] = $fullname;
            $_SESSION['user']['email'] = $email;

            set_flash('success', 'Đã cập nhật thông tin cá nhân thành công!');
            redirect('profile.php');
        } catch (PDOException $e) {
            log_system_error("Lỗi cập nhật profile", $e);
            set_flash('danger', 'Lỗi hệ thống khi cập nhật thông tin.');
        }
    }
}

// Xử lý Đổi Mật Khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'change_password') {
    verify_csrf();

    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash('danger', 'Vui lòng điền đầy đủ tất cả các trường mật khẩu.');
    } elseif ($new_password !== $confirm_password) {
        set_flash('danger', 'Mật khẩu mới và xác nhận mật khẩu không trùng khớp.');
    } elseif (strlen($new_password) < 6) {
        set_flash('danger', 'Mật khẩu mới phải có ít nhất 6 ký tự.');
    } elseif (!password_verify($current_password, $db_user['password'])) {
        set_flash('danger', 'Mật khẩu hiện tại không chính xác.');
    } else {
        try {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $up = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $up->execute([$new_hash, $user_id]);

            set_flash('success', 'Đổi mật khẩu thành công! Mật khẩu mới đã được lưu an toàn.');
            redirect('profile.php');
        } catch (PDOException $e) {
            log_system_error("Lỗi đổi mật khẩu", $e);
            set_flash('danger', 'Lỗi hệ thống khi cập nhật mật khẩu.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <!-- Card Tổng quan Tài khoản -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 sm:p-8 border border-slate-200/80 dark:border-slate-700 shadow-sm relative overflow-hidden">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 relative z-10 text-center sm:text-left">
            <div class="w-24 h-24 rounded-3xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white flex items-center justify-center font-extrabold text-4xl shadow-lg shadow-indigo-200 dark:shadow-none flex-shrink-0">
                <?= mb_strtoupper(mb_substr($db_user['fullname'], 0, 1, 'UTF-8'), 'UTF-8') ?>
            </div>
            
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2.5 mb-1.5">
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white"><?= e($db_user['fullname']) ?></h2>
                    <?php if ($db_user['is_superadmin']): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300">
                            <i class="fa-solid fa-crown text-[10px] mr-1.5"></i> Super Admin
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 dark:bg-indigo-950/60 text-indigo-800 dark:text-indigo-300">
                            <i class="fa-solid fa-user-shield text-[10px] mr-1.5"></i> <?= e($db_user['role_name'] ?? 'Admin Phân Hệ') ?>
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Hoạt động
                    </span>
                </div>

                <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">
                    Tên đăng nhập: <span class="font-semibold text-slate-700 dark:text-slate-300">@<?= e($db_user['username']) ?></span> &bull; 
                    Email: <span class="font-semibold text-slate-700 dark:text-slate-300"><?= e($db_user['email'] ?: 'Chưa cập nhật') ?></span>
                </p>

                <div class="flex flex-wrap justify-center sm:justify-start gap-4 text-xs text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-1.5">
                        <i class="fa-regular fa-clock text-slate-400"></i>
                        <span>Tham gia: <?= format_date($db_user['created_at'] ?? 'now') ?></span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-indigo-500"></i>
                        <span>Bảo mật 2 lớp: Chuẩn Bcrypt</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cụm Form Cập nhật Thông tin & Đổi Mật Khẩu -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Cột 1: Thông Tin Cá Nhân -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-700 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 pb-4 mb-5 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-lg">
                        <i class="fa-regular fa-id-card"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white">Thông Tin Cơ Bản</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Cập nhật họ tên và hòm thư điện tử</p>
                    </div>
                </div>

                <form action="<?= base_url('profile.php') ?>" method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="update_profile">

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                            Tên Đăng Nhập
                        </label>
                        <input type="text" value="<?= e($db_user['username']) ?>" disabled
                               class="w-full px-4 py-2.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-500 dark:text-slate-400 cursor-not-allowed">
                        <p class="text-[11px] text-slate-400 mt-1">Tên tài khoản hệ thống không thể thay đổi</p>
                    </div>

                    <div>
                        <label for="fullname" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                            Họ Và Tên <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="fullname" name="fullname" value="<?= e($db_user['fullname']) ?>" required
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                            Địa Chỉ Email
                        </label>
                        <input type="email" id="email" name="email" value="<?= e($db_user['email']) ?>"
                               placeholder="admin@example.com"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm shadow-sm transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-floppy-disk text-xs"></i>
                            <span>Lưu Thông Tin Cá Nhân</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Cột 2: Đổi Mật Khẩu -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-700 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 pb-4 mb-5 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-slate-800 dark:text-white">Bảo Mật & Mật Khẩu</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Thay đổi mật khẩu đăng nhập định kỳ</p>
                    </div>
                </div>

                <form action="<?= base_url('profile.php') ?>" method="POST" class="space-y-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="change_password">

                    <div>
                        <label for="current_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                            Mật Khẩu Hiện Tại <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="current_password" name="current_password" required
                               placeholder="••••••••"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label for="new_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                            Mật Khẩu Mới <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6"
                               placeholder="Tối thiểu 6 ký tự"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1.5">
                            Xác Nhận Mật Khẩu Mới <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               placeholder="Nhập lại mật khẩu mới"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl text-sm text-slate-800 dark:text-slate-100 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition">
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 px-4 bg-slate-800 dark:bg-indigo-600 hover:bg-slate-900 dark:hover:bg-indigo-700 text-white font-semibold rounded-xl text-sm shadow-sm transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-lock text-xs"></i>
                            <span>Cập Nhật Mật Khẩu Mới</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- Thông tin phiên đăng nhập an toàn -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 border border-slate-200/80 dark:border-slate-700 shadow-sm">
        <div class="flex items-center gap-3 pb-3 mb-4 border-b border-slate-100 dark:border-slate-700">
            <i class="fa-solid fa-laptop-code text-indigo-500"></i>
            <h4 class="font-bold text-sm text-slate-800 dark:text-white">Phiên Đăng Nhập & Môi Trường</h4>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
                <div class="text-slate-400 mb-1">Địa chỉ IP Kết Nối</div>
                <div class="font-semibold text-slate-700 dark:text-slate-200"><?= e($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') ?></div>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
                <div class="text-slate-400 mb-1">Cơ Chế CSRF Token</div>
                <div class="font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                    <i class="fa-solid fa-circle-check"></i> Đang kích hoạt (64 chars)
                </div>
            </div>
            <div class="p-3 bg-slate-50 dark:bg-slate-900 rounded-xl border border-slate-100 dark:border-slate-800">
                <div class="text-slate-400 mb-1">Session ID</div>
                <div class="font-semibold text-slate-700 dark:text-slate-200 truncate"><?= substr(session_id(), 0, 16) ?>...</div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
