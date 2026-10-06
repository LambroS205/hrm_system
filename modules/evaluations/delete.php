<?php
// modules/evaluations/delete.php - Xóa hồ sơ phiếu đánh giá
require_once __DIR__ . '/../../core/auth.php';
require_permission('evaluations', 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);
} else {
    $id = (int)($_GET['id'] ?? 0);
}

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT evaluation_code FROM evaluations WHERE id = ?");
        $stmt->execute([$id]);
        $code = $stmt->fetchColumn();

        if ($code) {
            $del = $pdo->prepare("DELETE FROM evaluations WHERE id = ?");
            $del->execute([$id]);
            set_flash('success', "Đã xóa phiếu đánh giá [{$code}] thành công.");
        } else {
            set_flash('danger', 'Không tìm thấy phiếu đánh giá cần xóa.');
        }
    } catch (Exception $e) {
        set_flash('danger', 'Lỗi khi xóa phiếu đánh giá: ' . $e->getMessage());
    }
}

redirect('modules/evaluations/index.php');
