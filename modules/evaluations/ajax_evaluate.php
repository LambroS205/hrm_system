<?php
// modules/evaluations/ajax_evaluate.php - Xử lý AJAX chấm điểm và tính toán KPI tự động
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/auth.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// Nhận dữ liệu JSON hoặc POST data
$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);
if (!$data) {
    $data = $_POST;
}

$evaluation_id = (int)($data['evaluation_id'] ?? 0);
$review_type = $data['review_type'] ?? ''; // 'self' hoặc 'manager'
$scores_input = $data['scores'] ?? [];
$notes = trim($data['notes'] ?? '');
$is_submit_final = !empty($data['submit_final']);
$promotion_rec = $data['promotion_recommendation'] ?? 'none';

if ($evaluation_id <= 0 || !in_array($review_type, ['self', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.']);
    exit;
}

// Lấy thông tin phiếu đánh giá
$eval_stmt = $pdo->prepare("
    SELECT e.*, ep.status AS period_status,
           emp.user_id AS emp_user_id,
           rev.user_id AS rev_user_id
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    JOIN employees rev ON e.reviewer_id = rev.id
    WHERE e.id = ?
");
$eval_stmt->execute([$evaluation_id]);
$eval = $eval_stmt->fetch();

if (!$eval) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy phiếu đánh giá.']);
    exit;
}

$user = current_user();

// Kiểm tra quyền chấm điểm:
// - Self review: người chấm phải là chính nhân viên đó (user_id của employee hoặc employee_id khớp), hoặc admin
// - Manager review: người chấm phải là reviewer (user_id của reviewer), hoặc có quyền evaluations:manager_review / evaluations:approve, hoặc admin
if ($review_type === 'self') {
    $can_self = is_superadmin() || 
                (has_permission('evaluations', 'self_review') && ($user['id'] == $eval['emp_user_id'] || $user['id'] == $eval['employee_id']));
    
    // Nếu là admin hoặc có quyền edit thì cũng cho phép chấm thử/hỗ trợ
    if (!$can_self && !has_permission('evaluations', 'edit')) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện tự đánh giá trên hồ sơ này.']);
        exit;
    }
} else if ($review_type === 'manager') {
    $can_mgr = is_superadmin() || 
               has_permission('evaluations', 'approve') || 
               ($user['id'] == $eval['rev_user_id'] || $user['id'] == $eval['reviewer_id']) ||
               has_permission('evaluations', 'manager_review');

    if (!$can_mgr) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có thẩm quyền quản lý để chấm điểm phiếu này.']);
        exit;
    }
}

// Lấy danh sách tiêu chí của template để tính điểm theo trọng số
$items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
$items_stmt->execute([$eval['template_id']]);
$template_items = $items_stmt->fetchAll();

$items_map = [];
foreach ($template_items as $ti) {
    $items_map[$ti['id']] = $ti;
}

// Xử lý tính điểm theo trọng số chuẩn xác
$total_weighted_score = 0;
$total_weight_accounted = 0;
$scores_json_array = [];

foreach ($scores_input as $sc) {
    $item_id = (int)($sc['item_id'] ?? 0);
    if (!isset($items_map[$item_id])) continue;

    $item = $items_map[$item_id];
    $score_val = max(0, min(100, (float)($sc['score'] ?? 0)));
    $comment_val = trim($sc['comment'] ?? '');
    $weight = (float)$item['weight'];

    $total_weighted_score += ($score_val * $weight / 100);
    $total_weight_accounted += $weight;

    $scores_json_array[] = [
        'item_id'       => $item_id,
        'criteria'      => $item['criteria_name'],
        'weight'        => $weight,
        'score'         => $score_val,
        'comment'       => $comment_val,
        'target'        => $item['target_value'] ?? ''
    ];
}

// Chuẩn hóa điểm thang 100 theo tổng trọng số đã chấm
$calculated_score = $total_weight_accounted > 0 ? round(($total_weighted_score / $total_weight_accounted) * 100, 2) : 0;
$scores_json_encoded = json_encode($scores_json_array, JSON_UNESCAPED_UNICODE);

try {
    $pdo->beginTransaction();

    if ($review_type === 'self') {
        $new_status = $eval['status'];
        if ($is_submit_final) {
            $new_status = 'manager_review';
        } else if ($eval['status'] === 'pending') {
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
        $stmt->execute([$calculated_score, $scores_json_encoded, $notes, $new_status, $evaluation_id]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => $is_submit_final ? 'Đã hoàn tất tự đánh giá và chuyển phiếu sang Quản lý duyệt!' : 'Đã lưu tạm kết quả tự đánh giá thành công.',
            'data' => [
                'self_score' => $calculated_score,
                'status' => $new_status,
                'is_submitted' => $is_submit_final
            ]
        ]);
        exit;
    } 
    
    if ($review_type === 'manager') {
        // Lấy lại self_score hiện tại
        $curr_self_score = $eval['self_score'];
        $mgr_score = $calculated_score;

        // Tính điểm tổng kết: 30% nhân viên + 70% quản lý (hoặc 100% quản lý nếu NV chưa chấm)
        if ($curr_self_score !== null) {
            $final_score = round((0.30 * (float)$curr_self_score) + (0.70 * $mgr_score), 2);
        } else {
            $final_score = $mgr_score;
        }

        // Tự động xếp loại theo thang điểm AURA HRM
        $rating = 'average';
        if ($final_score >= 95.0) {
            $rating = 'outstanding'; // Xuất sắc
        } elseif ($final_score >= 85.0) {
            $rating = 'excellent';   // Tốt
        } elseif ($final_score >= 70.0) {
            $rating = 'good';        // Khá
        } elseif ($final_score >= 55.0) {
            $rating = 'average';     // Trung bình
        } elseif ($final_score >= 40.0) {
            $rating = 'below_average';// Yếu
        } else {
            $rating = 'poor';        // Kém
        }

        $new_status = $is_submit_final ? 'completed' : 'manager_review';

        $stmt = $pdo->prepare("
            UPDATE evaluations
            SET manager_score = ?,
                manager_scores_json = ?,
                manager_notes = ?,
                manager_submitted_at = NOW(),
                final_score = ?,
                rating = ?,
                promotion_recommendation = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $mgr_score,
            $scores_json_encoded,
            $notes,
            $final_score,
            $rating,
            $promotion_rec,
            $new_status,
            $evaluation_id
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $is_submit_final ? 'Đã hoàn tất đánh giá quản lý và chốt kết quả xếp loại!' : 'Đã lưu tạm bảng chấm điểm quản lý thành công.',
            'data' => [
                'manager_score' => $mgr_score,
                'final_score' => $final_score,
                'rating' => $rating,
                'status' => $new_status,
                'is_submitted' => $is_submit_final
            ]
        ]);
        exit;
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi lưu đánh giá: ' . $e->getMessage()]);
    exit;
}
