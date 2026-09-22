<?php
// modules/recruitment/candidate_view.php - Chi Tiết Hồ Sơ Ứng Viên & Thẩm Định Tuyển Dụng
$page_title = 'Chi Tiết Hồ Sơ Ứng Viên';

require_once __DIR__ . '/../../core/auth.php';
require_permission('recruitment', 'view');

$id = (int)($_GET['id'] ?? 0);

// Xử lý cập nhật nhanh Ghi chú Phỏng vấn hoặc Stage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    verify_csrf();
    require_permission('recruitment', 'edit');

    if ($_POST['action_type'] === 'update_notes') {
        $interview_date = !empty($_POST['interview_date']) ? str_replace('T', ' ', $_POST['interview_date']) : null;
        $interview_notes = trim($_POST['interview_notes'] ?? '');
        $rating = (int)($_POST['rating'] ?? 3);
        $stage = trim($_POST['stage'] ?? '');

        $upStmt = $pdo->prepare("UPDATE candidates SET interview_date = ?, interview_notes = ?, rating = ?, stage = ? WHERE id = ?");
        $upStmt->execute([$interview_date, $interview_notes, $rating, $stage, $id]);

        set_flash('success', 'Đã cập nhật biên bản thẩm định phỏng vấn thành công.');
        redirect('modules/recruitment/candidate_view.php?id=' . $id);
    }
}

