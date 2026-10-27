<?php
// modules/evaluations/ajax_evaluate.php - Xử lý AJAX chấm điểm và tính toán KPI khoa học (BSC & SMART Link Power)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/KpiCalculator.php';
require_once __DIR__ . '/../../core/KpiService.php';

// 1. Kiểm tra đăng nhập
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức HTTP không được hỗ trợ.']);
    exit;
}

// 2. Nhận và phân tích dữ liệu JSON hoặc POST data
$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);
if (!$data) {
    $data = $_POST;
}

// 3. Xác thực mã bảo mật CSRF
verify_csrf_json($data['_csrf_token'] ?? null);

// 3.1 Xử lý mở lại đánh giá (Reopen) cho SuperAdmin hoặc cấp có thẩm quyền
if (($data['action'] ?? '') === 'reopen') {
    $evaluation_id = (int)($data['evaluation_id'] ?? 0);
    $reopen_to = ($data['reopen_to'] ?? '') === 'self' ? 'self' : 'manager';

    $chk_stmt = $pdo->prepare("SELECT e.*, ep.status AS period_status FROM evaluations e JOIN evaluation_periods ep ON e.period_id = ep.id WHERE e.id = ?");
    $chk_stmt->execute([$evaluation_id]);
    $target_eval = $chk_stmt->fetch();

    if (!$target_eval) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ đánh giá.']);
        exit;
    }

    if (($target_eval['period_status'] ?? '') === 'closed') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Kỳ đánh giá này đã đóng, không thể mở lại hồ sơ.']);
        exit;
    }

    if (!is_superadmin() && !has_permission('evaluations', 'approve')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Bạn không có thẩm quyền để mở lại phiếu đánh giá này.']);
        exit;
    }

    $new_status = $reopen_to === 'self' ? 'self_review' : 'manager_review';
    $upd_stmt = $pdo->prepare("UPDATE evaluations SET status = ?, hr_approved_at = NULL, hr_approved_by = NULL WHERE id = ?");
    $upd_stmt->execute([$new_status, $evaluation_id]);

    $msg = $reopen_to === 'self' 
        ? 'Đã mở lại hồ sơ cho Nhân viên tự chấm lại thành công!'
        : 'Đã mở lại hồ sơ cho Quản lý thẩm tra và chấm lại thành công!';

    echo json_encode(['success' => true, 'message' => $msg, 'new_status' => $new_status]);
    exit;
}

$evaluation_id   = (int)($data['evaluation_id'] ?? 0);
$review_type     = $data['review_type'] ?? ''; // 'self' hoặc 'manager'
$scores_input    = is_array($data['scores'] ?? null) ? $data['scores'] : [];
$notes           = trim((string)($data['notes'] ?? ''));
$is_submit_final = !empty($data['submit_final']);
$promotion_rec   = trim((string)($data['promotion_recommendation'] ?? 'none'));

// Validate whitelist hình thức khen thưởng / đề xuất
$allowedRecs = KpiService::allowedPromotionRecommendations();
if (!array_key_exists($promotion_rec, $allowedRecs)) {
    $promotion_rec = 'none';
}

if ($evaluation_id <= 0 || !in_array($review_type, ['self', 'manager'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.']);
    exit;
}

// 4. Lấy thông tin phiếu đánh giá kèm User ID để phân quyền an toàn
$eval_stmt = $pdo->prepare("
    SELECT e.*, ep.status AS period_status,
           emp.user_id AS employee_user_id,
           rev.user_id AS reviewer_user_id
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    LEFT JOIN employees rev ON e.reviewer_id = rev.id
    WHERE e.id = ?
");
$eval_stmt->execute([$evaluation_id]);
$eval = $eval_stmt->fetch();

if (!$eval) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy hồ sơ đánh giá trong hệ thống.']);
    exit;
}

$user = current_user();

// 5. Kiểm tra tính khóa của hồ sơ hoặc đợt đánh giá
$lockInfo = KpiService::getLockStatus($eval);
if ($lockInfo['is_locked']) {
    // Nếu phiếu đã hoàn tất, chỉ Super Admin hoặc cấp phê duyệt mới có thể điều chỉnh
    if ($eval['status'] === 'completed' && (is_superadmin() || has_permission('evaluations', 'approve'))) {
        // Cho phép Approver điều chỉnh điểm
    } else {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => $lockInfo['reason'] ?? 'Hồ sơ này hiện đang bị khóa không cho phép sửa đổi.'
        ]);
        exit;
    }
}

// 6. Kiểm tra quyền chấm điểm theo vai trò và danh tính thực tế (Không nhầm User ID với Employee ID)
if ($review_type === 'self') {
    if (!KpiService::canSelfReview($eval, $user)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không có quyền thực hiện tự đánh giá trên hồ sơ này (Chỉ nhân sự sở hữu phiếu khi kỳ đang mở).'
        ]);
        exit;
    }
} elseif ($review_type === 'manager') {
    if (!KpiService::canManagerReview($eval, $user)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Bạn không có thẩm quyền quản lý để chấm điểm phiếu này (Chỉ Quản lý được phân công hoặc cấp có thẩm quyền phê duyệt).'
        ]);
        exit;
    }
}

// 7. Lấy danh sách tiêu chí (ưu tiên snapshot custom_items_json đã thiết lập theo vị trí)
if (!empty($eval['custom_items_json'])) {
    $parsed_custom = json_decode($eval['custom_items_json'], true);
    $template_items = is_array($parsed_custom) && !empty($parsed_custom) ? $parsed_custom : [];
} else {
    $items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
    $items_stmt->execute([$eval['template_id']]);
    $template_items = $items_stmt->fetchAll();
}

