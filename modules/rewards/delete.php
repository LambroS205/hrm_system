<?php
// modules/rewards/delete.php - Xóa quyết định khen thưởng
require_once __DIR__ . '/../../core/auth.php';
require_permission('rewards', 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT reward_code, attachment FROM rewards WHERE id = ?");
            $stmt->execute([$id]);
            $reward = $stmt->fetch();

            if ($reward) {
                // Xóa tệp đính kèm trên đĩa nếu có
                if (!empty($reward['attachment'])) {
                    $filePath = __DIR__ . '/../../assets/uploads/attachments/' . $reward['attachment'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }

                $delStmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
                $delStmt->execute([$id]);

                set_flash('success', "Đã xóa quyết định khen thưởng '{$reward['reward_code']}' thành công.");
            } else {
                set_flash('danger', 'Quyết định khen thưởng không tồn tại.');
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi khi xóa quyết định khen thưởng: ' . $e->getMessage());
        }
    }
}

redirect('modules/rewards/index.php');
