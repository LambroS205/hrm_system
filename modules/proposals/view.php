<?php
// modules/proposals/view.php - Chi Tiết Đề Xuất & Timeline Phê Duyệt
require_once __DIR__ . '/../../core/auth.php';

if (!has_permission('proposals', 'view') && !has_permission('proposals', 'create')) {
    require_permission('proposals', 'view');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    set_flash('danger', 'Không tìm thấy đề xuất.');
    redirect('modules/proposals/index.php');
}

// Lấy chi tiết đề xuất
$stmt = $pdo->prepare("
    SELECT p.*,
           e.fullname, e.employee_code, e.avatar, e.phone, e.email,
           d.name AS department_name,
           pos.name AS position_name,
           b.name AS branch_name,
           u.fullname AS approver_name
    FROM proposals p
    JOIN employees e ON p.employee_id = e.id
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions pos ON e.position_id = pos.id
    LEFT JOIN branches b ON e.branch_id = b.id
    LEFT JOIN users u ON p.approved_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    set_flash('danger', 'Đề xuất không tồn tại.');
    redirect('modules/proposals/index.php');
}

$page_title = 'Chi Tiết Đề Xuất ' . $p['proposal_code'];

// Map labels
$type_labels = [
    'leave'         => ['Nghỉ Phép',    'fa-calendar-minus', 'sky'],
    'overtime'      => ['Tăng Ca',       'fa-clock',          'violet'],
    'business_trip' => ['Công Tác',      'fa-plane',          'teal'],
    'salary_raise'  => ['Tăng Lương',    'fa-coins',          'amber'],
    'equipment'     => ['Thiết Bị',      'fa-laptop',         'indigo'],
    'other'         => ['Khác',          'fa-clipboard-list', 'slate'],
];

$priority_labels = [
    'low'    => ['Thấp',        'slate'],
    'normal' => ['Bình Thường', 'sky'],
    'high'   => ['Cao',         'amber'],
    'urgent' => ['Khẩn Cấp',   'rose'],
];

$status_config = [
    'pending'  => ['Chờ Phê Duyệt', 'amber', 'fa-hourglass-half'],
    'approved' => ['Đã Phê Duyệt',  'emerald', 'fa-circle-check'],
    'rejected' => ['Đã Từ Chối',    'rose', 'fa-circle-xmark'],
];

$leave_type_labels = [
    'annual'    => 'Phép Năm',
    'sick'      => 'Nghỉ Ốm',
    'maternity' => 'Thai Sản',
    'personal'  => 'Việc Riêng',
    'unpaid'    => 'Không Lương',
];

