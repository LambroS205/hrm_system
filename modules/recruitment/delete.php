<?php
// modules/recruitment/delete.php - Xóa hồ sơ ứng viên hoặc vị trí tuyển dụng
require_once __DIR__ . '/../../core/auth.php';
require_permission('recruitment', 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
    $type = trim($_POST['type'] ?? 'candidate');

    if ($id > 0) {
        try {
            if ($type === 'job') {
                $stmt = $pdo->prepare("SELECT job_code, title FROM job_positions WHERE id = ?");
                $stmt->execute([$id]);
                $job = $stmt->fetch();

                if ($job) {
                    $delStmt = $pdo->prepare("DELETE FROM job_positions WHERE id = ?");
                    $delStmt->execute([$id]);
                    set_flash('success', "Đã xóa tin tuyển dụng '{$job['title']}' ({$job['job_code']}) thành công.");
                }
                redirect('modules/recruitment/jobs.php');
            } else {
                // Xóa ứng viên
                $stmt = $pdo->prepare("SELECT candidate_code, fullname, cv_file FROM candidates WHERE id = ?");
                $stmt->execute([$id]);
                $cand = $stmt->fetch();

                if ($cand) {
                    if (!empty($cand['cv_file'])) {
                        $filePath = __DIR__ . '/../../assets/uploads/cvs/' . $cand['cv_file'];
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }

                    $delStmt = $pdo->prepare("DELETE FROM candidates WHERE id = ?");
                    $delStmt->execute([$id]);
                    set_flash('success', "Đã xóa hồ sơ ứng viên '{$cand['fullname']}' ({$cand['candidate_code']}) thành công.");
                }
                redirect('modules/recruitment/index.php');
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi khi xóa dữ liệu: ' . $e->getMessage());
            redirect('modules/recruitment/index.php');
        }
    }
}

redirect('modules/recruitment/index.php');
