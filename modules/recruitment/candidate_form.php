<?php
// modules/recruitment/candidate_form.php - Tiếp nhận và Cập nhật Hồ sơ Ứng viên
require_once __DIR__ . '/../../core/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = $id > 0;

if ($is_edit) {
    require_permission('recruitment', 'edit');
    $page_title = 'Cập Nhật Hồ Sơ Ứng Viên';
} else {
    require_permission('recruitment', 'create');
    $page_title = 'Tiếp Nhận Hồ Sơ Ứng Viên Mới';
}

// Lấy danh sách các vị trí tuyển dụng đang mở
$job_positions = $pdo->query("
    SELECT j.id, j.job_code, j.title, b.name AS branch_name, d.name AS department_name 
    FROM job_positions j
    LEFT JOIN branches b ON j.branch_id = b.id
    LEFT JOIN departments d ON j.department_id = d.id
    WHERE j.status IN ('open', 'paused')
    ORDER BY j.id DESC
")->fetchAll();

$candidate = [
    'candidate_code'   => '',
    'job_position_id'  => isset($_GET['job_id']) ? (int)$_GET['job_id'] : '',
    'fullname'         => '',
    'email'            => '',
    'phone'            => '',
    'gender'           => 'Nam',
    'birth_date'       => '',
    'education'        => '',
    'experience_years' => 0,
    'source'           => 'website',
    'stage'            => 'applied',
    'rating'           => 3,
    'interview_date'   => '',
    'interview_notes'  => '',
    'cover_letter'     => '',
    'cv_file'          => ''
];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM candidates WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        set_flash('danger', 'Không tìm thấy hồ sơ ứng viên này.');
        redirect('modules/recruitment/index.php');
    }
    $candidate = $existing;
} else {
    $year = date('Y');
    $last_code = $pdo->query("SELECT candidate_code FROM candidates WHERE candidate_code LIKE 'UV-{$year}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $next_num = 1;
    if ($last_code && preg_match('/UV-\d{4}-(\d+)/', $last_code, $matches)) {
        $next_num = (int)$matches[1] + 1;
    }
    $candidate['candidate_code'] = sprintf('UV-%s-%03d', $year, $next_num);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $job_position_id  = (int)($_POST['job_position_id'] ?? 0);
    $fullname         = trim($_POST['fullname'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $gender           = $_POST['gender'] ?? 'Nam';
    $birth_date       = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $education        = trim($_POST['education'] ?? '');
    $experience_years = max(0, (int)($_POST['experience_years'] ?? 0));
    $source           = $_POST['source'] ?? 'website';
    $stage            = $_POST['stage'] ?? 'applied';
    $rating           = max(1, min(5, (int)($_POST['rating'] ?? 3)));
    $interview_date   = !empty($_POST['interview_date']) ? str_replace('T', ' ', $_POST['interview_date']) : null;
    $interview_notes  = trim($_POST['interview_notes'] ?? '');
    $cover_letter     = trim($_POST['cover_letter'] ?? '');

    $candidate = array_merge($candidate, [
        'job_position_id'  => $job_position_id,
        'fullname'         => $fullname,
        'email'            => $email,
        'phone'            => $phone,
        'gender'           => $gender,
        'birth_date'       => $birth_date,
        'education'        => $education,
        'experience_years' => $experience_years,
        'source'           => $source,
        'stage'            => $stage,
        'rating'           => $rating,
        'interview_date'   => $interview_date,
        'interview_notes'  => $interview_notes,
        'cover_letter'     => $cover_letter
    ]);

    if (empty($fullname)) {
        $errors[] = 'Họ và tên ứng viên không được để trống.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email liên hệ không hợp lệ.';
    }
    if (empty($phone)) {
        $errors[] = 'Số điện thoại liên hệ là bắt buộc.';
    }
    if ($job_position_id <= 0) {
        $errors[] = 'Vui lòng chọn vị trí ứng tuyển.';
    }

    // Xử lý upload tệp CV (Drag & Drop)
    $cv_name = $candidate['cv_file'];
    if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['cv_file']['tmp_name'];
        $file_name = $_FILES['cv_file']['name'];
        $file_size = $_FILES['cv_file']['size'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowed_exts)) {
            $errors[] = 'Tệp CV chỉ chấp nhận định dạng PDF, Word (DOC, DOCX) hoặc Ảnh.';
        } elseif ($file_size > 10 * 1024 * 1024) {
            $errors[] = 'Kích thước tệp CV không được vượt quá 10MB.';
        } else {
            $upload_dir = __DIR__ . '/../../assets/uploads/cvs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_cv_name = 'cv_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_cv_name)) {
                if (!empty($cv_name) && file_exists($upload_dir . $cv_name)) {
                    @unlink($upload_dir . $cv_name);
                }
                $cv_name = $new_cv_name;
            } else {
                $errors[] = 'Không thể lưu tệp CV tải lên máy chủ.';
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare("
                    UPDATE candidates SET
                        job_position_id = :job_id,
                        fullname = :fullname,
                        email = :email,
                        phone = :phone,
                        gender = :gender,
                        birth_date = :birth_date,
                        education = :education,
                        experience_years = :experience,
                        cv_file = :cv,
                        cover_letter = :cover,
                        source = :source,
                        stage = :stage,
                        rating = :rating,
                        interview_date = :interview_date,
                        interview_notes = :interview_notes
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':job_id'          => $job_position_id,
                    ':fullname'        => $fullname,
                    ':email'           => $email,
                    ':phone'           => $phone,
                    ':gender'          => $gender,
                    ':birth_date'      => $birth_date,
                    ':education'       => $education,
                    ':experience'      => $experience_years,
                    ':cv'              => $cv_name,
                    ':cover'           => $cover_letter,
                    ':source'          => $source,
                    ':stage'           => $stage,
                    ':rating'          => $rating,
                    ':interview_date'  => $interview_date,
                    ':interview_notes' => $interview_notes,
                    ':id'              => $id
                ]);
                set_flash('success', "Đã cập nhật thông tin ứng viên '{$fullname}' thành công.");
                redirect('modules/recruitment/candidate_view.php?id=' . $id);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO candidates
                        (candidate_code, job_position_id, fullname, email, phone, gender, birth_date, education, experience_years, cv_file, cover_letter, source, stage, rating, interview_date, interview_notes)
                    VALUES
                        (:code, :job_id, :fullname, :email, :phone, :gender, :birth_date, :education, :experience, :cv, :cover, :source, :stage, :rating, :interview_date, :interview_notes)
                ");
                $stmt->execute([
                    ':code'            => $candidate['candidate_code'],
                    ':job_id'          => $job_position_id,
                    ':fullname'        => $fullname,
                    ':email'           => $email,
                    ':phone'           => $phone,
                    ':gender'          => $gender,
                    ':birth_date'      => $birth_date,
                    ':education'       => $education,
                    ':experience'      => $experience_years,
                    ':cv'              => $cv_name,
                    ':cover'           => $cover_letter,
                    ':source'          => $source,
                    ':stage'           => $stage,
                    ':rating'          => $rating,
                    ':interview_date'  => $interview_date,
                    ':interview_notes' => $interview_notes
                ]);
                $newId = (int)$pdo->lastInsertId();
                set_flash('success', "Đã tiếp nhận hồ sơ ứng viên '{$fullname}' ({$candidate['candidate_code']}) thành công.");
                redirect('modules/recruitment/candidate_view.php?id=' . $newId);
            }
        } catch (PDOException $e) {
            $errors[] = 'Lỗi cơ sở dữ liệu: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-3">
                <a href="index.php" class="w-9 h-9 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-500 hover:text-slate-800 dark:hover:text-white flex items-center justify-center transition shadow-xs">
                    <i class="fa-solid fa-arrow-left text-sm"></i>
                </a>
                <h2 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-indigo-600"></i>
                    <span><?= $is_edit ? 'Cập Nhật Hồ Sơ Ứng Viên' : 'Tiếp Nhận Hồ Sơ Ứng Viên Mới' ?></span>
                </h2>
            </div>
            <p class="text-xs text-slate-400 mt-1 ml-12">
                Mã hồ sơ: <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400"><?= e($candidate['candidate_code']) ?></span>
            </p>
        </div>

        <a href="index.php" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold hover:bg-slate-100 transition">
            Hủy Bỏ
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-300 text-sm space-y-1">
            <div class="font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                <span>Vui lòng kiểm tra lại:</span>
            </div>
            <ul class="list-disc list-inside text-xs pl-2 space-y-0.5">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <?= csrf_field() ?>

        <div class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 shadow-sm p-6 sm:p-8 space-y-6">

            <!-- Phần 1: Vị Trí Ứng Tuyển & Giai Đoạn -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs">1</span>
                    <span>Vị Trí & Tiến Độ Tuyển Dụng</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Vị Trí Ứng Tuyển <span class="text-rose-500">*</span>
                        </label>
                        <select name="job_position_id" required class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">-- Chọn vị trí đang tuyển --</option>
                            <?php foreach ($job_positions as $jp): ?>
                                <option value="<?= $jp['id'] ?>" <?= ($candidate['job_position_id'] == $jp['id']) ? 'selected' : '' ?>>
                                    <?= e($jp['title']) ?> (<?= e($jp['job_code']) ?> - <?= e($jp['branch_name'] ?? 'Chi nhánh') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Giai Đoạn Hiện Tại
                        </label>
                        <select name="stage" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                            <option value="applied" <?= $candidate['stage'] === 'applied' ? 'selected' : '' ?>>📥 Ứng Tuyển (Applied)</option>
                            <option value="screening" <?= $candidate['stage'] === 'screening' ? 'selected' : '' ?>>🔍 Sàng Lọc (Screening)</option>
                            <option value="interview" <?= $candidate['stage'] === 'interview' ? 'selected' : '' ?>>🗓️ Phỏng Vấn (Interview)</option>
                            <option value="offer" <?= $candidate['stage'] === 'offer' ? 'selected' : '' ?>>💼 Gửi Offer (Job Offer)</option>
                            <option value="hired" <?= $candidate['stage'] === 'hired' ? 'selected' : '' ?>>🎉 Trúng Tuyển (Hired)</option>
                            <option value="rejected" <?= $candidate['stage'] === 'rejected' ? 'selected' : '' ?>>❌ Không Phù Hợp (Rejected)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Phần 2: Thông Tin Ứng Viên -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs">2</span>
                    <span>Thông Tin Nhân Thân & Liên Hệ</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Họ và Tên Ứng Viên <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="fullname" required value="<?= e($candidate['fullname']) ?>" placeholder="Nguyễn Văn A" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Email <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" name="email" required value="<?= e($candidate['email']) ?>" placeholder="email@domain.com" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Số Điện Thoại <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" name="phone" required value="<?= e($candidate['phone']) ?>" placeholder="0912 345 678" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Giới Tính</label>
                        <select name="gender" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100">
                            <option value="Nam" <?= $candidate['gender'] === 'Nam' ? 'selected' : '' ?>>Nam</option>
                            <option value="Nu" <?= $candidate['gender'] === 'Nu' ? 'selected' : '' ?>>Nữ</option>
                            <option value="Khac" <?= $candidate['gender'] === 'Khac' ? 'selected' : '' ?>>Khác</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Ngày Sinh</label>
                        <input type="date" name="birth_date" value="<?= e($candidate['birth_date']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Nguồn Tuyển Dụng</label>
                        <select name="source" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100">
                            <option value="website" <?= $candidate['source'] === 'website' ? 'selected' : '' ?>>Website cơ quan</option>
                            <option value="job_board" <?= $candidate['source'] === 'job_board' ? 'selected' : '' ?>>Trang tin tuyển dụng (TopCV, VietnamWorks...)</option>
                            <option value="referral" <?= $candidate['source'] === 'referral' ? 'selected' : '' ?>>Cán bộ nội bộ giới thiệu</option>
                            <option value="headhunt" <?= $candidate['source'] === 'headhunt' ? 'selected' : '' ?>>Săn đầu người (Headhunt)</option>
                            <option value="social" <?= $candidate['source'] === 'social' ? 'selected' : '' ?>>Mạng xã hội (LinkedIn, Facebook)</option>
                            <option value="other" <?= $candidate['source'] === 'other' ? 'selected' : '' ?>>Khác</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mt-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Trình Độ Học Vấn & Chuyên Ngành</label>
                        <input type="text" name="education" value="<?= e($candidate['education']) ?>" placeholder="Ví dụ: Kỹ sư Phần mềm - ĐH Bách Khoa Hà Nội" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Số Năm Kinh Nghiệm</label>
                        <input type="number" name="experience_years" min="0" max="40" value="<?= (int)$candidate['experience_years'] ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm font-bold text-slate-800 dark:text-slate-100">
                    </div>
                </div>
            </div>

            <!-- Phần 3: Kéo - Thả Tải Tệp CV Đính Kèm (Drag & Drop Zone) -->
            <div class="border-b border-slate-100 dark:border-slate-700/70 pb-6">
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs">3</span>
                    <span>Tệp Hồ Sơ / CV Scan Đính Kèm</span>
                </h3>

                <!-- Dropzone Kéo Thả CV -->
                <div class="drag-drop-zone relative border-2 border-dashed border-slate-300 dark:border-slate-600 hover:border-indigo-500 dark:hover:border-indigo-500 rounded-3xl p-6 text-center cursor-pointer transition-all duration-200 bg-slate-50/60 dark:bg-slate-900/40">
                    <input type="file" name="cv_file" class="drag-drop-input absolute inset-0 opacity-0 cursor-pointer w-full h-full z-10" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    
                    <div class="drag-drop-idle space-y-2 py-4">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-2xl shadow-sm">
                            <i class="fa-solid fa-file-arrow-up"></i>
                        </div>
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                            Kéo và thả tệp CV vào đây, hoặc <span class="text-indigo-600 dark:text-indigo-400 underline">chọn từ máy tính</span>
                        </div>
                        <p class="text-xs text-slate-400">
                            Hỗ trợ tệp PDF, Word (.doc, .docx), PNG, JPG (Tối đa 10MB)
                        </p>
                    </div>

                    <div class="drag-drop-preview hidden text-left relative z-20"></div>
                </div>

                <?php if (!empty($candidate['cv_file']) && file_exists(__DIR__ . '/../../assets/uploads/cvs/' . $candidate['cv_file'])): ?>
                    <div class="mt-3 p-3.5 bg-indigo-50/50 dark:bg-indigo-950/20 rounded-2xl border border-indigo-200/60 dark:border-indigo-800/60 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i class="fa-solid fa-file-pdf text-indigo-600 text-lg"></i>
                            <div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 block">Tệp CV hiện có: <?= e($candidate['cv_file']) ?></span>
                                <span class="text-[11px] text-slate-400">Tải tệp mới ở trên nếu bạn muốn cập nhật CV mới</span>
                            </div>
                        </div>
                        <a href="<?= base_url('assets/uploads/cvs/' . e($candidate['cv_file'])) ?>" target="_blank" class="px-3 py-1.5 rounded-xl bg-white dark:bg-slate-800 border border-indigo-200 text-indigo-700 dark:text-indigo-300 text-xs font-bold hover:bg-indigo-50 transition flex items-center gap-1.5 shadow-2xs">
                            <i class="fa-solid fa-arrow-down"></i> Tải Về / Xem
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Phần 4: Đánh Giá & Phỏng Vấn -->
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-white uppercase tracking-wider mb-4 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-xs">4</span>
                    <span>Đánh Giá Năng Lực & Lịch Hẹn Phỏng Vấn</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Đánh Giá Sơ Bộ (1 - 5 Sao)
                        </label>
                        <select name="rating" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100 font-bold">
                            <option value="5" <?= $candidate['rating'] == 5 ? 'selected' : '' ?>>⭐⭐⭐⭐⭐ (5/5 - Xuất Sắc)</option>
                            <option value="4" <?= $candidate['rating'] == 4 ? 'selected' : '' ?>>⭐⭐⭐⭐ (4/5 - Rất Tốt)</option>
                            <option value="3" <?= $candidate['rating'] == 3 ? 'selected' : '' ?>>⭐⭐⭐ (3/5 - Đạt Yêu Cầu)</option>
                            <option value="2" <?= $candidate['rating'] == 2 ? 'selected' : '' ?>>⭐⭐ (2/5 - Yếu / Cần Bổ Sung)</option>
                            <option value="1" <?= $candidate['rating'] == 1 ? 'selected' : '' ?>>⭐ (1/5 - Không Phù Hợp)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">
                            Lịch Hẹn Phỏng Vấn
                        </label>
                        <input type="datetime-local" name="interview_date" value="<?= !empty($candidate['interview_date']) ? date('Y-m-d\TH:i', strtotime($candidate['interview_date'])) : '' ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-sm text-slate-800 dark:text-slate-100">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider mb-1.5">Nhận Xét / Ghi Chú Phỏng Vấn</label>
                    <textarea name="interview_notes" rows="3" placeholder="Đánh giá kỹ năng mềm, kiến thức kỹ thuật, mức lương mong muốn..." class="w-full px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-xs text-slate-800 dark:text-slate-100"><?= e($candidate['interview_notes']) ?></textarea>
                </div>
            </div>

        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="index.php" class="px-5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-semibold hover:bg-slate-100 transition">
                Hủy Bỏ
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-sm font-semibold shadow-md shadow-indigo-200 dark:shadow-none transition flex items-center gap-2">
                <i class="fa-solid fa-check"></i>
                <span><?= $is_edit ? 'Cập Nhật Hồ Sơ' : 'Lưu Hồ Sơ Ứng Viên' ?></span>
            </button>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    HRMSDragDrop.initDropzone({
        dropzoneSelector: '.drag-drop-zone',
        inputSelector: '.drag-drop-input',
        previewSelector: '.drag-drop-preview',
        allowedExts: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        maxSizeMB: 10
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
