<?php
// modules/evaluations/delete.php - Xóa hồ sơ phiếu đánh giá
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/KpiService.php';
require_permission('evaluations', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Yêu cầu xóa không hợp lệ (chỉ hỗ trợ POST kèm CSRF Token).');
    redirect('modules/evaluations/index.php');
}

verify_csrf();
$id = (int)($_POST['id'] ?? 0);

if ($id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, ep.status AS period_status
            FROM evaluations e
            JOIN evaluation_periods ep ON e.period_id = ep.id
            WHERE e.id = ?
        ");
        $stmt->execute([$id]);
        $eval = $stmt->fetch();

        if (!$eval) {
            set_flash('danger', 'Không tìm thấy phiếu đánh giá cần xóa.');
        } else {
            require_employee_access((int)$eval['employee_id']);

            if (!KpiService::canDelete($eval, current_user())) {
                set_flash('danger', 'Không thể xóa phiếu đánh giá đã hoàn tất hoặc thuộc kỳ đánh giá đã đóng.');
            } else {
                $del = $pdo->prepare("DELETE FROM evaluations WHERE id = ?");
                $del->execute([$id]);
                set_flash('success', "Đã xóa phiếu đánh giá [{$eval['evaluation_code']}] thành công.");
            }
        }
    } catch (Exception $e) {
        set_flash('danger', 'Lỗi khi xóa phiếu đánh giá: ' . $e->getMessage());
    }
}

redirect('modules/evaluations/index.php');
