<?php
// modules/disciplines/delete.php - Xóa biên bản kỷ luật
require_once __DIR__ . '/../../core/auth.php';
require_permission('disciplines', 'delete');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT discipline_code, attachment FROM disciplines WHERE id = ?");
            $stmt->execute([$id]);
            $discipline = $stmt->fetch();

            if ($discipline) {
                // Xóa tệp đính kèm trên đĩa nếu có
                if (!empty($discipline['attachment'])) {
                    $filePath = __DIR__ . '/../../assets/uploads/attachments/' . $discipline['attachment'];
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }

                $delStmt = $pdo->prepare("DELETE FROM disciplines WHERE id = ?");
                $delStmt->execute([$id]);

                set_flash('success', "Đã xóa biên bản kỷ luật '{$discipline['discipline_code']}' thành công.");
            } else {
                set_flash('danger', 'Biên bản kỷ luật không tồn tại.');
            }
        } catch (PDOException $e) {
            set_flash('danger', 'Lỗi khi xóa biên bản kỷ luật: ' . $e->getMessage());
        }
    }
}

redirect('modules/disciplines/index.php');