if (empty($template_items)) {
    echo json_encode(['success' => false, 'message' => 'Bộ tiêu chí của phiếu đánh giá này không có tiêu chí nào.']);
    exit;
}

// 8. Tính toán bảng điểm khoa học bằng KpiCalculator (chống gian lận bỏ trống tiêu chí)
$sheetResult = KpiCalculator::calculateScoreSheet($template_items, $scores_input, $is_submit_final);

// Nếu là nộp chính thức (submit_final), kiểm tra có tiêu chí nào bị bỏ trống hoàn toàn không
if ($is_submit_final && !empty($sheetResult['missing_items'])) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng hoàn tất đánh giá đủ tất cả tiêu chí trước khi nộp chính thức. Tiêu chí còn thiếu: ' . implode(', ', array_slice($sheetResult['missing_items'], 0, 3))
    ]);
    exit;
}

$calculatedScore     = $sheetResult['score'];
$scoresJsonEncoded   = json_encode($sheetResult['scores_json'], JSON_UNESCAPED_UNICODE);
$priorityAvg         = $sheetResult['priority_avg'];
$bscAvg              = $sheetResult['bsc_avg'];

try {
    $pdo->beginTransaction();

    if ($review_type === 'self') {
        $new_status = $eval['status'];
        if ($is_submit_final) {
            $new_status = 'manager_review';
        } elseif ($eval['status'] === 'pending') {
            $new_status = 'self_review';
        }

        $stmt = $pdo->prepare("
            UPDATE evaluations
            SET self_score = ?,
                self_scores_json = ?,
                self_notes = ?,
                self_submitted_at = NOW(),
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([$calculatedScore, $scoresJsonEncoded, $notes, $new_status, $evaluation_id]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => $is_submit_final ? 'Đã hoàn tất tự đánh giá và chuyển phiếu sang Quản lý duyệt!' : 'Đã lưu tạm kết quả tự đánh giá thành công.',
            'data' => [
                'self_score'   => $calculatedScore,
                'status'       => $new_status,
                'is_submitted' => $is_submit_final,
                'priority_avg' => $priorityAvg,
                'bsc_avg'      => $bscAvg
            ]
        ]);
        exit;
    }

    if ($review_type === 'manager') {
        $currSelfScore = $eval['self_score'];
        $mgrScore = $calculatedScore;

        // Tính điểm tổng kết (30% NV + 70% QL)
        $finalScore = KpiCalculator::calculateFinalScore($currSelfScore, $mgrScore);

        // Xếp loại & Tính hệ số Total Rewards (Kèm Gate điểm liệt Nhóm A)
        $classification = KpiCalculator::classifyRating($finalScore, $priorityAvg);

        $ratingKey       = $classification['rating'];
        $ratingGrade     = $classification['rating_grade'];
        $bonusMultiplier = $classification['multiplier'];

        if ($promotion_rec === 'none' && !empty($classification['default_rec'])) {
            $promotion_rec = $classification['default_rec'];
        }

        $new_status = $is_submit_final ? 'completed' : 'manager_review';
        $approvedAt = $is_submit_final ? date('Y-m-d H:i:s') : $eval['hr_approved_at'];
        $approvedBy = $is_submit_final ? (int)$user['id'] : $eval['hr_approved_by'];

        $stmt = $pdo->prepare("
            UPDATE evaluations
            SET manager_score = ?,
                manager_scores_json = ?,
                manager_notes = ?,
                manager_submitted_at = NOW(),
                final_score = ?,
                rating = ?,
                rating_grade = ?,
                bonus_multiplier = ?,
                promotion_recommendation = ?,
                status = ?,
                hr_approved_at = ?,
                hr_approved_by = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $mgrScore,
            $scoresJsonEncoded,
            $notes,
            $finalScore,
            $ratingKey,
            $ratingGrade,
            $bonusMultiplier,
            $promotion_rec,
            $new_status,
            $approvedAt,
            $approvedBy,
            $evaluation_id
        ]);

        $pdo->commit();

        $successMessage = $is_submit_final 
            ? 'Đã hoàn tất đánh giá quản lý và chốt kết quả xếp loại ' . $classification['label'] . ' (Hệ số thưởng ' . number_format($bonusMultiplier * 100) . '%)!'
            : 'Đã lưu tạm bảng chấm điểm quản lý thành công.';

        if ($classification['gate_applied']) {
            $successMessage .= ' [Lưu ý: ' . $classification['gate_reason'] . ']';
        }

        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'data' => [
                'manager_score'    => $mgrScore,
                'final_score'      => $finalScore,
                'rating'           => $ratingKey,
                'rating_grade'     => $ratingGrade,
                'bonus_multiplier' => $bonusMultiplier,
                'status'           => $new_status,
                'is_submitted'     => $is_submit_final,
                'gate_applied'     => $classification['gate_applied'],
                'gate_reason'      => $classification['gate_reason'],
                'priority_avg'     => $priorityAvg,
                'bsc_avg'          => $bscAvg
            ]
        ]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_system_error('Lỗi lưu đánh giá KPI ID=' . $evaluation_id, $e);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Đã có lỗi hệ thống xảy ra khi lưu đánh giá. Vui lòng liên hệ quản trị viên.']);
    exit;
}