// Lấy đầy đủ thông tin ứng viên kèm vị trí, phòng ban, chi nhánh
$stmt = $pdo->prepare("
    SELECT c.*, 
           j.job_code, j.title AS job_title, j.salary_range_min, j.salary_range_max, j.requirements,
           b.name AS branch_name, d.name AS department_name, p.name AS position_name,
           e.employee_code AS hired_emp_code, e.fullname AS hired_emp_name
    FROM candidates c
    JOIN job_positions j ON c.job_position_id = j.id
    LEFT JOIN branches b ON j.branch_id = b.id
    LEFT JOIN departments d ON j.department_id = d.id
    LEFT JOIN positions p ON j.position_id = p.id
    LEFT JOIN employees e ON c.hired_employee_id = e.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$candidate = $stmt->fetch();

if (!$candidate) {
    set_flash('danger', 'Hồ sơ ứng viên không tồn tại trong hệ thống.');
    redirect('modules/recruitment/index.php');
}

$stage_meta = [
    'applied'   => ['label' => 'Ứng Tuyển', 'class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300', 'step' => 1],
    'screening' => ['label' => 'Sàng Lọc', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300', 'step' => 2],
    'interview' => ['label' => 'Phỏng Vấn', 'class' => 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300', 'step' => 3],
    'offer'     => ['label' => 'Gửi Offer', 'class' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300', 'step' => 4],
    'hired'     => ['label' => 'Trúng Tuyển', 'class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300', 'step' => 5],
    'rejected'  => ['label' => 'Từ Chối', 'class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300', 'step' => 0]
];

$cur_stage = $stage_meta[$candidate['stage']] ?? $stage_meta['applied'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Banner Hồ Sơ Ứng Viên -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 sm:p-8 space-y-6">
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6 pb-6 border-b border-slate-100 dark:border-slate-700/60">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-2xl shadow-md flex-shrink-0">
                    <?= strtoupper(mb_substr($candidate['fullname'], 0, 1, 'UTF-8')) ?>
                </div>

                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-2xl font-bold text-slate-800 dark:text-white"><?= e($candidate['fullname']) ?></h2>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $cur_stage['class'] ?>">
                            <?= $cur_stage['label'] ?>
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                        Mã ứng viên: <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400"><?= e($candidate['candidate_code']) ?></span> 
                        • Ứng tuyển: <strong class="text-slate-800 dark:text-slate-200"><?= e($candidate['job_title']) ?></strong>
                        • Nơi làm việc: <span class="text-indigo-700 dark:text-indigo-300 font-semibold"><?= e($candidate['branch_name'] ?? 'Chi nhánh') ?></span>
                    </p>
                </div>
            </div>

            <!-- Nút Hành Động -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="index.php" class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-200 text-xs font-semibold hover:bg-slate-200 transition">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Quay Lại
                </a>

                <?php if (!empty($candidate['hired_employee_id'])): ?>
                    <a href="<?= base_url('modules/employees/view.php?id=' . $candidate['hired_employee_id']) ?>" 
                       class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-id-card"></i>
                        <span>Xem Hồ Sơ Nhân Viên (<?= e($candidate['hired_emp_code']) ?>)</span>
                    </a>
                <?php else: ?>
                    <?php if (has_permission('recruitment', 'hire')): ?>
                        <a href="hire.php?id=<?= $candidate['id'] ?>" 
                           class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-md shadow-emerald-200 dark:shadow-none animate-pulse">
                            <i class="fa-solid fa-user-check"></i>
                            <span>Tuyển Dụng ➜ Tạo Nhân Viên</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (has_permission('recruitment', 'edit')): ?>
                    <a href="candidate_form.php?id=<?= $candidate['id'] ?>" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition flex items-center gap-1.5 shadow-2xs">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Chỉnh Sửa</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Thanh Tiến Trình Pipeline Tuyển Dụng (Step Indicator) -->
        <div class="py-2">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-3">Lộ Trình Tiến Độ Tuyển Dụng</span>
            <div class="grid grid-cols-5 gap-2 text-center text-xs">
                <?php 
                    $steps = [
                        'applied'   => ['name' => '1. Ứng Tuyển', 'icon' => 'fa-file-lines'],
                        'screening' => ['name' => '2. Sàng Lọc', 'icon' => 'fa-filter'],
                        'interview' => ['name' => '3. Phỏng Vấn', 'icon' => 'fa-comments'],
                        'offer'     => ['name' => '4. Gửi Offer', 'icon' => 'fa-file-signature'],
                        'hired'     => ['name' => '5. Trúng Tuyển', 'icon' => 'fa-trophy']
                    ];
                    $current_step_num = $cur_stage['step'];
                ?>
                <?php foreach ($steps as $st_key => $st_info): ?>
                    <?php 
                        $is_passed = ($current_step_num >= $stage_meta[$st_key]['step'] && $candidate['stage'] !== 'rejected');
                        $is_current = ($candidate['stage'] === $st_key);
                    ?>
                    <div class="p-3 rounded-2xl border transition <?= $is_current ? 'bg-indigo-50 dark:bg-indigo-950/60 border-indigo-500 text-indigo-700 dark:text-indigo-300 font-bold shadow-xs' : ($is_passed ? 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-200 text-emerald-800 dark:text-emerald-300 font-semibold' : 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-800 text-slate-400') ?>">
                        <i class="fa-solid <?= $st_info['icon'] ?> text-sm mb-1 block"></i>
                        <span class="text-[11px]"><?= $st_info['name'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3 Chỉ Số Quan Trọng -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Kinh Nghiệm Chuyên Môn</span>
                <div class="text-base font-bold text-slate-800 dark:text-white mt-1">
                    <?= (int)$candidate['experience_years'] > 0 ? $candidate['experience_years'] . ' năm kinh nghiệm' : 'Mới tốt nghiệp / Chưa có kn' ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5"><?= e($candidate['education'] ?: 'Chưa cập nhật bằng cấp') ?></div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Đánh Giá Năng Lực</span>
                <div class="text-base font-bold text-amber-500 mt-1">
                    <?= str_repeat('★', (int)$candidate['rating']) ?><span class="text-slate-300 dark:text-slate-600"><?= str_repeat('★', 5 - (int)$candidate['rating']) ?></span>
                    <span class="text-xs font-semibold text-slate-600 dark:text-slate-300 ml-1">(<?= (int)$candidate['rating'] ?>/5 sao)</span>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Nguồn: <strong><?= ucfirst($candidate['source']) ?></strong></div>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-800">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Lịch Hẹn Phỏng Vấn</span>
                <div class="text-base font-bold text-slate-800 dark:text-white mt-1">
                    <?= !empty($candidate['interview_date']) ? format_datetime($candidate['interview_date']) : 'Chưa đặt lịch' ?>
                </div>
                <div class="text-[11px] text-slate-400 mt-0.5">Thời gian thẩm định trực tiếp</div>
            </div>
        </div>

    </div>

    <!-- Khối 2 Cột: Thông Tin Liên Hệ & Tệp CV Scan -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Cột Trái: Nhân Thân & Liên Hệ -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 space-y-4">
            <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <i class="fa-regular fa-address-book text-indigo-600"></i>
                <span>Thông Tin Ứng Viên Chi Tiết</span>
            </h3>

            <div class="space-y-3 text-xs">
                <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-800">
                    <span class="text-slate-400">Email:</span>
                    <a href="mailto:<?= e($candidate['email']) ?>" class="font-semibold text-indigo-600 hover:underline"><?= e($candidate['email']) ?></a>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-800">
                    <span class="text-slate-400">Số Điện Thoại:</span>
                    <a href="tel:<?= e($candidate['phone']) ?>" class="font-semibold text-slate-800 dark:text-slate-100 hover:underline"><?= e($candidate['phone']) ?></a>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-800">
                    <span class="text-slate-400">Giới Tính:</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-100"><?= e($candidate['gender']) ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-800">
                    <span class="text-slate-400">Ngày Sinh:</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-100"><?= format_date($candidate['birth_date']) ?></span>
                </div>
                <div class="flex justify-between py-1.5 border-b border-slate-50 dark:border-slate-800">
                    <span class="text-slate-400">Học Vấn / Chuyên Ngành:</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-100"><?= e($candidate['education'] ?: '---') ?></span>
                </div>
                <div class="py-1.5">
                    <span class="text-slate-400 block mb-1">Thư Xin Việc / Giới Thiệu:</span>
                    <div class="p-3 bg-slate-50 dark:bg-slate-900/60 rounded-xl text-slate-600 dark:text-slate-300 leading-relaxed italic">
                        <?= nl2br(e($candidate['cover_letter'] ?: 'Ứng viên không cung cấp thư xin việc bổ sung.')) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột Phải: Tệp CV & Hồ Sơ Đính Kèm -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 space-y-4">
            <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
                <i class="fa-solid fa-file-lines text-emerald-600"></i>
                <span>Tài Liệu Hồ Sơ / CV Ứng Tuyển</span>
            </h3>

            <?php if (!empty($candidate['cv_file']) && file_exists(__DIR__ . '/../../assets/uploads/cvs/' . $candidate['cv_file'])): ?>
                <?php $ext = strtolower(pathinfo($candidate['cv_file'], PATHINFO_EXTENSION)); ?>
                <div class="p-5 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/60 flex items-center justify-between">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-xl shadow-sm">
                            <i class="fa-solid <?= $ext === 'pdf' ? 'fa-file-pdf' : 'fa-file-word' ?>"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-800 dark:text-white block"><?= e($candidate['cv_file']) ?></span>
                            <span class="text-[11px] text-slate-400 uppercase font-semibold">Định dạng .<?= $ext ?> • Sẵn sàng xem</span>
                        </div>
                    </div>
                    <a href="<?= base_url('assets/uploads/cvs/' . e($candidate['cv_file'])) ?>" target="_blank" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-arrow-down"></i> Tải / Mở CV
                    </a>
                </div>

                <?php if ($ext === 'pdf'): ?>
                    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mt-4">
                        <iframe src="<?= base_url('assets/uploads/cvs/' . e($candidate['cv_file'])) ?>" class="w-full h-80 border-0"></iframe>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-center py-12 text-slate-400 text-xs border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-2xl">
                    <i class="fa-solid fa-file-excel text-3xl mb-2 text-slate-300 dark:text-slate-600 block"></i>
                    Ứng viên chưa tải lên tệp CV scan nào.
                    <div class="mt-2">
                        <a href="candidate_form.php?id=<?= $candidate['id'] ?>" class="text-indigo-600 hover:underline font-bold">Tải tệp CV lên ngay &rarr;</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Khối Cập Nhật Biên Bản Phỏng Vấn & Đánh Giá Nhanh -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 sm:p-8 space-y-4">
        <h3 class="font-bold text-slate-800 dark:text-white text-sm flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-700/60">
            <i class="fa-solid fa-clipboard-check text-purple-600"></i>
            <span>Biên Bản Thẩm Định Phỏng Vấn & Chuyển Giai Đoạn Nhanh</span>
        </h3>

        <form method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="update_notes">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Chuyển Giai Đoạn</label>
                    <select name="stage" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-bold">
                        <option value="applied" <?= $candidate['stage'] === 'applied' ? 'selected' : '' ?>>1. Ứng Tuyển</option>
                        <option value="screening" <?= $candidate['stage'] === 'screening' ? 'selected' : '' ?>>2. Sàng Lọc</option>
                        <option value="interview" <?= $candidate['stage'] === 'interview' ? 'selected' : '' ?>>3. Phỏng Vấn</option>
                        <option value="offer" <?= $candidate['stage'] === 'offer' ? 'selected' : '' ?>>4. Gửi Offer</option>
                        <option value="hired" <?= $candidate['stage'] === 'hired' ? 'selected' : '' ?>>5. Trúng Tuyển</option>
                        <option value="rejected" <?= $candidate['stage'] === 'rejected' ? 'selected' : '' ?>>6. Từ Chối</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Đánh Giá Sao</label>
                    <select name="rating" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-bold">
                        <option value="5" <?= $candidate['rating'] == 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ (5/5)</option>
                        <option value="4" <?= $candidate['rating'] == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ (4/5)</option>
                        <option value="3" <?= $candidate['rating'] == 3 ? 'selected' : '' ?>>⭐⭐⭐ (3/5)</option>
                        <option value="2" <?= $candidate['rating'] == 2 ? 'selected' : '' ?>>⭐⭐ (2/5)</option>
                        <option value="1" <?= $candidate['rating'] == 1 ? 'selected' : '' ?>>⭐ (1/5)</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Lịch Hẹn Phỏng Vấn</label>
                    <input type="datetime-local" name="interview_date" value="<?= !empty($candidate['interview_date']) ? date('Y-m-d\TH:i', strtotime($candidate['interview_date'])) : '' ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Nội Dung Nhận Xét Phỏng Vấn Của Hội Đồng</label>
                <textarea name="interview_notes" rows="3" placeholder="Nhập tóm tắt câu hỏi, năng lực phản xạ, kiến thức chuyên sâu và kết luận của hội đồng phỏng vấn..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100"><?= e($candidate['interview_notes']) ?></textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-save"></i>
                    <span>Lưu Nhận Xét & Trạng Thái</span>
                </button>
            </div>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
