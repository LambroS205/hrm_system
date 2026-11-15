<?php
// login.php - Cổng đăng nhập WinCommerce People (WinMart / Masan Group)
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
                    SELECT u.*, r.name as role_name, r.data_scope 
                    FROM users u
                    LEFT JOIN roles r ON u.role_id = r.id
                    WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
                    LIMIT 1
                ");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                // Xác thực mật khẩu theo chuẩn mã hóa an toàn bcrypt
                if ($user && password_verify($password, $user['password'])) {
                    // Đổi Session ID mới để triệt tiêu lỗ hổng Session Fixation
                    session_regenerate_id(true);

                    // Xóa đếm đăng nhập thất bại
                    clear_login_rate_attempts();

                    // Xóa cache thông tin nhân viên cũ
                    unset($_SESSION['current_employee']);

                    // Lưu session thông tin tài khoản
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

                    // Tải ma trận quyền tương ứng vào session
                    if ($user['role_id']) {
                        reload_user_permissions($pdo, $user['role_id']);
                    } else {
                        $_SESSION['permissions'] = [];
                    }

                    set_flash('success', 'Chào mừng ' . $user['fullname'] . ' đã đăng nhập thành công vào hệ thống ' . brand('product_name') . '!');
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
    <title><?= e(brand('product_name')) ?> - Đăng Nhập Hệ Thống Quản Trị Nhân Sự</title>
    <meta name="description" content="<?= e(brand('company_legal')) ?> - <?= e(brand('product_name')) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= asset('img/brand/favicon.svg') ?>">
    
    <!-- Tailwind CSS CDN & Flat Config -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= asset('js/tailwind-flat.config.js') ?>"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script>
        if (localStorage.getItem('hrms_theme') === 'dark' || (!('hrms_theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="h-full antialiased text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-slate-900 flex flex-col justify-between transition-colors duration-200">

<!-- Nút chuyển đổi Dark/Light mode góc màn hình -->
<div class="fixed top-5 right-5 z-50">
    <button type="button" id="themeToggleBtn" onclick="toggleTheme()" class="w-10 h-10 rounded-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 hover:text-indigo-600 dark:hover:text-indigo-400 shadow-sm transition" title="Chuyển chế độ Sáng/Tối">
        <i class="fa-regular fa-moon dark:hidden text-lg"></i>
        <i class="fa-regular fa-sun hidden dark:inline text-lg text-amber-400"></i>
    </button>
</div>

<!-- Layout Split Screen Hiện Đại -->
<div class="min-h-full flex-1 flex flex-col lg:flex-row">
    
    <!-- CỘT TRÁI: Brand Showcase & Nhận diện WinCommerce (Desktop) -->
    <div class="lg:w-1/2 brand-gradient text-white flex flex-col justify-between p-8 sm:p-12 lg:p-16 relative overflow-hidden">
        <!-- Họa tiết chéo nền mờ -->
        <div class="absolute inset-0 brand-pattern opacity-30 pointer-events-none"></div>
        <div class="absolute -right-24 -bottom-24 w-96 h-96 rounded-full bg-white/5 blur-3xl pointer-events-none"></div>

        <!-- Top Brand Header -->
        <div class="relative z-10 flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-sm bg-white p-1.5 flex items-center justify-center shadow-lg">
                <img src="<?= asset('img/brand/logo-mark.svg') ?>" alt="Logo" class="w-full h-full object-contain">
            </div>
            <div>
                <div class="font-extrabold text-xl sm:text-2xl tracking-tight leading-tight"><?= e(brand('company_short')) ?></div>
                <div class="text-xs uppercase font-bold tracking-widest text-amber-300"><?= e(brand('parent_group')) ?></div>
            </div>
        </div>

        <!-- Main Marketing Hero Text -->
        <div class="relative z-10 my-10 lg:my-0 space-y-6 max-w-xl">
            <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 rounded-sm text-xs font-bold tracking-wide backdrop-blur-sm border border-white/15">
                <i class="fa-solid fa-store text-amber-300"></i>
                <span><?= e(brand('retail_brands')) ?></span>
            </div>
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                <?= e(brand('product_name')) ?>
            </h1>
            <p class="text-base sm:text-lg text-red-100 font-medium leading-relaxed">
                <?= e(brand('tagline')) ?>. Nền tảng số hóa quản trị nhân lực, chuỗi cung ứng và đánh giá hiệu suất BSC & SMART KPI.
            </p>

            <!-- 3 Chỉ số vận hành nổi bật -->
            <div class="grid grid-cols-3 gap-4 pt-4 border-t border-white/20">
                <div>
                    <div class="text-2xl sm:text-3xl font-black text-amber-300">4.590+</div>
                    <div class="text-[11px] font-semibold text-red-100 uppercase tracking-wider mt-0.5">Siêu thị & Cửa hàng</div>
                </div>
                <div>
                    <div class="text-2xl sm:text-3xl font-black text-white">32.000+</div>
                    <div class="text-[11px] font-semibold text-red-100 uppercase tracking-wider mt-0.5">Cán bộ nhân viên</div>
                </div>
                <div>
                    <div class="text-2xl sm:text-3xl font-black text-white">63/63</div>
                    <div class="text-[11px] font-semibold text-red-100 uppercase tracking-wider mt-0.5">Tỉnh thành phủ sóng</div>
                </div>
            </div>
        </div>

        <!-- Bottom Footer info -->
        <div class="relative z-10 text-xs text-red-200/90 font-medium">
            <i class="fa-solid fa-location-dot text-amber-300 mr-1.5"></i>
            <span><?= e(brand('hq_address')) ?></span>
        </div>
    </div>

    <!-- CỘT PHẢI: Form Đăng Nhập & Tài khoản Demo -->
    <div class="lg:w-1/2 flex items-center justify-center p-6 sm:p-10 lg:p-12">
        <div class="max-w-md w-full">
            
            <div class="mb-6 lg:mb-8 text-center lg:text-left">
                <div class="inline-flex lg:hidden items-center justify-center w-12 h-12 bg-indigo-600 text-white rounded-sm mb-3">
                    <img src="<?= asset('img/brand/logo-mark.svg') ?>" alt="Logo" class="w-10 h-10 object-contain">
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Đăng Nhập Hệ Thống
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                    Nhập thông tin tài khoản nội bộ để truy cập cổng nhân sự WinCommerce.
                </p>
            </div>

            <!-- Thông báo lỗi hoặc flash -->
            <?php if (!empty($error)): ?>
                <div class="mb-5 p-4 rounded-sm bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-xs sm:text-sm flex items-center gap-3">
                    <i class="fa-solid fa-circle-exclamation text-base flex-shrink-0 text-rose-500"></i>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($flash): ?>
                <div class="mb-5 p-4 rounded-sm bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-300 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 text-xs sm:text-sm flex items-center gap-3">
                    <i class="fa-solid fa-circle-info text-base flex-shrink-0 text-indigo-500"></i>
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Card Form Đăng Nhập -->
            <div class="bg-white dark:bg-slate-800 p-6 sm:p-8 rounded-sm border border-slate-300 dark:border-slate-700 shadow-xs">
                <form action="<?= base_url('login.php') ?>" method="POST" class="space-y-4" onsubmit="handleLoginSubmit(this)">
                    <?= csrf_field() ?>

                    <div>
                        <label for="username" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            Tên Đăng Nhập (Email Nội Bộ)
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </div>
                            <input type="text" id="username" name="username" value="<?= e($username) ?>" required autofocus
                                   placeholder="vd: admin@wincommerce.vn"
                                   class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-sm text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-600 transition duration-150">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                                Mật Khẩu
                            </label>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-lock text-sm"></i>
                            </div>
                            <input type="password" id="password" name="password" required
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-sm text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:border-indigo-600 transition duration-150">
                            <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <i class="fa-regular fa-eye" id="passwordEyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="loginBtn"
                            class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-bold rounded-sm text-sm transition duration-150 flex items-center justify-center gap-2 shadow-xs cursor-pointer mt-2">
                        <span>Đăng Nhập Vào Hệ Thống</span>
                        <i class="fa-solid fa-arrow-right text-xs" id="loginArrow"></i>
                    </button>
                </form>

                <!-- Khối tài khoản trình diễn chuẩn hóa theo 7 Persona (6 Vai Trò & 4 Data Scope) -->
                <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-700/60">
                    <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2 text-center flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-circle-play text-indigo-600 dark:text-indigo-400"></i>
                        <span>7 Persona Trình Diễn &bull; 6 Vai Trò (Mật khẩu: <span class="text-indigo-600 dark:text-indigo-400 font-extrabold">WinMart@2026</span>)</span>
                    </h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                        <!-- Persona 1: CEO / Super Admin (Full width top) -->
                        <button type="button" onclick="fillAccount('admin@wincommerce.vn')"
                                class="sm:col-span-2 p-2.5 rounded-sm bg-amber-50/70 hover:bg-amber-100 dark:bg-amber-950/30 dark:hover:bg-amber-950/50 text-amber-900 dark:text-amber-300 border border-amber-200/80 dark:border-amber-800 flex items-center justify-between text-left transition cursor-pointer">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-xs">admin@wincommerce.vn</span>
                                    <span class="px-1.5 py-0.2 text-[9px] font-extrabold uppercase bg-amber-200 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 rounded">CEO &bull; All</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5">Nguyễn Hoàng Nam &bull; Tổng quan tập đoàn & Quản trị RBAC</div>
                            </div>
                            <i class="fa-solid fa-crown text-amber-500 text-sm"></i>
                        </button>

                        <!-- Persona 2: CHRO -->
                        <button type="button" onclick="fillAccount('phuong.le@wincommerce.vn')"
                                class="p-2.5 rounded-sm bg-teal-50/70 hover:bg-teal-100 dark:bg-teal-950/30 dark:hover:bg-teal-950/50 text-teal-900 dark:text-teal-300 border border-teal-200/80 dark:border-teal-800 flex items-center justify-between gap-2 text-left transition cursor-pointer">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-[11px] truncate">phuong.le@...</span>
                                    <span class="px-1 py-0.2 text-[9px] font-extrabold uppercase bg-teal-200 dark:bg-teal-900/60 text-teal-800 dark:text-teal-200 rounded">CHRO &bull; All</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5 truncate">Lê Mai Phương &bull; GĐ Nhân Sự</div>
                            </div>
                            <i class="fa-solid fa-user-tie text-teal-500 text-sm flex-shrink-0"></i>
                        </button>

                        <!-- Persona 3: HR Executive (Role #3 - SoD Che Lương) -->
                        <button type="button" onclick="fillAccount('giang.tran@wincommerce.vn')"
                                class="p-2.5 rounded-sm bg-indigo-50/70 hover:bg-indigo-100 dark:bg-indigo-950/30 dark:hover:bg-indigo-950/50 text-indigo-900 dark:text-indigo-300 border border-indigo-200/80 dark:border-indigo-800 flex items-center justify-between gap-2 text-left transition cursor-pointer">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-[11px] truncate">giang.tran@...</span>
                                    <span class="px-1 py-0.2 text-[9px] font-extrabold uppercase bg-indigo-200 dark:bg-indigo-900/60 text-indigo-800 dark:text-indigo-200 rounded">HR &bull; All</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5 truncate">Trần T. Hương Giang &bull; Che Lương</div>
                            </div>
                            <i class="fa-solid fa-id-card-clip text-indigo-500 text-sm flex-shrink-0"></i>
                        </button>

                        <!-- Persona 4: C&B Specialist (Role #5) -->
                        <button type="button" onclick="fillAccount('cb@wincommerce.vn')"
                                class="p-2.5 rounded-sm bg-emerald-50/70 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:hover:bg-emerald-950/50 text-emerald-900 dark:text-emerald-300 border border-emerald-200/80 dark:border-emerald-800 flex items-center justify-between gap-2 text-left transition cursor-pointer">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-[11px] truncate">cb@wincommerce.vn</span>
                                    <span class="px-1 py-0.2 text-[9px] font-extrabold uppercase bg-emerald-200 dark:bg-emerald-900/60 text-emerald-800 dark:text-emerald-200 rounded">C&B &bull; All</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5 truncate">Phạm Thu Thảo &bull; Lương & BHXH</div>
                            </div>
                            <i class="fa-solid fa-money-bill-wave text-emerald-500 text-sm flex-shrink-0"></i>
                        </button>

                        <!-- Persona 5: Branch Manager (Role #2) -->
                        <button type="button" onclick="fillAccount('mienbac@wincommerce.vn')"
                                class="p-2.5 rounded-sm bg-sky-50/70 hover:bg-sky-100 dark:bg-sky-950/30 dark:hover:bg-sky-950/50 text-sky-900 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800 flex items-center justify-between gap-2 text-left transition cursor-pointer">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-[11px] truncate">mienbac@...</span>
                                    <span class="px-1 py-0.2 text-[9px] font-extrabold uppercase bg-sky-200 dark:bg-sky-900/60 text-sky-800 dark:text-sky-200 rounded">Branch</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5 truncate">Trần Quốc Bảo &bull; GĐ Miền Bắc</div>
                            </div>
                            <i class="fa-solid fa-building-user text-sky-500 text-sm flex-shrink-0"></i>
                        </button>

                        <!-- Persona 6: Department Manager (Role #6) -->
                        <button type="button" onclick="fillAccount('long.vu@wincommerce.vn')"
                                class="p-2.5 rounded-sm bg-orange-50/70 hover:bg-orange-100 dark:bg-orange-950/30 dark:hover:bg-orange-950/50 text-orange-900 dark:text-orange-300 border border-orange-200/80 dark:border-orange-800 flex items-center justify-between gap-2 text-left transition cursor-pointer">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-[11px] truncate">long.vu@...</span>
                                    <span class="px-1 py-0.2 text-[9px] font-extrabold uppercase bg-orange-200 dark:bg-orange-900/60 text-orange-800 dark:text-orange-200 rounded">Dept</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5 truncate">Vũ Hoàng Long &bull; TP CNTT (14 NV)</div>
                            </div>
                            <i class="fa-solid fa-sitemap text-orange-500 text-sm flex-shrink-0"></i>
                        </button>

                        <!-- Persona 7: Employee ESS (Role #4) -->
                        <button type="button" onclick="fillAccount('ngoc.do@wincommerce.vn')"
                                class="p-2.5 rounded-sm bg-purple-50/70 hover:bg-purple-100 dark:bg-purple-950/30 dark:hover:bg-purple-950/50 text-purple-900 dark:text-purple-300 border border-purple-200/80 dark:border-purple-800 flex items-center justify-between gap-2 text-left transition cursor-pointer">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-[11px] truncate">ngoc.do@...</span>
                                    <span class="px-1 py-0.2 text-[9px] font-extrabold uppercase bg-purple-200 dark:bg-purple-900/60 text-purple-800 dark:text-purple-200 rounded">Self</span>
                                </div>
                                <div class="text-[11px] opacity-90 mt-0.5 truncate">Đỗ T. Bích Ngọc &bull; Nhân viên ESS</div>
                            </div>
                            <i class="fa-solid fa-user text-purple-500 text-sm flex-shrink-0"></i>
                        </button>
                    </div>
                    <div class="mt-3.5 p-2 rounded-sm bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700/80 text-center text-[11px] text-slate-500 dark:text-slate-400">
                        <i class="fa-solid fa-circle-info text-indigo-500 mr-1"></i>
                        Toàn bộ <b class="text-slate-700 dark:text-slate-200">170+ nhân sự (6 vai trò)</b> đều có thể đăng nhập bằng email công ty, mật khẩu: <span class="font-bold text-indigo-600 dark:text-indigo-400">WinMart@2026</span>
                    </div>
                </div>
            </div>

            <!-- Chú thích bản quyền & Disclaimer -->
            <div class="mt-6 text-center space-y-1">
                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                    &copy; <?= date('Y') ?> <?= e(brand('company_legal')) ?>.
                </p>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 italic">
                    <?= e(brand('demo_disclaimer')) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function fillAccount(username) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = 'WinMart@2026';
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