$t = $type_labels[$p['type']] ?? ['Khác', 'fa-clipboard-list', 'slate'];
$pr = $priority_labels[$p['priority']] ?? ['Bình Thường', 'sky'];
$sc = $status_config[$p['status']] ?? $status_config['pending'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6 max-w-4xl mx-auto">

    <!-- Header & Trạng Thái -->
    <div class="bg-white dark:bg-slate-800 p-6 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-<?= $t[2] ?>-50 dark:bg-<?= $t[2] ?>-950/60 text-<?= $t[2] ?>-700 dark:text-<?= $t[2] ?>-300 border border-<?= $t[2] ?>-200 dark:border-<?= $t[2] ?>-800">
                        <i class="fa-solid <?= $t[1] ?> text-[10px]"></i>
                        <?= $t[0] ?>
                    </span>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold bg-<?= $pr[1] ?>-50 dark:bg-<?= $pr[1] ?>-950/60 text-<?= $pr[1] ?>-700 dark:text-<?= $pr[1] ?>-300 border border-<?= $pr[1] ?>-200 dark:border-<?= $pr[1] ?>-800">
                        Ưu tiên: <?= $pr[0] ?>
                    </span>
                </div>
                <h2 class="text-xl font-bold text-slate-800 dark:text-white"><?= e($p['title']) ?></h2>
                <div class="flex items-center gap-3 mt-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="font-mono font-bold text-violet-600 dark:text-violet-400"><?= e($p['proposal_code']) ?></span>
                    <span>•</span>
                    <span>Tạo lúc <?= format_datetime($p['created_at']) ?></span>
                </div>
            </div>

            <div class="flex items-center gap-2.5 flex-shrink-0">
                <!-- Badge trạng thái lớn -->
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-<?= $sc[1] ?>-50 dark:bg-<?= $sc[1] ?>-950/60 text-<?= $sc[1] ?>-700 dark:text-<?= $sc[1] ?>-300 border border-<?= $sc[1] ?>-200 dark:border-<?= $sc[1] ?>-800 font-bold text-sm">
                    <i class="fa-solid <?= $sc[2] ?>"></i>
                    <?= $sc[0] ?>
                </div>
                <a href="<?= base_url('modules/proposals/index.php') ?>"
                   class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-500 dark:text-slate-400 flex items-center justify-center transition border border-slate-200 dark:border-slate-600"
                   title="Quay lại danh sách">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
            </div>
        </div>

        <!-- Nút hành động cho quản lý -->
        <?php if ($p['status'] === 'pending' && has_permission('proposals', 'approve')): ?>
            <div class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700 flex items-center gap-3">
                <form action="<?= base_url('modules/proposals/index.php') ?>" method="POST" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="approve_proposal">
                    <input type="hidden" name="proposal_id" value="<?= $p['id'] ?>">
                    <button type="submit" onclick="return confirm('Xác nhận PHÊ DUYỆT đề xuất <?= e($p['proposal_code']) ?>?')"
                            class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-check"></i>
                        <span>Phê Duyệt</span>
                    </button>
                </form>
                <button type="button" onclick="document.getElementById('inlineRejectForm').classList.toggle('hidden')"
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-sm font-bold rounded-xl shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-xmark"></i>
                    <span>Từ Chối</span>
                </button>
            </div>

            <!-- Form từ chối inline -->
            <div id="inlineRejectForm" class="hidden mt-4 p-4 bg-rose-50 dark:bg-rose-950/30 rounded-2xl border border-rose-200 dark:border-rose-800">
                <form action="<?= base_url('modules/proposals/index.php') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action_type" value="reject_proposal">
                    <input type="hidden" name="proposal_id" value="<?= $p['id'] ?>">
                    <label class="block text-xs font-bold text-rose-700 dark:text-rose-300 mb-1.5">Lý Do Từ Chối <span class="text-rose-500">*</span></label>
                    <textarea name="rejection_reason" rows="3" required placeholder="Nhập lý do từ chối..."
                              class="w-full px-3 py-2 bg-white dark:bg-slate-900 border border-rose-200 dark:border-rose-800 rounded-xl text-sm text-slate-800 dark:text-slate-200 focus:ring-2 focus:ring-rose-500 outline-none resize-none mb-3"></textarea>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition">
                            <i class="fa-solid fa-ban mr-1"></i>Xác Nhận Từ Chối
                        </button>
                        <button type="button" onclick="document.getElementById('inlineRejectForm').classList.add('hidden')" class="px-4 py-2 text-slate-600 dark:text-slate-300 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-700 rounded-xl transition">
                            Hủy
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Cột Trái: Thông tin chi tiết -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Nội dung đề xuất -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4 pb-3 border-b border-slate-200 dark:border-slate-700">
                    <i class="fa-solid fa-file-lines text-violet-600 dark:text-violet-400"></i>
                    Nội Dung Đề Xuất
                </h3>

                <?php if (!empty($p['description'])): ?>
                    <div class="text-sm text-slate-700 dark:text-slate-300 leading-relaxed whitespace-pre-line"><?= e($p['description']) ?></div>
                <?php else: ?>
                    <p class="text-sm text-slate-400 dark:text-slate-500 italic">Không có mô tả chi tiết.</p>
                <?php endif; ?>

                <!-- Thông tin bổ sung theo loại -->
                <div class="mt-5 pt-5 border-t border-slate-200 dark:border-slate-700 grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <?php if (!empty($p['start_date'])): ?>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Ngày Bắt Đầu</div>
                            <div class="text-sm font-bold text-slate-800 dark:text-white font-mono"><?= format_date($p['start_date']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($p['end_date'])): ?>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Ngày Kết Thúc</div>
                            <div class="text-sm font-bold text-slate-800 dark:text-white font-mono"><?= format_date($p['end_date']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ((float)$p['total_days'] > 0): ?>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Tổng Ngày</div>
                            <div class="text-sm font-bold text-slate-800 dark:text-white"><?= number_format((float)$p['total_days'], 1) ?> ngày</div>
                        </div>
                    <?php endif; ?>

                    <?php if ($p['type'] === 'leave' && !empty($p['leave_type'])): ?>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Loại Nghỉ Phép</div>
                            <div class="text-sm font-bold text-slate-800 dark:text-white"><?= $leave_type_labels[$p['leave_type']] ?? 'N/A' ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ((float)$p['amount'] > 0): ?>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Số Tiền</div>
                            <div class="text-sm font-bold text-amber-600 dark:text-amber-400 font-mono"><?= format_money($p['amount']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lý do từ chối (nếu bị từ chối) -->
            <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                <div class="bg-rose-50 dark:bg-rose-950/30 p-5 rounded-2xl border border-rose-200 dark:border-rose-800">
                    <h3 class="text-sm font-bold text-rose-700 dark:text-rose-300 flex items-center gap-2 mb-3">
                        <i class="fa-solid fa-comment-slash"></i>
                        Lý Do Từ Chối
                    </h3>
                    <div class="text-sm text-rose-800 dark:text-rose-200 leading-relaxed"><?= e($p['rejection_reason']) ?></div>
                    <?php if (!empty($p['approver_name'])): ?>
                        <div class="mt-3 text-xs text-rose-500 dark:text-rose-400">
                            Từ chối bởi <strong><?= e($p['approver_name']) ?></strong> lúc <?= format_datetime($p['approved_at']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Timeline Phê Duyệt -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-5 pb-3 border-b border-slate-200 dark:border-slate-700">
                    <i class="fa-solid fa-timeline text-violet-600 dark:text-violet-400"></i>
                    Lịch Sử Xử Lý
                </h3>

                <div class="relative pl-8 space-y-6">
                    <!-- Đường timeline dọc -->
                    <div class="absolute left-3 top-2 bottom-2 w-0.5 bg-slate-200 dark:bg-slate-700"></div>

                    <!-- Bước 1: Tạo đề xuất -->
                    <div class="relative">
                        <div class="absolute -left-5 w-6 h-6 rounded-full bg-violet-100 dark:bg-violet-900/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs border-2 border-white dark:border-slate-800 shadow-sm">
                            <i class="fa-solid fa-plus text-[9px]"></i>
                        </div>
                        <div class="ml-4">
                            <div class="text-sm font-bold text-slate-800 dark:text-white">Đề xuất được tạo</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                <strong><?= e($p['fullname']) ?></strong> đã gửi đề xuất <?= e($p['proposal_code']) ?>
                            </div>
                            <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 font-mono"><?= format_datetime($p['created_at']) ?></div>
                        </div>
                    </div>

                    <!-- Bước 2: Phê duyệt/Từ chối (nếu có) -->
                    <?php if ($p['status'] === 'approved'): ?>
                        <div class="relative">
                            <div class="absolute -left-5 w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-900/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs border-2 border-white dark:border-slate-800 shadow-sm">
                                <i class="fa-solid fa-check text-[9px]"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-emerald-700 dark:text-emerald-300">Đã được phê duyệt</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Phê duyệt bởi <strong><?= e($p['approver_name'] ?? 'Quản lý') ?></strong>
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 font-mono"><?= format_datetime($p['approved_at']) ?></div>
                                <?php if ($p['type'] === 'leave'): ?>
                                    <div class="mt-2 text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                                        <i class="fa-solid fa-link text-[9px]"></i>
                                        Bản ghi chấm công nghỉ phép đã được tạo tự động
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif ($p['status'] === 'rejected'): ?>
                        <div class="relative">
                            <div class="absolute -left-5 w-6 h-6 rounded-full bg-rose-100 dark:bg-rose-900/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs border-2 border-white dark:border-slate-800 shadow-sm">
                                <i class="fa-solid fa-xmark text-[9px]"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-rose-700 dark:text-rose-300">Đã bị từ chối</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Từ chối bởi <strong><?= e($p['approver_name'] ?? 'Quản lý') ?></strong>
                                </div>
                                <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-1 font-mono"><?= format_datetime($p['approved_at']) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="relative">
                            <div class="absolute -left-5 w-6 h-6 rounded-full bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs border-2 border-white dark:border-slate-800 shadow-sm animate-pulse">
                                <i class="fa-solid fa-hourglass-half text-[9px]"></i>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-bold text-amber-700 dark:text-amber-300">Đang chờ phê duyệt</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Đề xuất đang chờ quản lý xem xét và phê duyệt
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cột Phải: Thông tin người đề xuất -->
        <div class="space-y-6">

            <!-- Card thông tin nhân viên -->
            <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4 pb-3 border-b border-slate-200 dark:border-slate-700">
                    <i class="fa-solid fa-user text-violet-600 dark:text-violet-400"></i>
                    Người Đề Xuất
                </h3>

                <div class="flex flex-col items-center text-center">
                    <!-- Avatar -->
                    <?php if (!empty($p['avatar']) && file_exists(__DIR__ . '/../../assets/uploads/' . $p['avatar'])): ?>
                        <img src="<?= base_url('assets/uploads/' . e($p['avatar'])) ?>" class="w-16 h-16 rounded-2xl object-cover border-2 border-slate-200 dark:border-slate-700 shadow-md" alt="">
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 text-white flex items-center justify-center font-bold text-xl shadow-md">
                            <?= strtoupper(mb_substr($p['fullname'], 0, 1, 'UTF-8')) ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <div class="font-bold text-slate-800 dark:text-white text-base"><?= e($p['fullname']) ?></div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5"><?= e($p['employee_code']) ?></div>
                    </div>
                </div>

                <div class="mt-5 space-y-3 text-xs">
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold">Chức Vụ</span>
                        <span class="font-bold text-slate-800 dark:text-white text-right max-w-[60%] truncate"><?= e($p['position_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold">Phòng Ban</span>
                        <span class="font-bold text-slate-800 dark:text-white text-right max-w-[60%] truncate"><?= e($p['department_name'] ?? 'N/A') ?></span>
                    </div>
                    <div class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold">Chi Nhánh</span>
                        <span class="font-bold text-slate-800 dark:text-white text-right max-w-[60%] truncate"><?= e($p['branch_name'] ?? 'N/A') ?></span>
                    </div>
                    <?php if (!empty($p['email'])): ?>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-slate-500 dark:text-slate-400 font-semibold">Email</span>
                        <span class="font-semibold text-indigo-600 dark:text-indigo-400 text-right max-w-[60%] truncate"><?= e($p['email']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Tóm tắt nhanh -->
            <div class="bg-gradient-to-br from-violet-600 to-indigo-700 p-5 rounded-2xl text-white shadow-lg shadow-violet-100 dark:shadow-none">
                <h3 class="text-sm font-bold flex items-center gap-2 mb-3 opacity-90">
                    <i class="fa-solid fa-chart-simple"></i>
                    Tóm Tắt Đề Xuất
                </h3>
                <div class="space-y-2.5 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="opacity-80">Loại đề xuất</span>
                        <span class="font-bold"><?= $t[0] ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="opacity-80">Mức ưu tiên</span>
                        <span class="font-bold"><?= $pr[0] ?></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="opacity-80">Trạng thái</span>
                        <span class="font-bold"><?= $sc[0] ?></span>
                    </div>
                    <?php if ((float)$p['total_days'] > 0): ?>
                    <div class="flex items-center justify-between">
                        <span class="opacity-80">Tổng ngày</span>
                        <span class="font-bold font-mono"><?= number_format((float)$p['total_days'], 1) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ((float)$p['amount'] > 0): ?>
                    <div class="flex items-center justify-between">
                        <span class="opacity-80">Số tiền</span>
                        <span class="font-bold font-mono"><?= format_money($p['amount']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex items-center justify-between pt-2 border-t border-white/20">
                        <span class="opacity-80">Cập nhật</span>
                        <span class="font-bold font-mono text-[11px]"><?= format_datetime($p['updated_at']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
